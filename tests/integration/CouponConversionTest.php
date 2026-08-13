<?php
/**
 * Integration tests: coupon amount and spend-threshold conversion.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CouponConversion;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Tests\Support\ProductPricingTestGraph;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Coupon;
use WC_Discounts;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Fixed coupon amounts and min/max thresholds convert once base→active;
 * percentage coupons operate on already-converted totals.
 */
final class CouponConversionTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_currency',
		'wc_get_price_decimals',
		'woocommerce_coupon_get_amount',
		'woocommerce_coupon_get_minimum_amount',
		'woocommerce_coupon_get_maximum_amount',
		'umc_coupon_amount_is_base',
	);

	public function set_up(): void {
		parent::set_up();

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		WC()->customer = new \WC_Customer( 0, true );
		WC()->cart     = new \WC_Cart();
		WC()->cart->empty_cart();
	}

	public function tear_down(): void {
		WC()->cart->empty_cart();

		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	/**
	 * Builds and registers cart price + coupon conversion, forcing the currency.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code.
	 */
	private function activate( array $currencies, string $active ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_price_num_decimals', 2 );

		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		ProductPricingTestGraph::register( $context, $registry );
		( new CurrencyFormatting( $context ) )->register();
		( new CouponConversion( $service, $context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	private function simple_product( string $regular ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Product' );
		$product->set_regular_price( $regular );
		$product->set_status( 'publish' );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	private function make_coupon( string $code, string $type, string $amount, string $minimum = '' ): void {
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		if ( '' !== $minimum ) {
			$coupon->set_minimum_amount( $minimum );
		}
		$coupon->save();
	}

	public function test_fixed_cart_coupon_amount_is_converted_once(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->make_coupon( 'fix10', 'fixed_cart', '10' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 1 );
		WC()->cart->apply_coupon( 'fix10' );
		WC()->cart->calculate_totals();

		// 10 EUR off => 115 SEK off; 1150 - 115 = 1035.
		$this->assertEquals( 115.0, WC()->cart->get_discount_total() );
		$this->assertEquals( 1035.0, (float) WC()->cart->get_total( 'edit' ) );
	}

	public function test_fixed_product_coupon_amount_is_converted(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->make_coupon( 'fp10', 'fixed_product', '10' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 1 );
		WC()->cart->apply_coupon( 'fp10' );
		WC()->cart->calculate_totals();

		$this->assertEquals( 115.0, WC()->cart->get_discount_total() );
	}

	public function test_percentage_coupon_operates_on_converted_totals(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->make_coupon( 'p10', 'percent', '10' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 1 );
		WC()->cart->apply_coupon( 'p10' );
		WC()->cart->calculate_totals();

		// 10% of 1150 SEK = 115; the percentage amount itself is never converted.
		$this->assertEquals( 115.0, WC()->cart->get_discount_total() );
	}

	public function test_minimum_spend_threshold_is_converted_and_valid_above_it(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->make_coupon( 'min50', 'fixed_cart', '10', '50' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 1 );
		WC()->cart->calculate_totals();

		// Min 50 EUR => 575 SEK; cart 1150 SEK is above it → valid.
		$discounts = new WC_Discounts( WC()->cart );
		$this->assertTrue( true === $discounts->is_coupon_valid( new WC_Coupon( 'min50' ) ) );
	}

	public function test_minimum_spend_threshold_blocks_below_boundary(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->make_coupon( 'min50', 'fixed_cart', '10', '50' );
		WC()->cart->add_to_cart( $this->simple_product( '4' )->get_id(), 1 );
		WC()->cart->calculate_totals();

		// 4 EUR => 46 SEK, below the converted 575 SEK minimum → invalid.
		$discounts = new WC_Discounts( WC()->cart );
		$this->assertNotTrue( $discounts->is_coupon_valid( new WC_Coupon( 'min50' ) ) );
	}
}
