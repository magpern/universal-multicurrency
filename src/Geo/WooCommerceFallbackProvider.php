<?php
/**
 * WooCommerce fallback country context provider.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Checkout customer country and optional WC geolocation fallback.
 */
final class WooCommerceFallbackProvider implements CountryContextProviderInterface {

	/**
	 * Whether geolocation was already invoked this request.
	 *
	 * @var bool
	 */
	private static bool $geolocation_called = false;

	/**
	 * Whether WooCommerce geolocation fallback is allowed.
	 *
	 * @var bool
	 */
	private bool $allow_geolocation;

	/**
	 * Checkout country precedence (billing or shipping).
	 *
	 * @var string
	 */
	private string $checkout_precedence;

	/**
	 * Whether the current request is checkout-eligible.
	 *
	 * @var bool
	 */
	private bool $is_checkout_context;

	/**
	 * Constructs the WooCommerce fallback provider.
	 *
	 * @param bool   $allow_geolocation   Whether WC geolocation is allowed.
	 * @param string $checkout_precedence billing|shipping.
	 * @param bool   $is_checkout_context Whether request is checkout-eligible.
	 */
	public function __construct(
		bool $allow_geolocation,
		string $checkout_precedence,
		bool $is_checkout_context
	) {
		$this->allow_geolocation   = $allow_geolocation;
		$this->checkout_precedence = $checkout_precedence;
		$this->is_checkout_context = $is_checkout_context;
	}

	/**
	 * Provider identifier.
	 */
	public function id(): string {
		return 'woocommerce';
	}

	/**
	 * Whether WooCommerce is loaded.
	 */
	public function is_available(): bool {
		return function_exists( 'WC' );
	}

	/**
	 * Resolves country from checkout customer or geolocation.
	 */
	public function resolve(): ?CountryContext {
		if ( ! $this->is_available() ) {
			return null;
		}

		if ( $this->is_checkout_context ) {
			$checkout_country = $this->checkout_country();

			if ( null !== $checkout_country ) {
				return new CountryContext( $checkout_country, 'woocommerce_checkout', 1.0 );
			}
		}

		if ( ! $this->allow_geolocation || self::$geolocation_called ) {
			return null;
		}

		if ( ! class_exists( 'WC_Geolocation' ) ) {
			return null;
		}

		self::$geolocation_called = true;

		$ip = \WC_Geolocation::get_ip_address();

		if ( '' === $ip ) {
			return null;
		}

		$result = \WC_Geolocation::geolocate_ip( $ip, false, false );

		if ( ! is_array( $result ) || ! isset( $result['country'] ) || ! is_string( $result['country'] ) ) {
			return null;
		}

		$country = strtoupper( trim( $result['country'] ) );

		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return null;
		}

		return new CountryContext( $country, 'woocommerce_geolocation', 0.75 );
	}

	/**
	 * Resets geolocation call guard (tests only).
	 */
	public static function reset_geolocation_guard(): void {
		self::$geolocation_called = false;
	}

	/**
	 * Reads billing or shipping country from the active customer/session.
	 */
	private function checkout_country(): ?string {
		$customer = WC()->customer;

		if ( ! $customer ) {
			return null;
		}

		$billing  = strtoupper( trim( (string) $customer->get_billing_country() ) );
		$shipping = strtoupper( trim( (string) $customer->get_shipping_country() ) );

		$prefer_shipping = GeoDetectionSettings::PRECEDENCE_SHIPPING === $this->checkout_precedence;

		$candidates = $prefer_shipping
			? array( $shipping, $billing )
			: array( $billing, $shipping );

		foreach ( $candidates as $code ) {
			if ( 1 === preg_match( '/^[A-Z]{2}$/', $code ) ) {
				return $code;
			}
		}

		return null;
	}
}
