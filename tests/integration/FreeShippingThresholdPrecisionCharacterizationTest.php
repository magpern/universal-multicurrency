<?php
/**
 * WP1 characterization: free-shipping threshold precision vs display/eligibility.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration;

use UMC\Converter;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\PriceConversionService;
use UMC\Integration\FreeShippingThresholdResolver;
use UMC\Integration\ShippingConversion;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Support\GoldenTransactionFixtures as Golden;
use UMC\Tests\Support\ProductPricingTestGraph;
use WC_Product_Simple;
use WC_Shipping_Zone;
use WP_UnitTestCase;

/**
 * Empirically records WooCommerce / UMC free-shipping threshold precision
 * behaviour for ADR-0034 WP1 (over-precision scope).
 *
 * @group free-shipping
 * @group wp1-characterization
 */
final class FreeShippingThresholdPrecisionCharacterizationTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_currency',
		'woocommerce_currency_symbol',
		'wc_get_price_decimals',
		'wc_price_args',
		'woocommerce_cart_loaded_from_session',
		'woocommerce_package_rates',
		'woocommerce_cart_shipping_packages',
		'woocommerce_shipping_free_shipping_is_available',
		'umc_convert_shipping_rate',
	);

	/**
	 * Over-precise base threshold (store decimals = 2).
	 */
	private const OVERPRECISE_MIN = '200.001';

	/**
	 * Valid two-decimal base threshold.
	 */
	private const PRECISE_MIN = '200.50';

	private ?WC_Shipping_Zone $zone = null;

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
		update_option( 'woocommerce_price_num_decimals', 2 );

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
	 * Boots SEK base with EUR (2dp) and JPY (0dp) selectables.
	 */
	private function activate_graph( string $active ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		update_option( 'woocommerce_currency', Golden::BASE );
		update_option( 'woocommerce_price_num_decimals', 2 );

		( new Settings() )->save(
			array(
				'currencies' => array(
					'EUR' => array(
						'rate'     => Golden::RATE,
						'enabled'  => true,
						'decimals' => 2,
					),
					'JPY' => array(
						'rate'     => '15.5',
						'enabled'  => true,
						'decimals' => 0,
					),
				),
			)
		);

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( Golden::BASE, 2 ) );
		$rates    = new ManualRateProvider( $settings, Golden::BASE );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		ProductPricingTestGraph::register( $context, $registry );
		( new CurrencyFormatting( $context ) )->register();
		( new ShippingConversion( $service, $context, new FreeShippingThresholdResolver( $service, $context ) ) )->register();

		if ( Golden::BASE === $active ) {
			unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		} else {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
		}
	}

	private function free_shipping_with_min( string $min_amount ): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'WP1 Precision' );
		$zone->add_location( 'SE', 'country' );
		$this->instance_id = (int) $zone->add_shipping_method( 'free_shipping' );
		$zone->save();

		update_option(
			'woocommerce_free_shipping_' . $this->instance_id . '_settings',
			array(
				'enabled'          => 'yes',
				'title'            => 'Free shipping',
				'requires'         => 'min_amount',
				'min_amount'       => $min_amount,
				'ignore_discounts' => 'no',
			)
		);

		$this->zone = $zone;
		WC()->shipping()->unregister_shipping_methods();
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	private function simple_product( string $regular ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Precision probe' );
		$product->set_regular_price( $regular );
		$product->set_virtual( false );
		$product->set_status( 'publish' );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

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

	/**
	 * Base active: cart at store-decimal 200.00 does NOT meet raw 200.001.
	 */
	public function test_base_active_overprecise_threshold_rejects_display_rounded_cart(): void {
		$this->activate_graph( Golden::BASE );
		$this->free_shipping_with_min( self::OVERPRECISE_MIN );

		$product = $this->simple_product( '200.00' );
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$this->assertFalse(
			$this->free_shipping_offered(),
			'Native WC compares against raw 200.001; a 200.00 cart must not qualify.'
		);

		$displayed = wp_strip_all_tags( wc_price( self::OVERPRECISE_MIN, array( 'currency' => Golden::BASE ) ) );
		$normalized = preg_replace( '/[^\d.,]/', '', $displayed );
		$this->assertMatchesRegularExpression(
			'/200[.,]00/',
			(string) $normalized,
			'wc_price hides the third fractional digit for a 2-decimal store (shows 200.00 / 200,00).'
		);
	}

	/**
	 * Foreign active: Converter rounds overprecise base input to target decimals.
	 */
	public function test_foreign_active_overprecise_threshold_converts_to_rounded_target(): void {
		$this->activate_graph( Golden::FOREIGN );
		$this->free_shipping_with_min( self::OVERPRECISE_MIN );

		$converted = Converter::apply_rate( self::OVERPRECISE_MIN, Golden::RATE, 2 );
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d{2}$/',
			$converted,
			'Foreign path produces a target-decimal string via Converter::apply_rate.'
		);

		$product = $this->simple_product( self::OVERPRECISE_MIN );
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$this->assertTrue(
			$this->free_shipping_offered(),
			'Foreign eligibility uses Converter-rounded threshold; exact converted cart qualifies.'
		);
	}

	/**
	 * Valid base 200.50 remains valid input even when active currency is 0-decimal JPY.
	 */
	public function test_valid_base_threshold_accepted_with_zero_decimal_active_currency(): void {
		$this->activate_graph( 'JPY' );
		$this->free_shipping_with_min( self::PRECISE_MIN );

		$converted = Converter::apply_rate( self::PRECISE_MIN, '15.5', 0 );
		$this->assertMatchesRegularExpression( '/^\d+$/', $converted );

		$product = $this->simple_product( self::PRECISE_MIN );
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$this->assertTrue(
			$this->free_shipping_offered(),
			'EUR/SEK-style 200.50 base input must convert through Converter for JPY (0dp).'
		);
	}
}
