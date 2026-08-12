<?php
/**
 * Characterization: cart fees are intentionally not converted (Known limitation).
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
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Documents the M18 fee boundary: {@see woocommerce_cart_calculate_fees} has no
 * UMC callbacks (also asserted by {@see StorefrontGuardTest}), and amounts
 * passed to {@see WC_Cart::add_fee()} stay exactly as authored — fee authors
 * must supply the effective active currency.
 */
final class FeeBoundaryTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_currency',
		'woocommerce_currency_symbol',
		'wc_get_price_decimals',
		'wc_price_args',
		'woocommerce_cart_loaded_from_session',
		'woocommerce_cart_calculate_fees',
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

	public function test_add_fee_amount_is_not_converted(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$product = new WC_Product_Simple();
		$product->set_name( 'Fee product' );
		$product->set_regular_price( '100' );
		$product->set_status( 'publish' );
		$product->save();

		WC()->cart->add_to_cart( $product->get_id(), 1 );

		// Authored as a base-looking 10.00; UMC must not turn this into 115.00 SEK.
		add_action(
			'woocommerce_cart_calculate_fees',
			static function (): void {
				WC()->cart->add_fee( 'Handling', 10.0, false );
			}
		);

		WC()->cart->calculate_totals();

		$fees = WC()->cart->get_fees();
		$this->assertNotEmpty( $fees, 'Expected the authored fee to be present.' );

		$fee = reset( $fees );
		$this->assertEqualsWithDelta(
			10.0,
			(float) $fee->amount,
			0.001,
			'Fees are a Known limitation: add_fee amounts stay as authored (not converted).'
		);

		// Product line converts; fee does not — total = converted product + raw fee.
		$this->assertEqualsWithDelta(
			1160.0,
			(float) WC()->cart->get_total( 'edit' ),
			0.001,
			'1150.00 converted product + unconverted 10.00 fee.'
		);
	}

	public function test_plugin_registers_no_fee_conversion_callback(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$hook      = $GLOBALS['wp_filter']['woocommerce_cart_calculate_fees'] ?? null;
		$callbacks = ( $hook instanceof \WP_Hook ) ? $hook->callbacks : array();
		$umc       = array();

		foreach ( $callbacks as $priority ) {
			foreach ( $priority as $callback ) {
				$fn = $callback['function'] ?? null;
				if ( is_array( $fn ) && is_object( $fn[0] ) && 0 === strpos( get_class( $fn[0] ), 'UMC\\' ) ) {
					$umc[] = get_class( $fn[0] );
				}
			}
		}

		$this->assertSame(
			array(),
			$umc,
			'UMC must not hook woocommerce_cart_calculate_fees (fee conversion unwired).'
		);
	}

	/**
	 * Registers the storefront conversion graph without fee hooks.
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

		( new PriceHooks( $service, $context ) )->register();
		( new CurrencyFormatting( $context ) )->register();
		( new CartRecalculation( $context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}
}
