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
	 * The declaration above is only honest if something actually implements it.
	 * A Store API order carries no snapshot unless the adapter is listening,
	 * because WooCommerce's block checkout never fires the classic hook.
	 */
	public function test_backs_the_blocks_declaration_with_a_snapshot_adapter(): void {
		$this->assertSame(
			array( 'UMC\StoreApi\CheckoutSnapshotAdapter::stage_snapshot' ),
			$this->plugin_callbacks_on( 'woocommerce_store_api_checkout_update_order_meta' )
		);
	}

	public function test_registers_plugin_settings_action_link(): void {
		$hook = 'plugin_action_links_' . self::PLUGIN_ID;

		$this->assertSame(
			array( 'UMC\Admin\PluginActionLinks::add_settings_link' ),
			$this->plugin_callbacks_on( $hook )
		);

		$links = apply_filters( $hook, array( '<a>Deactivate</a>' ) );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'page=wc-settings', $links[0] );
		$this->assertStringContainsString( 'tab=umc', $links[0] );
		$this->assertStringContainsString( 'section=currencies', $links[0] );
		$this->assertStringContainsString( 'Settings', $links[0] );
	}

	/**
	 * The plugin must never register stock or order-status callbacks.
	 * Fee conversion is opt-in via FeeConversion (M19) — asserted in
	 * FeeBoundaryTest. Core shipping and cart hooks are asserted in
	 * StorefrontGuardTest.
	 */
	public function test_registers_no_out_of_scope_hooks(): void {
		$forbidden = array(
			'woocommerce_shipping_packages',
			'woocommerce_cart_hash',
			'woocommerce_product_get_stock_quantity',
			'woocommerce_product_get_stock_status',
			'woocommerce_payment_complete_reduce_order_stock',
			'woocommerce_order_status_changed',
		);

		foreach ( $forbidden as $hook ) {
			$this->assertSame(
				array(),
				$this->plugin_callbacks_on( $hook ),
				"The plugin must not hook '{$hook}'."
			);
		}

		$this->assertSame(
			array( 'UMC\Integration\FeeConversion::convert_opt_in_fees' ),
			$this->plugin_callbacks_on( 'woocommerce_cart_calculate_fees' ),
			'Only FeeConversion may hook woocommerce_cart_calculate_fees.'
		);
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
