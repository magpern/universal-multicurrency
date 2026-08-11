<?php
/**
 * Unit tests for RateFailureCode.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\RateFailureCode;

/**
 * Covers the closed failure-code vocabulary.
 */
final class RateFailureCodeTest extends TestCase {

	public function test_sanitize_keeps_known_codes(): void {
		$this->assertSame( RateFailureCode::TIMEOUT, RateFailureCode::sanitize( 'timeout' ) );
		$this->assertSame( RateFailureCode::NETWORK_ERROR, RateFailureCode::sanitize( 'NETWORK_ERROR' ) );
	}

	public function test_sanitize_maps_unknown_to_provider_unavailable(): void {
		$this->assertSame(
			RateFailureCode::PROVIDER_UNAVAILABLE,
			RateFailureCode::sanitize( 'totally_unknown_code' )
		);
	}
}
