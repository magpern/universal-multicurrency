<?php
/**
 * Opt-in fee conversion integration tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Compatibility;

use UMC\Cart\CartRecalculation;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\FeeConversion;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Tests\Support\ProductPricingTestGraph;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Integration tests for opt-in fee conversion (M19).
 */
final class FeeConversionTest extends WP_UnitTestCase {

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
		remove_all_filters( 'umc_convert_fee' );
		remove_all_actions( 'woocommerce_cart_calculate_fees' );
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_default_fee_remains_unconverted(): void {
		$this->activate();

		$product = new WC_Product_Simple();
		$product->set_regular_price( '100' );
		$product->save();

		WC()->cart->add_to_cart( $product->get_id(), 1 );

		add_action(
			'woocommerce_cart_calculate_fees',
			static function (): void {
				WC()->cart->add_fee( 'Handling', 10.0, false );
			}
		);

		WC()->cart->calculate_totals();

		$fees = WC()->cart->get_fees();
		$fee  = reset( $fees );
		$this->assertEqualsWithDelta( 10.0, (float) $fee->amount, 0.001 );
	}

	public function test_opt_in_fee_is_converted_once(): void {
		$this->activate();

		add_filter(
			'umc_convert_fee',
			static function ( bool $convert, $fee ): bool {
				return 'Base fee' === (string) ( $fee->name ?? '' );
			},
			10,
			2
		);

		$product = new WC_Product_Simple();
		$product->set_regular_price( '100' );
		$product->save();

		WC()->cart->add_to_cart( $product->get_id(), 1 );

		add_action(
			'woocommerce_cart_calculate_fees',
			static function (): void {
				WC()->cart->add_fee( 'Base fee', 10.0, false );
				WC()->cart->add_fee( 'Active fee', 5.0, false );
			}
		);

		WC()->cart->calculate_totals();

		$amounts = array();
		foreach ( WC()->cart->get_fees() as $fee ) {
			$amounts[ (string) $fee->name ] = (float) $fee->amount;
		}

		$this->assertEqualsWithDelta( 115.0, $amounts['Base fee'], 0.001 );
		$this->assertEqualsWithDelta( 5.0, $amounts['Active fee'], 0.001 );
	}

	private function activate(): void {
		update_option( 'woocommerce_currency', 'EUR' );
		( new Settings() )->save( array( 'currencies' => array( 'SEK' => array( 'rate' => '11.50' ) ) ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		ProductPricingTestGraph::register( $context, $registry );
		( new CurrencyFormatting( $context ) )->register();
		( new CartRecalculation( $context ) )->register();
		( new FeeConversion( $service, $context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = 'SEK';
	}
}
