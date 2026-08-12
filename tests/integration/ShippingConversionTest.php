<?php
/**
 * Integration tests: core shipping-rate conversion and per-currency cache
 * isolation.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\PriceConversionService;
use UMC\Integration\ShippingConversion;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Shipping_Rate;
use WP_UnitTestCase;

/**
 * Core methods (flat_rate/free_shipping/local_pickup) convert cost + taxes;
 * non-core rates pass through; the shipping cache is isolated per currency+rate.
 */
final class ShippingConversionTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_package_rates',
		'woocommerce_cart_shipping_packages',
		'woocommerce_shipping_free_shipping_is_available',
		'umc_convert_shipping_rate',
	);

	public function tear_down(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	/**
	 * Registers shipping conversion and forces the active currency.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code.
	 */
	private function activate( array $currencies, string $active ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		update_option( 'woocommerce_currency', 'EUR' );

		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		( new ShippingConversion( $service, $context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	public function test_core_flat_rate_cost_is_converted(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$rate  = new WC_Shipping_Rate( 'flat_rate:1', 'Flat', '10', array(), 'flat_rate' );
		$rates = apply_filters( 'woocommerce_package_rates', array( 'flat_rate:1' => $rate ), array() );

		$this->assertEquals( 115.0, (float) $rates['flat_rate:1']->get_cost() );
	}

	public function test_non_core_shipping_rate_passes_through_unchanged(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$rate  = new WC_Shipping_Rate( 'custom:1', 'Custom', '10', array(), 'custom_method' );
		$rates = apply_filters( 'woocommerce_package_rates', array( 'custom:1' => $rate ), array() );

		$this->assertEquals( 10.0, (float) $rates['custom:1']->get_cost() );
	}

	public function test_shipping_taxes_are_scaled_with_the_cost(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$rate  = new WC_Shipping_Rate( 'flat_rate:1', 'Flat', '10', array( 1 => 2.5 ), 'flat_rate' );
		$rates = apply_filters( 'woocommerce_package_rates', array( 'flat_rate:1' => $rate ), array() );

		$taxes = $rates['flat_rate:1']->get_taxes();
		$this->assertEquals( 28.75, (float) $taxes[1] ); // 2.5 * 11.5.
	}

	public function test_base_currency_leaves_rates_unchanged(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$rate  = new WC_Shipping_Rate( 'flat_rate:1', 'Flat', '10', array(), 'flat_rate' );
		$rates = apply_filters( 'woocommerce_package_rates', array( 'flat_rate:1' => $rate ), array() );

		$this->assertEquals( 10.0, (float) $rates['flat_rate:1']->get_cost() );
	}

	public function test_shipping_package_cache_is_isolated_per_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$packages = apply_filters( 'woocommerce_cart_shipping_packages', array( array( 'contents' => array() ) ) );
		$this->assertSame( 'SEK:11.50', $packages[0]['umc_currency_signature'] );

		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$base = apply_filters( 'woocommerce_cart_shipping_packages', array( array( 'contents' => array() ) ) );
		$this->assertArrayNotHasKey( 'umc_currency_signature', $base[0] );
	}
}
