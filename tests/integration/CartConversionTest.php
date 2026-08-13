<?php
/**
 * Integration tests: authoritative cart totals in the selected currency and
 * recalculation when the rate identity changes.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Cart\CartRecalculation;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Tests\Support\ProductPricingTestGraph;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Proves cart line totals are converted exactly once (unit-price-authoritative)
 * and that the cart recalculates when the currency or rate changes.
 */
final class CartConversionTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_variation_prices_price',
		'woocommerce_get_variation_prices_hash',
		'woocommerce_currency',
		'woocommerce_currency_symbol',
		'wc_get_price_decimals',
		'wc_price_args',
		'woocommerce_cart_loaded_from_session',
	);

	/**
	 * Currency context for the active graph.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

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
	 * Builds and registers a fresh conversion graph, forcing the active currency.
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

		$settings      = new Settings();
		$registry      = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates         = new ManualRateProvider( $settings, 'EUR' );
		$this->context = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service       = new PriceConversionService( $this->context );

		ProductPricingTestGraph::register( $this->context, $registry );
		( new CurrencyFormatting( $this->context ) )->register();
		( new CartRecalculation( $this->context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	private function simple_product( string $regular, string $sale = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Product' );
		$product->set_regular_price( $regular );
		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}
		$product->set_status( 'publish' );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	public function test_cart_subtotal_and_total_convert_to_active_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( 2300.0, WC()->cart->get_subtotal() );
		$this->assertEquals( 2300.0, (float) WC()->cart->get_total( 'edit' ) );
	}

	public function test_base_currency_cart_is_a_no_op(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( 200.0, WC()->cart->get_subtotal() );
	}

	public function test_sale_price_cart_uses_converted_sale_price(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		WC()->cart->add_to_cart( $this->simple_product( '100', '80' )->get_id(), 1 );
		WC()->cart->calculate_totals();

		// 80 EUR * 11.5 = 920 SEK.
		$this->assertEquals( 920.0, (float) WC()->cart->get_total( 'edit' ) );
	}

	public function test_no_double_conversion_of_cart_line_price(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 1 );
		WC()->cart->calculate_totals();

		// Exactly one conversion: 1150, never 1150*11.5.
		$this->assertEquals( 1150.0, (float) WC()->cart->get_total( 'edit' ) );
	}

	public function test_cart_recalculates_when_rate_identity_changes(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 1 );
		WC()->session->set( CartRecalculation::SESSION_KEY, 'EUR:1' );

		do_action( 'woocommerce_cart_loaded_from_session', WC()->cart );

		$this->assertSame( 'SEK:11.50', WC()->session->get( CartRecalculation::SESSION_KEY ) );
		$this->assertEquals( 1150.0, (float) WC()->cart->get_total( 'edit' ) );
	}
}
