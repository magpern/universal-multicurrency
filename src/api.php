<?php
/**
 * Public PHP API facades for Universal Multicurrency.
 *
 * Loaded via Composer autoload.files. Functions exist after plugin bootstrap;
 * they return null until Plugin binds the underlying services on woocommerce_init
 * and only succeed on convertible storefront requests with valid input.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

use UMC\Plugin;
use UMC\User\PreferenceServices;

if ( ! function_exists( 'umc_get_free_shipping_threshold_display' ) ) {
	/**
	 * Display a base-authored free-shipping threshold in the active UMC currency.
	 *
	 * Uses the same threshold resolution as checkout eligibility. Display-only —
	 * does not evaluate whether the current cart qualifies.
	 *
	 * @since 1.2.0
	 *
	 * @param string $base_threshold Decimal string in the store base currency.
	 * @return array{formatted_html: string, amount: string, currency_code: string}|null
	 */
	function umc_get_free_shipping_threshold_display( string $base_threshold ): ?array {
		if ( ! class_exists( Plugin::class ) ) {
			return null;
		}

		$service = Plugin::instance()->free_shipping_threshold_display();

		if ( null === $service ) {
			return null;
		}

		return $service->get_display( $base_threshold );
	}
}

if ( ! function_exists( 'umc_get_preferred_currency' ) ) {
	/**
	 * Returns a user's valid stored preferred currency.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id User id.
	 */
	function umc_get_preferred_currency( int $user_id ): ?string {
		$service = PreferenceServices::preferred_currency();

		return null !== $service ? $service->get( $user_id ) : null;
	}
}

if ( ! function_exists( 'umc_get_preferred_currency_state' ) ) {
	/**
	 * Returns effective preferred-currency state.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id User id.
	 * @return array{
	 *   available: bool,
	 *   editable: bool,
	 *   stored: ?string,
	 *   effective: string,
	 *   source: string,
	 *   label: string,
	 *   options: array<string, string>,
	 *   unavailable_message: ?string
	 * }|null
	 */
	function umc_get_preferred_currency_state( int $user_id ): ?array {
		$service = PreferenceServices::preferred_currency();

		return null !== $service ? $service->get_state( $user_id ) : null;
	}
}

if ( ! function_exists( 'umc_set_preferred_currency' ) ) {
	/**
	 * Sets or clears a user's preferred currency.
	 *
	 * @since 1.3.0
	 *
	 * @param int         $user_id User id.
	 * @param string|null $code    Currency code, or null to clear.
	 * @return true|\WP_Error
	 */
	function umc_set_preferred_currency( int $user_id, ?string $code ): bool|\WP_Error {
		$service = PreferenceServices::preferred_currency();

		if ( null === $service ) {
			return new \WP_Error(
				'umc_unavailable',
				__( 'Multicurrency support is not currently available.', 'universal-multicurrency' )
			);
		}

		return $service->set( $user_id, $code );
	}
}
