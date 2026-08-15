<?php
/**
 * M24 WP3 acceptance: classified, bounded catalog listing.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Pricing;

use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * @covers \UMC\Pricing\FixedPriceCatalogQuery
 */
final class FixedPriceCatalogQueryTest extends M20PricingTestCase {

	/**
	 * Query under test.
	 *
	 * @var FixedPriceCatalogQuery
	 */
	private FixedPriceCatalogQuery $query;

	public function set_up(): void {
		parent::set_up();

		$this->query = new FixedPriceCatalogQuery( new FixedPriceCoverageReport( $this->repository ) );
	}

	public function test_classifies_and_filters_by_status(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$fixed = $this->simple_product( '50' );
		$this->save_fixed( $fixed->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '575' ) ), 'EUR' ) );
		$fallback = $this->simple_product( '80' );

		$all = $this->query->classify_catalog( 'SEK', '', '', 50 );
		$this->assertFalse( $all['truncated'] );
		$this->assertGreaterThanOrEqual( 2, count( $all['rows'] ) );

		$fixed_only = $this->query->classify_catalog( 'SEK', FixedPriceCoverageReport::STATUS_FIXED, '', 50 );
		$ids        = array_map( static fn( array $row ): int => $row['product']->get_id(), $fixed_only['rows'] );

		$this->assertContains( $fixed->get_id(), $ids );
		$this->assertNotContains( $fallback->get_id(), $ids );
	}

	public function test_search_narrows_by_product_name(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$target = new \WC_Product_Simple();
		$target->set_name( 'Unique Searchable Widget' );
		$target->set_status( 'publish' );
		$target->set_regular_price( '10' );
		$target->save();

		$other = $this->simple_product( '10' );

		$result = $this->query->classify_catalog( 'SEK', '', 'Unique Searchable Widget', 50 );
		$ids    = array_map( static fn( array $row ): int => $row['product']->get_id(), $result['rows'] );

		$this->assertContains( $target->get_id(), $ids );
		$this->assertNotContains( $other->get_id(), $ids );
	}

	public function test_truncates_and_reports_when_more_products_match_than_the_limit(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$this->simple_product( '10' );
		$this->simple_product( '20' );
		$this->simple_product( '30' );

		$result = $this->query->classify_catalog( 'SEK', '', '', 2 );

		$this->assertTrue( $result['truncated'] );
		$this->assertCount( 2, $result['rows'] );
	}

	public function test_does_not_truncate_when_matches_equal_the_limit(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$this->simple_product( '10' );
		$this->simple_product( '20' );

		$result = $this->query->classify_catalog( 'SEK', '', '', 2 );

		$this->assertFalse( $result['truncated'] );
		$this->assertCount( 2, $result['rows'] );
	}
}
