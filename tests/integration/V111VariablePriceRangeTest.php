<?php
/**
 * V1.1.1 regression: variable-product ranges after currency switch / parent get_price.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * Proves ADR-0033: parent ranges use resolved variation prices, including when
 * parent get_price() runs before get_variation_prices() (the poison path).
 *
 * @covers \UMC\Integration\PriceHooks
 * @covers \UMC\Pricing\ProductSaleStateResolver
 * @covers \UMC\Pricing\ProductPriceResolutionService
 */
final class V111VariablePriceRangeTest extends M20PricingTestCase {

	public function test_parent_get_price_does_not_poison_foreign_variation_range(): void {
		$this->activate( array( 'DKK' => array( 'rate' => '7.4758' ) ), 'DKK' );
		$pair = $this->variable_product_pair( '35.99', '65.99' );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price(); // Poison path prior to v1.1.1.

		$prices = $parent->get_variation_prices( true );
		$values = array_map( 'strval', array_values( $prices['price'] ) );

		$this->assertSame( '269.05', (string) min( $values ) );
		$this->assertSame( '493.33', (string) max( $values ) );
		$this->assertSame( '269.05', $pair['low']->get_price() );
		$this->assertSame( '493.33', $pair['high']->get_price() );
	}

	public function test_all_converted_variable_range_matches_fx(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair   = $this->variable_product_pair( '44.99', '79.99' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();

		$prices = $parent->get_variation_prices( true );
		$values = array_map( 'strval', array_values( $prices['price'] ) );

		$this->assertSame( '517.39', (string) min( $values ) );
		$this->assertSame( '919.89', (string) max( $values ) );
		$this->assertNotContains( '44.99', $values );
		$this->assertNotContains( '79.99', $values );
	}

	public function test_all_fixed_sek_range_uses_authored_values_without_converter(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '500' ) ), 'EUR' )
		);
		$this->save_fixed(
			$pair['high']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '900' ) ), 'EUR' )
		);

		$this->counting_converter->reset();
		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$this->counting_converter->reset();
		$prices = $parent->get_variation_prices( true );

		$this->assertSame( '500.00', (string) min( array_map( 'strval', array_values( $prices['price'] ) ) ) );
		$this->assertSame( '900.00', (string) max( array_map( 'strval', array_values( $prices['price'] ) ) ) );
		$this->assertSame( 0, $this->counting_converter->convert_calls(), 'Fixed range members must not invoke FX.' );
	}

	public function test_mixed_fixed_and_converted_range(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '500' ) ), 'EUR' )
		);

		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$prices = $parent->get_variation_prices( true );

		$this->assertSame( '500.00', $prices['price'][ $pair['low']->get_id() ] );
		$this->assertSame( '1150.00', $prices['price'][ $pair['high']->get_id() ] );
	}

	public function test_active_sale_range_uses_resolved_sale_amounts(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$low  = wc_get_product( $pair['low']->get_id() );
		$low->set_sale_price( '40' );
		$low->save();
		wc_delete_product_transients( $pair['parent']->get_id() );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$prices = $parent->get_variation_prices( true );

		$this->assertSame( '460.00', (string) min( array_map( 'strval', array_values( $prices['price'] ) ) ) );
		$this->assertSame( '1150.00', (string) max( array_map( 'strval', array_values( $prices['price'] ) ) ) );
	}

	public function test_inactive_scheduled_sale_uses_resolved_regular_range(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$low  = wc_get_product( $pair['low']->get_id() );
		$low->set_sale_price( '40' );
		$low->set_date_on_sale_from( time() + WEEK_IN_SECONDS );
		$low->save();
		wc_delete_product_transients( $pair['parent']->get_id() );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$prices = $parent->get_variation_prices( true );

		$this->assertSame( '575.00', (string) min( array_map( 'strval', array_values( $prices['price'] ) ) ) );
		$this->assertSame( '1150.00', (string) max( array_map( 'strval', array_values( $prices['price'] ) ) ) );
	}

	public function test_currency_switch_eur_sek_eur_ranges(): void {
		$pair = $this->variable_product_pair( '35.99', '65.99' );

		$this->activate( array( 'SEK' => array( 'rate' => '11.0875' ) ), 'EUR' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$eur = array_map( 'strval', array_values( $parent->get_variation_prices( true )['price'] ) );

		$this->activate( array( 'SEK' => array( 'rate' => '11.0875' ) ), 'SEK' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$sek = array_map( 'strval', array_values( $parent->get_variation_prices( true )['price'] ) );

		$this->activate( array( 'SEK' => array( 'rate' => '11.0875' ) ), 'EUR' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$eur_again = array_map( 'strval', array_values( $parent->get_variation_prices( true )['price'] ) );

		$this->assertSame( '35.99', (string) min( $eur ) );
		$this->assertSame( '65.99', (string) max( $eur ) );
		$this->assertSame( '399.04', (string) min( $sek ) );
		$this->assertSame( '731.66', (string) max( $sek ) );
		$this->assertSame( $eur, $eur_again );
	}

	public function test_rate_change_updates_converted_range_only(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '500' ) ), 'EUR' )
		);

		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$first = $parent->get_variation_prices( true )['price'];

		$this->activate( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$second = $parent->get_variation_prices( true )['price'];

		$this->assertSame( '500.00', $first[ $pair['low']->get_id() ] );
		$this->assertSame( '1150.00', $first[ $pair['high']->get_id() ] );
		$this->assertSame( '500.00', $second[ $pair['low']->get_id() ], 'Fixed member stable across rate change.' );
		$this->assertSame( '2000.00', $second[ $pair['high']->get_id() ], 'Converted member follows rate.' );
	}

	public function test_base_currency_range_unchanged(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair   = $this->variable_product_pair( '35.99', '65.99' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$parent->get_price();
		$values = array_map( 'strval', array_values( $parent->get_variation_prices( true )['price'] ) );

		$this->assertSame( '35.99', (string) min( $values ) );
		$this->assertSame( '65.99', (string) max( $values ) );
	}

	public function test_simple_product_unaffected(): void {
		$this->activate( array( 'DKK' => array( 'rate' => '7.4758' ) ), 'DKK' );
		$product = $this->simple_product( '35.99' );

		$this->assertSame( '269.05', $product->get_price() );
	}
}
