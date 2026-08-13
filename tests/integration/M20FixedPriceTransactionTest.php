<?php
/**
 * M20 acceptance: cart, checkout, provenance, mixed cart, and base exclusion.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Order\LineItemPriceProvenance;
use UMC\Order\OrderSnapshot;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\ProductPriceResolution;
use UMC\Tests\Support\M20PricingTestCase;
use WC_Order_Item_Product;

/**
 * @covers \UMC\Order\LineItemPriceProvenance
 * @covers \UMC\Pricing\ProductPriceProvenanceRegistry
 */
final class M20FixedPriceTransactionTest extends M20PricingTestCase {

	public function test_fixed_product_flows_through_cart_and_order_without_reconversion(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );
		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$product = wc_get_product( $product->get_id() );
		$this->reset_conversion_counter();
		$this->assertSame( '1100.00', $product->get_price() );

		$this->add_to_cart( $product->get_id(), 2 );
		$this->assertSame( 2200.0, (float) WC()->cart->get_subtotal() );
		$this->assertSame( 2200.0, (float) WC()->cart->get_total( 'edit' ) );

		$order = $this->create_order_from_cart();
		$this->assertSame( 'SEK', $order->get_currency() );
		$this->assertSame( '2200.00', $order->get_total() );

		$rows = $this->line_provenance( $order );
		$this->assertCount( 1, $rows );
		$this->assertSame( ProductPriceResolution::SOURCE_FIXED, $rows[0]['source'] );
		$this->assertSame( 'SEK', $rows[0]['currency'] );
		$this->assert_snapshot_schema_unchanged( $order );
	}

	public function test_converted_product_flows_through_cart_and_order(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );
		$product = $this->simple_product( '100' );

		$this->add_to_cart( $product->get_id(), 1 );
		$this->assertSame( 1150.0, (float) WC()->cart->get_subtotal() );

		$order = $this->create_order_from_cart();
		$rows  = $this->line_provenance( $order );
		$this->assertSame( ProductPriceResolution::SOURCE_CONVERTED, $rows[0]['source'] );
		$this->assertSame( 'SEK', $rows[0]['currency'] );
	}

	public function test_mixed_fixed_and_converted_cart_assigns_distinct_provenance(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );

		$fixed = $this->simple_product( '100' );
		$this->save_fixed(
			$fixed->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$converted = $this->simple_product( '200' );

		WC()->cart->add_to_cart( $fixed->get_id(), 1 );
		WC()->cart->add_to_cart( $converted->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertSame( 3400.0, (float) WC()->cart->get_subtotal() );

		$order = $this->create_order_from_cart();
		$rows  = $this->line_provenance( $order );

		$this->assertCount( 2, $rows );
		$this->assertSame( ProductPriceResolution::SOURCE_FIXED, $rows[0]['source'] );
		$this->assertSame( ProductPriceResolution::SOURCE_CONVERTED, $rows[1]['source'] );
		$this->assertSame( 'SEK', $rows[0]['currency'] );
		$this->assertSame( 'SEK', $rows[1]['currency'] );
	}

	public function test_fixed_variation_records_variation_provenance(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$this->add_to_cart( $pair['low']->get_id(), 1 );
		$order = $this->create_order_from_cart();
		$rows  = $this->line_provenance( $order );

		$this->assertSame( ProductPriceResolution::SOURCE_FIXED, $rows[0]['source'] );
		$item = array_values( $order->get_items() )[0];
		$this->assertInstanceOf( WC_Order_Item_Product::class, $item );
		$this->assertSame( 550.0, (float) $item->get_total() );
	}

	public function test_injected_base_currency_meta_does_not_affect_cart_or_order(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR', true );
		$product = $this->simple_product( '100' );
		$this->inject_raw_fixed_meta(
			$product->get_id(),
			(string) wp_json_encode(
				array(
					'currencies' => array(
						'EUR' => array( 'regular' => '1' ),
					),
				)
			)
		);

		$product = wc_get_product( $product->get_id() );
		$this->assertSame( 100.0, (float) $product->get_price() );

		$this->add_to_cart( $product->get_id(), 1 );
		$this->assertSame( 100.0, (float) WC()->cart->get_subtotal() );

		$order = $this->create_order_from_cart();
		$this->assertSame( 'EUR', $order->get_currency() );
		$this->assertSame( 100.0, (float) $order->get_total() );
	}

	public function test_fixed_price_resolution_does_not_call_converter(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$product = wc_get_product( $product->get_id() );
		$this->reset_conversion_counter();
		$product->get_price();
		$this->assertSame( 0, $this->conversion_calls() );
	}

	public function test_converted_price_resolution_calls_converter(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		$this->reset_conversion_counter();
		$product->get_price();
		$this->assertGreaterThan( 0, $this->conversion_calls() );
	}

	public function test_disabled_currency_values_survive_product_resave(): void {
		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => true,
				),
			),
			'SEK'
		);
		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => false,
				),
			),
			'EUR'
		);
		$product = wc_get_product( $product->get_id() );
		$product->set_name( 'Resaved product' );
		$product->save();

		$stored = $this->repository->get( $product->get_id() )->get_currency( 'SEK' );
		$this->assertSame( '1100.00', $stored?->regular() );

		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => true,
				),
			),
			'SEK'
		);
		$product = wc_get_product( $product->get_id() );
		$this->assertSame( '1100.00', $product->get_price() );
	}

	public function test_no_fixed_meta_upgrade_regression_matches_conversion_path(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );
		$product = $this->simple_product( '100' );

		$this->assertSame( '1150.00', wc_get_product( $product->get_id() )->get_price() );
		$this->add_to_cart( $product->get_id(), 1 );
		$this->assertSame( 1150.0, (float) WC()->cart->get_subtotal() );
	}

	public function test_provenance_reflects_final_cart_resolution_not_stale_display(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );
		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$product->get_price();
		$this->assertSame( ProductPriceResolution::SOURCE_FIXED, $this->provenance->get( $product->get_id() )['source'] );

		$this->add_to_cart( $product->get_id(), 1 );
		$order = $this->create_order_from_cart();
		$rows  = $this->line_provenance( $order );
		$this->assertSame( ProductPriceResolution::SOURCE_FIXED, $rows[0]['source'] );
	}
}
