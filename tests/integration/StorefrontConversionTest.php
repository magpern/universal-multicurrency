<?php
/**
 * Integration tests for runtime storefront price conversion.
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
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_UnitTestCase;

/**
 * Exercises the real WooCommerce price getters through the plugin's filters.
 */
final class StorefrontConversionTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_product_variation_get_price',
		'woocommerce_product_variation_get_regular_price',
		'woocommerce_product_variation_get_sale_price',
		'woocommerce_variation_prices_price',
		'woocommerce_variation_prices_regular_price',
		'woocommerce_variation_prices_sale_price',
		'woocommerce_get_variation_prices_hash',
		'woocommerce_currency',
		'woocommerce_currency_symbol',
		'wc_get_price_decimals',
		'wc_price_args',
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
	 * Registers a fresh conversion graph and forces the active currency.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code (via cookie).
	 */
	private function activate( array $currencies, string $active ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		// Align the store base with the injected base so currency-identity
		// assertions (get_woocommerce_currency) are meaningful.
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

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	private function simple_product( string $regular, string $sale = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_regular_price( $regular );
		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	private function variable_product( string $price_a, string $price_b ): WC_Product_Variable {
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Variable' );
		$parent->save();

		foreach ( array( $price_a, $price_b ) as $price ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_regular_price( $price );
			$variation->save();
		}

		wc_delete_product_transients( $parent->get_id() );

		return wc_get_product( $parent->get_id() );
	}

	public function test_simple_product_price_is_converted(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		$this->assertSame( '1150.00', $product->get_price() );
		$this->assertSame( '1150.00', $product->get_regular_price() );
	}

	public function test_base_currency_is_a_no_op(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$this->assertSame( '100', $product->get_price() );
		$this->assertSame( '100', $product->get_regular_price() );
	}

	public function test_sale_price_conversion_preserves_on_sale_flag(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100', '80' );

		$this->assertSame( '1150.00', $product->get_regular_price() );
		$this->assertSame( '920.00', $product->get_sale_price() );
		$this->assertTrue( $product->is_on_sale() );
	}

	public function test_empty_sale_price_stays_empty_and_not_on_sale(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		$this->assertSame( '', $product->get_sale_price() );
		$this->assertFalse( $product->is_on_sale() );
	}

	public function test_zero_decimal_currency_conversion(): void {
		$this->activate(
			array(
				'JPY' => array(
					'decimals' => 0,
					'rate'     => '161',
				),
			),
			'JPY'
		);
		$product = $this->simple_product( '100' );

		$this->assertSame( '16100', $product->get_price() );
	}

	public function test_variable_product_min_max_converted(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->variable_product( '100', '200' );

		$prices = $product->get_variation_prices( true );
		$values = array_values( $prices['price'] );

		$this->assertSame( '1150.00', (string) min( $values ) );
		$this->assertSame( '2300.00', (string) max( $values ) );
	}

	public function test_variation_prices_differ_per_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->variable_product( '100', '200' );
		$sek_min = (string) min( array_values( $product->get_variation_prices( true )['price'] ) );

		// Switch to base and re-read the same product: cache must not carry SEK.
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = wc_get_product( $product->get_id() );
		$eur_min = (string) min( array_values( $product->get_variation_prices( true )['price'] ) );

		// WooCommerce formats variation-price arrays to the store decimals, so
		// the base (unconverted) minimum is '100.00', not '100'.
		$this->assertSame( '1150.00', $sek_min );
		$this->assertSame( '100.00', $eur_min );
	}

	public function test_rate_change_reflects_on_next_read(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->variable_product( '100', '200' );
		$first   = (string) min( array_values( $product->get_variation_prices( true )['price'] ) );

		$this->activate( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$product = wc_get_product( $product->get_id() );
		$second  = (string) min( array_values( $product->get_variation_prices( true )['price'] ) );

		$this->assertSame( '1150.00', $first );
		$this->assertSame( '2000.00', $second );
	}

	public function test_currency_identity_and_symbol_follow_active_currency(): void {
		$this->activate(
			array(
				'SEK' => array(
					'symbol' => 'kr',
					'rate'   => '11.50',
				),
			),
			'SEK'
		);

		$this->assertSame( 'SEK', get_woocommerce_currency() );
		$this->assertSame( 'kr', get_woocommerce_currency_symbol() );
		$this->assertStringContainsString( 'kr', wc_price( 1150 ) );
	}

	public function test_rateless_currency_falls_back_to_base(): void {
		$this->activate(
			array(
				'SEK' => array(
					'enabled' => true,
					'rate'    => '',
				),
			),
			'SEK'
		);
		$product = $this->simple_product( '100' );

		// SEK has no usable rate → not selectable → base stays active, no conversion.
		$this->assertSame( '100', $product->get_price() );
		$this->assertSame( 'EUR', get_woocommerce_currency() );
	}

	public function test_base_prices_in_storage_are_untouched(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		// View context converts; edit context and stored meta remain base.
		$this->assertSame( '1150.00', $product->get_price( 'view' ) );
		$this->assertSame( '100', $product->get_regular_price( 'edit' ) );
		$this->assertSame( '100', get_post_meta( $product->get_id(), '_regular_price', true ) );
	}

	public function test_variation_prices_hash_includes_currency_and_rate(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$hash = apply_filters( 'woocommerce_get_variation_prices_hash', array( 'baseline' ) );

		$this->assertIsArray( $hash );
		$this->assertContains( 'SEK', $hash, 'Hash must vary by active currency code.' );
		$this->assertContains( '11.50', $hash, 'Hash must vary by rate so rate edits invalidate the cache.' );

		$this->activate( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$updated = apply_filters( 'woocommerce_get_variation_prices_hash', array( 'baseline' ) );

		$this->assertContains( '20', $updated );
		$this->assertNotSame( $hash, $updated, 'A rate change must produce a different variation-prices hash.' );
	}

	public function test_grouped_product_child_prices_convert_when_supported(): void {
		if ( ! class_exists( \WC_Product_Grouped::class ) ) {
			$this->markTestSkipped( 'WC_Product_Grouped is not available.' );
		}

		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$child_a = $this->simple_product( '100' );
		$child_b = $this->simple_product( '200' );

		$grouped = new \WC_Product_Grouped();
		$grouped->set_name( 'Grouped' );
		$grouped->set_status( 'publish' );
		$grouped->set_children( array( $child_a->get_id(), $child_b->get_id() ) );
		$grouped->save();

		$grouped = wc_get_product( $grouped->get_id() );
		$this->assertInstanceOf( \WC_Product_Grouped::class, $grouped );

		$children = array_map( 'wc_get_product', $grouped->get_children() );
		$prices   = array_map(
			static function ( $child ): string {
				return (string) $child->get_price();
			},
			$children
		);

		$this->assertContains( '1150.00', $prices );
		$this->assertContains( '2300.00', $prices );
	}

	public function test_external_product_price_converts_when_supported(): void {
		if ( ! class_exists( \WC_Product_External::class ) ) {
			$this->markTestSkipped( 'WC_Product_External is not available.' );
		}

		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$product = new \WC_Product_External();
		$product->set_name( 'External' );
		$product->set_regular_price( '100' );
		$product->set_product_url( 'https://example.com/buy' );
		$product->set_button_text( 'Buy' );
		$product->set_status( 'publish' );
		$product->save();

		$product = wc_get_product( $product->get_id() );

		$this->assertSame( '1150.00', $product->get_price() );
		$this->assertSame( '1150.00', $product->get_regular_price() );
	}
}
