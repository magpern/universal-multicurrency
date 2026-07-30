<?php
/**
 * Storefront geo currency application.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencySwitcher;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\Order\OrderCurrencyContext;

/**
 * Applies geo routing decisions on eligible storefront requests.
 */
final class GeoDetectionApplicator {

	/**
	 * Geo settings repository.
	 *
	 * @var GeoDetectionSettingsRepository
	 */
	private GeoDetectionSettingsRepository $settings_repository;

	/**
	 * Geo currency decision service.
	 *
	 * @var GeoCurrencyDecisionService
	 */
	private GeoCurrencyDecisionService $decision_service;

	/**
	 * Country context resolver.
	 *
	 * @var CountryContextResolver
	 */
	private CountryContextResolver $country_resolver;

	/**
	 * Storefront currency context.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Currency switcher.
	 *
	 * @var CurrencySwitcher
	 */
	private CurrencySwitcher $switcher;

	/**
	 * Currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Order currency context.
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $order_context;

	/**
	 * Checkout transition state repository.
	 *
	 * @var CheckoutTransitionStateRepository
	 */
	private CheckoutTransitionStateRepository $checkout_transition;

	/**
	 * Checkout geo re-evaluation policy.
	 *
	 * @var CheckoutGeoPolicy
	 */
	private CheckoutGeoPolicy $checkout_geo_policy;

	/**
	 * Constructs the storefront geo applicator.
	 *
	 * @param GeoDetectionSettingsRepository    $settings_repository Settings repository.
	 * @param GeoCurrencyDecisionService        $decision_service    Decision service.
	 * @param CountryContextResolver            $country_resolver    Country resolver.
	 * @param CurrencyContext                   $context             Currency context.
	 * @param CurrencySwitcher                  $switcher            Currency switcher.
	 * @param CurrencyRegistry                  $registry            Currency registry.
	 * @param OrderCurrencyContext              $order_context       Order context.
	 * @param CheckoutTransitionStateRepository $checkout_transition Checkout transition state.
	 * @param CheckoutGeoPolicy|null            $checkout_geo_policy Checkout geo policy.
	 */
	public function __construct(
		GeoDetectionSettingsRepository $settings_repository,
		GeoCurrencyDecisionService $decision_service,
		CountryContextResolver $country_resolver,
		CurrencyContext $context,
		CurrencySwitcher $switcher,
		CurrencyRegistry $registry,
		OrderCurrencyContext $order_context,
		CheckoutTransitionStateRepository $checkout_transition,
		?CheckoutGeoPolicy $checkout_geo_policy = null
	) {
		$this->settings_repository = $settings_repository;
		$this->decision_service    = $decision_service;
		$this->country_resolver    = $country_resolver;
		$this->context             = $context;
		$this->switcher            = $switcher;
		$this->registry            = $registry;
		$this->order_context       = $order_context;
		$this->checkout_transition = $checkout_transition;
		$this->checkout_geo_policy = $checkout_geo_policy ?? new CheckoutGeoPolicy();
	}

	/**
	 * Applies geo detection when eligible.
	 */
	public function maybe_apply(): void {
		$settings = $this->settings_repository->get();

		if ( ! $settings->is_enabled() ) {
			return;
		}

		if ( ! $this->context->is_convertible_request() ) {
			return;
		}

		if ( $this->order_context->is_active() ) {
			return;
		}

		if ( $this->is_order_owned_route() ) {
			return;
		}

		if ( ! $this->decision_service->should_apply_for_mode( $settings ) ) {
			return;
		}

		if ( $this->context->has_valid_shopper_currency_source() ) {
			return;
		}

		if ( $this->has_manual_currency_selection() ) {
			return;
		}

		$checkout_locked = null !== $this->checkout_transition->get();

		if ( ! $this->checkout_geo_policy->allows_revaluation( $settings, $checkout_locked ) ) {
			return;
		}

		$this->apply_for_country_context( $settings );
	}

