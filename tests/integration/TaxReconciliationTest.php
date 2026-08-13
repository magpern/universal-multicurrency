<?php
/**
 * Integration tests: taxes are computed natively on converted amounts, totals
 * reconcile, and repeated recalculation does not drift.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

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
use WC_Tax;
use WP_UnitTestCase;

/**
 * M3 adds no tax-conversion hooks: WooCommerce computes tax natively on the
 * already-converted unit prices. These tests prove the parts reconcile and that
 * recalculating repeatedly is stable.
 */
final class TaxReconciliationTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_currency',
		'wc_get_price_decimals',
	);

	/**
	 * Inserted tax-rate id, for cleanup.
	 *
	 * @var int
	 */
	private int $rate_id = 0;

	public function set_up(): void {
		parent::set_up();

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		WC()->customer = new \WC_Customer( 0, true );
		WC()->cart     = new \WC_Cart();
		WC()->cart->empty_cart();

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_based_on', 'base' );
		update_option( 'woocommerce_default_country', 'SE' );

		$this->rate_id = WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '25.0000',
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_class'    => '',
			)
		);
	}

	public function tear_down(): void {
		WC()->cart->empty_cart();

		if ( $this->rate_id > 0 ) {
			WC_Tax::_delete_tax_rate( $this->rate_id );
		}

		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		update_option( 'woocommerce_calc_taxes', 'no' );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	/**
	 * Registers unit-price conversion + formatting and forces the currency.
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

		WC()->customer->set_billing_country( 'SE' );
		WC()->customer->set_shipping_country( 'SE' );

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	private function add_product( string $regular, int $qty = 1 ): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Product' );
		$product->set_regular_price( $regular );
		$product->set_status( 'publish' );
		$product->save();

		WC()->cart->add_to_cart( $product->get_id(), $qty );
	}

	public function test_tax_and_totals_reconcile_in_active_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->add_product( '100' );
		WC()->cart->calculate_totals();

		$subtotal = (float) WC()->cart->get_subtotal();       // 1150.
		$discount = (float) WC()->cart->get_discount_total();  // 0.
		$tax      = (float) WC()->cart->get_total_tax();       // 287.50.
		$total    = (float) WC()->cart->get_total( 'edit' );   // 1437.50.

		$this->assertEquals( 1150.0, $subtotal );
		$this->assertEquals( 287.5, $tax );
		$this->assertEqualsWithDelta( $subtotal - $discount + $tax, $total, 0.001 );
	}

	public function test_repeated_recalculation_does_not_drift(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->add_product( '100', 3 );

		WC()->cart->calculate_totals();
		$first = (float) WC()->cart->get_total( 'edit' );

		for ( $i = 0; $i < 4; $i++ ) {
			WC()->cart->calculate_totals();
		}
		$later = (float) WC()->cart->get_total( 'edit' );

		$this->assertSame( $first, $later );
	}
}
