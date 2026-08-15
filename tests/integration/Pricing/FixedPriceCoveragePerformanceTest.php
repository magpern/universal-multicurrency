<?php
/**
 * M24 WP5 performance guard: bounded queries for coverage classification.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Pricing;

use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Tests\Support\M20PricingTestCase;
use UMC\Tests\Support\PerformanceMetrics;

/**
 * Query-count ceiling for the Fixed Pricing coverage view (ADR-0029 § 16
 * Performance model). Not a wall-clock benchmark — a deterministic query
 * count delta, matching PerformanceBaselineTest's existing convention.
 *
 * @group performance
 */
final class FixedPriceCoveragePerformanceTest extends M20PricingTestCase {

	use PerformanceMetrics;

	public function test_classifying_a_page_of_products_does_not_scale_super_linearly(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->simple_product( (string) ( 10 + $i ) );
		}

		$query = new FixedPriceCatalogQuery( new FixedPriceCoverageReport( $this->repository ) );

		$small_batch_queries = $this->measure_query_delta(
			static function () use ( $query ) {
				$query->classify_catalog( 'SEK', '', '', 2 );
			}
		);

		for ( $i = 0; $i < 15; $i++ ) {
			$this->simple_product( (string) ( 20 + $i ) );
		}

		$large_batch_queries = $this->measure_query_delta(
			static function () use ( $query ) {
				$query->classify_catalog( 'SEK', '', '', 20 );
			}
		);

		// 10x the classified rows must not cost anywhere near 10x the
		// queries — proves there is no N+1 product-load pattern hiding in
		// classification (one bounded wc_get_products() call + one cheap
		// meta read per row, not one query per row for the product itself).
		$this->assertLessThan(
			$small_batch_queries * 5,
			$large_batch_queries,
			'Classifying 10x more products must not cost anywhere near 10x the queries.'
		);
	}
}
