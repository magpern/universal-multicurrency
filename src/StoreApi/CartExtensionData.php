<?php
/**
 * Currency and checkout policy data exposed on Store API endpoints.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\StoreApi;

use UMC\Checkout\CheckoutNoticeService;
use UMC\Checkout\CheckoutPolicyCoordinator;
use UMC\Checkout\CheckoutSettingsRepository;
use UMC\CurrencyContext;

/**
 * Publishes currency state and checkout transition notices on Store API responses.
 */
final class CartExtensionData {

	/**
	 * Extension namespace, matching the plugin prefix.
	 */
	public const NAMESPACE_KEY = 'umc';

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Checkout policy coordinator.
	 *
	 * @var CheckoutPolicyCoordinator
	 */
	private CheckoutPolicyCoordinator $coordinator;

	/**
	 * Checkout settings repository.
	 *
	 * @var CheckoutSettingsRepository
	 */
	private CheckoutSettingsRepository $checkout_settings;

	/**
	 * Checkout notice service.
	 *
	 * @var CheckoutNoticeService
	 */
	private CheckoutNoticeService $notice_service;

	/**
	 * Binds the extension to its collaborators.
	 *
	 * @param CurrencyContext            $context           Request-scoped currency facade.
	 * @param CheckoutPolicyCoordinator  $coordinator       Checkout policy coordinator.
	 * @param CheckoutSettingsRepository $checkout_settings Checkout settings repository.
	 * @param CheckoutNoticeService      $notice_service    Checkout notice service.
	 */
	public function __construct(
		CurrencyContext $context,
		CheckoutPolicyCoordinator $coordinator,
		CheckoutSettingsRepository $checkout_settings,
		CheckoutNoticeService $notice_service
	) {
		$this->context           = $context;
		$this->coordinator       = $coordinator;
		$this->checkout_settings = $checkout_settings;
		$this->notice_service    = $notice_service;
	}

	/**
	 * Registers cart and checkout endpoint extensions.
	 */
	public function register(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		$registration = array(
			'namespace'       => self::NAMESPACE_KEY,
			'data_callback'   => array( $this, 'data' ),
			'schema_callback' => array( $this, 'schema' ),
			'schema_type'     => ARRAY_A,
		);

		woocommerce_store_api_register_endpoint_data(
			array_merge( $registration, array( 'endpoint' => 'cart' ) )
		);

		if ( self::supports_checkout_endpoint_extension() ) {
			woocommerce_store_api_register_endpoint_data(
				array_merge( $registration, array( 'endpoint' => 'checkout' ) )
			);
		}
	}

	/**
	 * Whether the installed WooCommerce version accepts checkout endpoint extensions.
	 *
	 * WooCommerce 8.2 rejects checkout POST requests when this namespace is also
	 * registered on the checkout endpoint, even though cart/checkout GET responses
	 * remain valid with cart-only registration.
	 */
	public static function supports_checkout_endpoint_extension(): bool {
		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.3.0', '>=' );
	}

	/**
	 * The currency and checkout state for the current request.
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		$state    = $this->coordinator->current_state();
		$settings = $this->checkout_settings->get();
		$payload  = array(
			'active_currency'       => $this->context->get_active_code(),
			'base_currency'         => $this->context->get_base_currency()->code(),
			'selectable_currencies' => $this->context->get_selectable_codes(),
			'checkout_mode'         => $settings->mode(),
			'shopper_currency'      => $this->context->get_shopper_code(),
			'effective_currency'    => $this->context->get_active_code(),
			'transition_reason'     => '',
			'fallback_applied'      => false,
			'checkout_notice'       => $this->notice_service->build_payload( $state, $settings ),
		);

		if ( null !== $state ) {
			$payload['transition_reason']  = $state->reason();
			$payload['fallback_applied']   = $state->fallback_occurred();
			$payload['effective_currency'] = $state->effective_currency();
		}

		return $payload;
	}

	/**
	 * Schema for the exposed Store API extension data.
	 *
	 * @return array<string, mixed>
	 */
	public function schema(): array {
		return array(
			'active_currency'       => array(
				'description' => __( 'Currency code the cart is presented in.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'base_currency'         => array(
				'description' => __( 'Currency code prices are authored in.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'selectable_currencies' => array(
				'description' => __( 'Currency codes the shopper may choose.', 'universal-multicurrency' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'readonly'    => true,
			),
			'checkout_mode'         => array(
				'description' => __( 'Configured checkout currency mode.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'shopper_currency'      => array(
				'description' => __( 'Shopper-selected currency code.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'effective_currency'    => array(
				'description' => __( 'Effective checkout currency code.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'transition_reason'     => array(
				'description' => __( 'Checkout transition reason, when applicable.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'fallback_applied'      => array(
				'description' => __( 'Whether checkout fell back to store currency.', 'universal-multicurrency' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'checkout_notice'       => array(
				'description' => __( 'Checkout transition notice payload for Blocks.', 'universal-multicurrency' ),
				'type'        => 'object',
				'readonly'    => true,
			),
		);
	}
}
