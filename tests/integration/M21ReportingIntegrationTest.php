<?php
/**
 * Integration tests: M21 reporting truth contract end-to-end.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration;

use UMC\Checkout\CheckoutSettings;
use UMC\CurrencySwitcher;
use UMC\Order\OrderSnapshot;
use UMC\Pricing\ProductPriceResolution;
use UMC\Reporting\ReportingConstants;
use UMC\Reporting\ReportingDateRange;
use UMC\Tests\Support\M21ReportingTestCase;

/**
 * Proves currency authority, origin, performance totals, pricing-source scope,
 * checkout fallback, legacy handling, and cache behaviour.
 */
final class M21ReportingIntegrationTest extends M21ReportingTestCase {

	public function test_snapshot_transaction_currency_overrides_wc_currency(): void {
		$this->create_schema5_order(
			array(
				'currency'             => 'EUR',
				'transaction_currency' => 'SEK',
				'total'                => '120.00',
			)
		);

		$row = $this->performance_row( $this->build_report(), 'SEK' );

		$this->assertNotNull( $row );
		$this->assertSame( 1, $row->order_count() );
		$this->assertEqualsWithDelta( 120.0, $row->order_value(), 0.0001 );
	}

	public function test_legacy_order_falls_back_to_wc_currency(): void {
		$this->create_legacy_order(
			array(
				'currency' => 'USD',
				'total'    => '80.00',
			)
		);

		$row = $this->performance_row( $this->build_report(), 'USD' );

		$this->assertNotNull( $row );
		$this->assertEqualsWithDelta( 80.0, $row->order_value(), 0.0001 );
		$this->assertSame( 1, $this->build_report()->diagnostics()->legacy_orders() );
	}

	public function test_unresolvable_currency_is_excluded_from_monetary_aggregation(): void {
		$this->create_unresolvable_currency_order(
			array(
				'total' => '999.00',
			)
		);

		$result = $this->build_report();

		$this->assertSame( 0, $result->statistics()->qualifying_orders() );
		$this->assertSame( 1, $result->diagnostics()->unresolvable_currency_orders() );
		$this->assertSame( array(), $result->currency_performance()->rows() );
	}

	public function test_origin_capture_and_reporting_unknown(): void {
		$this->create_schema5_order(
			array(
				'origin' => CurrencySwitcher::ORIGIN_CUSTOMER,
				'total'  => '10.00',
			)
		);
		$this->create_schema5_order(
			array(
				'origin' => CurrencySwitcher::ORIGIN_VISITOR_LOCATION,
				'total'  => '20.00',
			)
		);
		$this->create_schema5_order(
			array(
				'total' => '30.00',
			)
		);

		$result = $this->build_report();

		$this->assertSame( 1, $result->origin()->customer_count() );
		$this->assertSame( 1, $result->origin()->visitor_location_count() );
		$this->assertSame( 1, $result->origin()->unknown_count() );
		$this->assertSame( 1, $result->diagnostics()->unknown_origin_orders() );
	}

	public function test_currency_performance_totals_refunds_and_aov_use_order_value_not_net(): void {
		$order = $this->create_schema5_order(
			array(
				'currency' => 'SEK',
				'total'    => '200.00',
			)
		);
		$this->refund_order( $order, '50.00' );

		$row = $this->performance_row( $this->build_report(), 'SEK' );

		$this->assertNotNull( $row );
		$this->assertEqualsWithDelta( 200.0, $row->order_value(), 0.0001 );
		$this->assertEqualsWithDelta( 50.0, $row->refunded_value(), 0.0001 );
		$this->assertEqualsWithDelta( 150.0, $row->net_order_value(), 0.0001 );
		$this->assertEqualsWithDelta( 200.0, $row->average_order_value(), 0.0001 );
	}

	public function test_pricing_source_fixed_converted_unknown_and_mixed_cart(): void {
		$this->create_schema5_order(
			array(
				'total' => '100.00',
				'lines' => array(
					array(
						'source' => ProductPriceResolution::SOURCE_FIXED,
						'total'  => '40.00',
					),
					array(
						'source' => ProductPriceResolution::SOURCE_CONVERTED,
						'total'  => '60.00',
					),
				),
			)
		);
		$this->create_legacy_order(
			array(
				'total' => '25.00',
				'lines' => array(
					array(
						'source' => '',
						'total'  => '25.00',
					),
				),
			)
		);

		$pricing = $this->build_report()->pricing_source();

		$this->assertEqualsWithDelta( 40.0, $pricing->fixed_total(), 0.0001 );
		$this->assertEqualsWithDelta( 60.0, $pricing->converted_total(), 0.0001 );
		$this->assertEqualsWithDelta( 25.0, $pricing->unknown_total(), 0.0001 );
	}

