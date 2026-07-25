<?php
/**
 * Integration guards: Milestone 2 stays within scope.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Plugin;
use WP_UnitTestCase;

/**
 * Asserts the plugin registers no out-of-scope (cart/order/stock/…) callbacks
 * and boots idempotently.
 */
final class StorefrontGuardTest extends WP_UnitTestCase {

	private const FORBIDDEN_HOOKS = array(
		'woocommerce_checkout_create_order',
		'woocommerce_store_api_checkout_update_order_meta',
		'woocommerce_cart_calculate_fees',
		'woocommerce_shipping_packages',
		'woocommerce_product_get_stock_quantity',
		'woocommerce_product_get_stock_status',
		'woocommerce_order_status_changed',
		'woocommerce_payment_complete_reduce_order_stock',
	);

	public function test_no_out_of_scope_hooks_have_plugin_callbacks(): void {
		foreach ( self::FORBIDDEN_HOOKS as $hook ) {
			$this->assertSame(
				array(),
				$this->umc_callbacks_on( $hook ),
				"Milestone 2 must not hook '{$hook}'."
			);
		}
	}

	public function test_plugin_boot_is_idempotent(): void {
		$plugin = Plugin::instance();
		$plugin->init();
		$plugin->init();

		$this->assertSame( $plugin, Plugin::instance() );
	}

	/**
	 * Descriptions of callbacks on a hook that originate from this plugin.
	 *
	 * @param string $hook Hook name.
	 * @return array<int, string>
	 */
	private function umc_callbacks_on( string $hook ): array {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return array();
		}

		$found = array();

		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				if ( is_array( $function ) ) {
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					if ( 0 === strpos( $class, 'UMC\\' ) ) {
						$found[] = "{$class}::{$function[1]}";
					}
				} elseif ( $function instanceof \Closure ) {
					$file = ( new \ReflectionFunction( $function ) )->getFileName();
					if ( is_string( $file ) && false !== strpos( $file, '/universal-multicurrency/src/' ) ) {
						$found[] = "closure:{$file}";
					}
				}
			}
		}

		return $found;
	}
}
