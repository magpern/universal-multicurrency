<?php
/**
 * Storefront HTML request guards for switcher output.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Centralizes request-context checks for automatic switcher rendering.
 */
final class StorefrontRequestContext {

	/**
	 * Whether automatic switcher markup may render on this request.
	 */
	public function allows_automatic_render(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return false;
		}

		if ( function_exists( 'WC' ) && WC()->is_rest_api_request() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether storefront switcher assets may load on this request.
	 */
	public function allows_storefront_assets(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}

		return $this->allows_automatic_render();
	}
}
