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
