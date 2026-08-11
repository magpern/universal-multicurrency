<?php
/**
 * Stateless Decision Inspector orchestration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\CurrencySwitcher;
use UMC\Decision\CurrencyDecisionExplanation;
use UMC\Decision\CurrencyDecisionExplainer;
use UMC\Decision\DecisionExplanationInput;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoCurrencyRuleEvaluator;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Geo\GeoRegionRegistry;
use UMC\Geo\UgcIntegrationStatus;
use UMC\Settings;

/**
 * Builds Decision Inspector explanations without mutating shopper state.
 */
final class DecisionInspectorService {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Explainer.
	 *
	 * @var CurrencyDecisionExplainer
	 */
	private CurrencyDecisionExplainer $explainer;

	/**
	 * Constructs the service.
	 *
	 * @param Settings $settings Settings.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->settings  = $settings;
		$this->registry  = new CurrencyRegistry( $settings, $base );
		$geo_repository  = new GeoDetectionSettingsRepository( $settings );
		$this->explainer = new CurrencyDecisionExplainer(
			new CurrencyResolver(),
			new GeoCurrencyDecisionService(
				$geo_repository,
				new GeoCurrencyRuleEvaluator( new GeoRegionRegistry() )
			)
		);
	}

	/**
	 * Explains a sanitized POST/input array.
	 *
	 * @param array<string, mixed> $raw Raw input.
	 */
	public function explain_from_array( array $raw ): CurrencyDecisionExplanation {
		return $this->explainer->explain( $this->input_from_array( $raw ) );
	}

	/**
	 * Builds a validated explanation input from raw admin data.
	 *
	 * @param array<string, mixed> $raw Raw input.
	 */
	public function input_from_array( array $raw ): DecisionExplanationInput {
		$selectable = $this->registry->get_selectable_codes();
		$base       = $this->registry->get_base_code();
		$geo        = ( new GeoDetectionSettingsRepository( $this->settings ) )->get();
		$checkout   = $this->settings->get()['checkout'] ?? array();

		$normalize = static function ( mixed $value ) use ( $selectable, $base ): ?string {
			if ( ! is_string( $value ) ) {
				return null;
			}

			$code = strtoupper( trim( $value ) );

			if ( '' === $code ) {
				return null;
			}

			if ( $code === $base || in_array( $code, $selectable, true ) ) {
				return $code;
			}

			return $code;
		};

		$country = strtoupper( trim( (string) ( $raw['country_code'] ?? '' ) ) );
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country ) ) {
			$country = '';
		}

		$origin = (string) ( $raw['currency_origin'] ?? '' );
		if ( CurrencySwitcher::ORIGIN_CUSTOMER !== $origin && CurrencySwitcher::ORIGIN_VISITOR_LOCATION !== $origin ) {
			$origin = '';
		}

		$mode = (string) ( $raw['checkout_mode'] ?? ( is_array( $checkout ) ? ( $checkout['mode'] ?? 'selected' ) : 'selected' ) );
		if ( 'store' !== $mode ) {
			$mode = 'selected';
		}

		$geo_enabled = array_key_exists( 'geo_enabled', $raw )
			? ! empty( $raw['geo_enabled'] )
			: $geo->is_enabled();

		return new DecisionExplanationInput(
			$normalize( $raw['explicit_currency'] ?? null ),
			$normalize( $raw['session_currency'] ?? null ),
			$normalize( $raw['cookie_currency'] ?? null ),
			$base,
			$selectable,
			! empty( $raw['manual_selection'] ),
			'' !== $origin ? $origin : null,
			! empty( $raw['order_context_active'] ),
			$geo_enabled,
			$country,
			! empty( $raw['checkout_locked'] ),
			! empty( $raw['include_checkout'] ),
			$mode,
			! array_key_exists( 'show_notice', $raw ) || ! empty( $raw['show_notice'] ),
			! array_key_exists( 'payment_required', $raw ) || ! empty( $raw['payment_required'] ),
			! array_key_exists( 'gateway_supports_display', $raw ) || ! empty( $raw['gateway_supports_display'] ),
			UgcIntegrationStatus::STATE_AVAILABLE === ( new UgcIntegrationStatus() )->state() ? 'available' : 'unavailable'
		);
	}
}
