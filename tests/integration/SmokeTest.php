<?php
/**
 * Milestone 0 integration smoke tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use UMC\Plugin;
use WP_UnitTestCase;

/**
 * Verifies the skeleton boots inside a real WordPress + WooCommerce install
 * and changes no store behavior.
 */
final class SmokeTest extends WP_UnitTestCase {

	private const PLUGIN_ID = 'universal-multicurrency/universal-multicurrency.php';

	public function test_woocommerce_is_loaded(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
	}

	public function test_plugin_bootstraps(): void {
		$this->assertTrue( class_exists( Plugin::class ) );
		$this->assertTrue( defined( 'UMC_VERSION' ) );
	}

	public function test_declares_hpos_compatibility(): void {
		$features = FeaturesUtil::get_compatible_features_for_plugin( self::PLUGIN_ID );
		$this->assertContains( 'custom_order_tables', $features['compatible'] );
	}

	public function test_declares_cart_checkout_blocks_compatibility(): void {
		$features = FeaturesUtil::get_compatible_features_for_plugin( self::PLUGIN_ID );
		$this->assertContains( 'cart_checkout_blocks', $features['compatible'] );
	}

	/**
	 * Milestone 0 must not register any behavior-changing WooCommerce hooks:
	 * no price, currency, cart, checkout, coupon, shipping, tax, fee,
	 * order-value or stock filters. WooCommerce itself hooks some of these
	 * (e.g. its deprecated-hook bridge), so only callbacks originating from
	 * this plugin are forbidden.
	 */
	public function test_registers_no_behavior_filters(): void {
		$forbidden = array(
			'woocommerce_currency',
			'woocommerce_currency_symbol',
			'woocommerce_product_get_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_product_get_sale_price',
			'woocommerce_product_variation_get_price',
			'woocommerce_variation_prices_price',
			'woocommerce_get_variation_prices_hash',
			'wc_get_price_decimals',
			'wc_price_args',
			'woocommerce_coupon_get_amount',
			'woocommerce_coupon_get_minimum_amount',
			'woocommerce_package_rates',
			'woocommerce_shipping_packages',
			'woocommerce_cart_hash',
			'woocommerce_cart_calculate_fees',
			'woocommerce_product_get_stock_quantity',
			'woocommerce_product_get_stock_status',
		);

		foreach ( $forbidden as $hook ) {
			$this->assertSame(
				array(),
				$this->plugin_callbacks_on( $hook ),
				"Milestone 0 must not hook '{$hook}'."
			);
		}
	}

	/**
	 * Returns descriptions of callbacks on a hook that originate from this
	 * plugin (UMC classes, umc_-prefixed functions, or closures defined in
	 * plugin files outside tests/).
	 *
	 * @param string $hook Hook name.
	 * @return array<string>
	 */
	private function plugin_callbacks_on( string $hook ): array {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return array();
		}

		$found = array();

		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				if ( $function instanceof \Closure ) {
					$file = ( new \ReflectionFunction( $function ) )->getFileName();
					if ( $file
						&& false !== strpos( $file, 'universal-multicurrency' )
						&& false === strpos( $file, '/tests/' )
					) {
						$found[] = "closure defined in {$file}";
					}
				} elseif ( is_array( $function ) ) {
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					if ( 0 === strpos( $class, 'UMC\\' ) ) {
						$found[] = "{$class}::{$function[1]}";
					}
				} elseif ( is_string( $function ) && 0 === strpos( $function, 'umc' ) ) {
					$found[] = $function;
				}
			}
		}

		return $found;
	}

	public function test_store_prices_are_untouched(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'M0 smoke product' );
		$product->set_regular_price( '10.00' );
		$product->save();

		$reloaded = wc_get_product( $product->get_id() );
		$this->assertSame( '10.00', $reloaded->get_regular_price() );
		$this->assertSame( '10.00', $reloaded->get_price() );
	}
}
