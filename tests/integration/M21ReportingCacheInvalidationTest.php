<?php
/**
 * Integration tests: reporting cache generation invalidation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration;

use UMC\Reporting\CurrencyPerformanceRow;
use UMC\Reporting\ReportingCache;
use UMC\Reporting\ReportingCacheInvalidator;
use UMC\Reporting\ReportingDateRange;
use UMC\Tests\Support\M21ReportingTestCase;

/**
 * Verifies coarse generation invalidation on order/refund lifecycle hooks.
 */
final class M21ReportingCacheInvalidationTest extends M21ReportingTestCase {

	/**
	 * Cache invalidation hooks under test.
	 *
	 * @var ReportingCacheInvalidator
	 */
	private ReportingCacheInvalidator $invalidator;

	public function set_up(): void {
		parent::set_up();

		$this->invalidator = new ReportingCacheInvalidator( $this->cache );
		$this->invalidator->register();
	}

	public function test_new_order_invalidates_cached_report(): void {
		$this->create_schema5_order( array( 'total' => '10.00' ) );

		$query      = $this->reporting_query();
		$generation = $this->cache->generation();
		$this->cache->get( $query, true );

		$this->create_schema5_order( array( 'total' => '20.00' ) );

		$this->assertGreaterThan( $generation, $this->cache->generation() );

		$this->repository->reset_load_count();
		$this->cache->get( $query, false );
		$this->assertGreaterThan( 0, $this->repository->load_count() );
	}

	public function test_refund_creation_invalidates_cached_report(): void {
		$order = $this->create_schema5_order( array( 'total' => '50.00' ) );
		$query = $this->reporting_query();

		$this->cache->get( $query, true );
		$generation = $this->cache->generation();

		$refund = wc_create_refund(
			array(
				'amount'   => 10.0,
				'reason'   => 'M21 cache invalidation test',
				'order_id' => $order->get_id(),
			)
		);

		$this->assertNotFalse( $refund );
		$this->assertGreaterThan( $generation, $this->cache->generation() );

		$this->repository->reset_load_count();
		$this->cache->get( $query, false );
		$this->assertGreaterThan( 0, $this->repository->load_count() );
	}

	public function test_refund_deletion_invalidates_cached_report(): void {
		$order  = $this->create_schema5_order( array( 'total' => '40.00' ) );
		$refund = wc_create_refund(
			array(
				'amount'   => 5.0,
				'reason'   => 'M21 cache invalidation test',
				'order_id' => $order->get_id(),
			)
		);

		$this->assertNotFalse( $refund );

		$query = $this->reporting_query();
		$this->cache->get( $query, true );
		$generation = $this->cache->generation();

		$refund_id = $refund->get_id();
		$order_id  = $order->get_id();
		$refund->delete( true );
		/**
		 * Fires after a refund is deleted (mirrors WC admin AJAX).
		 *
		 * @since 0.20.0
		 *
		 * @param int $refund_id Refund ID.
		 * @param int $order_id  Parent order ID.
		 */
		do_action( 'woocommerce_refund_deleted', $refund_id, $order_id );

		$this->assertGreaterThan( $generation, $this->cache->generation() );

		$this->repository->reset_load_count();
		$this->cache->get( $query, false );
		$this->assertGreaterThan( 0, $this->repository->load_count() );
	}

	public function test_refresh_bypasses_cached_report(): void {
		$this->create_schema5_order( array( 'total' => '12.00' ) );

		$query = $this->reporting_query(
			array(
				'preset' => ReportingDateRange::PRESET_30_DAYS,
			)
		);

		$this->cache->get( $query, true );
		$this->repository->reset_load_count();
		$this->cache->get( $query, true );
		$this->assertGreaterThan( 0, $this->repository->load_count() );
	}

	public function test_changed_currency_filter_uses_a_different_cache_entry(): void {
		$this->create_schema5_order(
			array(
				'currency'             => 'SEK',
				'transaction_currency' => 'SEK',
				'total'                => '30.00',
			)
		);
		$this->create_schema5_order(
			array(
				'currency'             => 'USD',
				'transaction_currency' => 'USD',
				'total'                => '40.00',
			)
		);

		$all = $this->cache->get( $this->reporting_query(), true );
		$sek = $this->cache->get(
			$this->reporting_query(
				array(
					'currency' => 'SEK',
				)
			),
			true
		);

		$this->assertSame( 2, $all->statistics()->qualifying_orders() );
		$this->assertSame( 1, $sek->statistics()->qualifying_orders() );

		$all_currencies = array_map(
			static fn ( CurrencyPerformanceRow $row ): string => $row->currency(),
			$all->currency_performance()->rows()
		);
		$sek_currencies = array_map(
			static fn ( CurrencyPerformanceRow $row ): string => $row->currency(),
			$sek->currency_performance()->rows()
		);

		$this->assertContains( 'SEK', $all_currencies );
		$this->assertContains( 'USD', $all_currencies );
		$this->assertSame( array( 'SEK' ), $sek_currencies );
	}
}