	public function test_pricing_source_filter_does_not_change_currency_performance(): void {
		$this->create_schema5_order(
			array(
				'currency' => 'SEK',
				'total'    => '150.00',
				'lines'    => array(
					array(
						'source' => ProductPriceResolution::SOURCE_FIXED,
						'total'  => '100.00',
					),
					array(
						'source' => ProductPriceResolution::SOURCE_CONVERTED,
						'total'  => '50.00',
					),
				),
			)
		);

		$baseline = $this->build_report();
		$filtered = $this->build_report(
			array(
				'pricing_source' => ReportingConstants::SOURCE_FIXED,
			)
		);

		$baseline_row = $this->performance_row( $baseline, 'SEK' );
		$filtered_row = $this->performance_row( $filtered, 'SEK' );

		$this->assertNotNull( $baseline_row );
		$this->assertNotNull( $filtered_row );
		$this->assertEqualsWithDelta( $baseline_row->order_value(), $filtered_row->order_value(), 0.0001 );
		$this->assertSame( $baseline_row->order_count(), $filtered_row->order_count() );
		$this->assertEqualsWithDelta( $baseline_row->refunded_value(), $filtered_row->refunded_value(), 0.0001 );
		$this->assertEqualsWithDelta( $baseline_row->net_order_value(), $filtered_row->net_order_value(), 0.0001 );
		$this->assertEqualsWithDelta( $baseline_row->average_order_value(), $filtered_row->average_order_value(), 0.0001 );

		$this->assertEqualsWithDelta( 100.0, $filtered->pricing_source()->fixed_total(), 0.0001 );
		$this->assertEqualsWithDelta( 0.0, $filtered->pricing_source()->converted_total(), 0.0001 );
		$this->assertEqualsWithDelta( 150.0, $baseline->pricing_source()->classified_total(), 0.0001 );
	}

	public function test_checkout_fallback_fields_are_reported(): void {
		$this->create_schema5_order(
			array(
				'checkout_mode'        => CheckoutSettings::MODE_SELECTED,
				'shopper_currency'     => 'USD',
				'currency'             => 'SEK',
				'transaction_currency' => 'SEK',
				'fallback_occurred'    => true,
				'total'                => '90.00',
			)
		);
		$this->create_legacy_order(
			array(
				'currency' => 'EUR',
				'total'    => '10.00',
			)
		);

		$fallback = $this->build_report()->checkout_fallback();

		$this->assertSame( 1, $fallback->fallback_count() );
		$this->assertSame( 1, $fallback->shopper_mismatch_count() );
		$this->assertSame( 1, $fallback->selected_mode_count() );
		$this->assertSame( 0, $fallback->store_mode_count() );
		$this->assertSame( 1, $fallback->unknown_checkout_data_count() );
	}

	public function test_legacy_and_partial_orders_are_diagnosed(): void {
		$this->create_legacy_order( array( 'total' => '10.00' ) );
		$this->create_partial_snapshot_order( array( 'total' => '20.00' ) );

		$diag = $this->build_report()->diagnostics();

		$this->assertSame( 1, $diag->legacy_orders() );
		$this->assertSame( 1, $diag->partial_snapshots() );
		$this->assertNotNull( $this->performance_row( $this->build_report(), 'USD' ) );
	}

	public function test_cache_second_call_reduces_repository_load_count(): void {
		$this->create_schema5_order( array( 'total' => '15.00' ) );

		$query = $this->reporting_query(
			array(
				'preset' => ReportingDateRange::PRESET_30_DAYS,
			)
		);

		$first = $this->cache->get( $query, true );
		$this->assertGreaterThan( 0, $first->repository_load_count() );

		$this->repository->reset_load_count();
		$this->cache->get( $query, false );
		$this->assertSame( 0, $this->repository->load_count() );

		$this->repository->reset_load_count();
		$this->cache->get( $query, false );
		$this->assertSame( 0, $this->repository->load_count() );
	}
}
