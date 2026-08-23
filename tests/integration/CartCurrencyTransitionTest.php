<?php
/**
 * Integration tests: classic cart recalculation across currency and rate changes.
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
use WP_UnitTestCase;

/**
 * Proves Invariant A on the classic cart: switching currency or rate rebuilds
 * totals from base-authored amounts (no compounding), including fixed coupons.
 */
final class CartCurrencyTransitionTest extends WP_UnitTestCase {

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
	 * Currencies last passed to {@see self::activate()}.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $booted_currencies = array();

	public function set_up(): void {
		parent::set_up();

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		WC()->session->set( CurrencyContext::SESSION_KEY, null );
		WC()->session->set( CartRecalculation::SESSION_KEY, null );

		WC()->customer = new \WC_Customer( 0, true );
		WC()->cart     = new \WC_Cart();
		WC()->cart->empty_cart();

		update_option( 'woocommerce_calc_taxes', 'no' );
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

	public function test_switch_with_fixed_coupon_recalculates_without_compounding(): void {
		$this->activate( Golden::currencies(), Golden::BASE, Golden::BASE );

		$product = $this->simple_product( Golden::PRODUCT_PRICE );
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$coupon = new WC_Coupon();
		$coupon->set_code( 'm18fixed' );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( (float) Golden::FIXED_COUPON );
		$coupon->save();
		WC()->cart->apply_coupon( 'm18fixed' );
		WC()->cart->calculate_totals();

		$base_total = (float) WC()->cart->get_total( 'edit' );
		$this->assertEqualsWithDelta(
			(float) Golden::PRODUCT_PRICE - (float) Golden::FIXED_COUPON,
			$base_total,
			0.001
		);

		$this->switch_currency( Golden::FOREIGN );
		WC()->cart->calculate_totals();

		$expected_product = (float) Golden::converted_product_price();
		$expected_coupon  = (float) \UMC\Converter::apply_rate( Golden::FIXED_COUPON, Golden::RATE, 2 );
		$foreign_total    = (float) WC()->cart->get_total( 'edit' );

		$this->assertEqualsWithDelta(
			$expected_product - $expected_coupon,
			$foreign_total,
			0.001,
			'Totals must be re-derived from base amounts, not from prior display currency.'
		);

		// Switch again to the same foreign currency: must not multiply twice.
		$this->switch_currency( Golden::FOREIGN );
		WC()->cart->calculate_totals();

		$this->assertEqualsWithDelta(
			$foreign_total,
			(float) WC()->cart->get_total( 'edit' ),
			0.001,
			'Rebuilding under the same currency must not compound conversion.'
		);
	}

	public function test_rate_change_same_currency_recalculates(): void {
		$this->activate( Golden::currencies(), Golden::FOREIGN, Golden::BASE );

		$product = $this->simple_product( '1000' );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$first = (float) WC()->cart->get_total( 'edit' );
		$this->assertEqualsWithDelta(
			(float) \UMC\Converter::apply_rate( '1000', Golden::RATE, 2 ),
			$first,
			0.001
		);

		$updated = array(
			Golden::FOREIGN => array(
				'rate'    => '0.10',
				'enabled' => true,
			),
		);

		$this->activate( $updated, Golden::FOREIGN, Golden::BASE );
		$this->rehydrate_cart();
		WC()->cart->calculate_totals();

		$this->assertEqualsWithDelta(
			100.0,
			(float) WC()->cart->get_total( 'edit' ),
			0.001,
			'A rate correction must recalculate the cart under the same currency code.'
		);
		$this->assertNotEquals( $first, (float) WC()->cart->get_total( 'edit' ) );
	}

	/**
	 * Builds and registers price + coupon + shipping + cart recalculation hooks.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code.
	 * @param string                              $base       Store base currency.
	 */
	private function activate( array $currencies, string $active, string $base ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		$this->booted_currencies = $currencies;

		update_option( 'woocommerce_currency', $base );
		update_option( 'woocommerce_price_num_decimals', 2 );

		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( $base, 2 ) );
		$rates    = new ManualRateProvider( $settings, $base );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		ProductPricingTestGraph::register( $context, $registry );
		( new CurrencyFormatting( $context ) )->register();
		( new CartRecalculation( $context ) )->register();
		( new CouponConversion( $service, $context ) )->register();
		( new ShippingConversion( $service, $context, new FreeShippingThresholdResolver( $service, $context ) ) )->register();

		if ( $active === $base ) {
			unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		} else {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
		}
	}

	/**
	 * Selects a different currency the way a new request would see it.
	 *
	 * @param string $code Currency code to select.
	 */
	private function switch_currency( string $code ): void {
		$this->activate( $this->booted_currencies, $code, Golden::BASE );
		$this->rehydrate_cart();
	}

	/**
	 * Reloads the cart from session so recalculation sees a fresh request.
	 */
	private function rehydrate_cart(): void {
		WC()->cart = new \WC_Cart();
		( new \WC_Cart_Session( WC()->cart ) )->get_cart_from_session();
	}

	/**
	 * Creates a published simple product.
	 *
	 * @param string $regular Regular price in base currency.
	 */
	private function simple_product( string $regular ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Transition product' );
		$product->set_regular_price( $regular );
		$product->set_status( 'publish' );
		$product->save();

		return wc_get_product( $product->get_id() );
	}
}
