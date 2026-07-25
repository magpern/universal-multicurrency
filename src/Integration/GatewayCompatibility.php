<?php
/**
 * Payment-gateway currency compatibility.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Integration;

use UMC\CurrencyContext;

/**
 * Hides payment gateways that do not support the active transaction currency.
 *
 * WooCommerce core gateways accept any store currency, so nothing is hidden by
 * default. A gateway's supported-currency list is declared through the
 * `umc_gateway_supported_currencies` filter (return `null` for "all currencies",
 * or an array of codes). When the active currency is not supported the gateway is
 * removed *before* order placement, so the customer is never silently charged in
 * a currency the gateway cannot process. If that leaves no gateway available in a
 * non-base currency, an explanatory checkout notice is shown.
 *
 * Gateway-specific settlement conversion is deliberately out of scope: this class
 * only decides availability; it never rewrites a gateway's amount or currency.
 */
final class GatewayCompatibility {

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the service to the context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the gateway-availability filter.
	 */
	public function register(): void {
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 10, 1 );
	}

	/**
	 * Removes gateways incompatible with the active currency.
	 *
	 * @param mixed $gateways Available gateways keyed by id.
	 * @return mixed
	 */
	public function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || array() === $gateways || ! $this->context->is_convertible_request() ) {
			return $gateways;
		}

		$active  = $this->context->get_active_code();
		$removed = false;

		foreach ( $gateways as $id => $gateway ) {
			$supported = $this->supported_currencies( $gateway );

			if ( null !== $supported && ! in_array( $active, $supported, true ) ) {
				unset( $gateways[ $id ] );
				$removed = true;

				/**
				 * Fires when a gateway is hidden because it does not support the
				 * active currency.
				 *
				 * @since 0.3.0
				 *
				 * @param string $id     Gateway id.
				 * @param string $active Active currency code.
				 */
				do_action( 'umc_gateway_hidden', (string) $id, $active );
			}
		}

		if ( $removed && array() === $gateways && ! $this->context->is_base_active() ) {
			$this->notify_no_gateway( $active );
		}

		return $gateways;
	}

	/**
	 * The uppercase currency codes a gateway supports, or null for "all".
	 *
	 * @param object $gateway Gateway instance.
	 * @return array<int, string>|null
	 */
	private function supported_currencies( $gateway ): ?array {
		/**
		 * The currencies a gateway supports. Return null for "all currencies"
		 * (the default) or an array of currency codes to restrict it.
		 *
		 * @since 0.3.0
		 *
		 * @param array<int, string>|null $codes   Supported codes, or null for all.
		 * @param object                  $gateway Gateway instance.
		 */
		$codes = apply_filters( 'umc_gateway_supported_currencies', null, $gateway );

		if ( ! is_array( $codes ) ) {
			return null;
		}

		return array_map( 'strtoupper', array_map( 'strval', $codes ) );
	}

	/**
	 * Adds a single explanatory notice when no gateway is available.
	 *
	 * @param string $active Active currency code.
	 */
	private function notify_no_gateway( string $active ): void {
		if ( ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: active currency code. */
			esc_html__( 'No payment method is available for %s. Please choose a different currency to continue.', 'universal-multicurrency' ),
			esc_html( $active )
		);

		if ( ! wc_has_notice( $message, 'error' ) ) {
			wc_add_notice( $message, 'error' );
		}
	}
}