	/**
	 * Registers checkout country-change hooks for optional geo re-evaluation.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'maybe_reapply_on_checkout_update' ), 20 );
	}

	/**
	 * Re-evaluates geo currency when checkout country changes before lock.
	 */
	public function maybe_reapply_on_checkout_update(): void {
		$settings = $this->settings_repository->get();

		if ( ! $settings->is_enabled() ) {
			return;
		}

		if ( ! $settings->reevaluate_on_billing_change() && ! $settings->reevaluate_on_shipping_change() ) {
			return;
		}

		if ( $this->order_context->is_active() ) {
			return;
		}

		if ( null !== $this->checkout_transition->get() && $settings->lock_on_entry() ) {
			return;
		}

		if ( $this->context->get_explicit_currency_code() || $this->has_manual_currency_selection() ) {
			return;
		}

		$change_source = $this->detect_checkout_country_change_source( $settings );

		if ( '' === $change_source ) {
			return;
		}

		if ( ! $this->checkout_geo_policy->allows_revaluation( $settings, false, $change_source ) ) {
			return;
		}

		$this->apply_for_country_context( $settings, true );
	}

	/**
	 * Whether the shopper manually selected a currency this session.
	 */
	private function has_manual_currency_selection(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		return (bool) WC()->session->get( CurrencySwitcher::SESSION_MANUAL_SELECTION );
	}

	/**
	 * Applies geo currency for the resolved country context.
	 *
	 * @param GeoDetectionSettings $settings Geo settings.
	 * @param bool                 $force    Bypass mode/session geo flags.
	 */
	private function apply_for_country_context( GeoDetectionSettings $settings, bool $force = false ): void {
		if ( ! $force && ! $this->decision_service->should_apply_for_mode( $settings ) ) {
			return;
		}

		$country_context = $this->country_resolver->resolve();

		if ( ! $country_context->is_known() ) {
			$this->apply_currency_fallback( $settings );
			$this->decision_service->mark_applied( $settings );

			return;
		}

		$base       = $this->registry->get_base_code();
		$selectable = $this->context->get_selectable_codes();
		$result     = $this->decision_service->evaluate_country(
			(string) $country_context->country_code(),
			$selectable,
			$base,
			$settings
		);

		$currency = $result->currency();

		if ( null === $currency || '' === $currency ) {
			return;
		}

		/**
		 * Fires after geo routing selects a currency.
		 *
		 * @since 0.11.0
		 *
		 * @param string                  $currency Selected currency code.
		 * @param GeoRuleEvaluationResult $result   Evaluation result.
		 * @param CountryContext          $country  Resolved country context.
		 */
		do_action( 'umc_geo_currency_decided', $currency, $result, $country_context );

		$this->switcher->persist( $currency );
		$this->decision_service->mark_applied( $settings );
	}

	/**
	 * Detects whether billing or shipping country changed on checkout update.
	 *
	 * @param GeoDetectionSettings $settings Geo settings.
	 */
	private function detect_checkout_country_change_source( GeoDetectionSettings $settings ): string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout AJAX; read-only country comparison.
		$billing = isset( $_POST['billing_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['billing_country'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout AJAX; read-only country comparison.
		$shipping = isset( $_POST['shipping_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['shipping_country'] ) ) ) : '';

		$prev_billing  = (string) WC()->session->get( 'umc_geo_prev_billing_country', '' );
		$prev_shipping = (string) WC()->session->get( 'umc_geo_prev_shipping_country', '' );

		if ( $settings->reevaluate_on_billing_change() && '' !== $billing && $billing !== $prev_billing ) {
			WC()->session->set( 'umc_geo_prev_billing_country', $billing );

			return 'billing';
		}

		if ( $settings->reevaluate_on_shipping_change() && '' !== $shipping && $shipping !== $prev_shipping ) {
			WC()->session->set( 'umc_geo_prev_shipping_country', $shipping );

			return 'shipping';
		}

		if ( '' !== $billing ) {
			WC()->session->set( 'umc_geo_prev_billing_country', $billing );
		}

		if ( '' !== $shipping ) {
			WC()->session->set( 'umc_geo_prev_shipping_country', $shipping );
		}

		return '';
	}

	/**
	 * Applies technical fallback when country is unknown.
	 *
	 * @param GeoDetectionSettings $settings Geo settings.
	 */
	private function apply_currency_fallback( GeoDetectionSettings $settings ): void {
		$fallback = strtoupper( trim( $settings->fallback_currency() ) );
		$base     = $this->registry->get_base_code();
		$codes    = $this->context->get_selectable_codes();

		if ( '' !== $fallback && in_array( $fallback, $codes, true ) ) {
			$this->switcher->persist( $fallback );
		}
	}

	/**
	 * Whether the current route is order-owned.
	 */
	private function is_order_owned_route(): bool {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return true;
		}

		return false;
	}
}
