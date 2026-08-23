<?php
/**
 * Public free-shipping threshold display API + checkout parity (v1.2.0).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration;

use ReflectionClass;
use UMC\Converter;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\FreeShippingThresholdResolver;
use UMC\Integration\PriceConversionService;
use UMC\Integration\ShippingConversion;
use UMC\Plugin;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;
use UMC\PublicApi\FreeShippingThresholdDisplayService;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Support\GoldenTransactionFixtures as Golden;
use UMC\Tests\Support\ProductPricingTestGraph;
use WC_Product_Simple;
use WC_Shipping_Zone;
use WP_UnitTestCase;

/**
 * Proves shared-resolver checkout/display parity and the public facade contract.
 *
 * @group free-shipping
 * @group v120
 */
final class FreeShippingThresholdDisplayApiTest extends WP_UnitTestCase {

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

	private const BASE_THRESHOLD = '200.00';

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

	/**
	 * Shared threshold resolver under test.
	 *
	 * @var FreeShippingThresholdResolver|null
	 */
	private ?FreeShippingThresholdResolver $resolver = null;

	/**
	 * Public display service under test.
	 *
	 * @var FreeShippingThresholdDisplayService|null
	 */
	private ?FreeShippingThresholdDisplayService $display = null;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext|null
	 */
	private ?CurrencyContext $context = null;

	/**
	 * Price conversion seam.
	 *
	 * @var PriceConversionService|null
	 */
	private ?PriceConversionService $service = null;

	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/src/api.php';

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
		$this->unbind_plugin_display();

