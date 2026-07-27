<?php
/**
 * Unit tests for RateUpdateInterval.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\RateUpdateInterval;

/**
 * Closed-set ISO-8601 interval tests.
 */
final class RateUpdateIntervalTest extends TestCase {

	public function test_supported_intervals_round_trip(): void {
		foreach ( array( 'PT6H', 'PT12H', 'P1D', 'P3D', 'P1W' ) as $iso ) {
			$interval = RateUpdateInterval::from_iso8601( $iso );
			$this->assertNotNull( $interval );
			$this->assertSame( $iso, $interval->iso8601() );
			$this->assertNotSame( '', $interval->label() );
		}
	}

	public function test_unsupported_interval_returns_null(): void {
		$this->assertNull( RateUpdateInterval::from_iso8601( 'P2D' ) );
	}

	public function test_default_is_daily(): void {
		$this->assertSame( 'P1D', RateUpdateInterval::default()->iso8601() );
	}
}
