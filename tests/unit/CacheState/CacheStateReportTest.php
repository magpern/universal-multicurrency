<?php
/**
 * Unit tests for the cache-state report contract.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CacheState;

use PHPUnit\Framework\TestCase;
use UMC\CacheState\CacheStateFactors;
use UMC\CacheState\CacheStateReport;

/**
 * Tests the cache-state report contract.
 */
final class CacheStateReportTest extends TestCase {

	private function factors(): CacheStateFactors {
		return new CacheStateFactors( 'EUR', array( 'EUR', 'SEK', 'USD' ), true );
	}

	public function test_to_array_key_set_matches_the_documented_contract_in_order(): void {
		$report = new CacheStateReport( $this->factors(), '', 0, 0 );

		$this->assertSame(
			array(
				'contract_version',
				'state_hash',
				'acknowledged_hash',
				'monitoring_enrolled',
				'reconciliation_required',
				'base_currency',
				'currencies',
				'geo_enabled',
				'acknowledged_at',
				'rates_last_updated_at',
			),
			array_keys( $report->to_array() )
		);
	}

	public function test_value_types_are_stable(): void {
		$report = new CacheStateReport( $this->factors(), $this->factors()->hash(), 1700000000, 1700000000 );
		$array  = $report->to_array();

		$this->assertIsInt( $array['contract_version'] );
		$this->assertIsString( $array['state_hash'] );
		$this->assertIsString( $array['acknowledged_hash'] );
		$this->assertIsBool( $array['monitoring_enrolled'] );
		$this->assertIsBool( $array['reconciliation_required'] );
		$this->assertIsString( $array['base_currency'] );
		$this->assertIsArray( $array['currencies'] );
		$this->assertIsBool( $array['geo_enabled'] );
		$this->assertIsString( $array['acknowledged_at'] );
		$this->assertIsString( $array['rates_last_updated_at'] );
	}

	public function test_never_acknowledged_report_is_honestly_not_reconciled(): void {
		$report = new CacheStateReport( $this->factors(), '', 0, 0 );

		$this->assertFalse( $report->monitoring_enrolled() );
		$this->assertSame( '', $report->acknowledged_hash() );
		$this->assertTrue( $report->reconciliation_required() );
	}

	public function test_matching_acknowledged_hash_is_reconciled_and_enrolled(): void {
		$factors = $this->factors();
		$report  = new CacheStateReport( $factors, $factors->hash(), 1700000000, 0 );

		$this->assertTrue( $report->monitoring_enrolled() );
		$this->assertFalse( $report->reconciliation_required() );
	}

	public function test_timestamps_render_as_iso8601_or_empty_string(): void {
		$report = new CacheStateReport( $this->factors(), '', 0, 0 );
		$array  = $report->to_array();

		$this->assertSame( '', $array['acknowledged_at'] );
		$this->assertSame( '', $array['rates_last_updated_at'] );

		$report = new CacheStateReport( $this->factors(), $this->factors()->hash(), 1700000000, 1700000500 );
		$array  = $report->to_array();

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $array['acknowledged_at'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $array['rates_last_updated_at'] );
	}
}