		parent::tear_down();
	}

	private function activate( string $active ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		update_option( 'woocommerce_currency', Golden::BASE );
		update_option( 'woocommerce_price_num_decimals', 2 );

		( new Settings() )->save(
			array(
				'currencies' => array(
					Golden::FOREIGN => array(
						'rate'     => Golden::RATE,
						'enabled'  => true,
						'decimals' => 2,
					),
					'JPY'           => array(
						'rate'     => '15.5',
						'enabled'  => true,
						'decimals' => 0,
					),
				),
			)
		);

		$settings       = new Settings();
		$registry       = new CurrencyRegistry( $settings, new Currency( Golden::BASE, 2 ) );
		$rates          = new ManualRateProvider( $settings, Golden::BASE );
		$this->context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$this->service  = new PriceConversionService( $this->context );
		$this->resolver = new FreeShippingThresholdResolver( $this->service, $this->context );
		$this->display  = new FreeShippingThresholdDisplayService( $this->resolver, $this->context );

		ProductPricingTestGraph::register( $this->context, $registry );
		( new CurrencyFormatting( $this->context ) )->register();
		( new ShippingConversion( $this->service, $this->context, $this->resolver ) )->register();

		if ( Golden::BASE === $active ) {
			unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		} else {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
		}

		$this->bind_plugin_display( $this->display );
	}

	private function bind_plugin_display( FreeShippingThresholdDisplayService $display ): void {
		$plugin = Plugin::instance();
		$ref    = new ReflectionClass( $plugin );
		$prop   = $ref->getProperty( 'free_shipping_display_service' );
		$prop->setAccessible( true );
		$prop->setValue( $plugin, $display );
	}

	private function unbind_plugin_display(): void {
		$plugin = Plugin::instance();
		$ref    = new ReflectionClass( $plugin );
		$prop   = $ref->getProperty( 'free_shipping_display_service' );
		$prop->setAccessible( true );
		$prop->setValue( $plugin, null );
	}

	private function free_shipping_with_min( string $min ): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'V120 Free Shipping API' );
		$zone->add_location( 'SE', 'country' );
		$this->instance_id = (int) $zone->add_shipping_method( 'free_shipping' );
		$zone->save();

		update_option(
			'woocommerce_free_shipping_' . $this->instance_id . '_settings',
			array(
				'enabled'          => 'yes',
				'title'            => 'Free shipping',
				'requires'         => 'min_amount',
				'min_amount'       => $min,
				'ignore_discounts' => 'no',
			)
		);

		$this->zone = $zone;
		WC()->shipping()->unregister_shipping_methods();
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
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

	private function native_product( string $regular ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Native boundary ' . $regular );
		$product->set_regular_price( $regular );
		$product->set_virtual( false );
		$product->set_status( 'publish' );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	private function fixed_product( string $currency, string $amount ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Fixed boundary ' . $currency . ' ' . $amount );
		$product->set_regular_price( '1' );
		$product->set_virtual( false );
		$product->set_status( 'publish' );
		$product->save();

		$repo = new FixedPriceRepository( Golden::BASE );
		$repo->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					$currency => array(
						'regular' => $amount,
					),
				),
				Golden::BASE
			)
		);
		wc_delete_product_transients( $product->get_id() );

		return wc_get_product( $product->get_id() );
	}

	public function test_function_exists_after_api_bootstrap(): void {
		$this->assertTrue( function_exists( 'umc_get_free_shipping_threshold_display' ) );
	}

	public function test_unbound_plugin_returns_null(): void {
		$this->unbind_plugin_display();
		$this->assertNull( umc_get_free_shipping_threshold_display( self::BASE_THRESHOLD ) );
	}

	public function test_invalid_inputs_return_null(): void {
		$this->activate( Golden::FOREIGN );

		$this->assertNull( umc_get_free_shipping_threshold_display( '' ) );
		$this->assertNull( umc_get_free_shipping_threshold_display( 'abc' ) );
		$this->assertNull( umc_get_free_shipping_threshold_display( '-10' ) );
		$this->assertNull( umc_get_free_shipping_threshold_display( '200.001' ) );
	}

	public function test_foreign_parity_with_fixed_price_boundary_cart(): void {
		$this->activate( Golden::FOREIGN );
		$this->free_shipping_with_min( self::BASE_THRESHOLD );

		$api = umc_get_free_shipping_threshold_display( self::BASE_THRESHOLD );
		$this->assertIsArray( $api );
		$this->assertSame( array( 'formatted_html', 'amount', 'currency_code' ), array_keys( $api ) );
		$this->assertSame( Golden::FOREIGN, $api['currency_code'] );

		$resolved = $this->resolver->resolve( self::BASE_THRESHOLD );
		$this->assertNotNull( $resolved );
		$this->assertSame( $resolved->amount(), $api['amount'] );
		$this->assertSame(
			Converter::apply_rate( self::BASE_THRESHOLD, Golden::RATE, 2 ),
			$api['amount']
		);

		$amount = $api['amount'];
		$below  = $this->decrement_minor( $amount, 2 );
		$above  = $this->increment_minor( $amount, 2 );

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->fixed_product( Golden::FOREIGN, $below )->get_id(), 1 );
		$this->assertFalse( $this->free_shipping_offered(), 'One minor below must not qualify.' );

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->fixed_product( Golden::FOREIGN, $amount )->get_id(), 1 );
		$this->assertTrue( $this->free_shipping_offered(), 'Exact API amount must qualify.' );

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->fixed_product( Golden::FOREIGN, $above )->get_id(), 1 );
		$this->assertTrue( $this->free_shipping_offered(), 'One minor above must qualify.' );

		$this->assertStringContainsString( 'woocommerce-Price-amount', $api['formatted_html'] );

		$settings_before = get_option( 'woocommerce_free_shipping_' . $this->instance_id . '_settings' );
		umc_get_free_shipping_threshold_display( self::BASE_THRESHOLD );
		$this->assertSame(
			$settings_before,
			get_option( 'woocommerce_free_shipping_' . $this->instance_id . '_settings' ),
			'API must not mutate shipping settings.'
		);
	}

	public function test_base_currency_parity_with_fixed_price_boundary(): void {
		$this->activate( Golden::BASE );
		$this->free_shipping_with_min( self::BASE_THRESHOLD );

		$api = umc_get_free_shipping_threshold_display( self::BASE_THRESHOLD );
		$this->assertIsArray( $api );
		$this->assertSame( Golden::BASE, $api['currency_code'] );
		$this->assertSame( '200.00', $api['amount'] );

		// Base currency uses native WooCommerce product prices (fixed prices are foreign-only).
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->native_product( '199.99' )->get_id(), 1 );
		$this->assertFalse( $this->free_shipping_offered() );

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->native_product( '200.00' )->get_id(), 1 );
		$this->assertTrue( $this->free_shipping_offered() );
	}

	public function test_usa_shaped_consumer_uses_formatted_html_only(): void {
		$this->activate( Golden::FOREIGN );

		$threshold = umc_get_free_shipping_threshold_display( '200.00' );
		$this->assertIsArray( $threshold );

		$message = sprintf(
			'Free shipping on orders of %s or more',
			$threshold['formatted_html']
		);

		$this->assertStringContainsString( $threshold['formatted_html'], $message );
		$this->assertStringNotContainsString( Golden::RATE, $message );
	}

	public function test_zero_decimal_foreign_accepts_valid_base_fraction(): void {
		$this->activate( 'JPY' );
		$api = umc_get_free_shipping_threshold_display( '200.50' );

		$this->assertIsArray( $api );
		$this->assertSame( 'JPY', $api['currency_code'] );
		$this->assertMatchesRegularExpression( '/^\d+$/', $api['amount'] );
	}

	private function decrement_minor( string $amount, int $decimals ): string {
		$factor = 10 ** $decimals;
		$value  = (int) round( (float) $amount * $factor ) - 1;

		return number_format( $value / $factor, $decimals, '.', '' );
	}

	private function increment_minor( string $amount, int $decimals ): string {
		$factor = 10 ** $decimals;
		$value  = (int) round( (float) $amount * $factor ) + 1;

		return number_format( $value / $factor, $decimals, '.', '' );
	}
}
