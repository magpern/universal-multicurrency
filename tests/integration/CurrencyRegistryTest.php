<?php
/**
 * Integration tests for the currency registry and converter over real settings.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Converter;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies the domain layer works over the real option and mutates no store data.
 */
final class CurrencyRegistryTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	private function seed_settings(): Settings {
		$settings = new Settings();
		$settings->save(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled' => true,
						'rate'    => '11.50',
					),
					'JPY' => array(
						'enabled'  => false,
						'decimals' => 0,
						'rate'     => '161',
					),
				),
			)
		);

		return new Settings();
	}

	public function test_base_present_and_enabled_when_absent_from_settings(): void {
		$registry = new CurrencyRegistry( $this->seed_settings(), new Currency( 'EUR', 2 ) );

		$this->assertTrue( $registry->has_currency( 'EUR' ) );
		$this->assertTrue( $registry->get_base_currency()->is_enabled() );
		$this->assertTrue( $registry->is_base( 'EUR' ) );
	}

	public function test_registry_ignores_the_woocommerce_currency_option(): void {
		// Store base option says USD, but we inject EUR — the registry must use EUR.
		update_option( 'woocommerce_currency', 'USD' );
		$registry = new CurrencyRegistry( $this->seed_settings(), new Currency( 'EUR', 2 ) );

		$this->assertSame( 'EUR', $registry->get_base_code() );
		$this->assertFalse( $registry->is_base( 'USD' ) );
	}

	public function test_enabled_filtering_over_real_settings(): void {
		$registry = new CurrencyRegistry( $this->seed_settings(), new Currency( 'EUR', 2 ) );
		$codes    = array_map(
			static fn ( Currency $c ): string => $c->code(),
			$registry->get_enabled_currencies()
		);

		$this->assertContains( 'EUR', $codes );
		$this->assertContains( 'SEK', $codes );
		$this->assertNotContains( 'JPY', $codes );
	}

	public function test_end_to_end_conversion_over_real_settings(): void {
		$settings  = $this->seed_settings();
		$registry  = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$converter = new Converter( new ManualRateProvider( $settings, 'EUR' ), $registry );

		$this->assertSame( '1150.00', $converter->convert( '100', 'SEK' ) );
		$this->assertSame( '100.00', $converter->convert( '100', 'EUR' ) );
	}

	public function test_domain_layer_mutates_no_store_data_and_registers_no_hooks(): void {
		$products_before = count(
			wc_get_products(
				array(
					'limit'  => -1,
					'return' => 'ids',
				)
			)
		);
		$orders_before   = count(
			wc_get_orders(
				array(
					'limit'  => -1,
					'return' => 'ids',
				)
			)
		);

		$settings  = $this->seed_settings();
		$registry  = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$converter = new Converter( new ManualRateProvider( $settings, 'EUR' ), $registry );
		$converter->convert( '100', 'SEK' );

		$this->assertSame(
			$products_before,
			count(
				wc_get_products(
					array(
						'limit'  => -1,
						'return' => 'ids',
					)
				)
			)
		);
		$this->assertSame(
			$orders_before,
			count(
				wc_get_orders(
					array(
						'limit'  => -1,
						'return' => 'ids',
					)
				)
			)
		);

		// The domain layer must never touch stock/order/cart hooks. Storefront
		// price/currency filters are legitimately added by the plugin from
		// Milestone 2 on, so they are asserted in StorefrontGuardTest instead.
		foreach ( array( 'woocommerce_product_get_stock_quantity', 'woocommerce_cart_calculate_fees' ) as $hook ) {
			$this->assertSame( array(), $this->umc_callbacks_on( $hook ), "The domain layer must not hook '{$hook}'." );
		}
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
				} elseif ( is_string( $function ) && 0 === strpos( $function, 'UMC\\' ) ) {
					$found[] = $function;
				}
			}
		}

		return $found;
	}
}
