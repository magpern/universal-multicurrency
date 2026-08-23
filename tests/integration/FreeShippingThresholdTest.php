<?php
/**
 * Integration tests: free-shipping min_amount must convert with the cart.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration;

use UMC\Cart\CartRecalculation;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CouponConversion;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Tests\Support\ProductPricingTestGraph;
use UMC\Integration\FreeShippingThresholdResolver;
use UMC\Integration\ShippingConversion;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Support\GoldenTransactionFixtures as Golden;
use WC_Coupon;
use WC_Product_Simple;
use WC_Shipping_Zone;
use WP_UnitTestCase;

/**
 * Proves free-shipping eligibility compares an active-currency threshold to
 * active-currency cart totals (Invariant K), without mutating persisted
 * shipping-method settings.
 */
final class FreeShippingThresholdTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_currency',
		'woocommerce_currency_symbol',
		'wc_get_price_decimals',
		'wc_price_args',
		'woocommerce_cart_loaded_from_session',
		'woocommerce_coupon_get_amount',
		'woocommerce_coupon_get_minimum_amount',
		'woocommerce_coupon_get_maximum_amount',
		'woocommerce_package_rates',
		'woocommerce_cart_shipping_packages',
		'woocommerce_shipping_free_shipping_is_available',
		'umc_convert_shipping_rate',
		'umc_coupon_amount_is_base',
	);

	/**
	 * Shipping zone under test.
	 *
	 * @var WC_Shipping_Zone|null
	 */
	private ?WC_Shipping_Zone $zone = null;

	/**
	 * Free-shipping instance id.
	 *
	 * @var int
	 */
	private int $instance_id = 0;

	public function set_up(): void {
		parent::set_up();

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		WC()->customer = new \WC_Customer( 0, true );
		WC()->cart     = new \WC_Cart();
		WC()->cart->empty_cart();

		update_option( 'woocommerce_ship_to_countries', 'all' );
		update_option( 'woocommerce_enable_shipping_calc', 'yes' );
		update_option( 'woocommerce_shipping_cost_requires_address', 'no' );
		update_option( 'woocommerce_calc_taxes', 'no' );

		WC()->customer->set_shipping_country( 'SE' );
		WC()->customer->set_shipping_postcode( '11122' );
	}

	public function tear_down(): void {
		WC()->cart->empty_cart();

		if ( $this->zone instanceof WC_Shipping_Zone ) {
			$this->zone->delete( true );
			$this->zone = null;
		}

		\WC_Cache_Helper::get_transient_version( 'shipping', true );

		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	/**
	 * Boots conversion graph with SEK base and EUR active.
	 */
	private function activate_foreign(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		update_option( 'woocommerce_currency', Golden::BASE );
		update_option( 'woocommerce_price_num_decimals', 2 );

		( new Settings() )->save( array( 'currencies' => Golden::currencies() ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( Golden::BASE, 2 ) );
		$rates    = new ManualRateProvider( $settings, Golden::BASE );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		ProductPricingTestGraph::register( $context, $registry );
		( new CurrencyFormatting( $context ) )->register();
		( new CartRecalculation( $context ) )->register();
		( new CouponConversion( $service, $context ) )->register();
		( new ShippingConversion( $service, $context, new FreeShippingThresholdResolver( $service, $context ) ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = Golden::FOREIGN;
	}

	/**
	 * Creates a free-shipping zone requiring the golden base-currency min amount.
	 */
	private function free_shipping_with_min(): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'M18 Free Shipping' );
		$zone->add_location( 'SE', 'country' );
		$this->instance_id = (int) $zone->add_shipping_method( 'free_shipping' );
		$zone->save();

		update_option(
			'woocommerce_free_shipping_' . $this->instance_id . '_settings',
			array(
				'enabled'          => 'yes',
				'title'            => 'Free shipping',
				'requires'         => 'min_amount',
				'min_amount'       => Golden::FREE_SHIPPING_MIN,
				'ignore_discounts' => 'no',
			)
		);

		$this->zone = $zone;

		WC()->shipping()->unregister_shipping_methods();
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	private function simple_product( string $regular ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Shipable' );
		$product->set_regular_price( $regular );
		$product->set_virtual( false );
		$product->set_status( 'publish' );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	/**
	 * Whether free shipping appears among calculated package rates.
	 */
	private function free_shipping_offered(): bool {
		WC()->cart->calculate_totals();
		$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );

		foreach ( $packages as $package ) {
			foreach ( $package['rates'] as $rate ) {
				if ( 'free_shipping' === $rate->get_method_id() ) {
					return true;
				}
			}
		}

		return false;
	}

	public function test_cart_below_converted_threshold_is_not_eligible(): void {
		$this->activate_foreign();
		$this->free_shipping_with_min();

		// 999 SEK → ~89.03 EUR; converted threshold ≈ 89.12 EUR → not eligible.
		$product = $this->simple_product( '999' );
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$this->assertFalse(
			$this->free_shipping_offered(),
			'Cart below the converted threshold must not unlock free shipping.'
		);
	}

	public function test_cart_at_or_above_converted_threshold_is_eligible(): void {
		$this->activate_foreign();
		$this->free_shipping_with_min();

		// Exactly the free-shipping min in base → converts to the same active amount.
		$product = $this->simple_product( Golden::FREE_SHIPPING_MIN );
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$this->assertTrue(
			$this->free_shipping_offered(),
			'Cart meeting the converted threshold must unlock free shipping.'
		);
	}

	public function test_persisted_min_amount_settings_remain_base_authored(): void {
		$this->activate_foreign();
		$this->free_shipping_with_min();

		$product = $this->simple_product( Golden::FREE_SHIPPING_MIN );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		$this->free_shipping_offered();

		$settings = get_option( 'woocommerce_free_shipping_' . $this->instance_id . '_settings', array() );

		$this->assertSame(
			Golden::FREE_SHIPPING_MIN,
			(string) $settings['min_amount'],
			'Eligibility conversion must not persist a converted threshold.'
		);
	}

	public function test_base_currency_still_uses_raw_threshold(): void {
		$this->activate_foreign();
		$this->free_shipping_with_min();

		// Switch to base: no conversion of threshold or prices.
		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = Golden::BASE;
		// Rebuild context memo by re-activating without foreign cookie.
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}
		update_option( 'woocommerce_currency', Golden::BASE );
		( new Settings() )->save( array( 'currencies' => Golden::currencies() ) );
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( Golden::BASE, 2 ) );
		$rates    = new ManualRateProvider( $settings, Golden::BASE );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );
		ProductPricingTestGraph::register( $context, $registry );
		( new CurrencyFormatting( $context ) )->register();
		( new CartRecalculation( $context ) )->register();
		( new ShippingConversion( $service, $context, new FreeShippingThresholdResolver( $service, $context ) ) )->register();
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );

		$below = $this->simple_product( '999' );
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $below->get_id(), 1 );
		$this->assertFalse( $this->free_shipping_offered() );

		WC()->cart->empty_cart();
		$at = $this->simple_product( Golden::FREE_SHIPPING_MIN );
		WC()->cart->add_to_cart( $at->get_id(), 1 );
		$this->assertTrue( $this->free_shipping_offered() );
	}

	public function test_ignore_discounts_uses_pre_discount_subtotal(): void {
		$this->activate_foreign();
		$this->free_shipping_with_min();

		update_option(
			'woocommerce_free_shipping_' . $this->instance_id . '_settings',
			array(
				'enabled'          => 'yes',
				'title'            => 'Free shipping',
				'requires'         => 'min_amount',
				'min_amount'       => Golden::FREE_SHIPPING_MIN,
				'ignore_discounts' => 'yes',
			)
		);
		WC()->shipping()->unregister_shipping_methods();
		\WC_Cache_Helper::get_transient_version( 'shipping', true );

		$product = $this->simple_product( Golden::FREE_SHIPPING_MIN );
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$coupon = new WC_Coupon();
		$coupon->set_code( 'm18fixed' );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( Golden::FIXED_COUPON );
		$coupon->save();
		WC()->cart->apply_coupon( 'm18fixed' );

		$this->assertTrue(
			$this->free_shipping_offered(),
			'With ignore_discounts, a qualifying pre-discount subtotal still unlocks free shipping.'
		);
	}
}
