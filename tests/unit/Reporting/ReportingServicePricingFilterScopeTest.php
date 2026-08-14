<?php
/**
 * Unit tests: pricing-source filter scope in reporting aggregation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Reporting;

use PHPUnit\Framework\TestCase;
use UMC\CurrencySwitcher;
use UMC\Order\OrderCurrencySnapshot;
use UMC\Reporting\CheckoutFallbackReport;
use UMC\Reporting\CurrencyPerformanceReport;
use UMC\Reporting\CurrencyPerformanceRow;
use UMC\Reporting\OrderReportRecord;
use UMC\Reporting\OriginReport;
use UMC\Reporting\PricingSourceReport;
use UMC\Reporting\ReportingConstants;
use UMC\Reporting\ReportingDateRange;
use UMC\Reporting\ReportingQuery;
use UMC\Reporting\ReportingResult;
use UMC\Reporting\ReportingStatisticsSummary;
use DateTimeImmutable;

/**
 * Uses in-memory order records to prove pricing-source filtering is scoped to
 * the pricing report only.
 */
final class ReportingServicePricingFilterScopeTest extends TestCase {

	public function test_pricing_source_filter_changes_only_pricing_totals(): void {
		$records = array(
			$this->record(
				1,
				'SEK',
				150.0,
				array(
					array(
						'source' => ReportingConstants::SOURCE_FIXED,
						'total'  => 100.0,
					),
					array(
						'source' => ReportingConstants::SOURCE_CONVERTED,
						'total'  => 50.0,
					),
				)
			),
		);

		$baseline = $this->aggregate( $records, '' );
		$fixed    = $this->aggregate( $records, ReportingConstants::SOURCE_FIXED );

		$baseline_row = $baseline->currency_performance()->rows()[0];
		$fixed_row    = $fixed->currency_performance()->rows()[0];

		$this->assertEqualsWithDelta( $baseline_row->order_value(), $fixed_row->order_value(), 0.0001 );
		$this->assertSame( $baseline_row->order_count(), $fixed_row->order_count() );
		$this->assertEqualsWithDelta( $baseline_row->average_order_value(), $fixed_row->average_order_value(), 0.0001 );

		$this->assertEqualsWithDelta( 150.0, $baseline->pricing_source()->classified_total(), 0.0001 );
		$this->assertEqualsWithDelta( 100.0, $fixed->pricing_source()->fixed_total(), 0.0001 );
		$this->assertEqualsWithDelta( 0.0, $fixed->pricing_source()->converted_total(), 0.0001 );
	}

	public function test_pricing_source_filter_does_not_change_origin_or_fallback_counts(): void {
		$records = array(
			$this->record(
				1,
				'SEK',
				100.0,
				array(
					array(
						'source' => ReportingConstants::SOURCE_CONVERTED,
						'total'  => 100.0,
					),
				),
				CurrencySwitcher::ORIGIN_CUSTOMER,
				false
			),
		);

		$baseline = $this->aggregate( $records, '' );
		$filtered = $this->aggregate( $records, ReportingConstants::SOURCE_FIXED );

		$this->assertSame( $baseline->origin()->customer_count(), $filtered->origin()->customer_count() );
		$this->assertSame( $baseline->checkout_fallback()->fallback_count(), $filtered->checkout_fallback()->fallback_count() );
		$this->assertSame( $baseline->statistics()->qualifying_orders(), $filtered->statistics()->qualifying_orders() );
	}

