<?php
/**
 * Unit tests for ReportingResultSerializer round-trip.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Reporting;

use PHPUnit\Framework\TestCase;
use UMC\CurrencySwitcher;
use UMC\Reporting\CheckoutFallbackReport;
use UMC\Reporting\CurrencyPerformanceReport;
use UMC\Reporting\CurrencyPerformanceRow;
use UMC\Reporting\OriginReport;
use UMC\Reporting\PricingSourceReport;
use UMC\Reporting\RateProvenanceReport;
use UMC\Reporting\RateProvenanceRow;
use UMC\Reporting\ReportingDateRange;
use UMC\Reporting\ReportingDiagnostics;
use UMC\Reporting\ReportingQuery;
use UMC\Reporting\ReportingResult;
use UMC\Reporting\ReportingResultSerializer;
use UMC\Reporting\ReportingStatisticsSummary;
use DateTimeImmutable;

/**
 * Ensures cached payloads survive serialization without semantic drift.
 */
final class ReportingResultSerializerTest extends TestCase {

	public function test_to_array_and_from_array_preserve_semantics(): void {
		$range = new ReportingDateRange(
			new DateTimeImmutable( '2026-01-01 00:00:00' ),
			new DateTimeImmutable( '2026-01-31 23:59:59' ),
			ReportingDateRange::PRESET_30_DAYS
		);

		$query = new ReportingQuery(
			$range,
			array( 'processing', 'completed' ),
			'SEK',
			CurrencySwitcher::ORIGIN_CUSTOMER,
			'no',
			'fixed'
		);

		$original = new ReportingResult(
			$query,
			new CurrencyPerformanceReport(
				array(
					new CurrencyPerformanceRow( 'SEK', 2, 300.0, 50.0, 250.0 ),
				)
			),
			new PricingSourceReport( 120.0, 180.0, 0.0 ),
			new OriginReport( 1, 1, 0 ),
			new CheckoutFallbackReport( 0, 1, 1, 0, 0 ),
			new RateProvenanceReport(
				array(
					new RateProvenanceRow( 'manual', '', 2 ),
				)
			),
			new ReportingStatisticsSummary( 2, 250.0, 1, 120.0 / 300.0 ),
			new ReportingDiagnostics( 0, 0, 0, 0 ),
			4
		);

		$payload = ReportingResultSerializer::to_array( $original );
		$round   = ReportingResultSerializer::from_array( $payload );

		$this->assertSame( $original->query()->transaction_currency(), $round->query()->transaction_currency() );
		$this->assertSame( $original->query()->origin(), $round->query()->origin() );
		$this->assertSame( $original->query()->fallback(), $round->query()->fallback() );
		$this->assertSame( $original->query()->pricing_source(), $round->query()->pricing_source() );

		$row = $round->currency_performance()->rows()[0];
		$this->assertSame( 'SEK', $row->currency() );
		$this->assertSame( 2, $row->order_count() );
		$this->assertEqualsWithDelta( 300.0, $row->order_value(), 0.0001 );
		$this->assertEqualsWithDelta( 50.0, $row->refunded_value(), 0.0001 );
		$this->assertEqualsWithDelta( 250.0, $row->net_order_value(), 0.0001 );

		$this->assertEqualsWithDelta( 120.0, $round->pricing_source()->fixed_total(), 0.0001 );
		$this->assertSame( 1, $round->origin()->customer_count() );
		$this->assertSame( 1, $round->checkout_fallback()->shopper_mismatch_count() );
		$this->assertSame( 2, $round->rate_provenance()->rows()[0]->order_count() );
		$this->assertSame( 2, $round->statistics()->qualifying_orders() );
		$this->assertSame( 4, $round->repository_load_count() );
	}
}
