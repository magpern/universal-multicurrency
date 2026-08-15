<?php
/**
 * M24 WP2 acceptance: fixed-price coverage classification.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Pricing;

use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * @covers \UMC\Pricing\FixedPriceCoverageReport
 */
final class FixedPriceCoverageReportTest extends M20PricingTestCase {

	/**
	 * Coverage resolver under test.
	 *
	 * @var FixedPriceCoverageReport
	 */
	private FixedPriceCoverageReport $coverage;

	public function set_up(): void {
		parent::set_up();

		$this->coverage = new FixedPriceCoverageReport( $this->repository );
	}

	public function test_simple_product_is_fixed_when_regular_authored(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '50' );
		$this->save_fixed( $product->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '575' ) ), 'EUR' ) );

		$this->assertSame( FixedPriceCoverageReport::STATUS_FIXED, $this->coverage->classify( $product, 'SEK' ) );
	}

	public function test_simple_product_is_fx_fallback_without_fixed_price(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '50' );

		$this->assertSame( FixedPriceCoverageReport::STATUS_FX_FALLBACK, $this->coverage->classify( $product, 'SEK' ) );
	}

	public function test_variable_product_is_fixed_when_every_population_member_fixed(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed( $pair['low']->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '575' ) ), 'EUR' ) );
		$this->save_fixed( $pair['high']->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1150' ) ), 'EUR' ) );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( FixedPriceCoverageReport::STATUS_FIXED, $this->coverage->classify( $parent, 'SEK' ) );
	}

	public function test_variable_product_is_partial_when_some_population_members_fixed(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed( $pair['low']->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '575' ) ), 'EUR' ) );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( FixedPriceCoverageReport::STATUS_PARTIAL, $this->coverage->classify( $parent, 'SEK' ) );
	}

	public function test_variable_product_is_fx_fallback_when_none_fixed(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair = $this->variable_product_pair( '50', '100' );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( FixedPriceCoverageReport::STATUS_FX_FALLBACK, $this->coverage->classify( $parent, 'SEK' ) );
	}

	public function test_variable_product_reports_no_priceable_variations_when_population_empty(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$this->assertSame(
			FixedPriceCoverageReport::STATUS_NO_PRICEABLE_VARIATIONS,
			$this->coverage->classify( wc_get_product( $parent->get_id() ), 'SEK' )
		);
	}

	public function test_population_excludes_disabled_variations(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair = $this->variable_product_pair( '50', '100' );

		$disabled = wc_get_product( $pair['high']->get_id() );
		$disabled->set_status( 'private' );
		$disabled->save();

		$population = $this->coverage->population( wc_get_product( $pair['parent']->get_id() ) );

		$this->assertCount( 1, $population );
		$this->assertArrayHasKey( $pair['low']->get_id(), $population );
		$this->assertArrayNotHasKey( $pair['high']->get_id(), $population );
	}

	public function test_population_excludes_variations_without_authored_regular_price(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$priced = new \WC_Product_Variation();
		$priced->set_parent_id( $parent->get_id() );
		$priced->set_regular_price( '50' );
		$priced->save();

		$unpriced = new \WC_Product_Variation();
		$unpriced->set_parent_id( $parent->get_id() );
		$unpriced->save();

		$population = $this->coverage->population( wc_get_product( $parent->get_id() ) );

		$this->assertCount( 1, $population );
		$this->assertArrayHasKey( $priced->get_id(), $population );
		$this->assertArrayNotHasKey( $unpriced->get_id(), $population );
	}

	/**
	 * M24 falsification P: coverage must never change solely because a
	 * population member's stock/purchasability changes.
	 */
	public function test_coverage_is_unaffected_by_stock_status_change(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed( $pair['low']->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '575' ) ), 'EUR' ) );
		$this->save_fixed( $pair['high']->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1150' ) ), 'EUR' ) );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$before = $this->coverage->classify( $parent, 'SEK' );

		$out_of_stock = wc_get_product( $pair['low']->get_id() );
		$out_of_stock->set_stock_status( 'outofstock' );
		$out_of_stock->save();

		$after = $this->coverage->classify( wc_get_product( $pair['parent']->get_id() ), 'SEK' );

		$this->assertSame( FixedPriceCoverageReport::STATUS_FIXED, $before );
		$this->assertSame( $before, $after, 'Coverage must not change when only stock status changes.' );
	}

	public function test_eligible_targets_returns_self_for_simple_product(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '50' );

		$targets = $this->coverage->eligible_targets( $product );

		$this->assertCount( 1, $targets );
		$this->assertSame( $product->get_id(), $targets[0]->get_id() );
	}

	public function test_eligible_targets_returns_population_for_variable_product(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair = $this->variable_product_pair( '50', '100' );

		$targets    = $this->coverage->eligible_targets( wc_get_product( $pair['parent']->get_id() ) );
		$target_ids = array_map( static fn( $product ) => $product->get_id(), $targets );

		sort( $target_ids );
		$expected = array( $pair['low']->get_id(), $pair['high']->get_id() );
		sort( $expected );

		$this->assertSame( $expected, $target_ids );
	}
}