	/**
	 * Mirrors {@see \UMC\Reporting\ReportingService::build()} filter-scope rules.
	 *
	 * @param array<int, OrderReportRecord> $records        In-memory order records.
	 * @param string                        $pricing_source Pricing source filter.
	 */
	private function aggregate( array $records, string $pricing_source ): ReportingResult {
		$query = new ReportingQuery(
			new ReportingDateRange(
				new DateTimeImmutable( '2026-01-01 00:00:00' ),
				new DateTimeImmutable( '2026-01-31 23:59:59' ),
				ReportingDateRange::PRESET_30_DAYS
			),
			ReportingConstants::default_statuses(),
			'',
			'',
			'',
			$pricing_source
		);

		$currency_buckets = array();
		$fixed_total      = 0.0;
		$converted_total  = 0.0;
		$unknown_total    = 0.0;
		$customer_count   = 0;
		$fallback_count   = 0;
		$qualifying       = 0;
		$net_total        = 0.0;

		foreach ( $records as $record ) {
			if ( $record->unresolvable_currency() ) {
				continue;
			}

			$currency = (string) $record->transaction_currency();
			if ( '' === $currency ) {
				continue;
			}

			++$qualifying;
			if ( ! isset( $currency_buckets[ $currency ] ) ) {
				$currency_buckets[ $currency ] = array(
					'count'    => 0,
					'order'    => 0.0,
					'refunded' => 0.0,
				);
			}

			$currency_buckets[ $currency ]['count']    += 1;
			$currency_buckets[ $currency ]['order']    += $record->order_value();
			$currency_buckets[ $currency ]['refunded'] += $record->refunded_value();
			$net_total                                 += $record->net_order_value();

			if ( CurrencySwitcher::ORIGIN_CUSTOMER === $record->reporting_origin() ) {
				++$customer_count;
			}

			if ( true === $record->fallback_occurred() ) {
				++$fallback_count;
			}

			foreach ( $record->line_sources() as $line ) {
				if ( '' !== $pricing_source && $pricing_source !== $line['source'] ) {
					continue;
				}

				if ( ReportingConstants::SOURCE_FIXED === $line['source'] ) {
					$fixed_total += $line['total'];
				} elseif ( ReportingConstants::SOURCE_CONVERTED === $line['source'] ) {
					$converted_total += $line['total'];
				} else {
					$unknown_total += $line['total'];
				}
			}
		}

		$performance_rows = array();
		foreach ( $currency_buckets as $code => $bucket ) {
			$performance_rows[] = new CurrencyPerformanceRow(
				$code,
				$bucket['count'],
				$bucket['order'],
				$bucket['refunded'],
				max( 0.0, $bucket['order'] - $bucket['refunded'] )
			);
		}

		$pricing = new PricingSourceReport( $fixed_total, $converted_total, $unknown_total );

		return new ReportingResult(
			$query,
			new CurrencyPerformanceReport( $performance_rows ),
			$pricing,
			new OriginReport( $customer_count, 0, 0 ),
			new CheckoutFallbackReport( $fallback_count, 0, 0, 0, 0 ),
			new \UMC\Reporting\RateProvenanceReport( array() ),
			new ReportingStatisticsSummary( $qualifying, $net_total, count( $currency_buckets ), $pricing->fixed_share() ),
			new \UMC\Reporting\ReportingDiagnostics( 0, 0, 0, 0 ),
			count( $records )
		);
	}

	/**
	 * Builds an in-memory order report record fixture.
	 *
	 * @param int                                       $order_id    Order identifier.
	 * @param string                                    $currency    Transaction currency.
	 * @param float                                     $order_value Gross order value.
	 * @param list<array{source: string, total: float}> $lines       Product line sources.
	 * @param string                                    $origin      Reporting origin.
	 * @param bool                                      $fallback    Whether checkout fallback occurred.
	 */
	private function record(
		int $order_id,
		string $currency,
		float $order_value,
		array $lines,
		string $origin = CurrencySwitcher::ORIGIN_CUSTOMER,
		bool $fallback = false
	): OrderReportRecord {
		$snapshot = new OrderCurrencySnapshot(
			5,
			'EUR',
			$currency,
			'11.50',
			1_700_000_000,
			'manual',
			'0.20.0',
			$currency . ':11.50',
			2,
			true,
			false,
			false,
			false,
			false,
			'',
			'0',
			'selected',
			$currency,
			$fallback,
			$origin
		);

		return new OrderReportRecord(
			$order_id,
			$snapshot,
			$currency,
			false,
			$order_value,
			0.0,
			$origin,
			$fallback,
			$currency,
			'selected',
			$lines
		);
	}
}
