<?php
/**
 * Unit tests for FixedPriceOperationResult.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use UMC\Pricing\FixedPriceOperationResult;

/**
 * @covers \UMC\Pricing\FixedPriceOperationResult
 */
final class FixedPriceOperationResultTest extends TestCase {

	public function test_aborted_result_carries_no_products_and_a_reason(): void {
		$result = FixedPriceOperationResult::aborted( FixedPriceOperationResult::ABORT_NO_RATE );

		$this->assertTrue( $result->is_aborted() );
		$this->assertSame( FixedPriceOperationResult::ABORT_NO_RATE, $result->abort_reason() );
		$this->assertSame( array(), $result->succeeded() );
		$this->assertSame( array(), $result->skipped() );
		$this->assertSame( array(), $result->failed() );
		$this->assertNull( $result->rate_used() );
		$this->assertSame( 0, $result->total_processed() );
	}

	public function test_completed_result_exposes_outcomes_and_rate(): void {
		$result = FixedPriceOperationResult::completed(
			array( 10, 11 ),
			array( 12 => 'no_authored_regular_price' ),
			array( 13 => 'unexpected error' ),
			'11.50'
		);

		$this->assertFalse( $result->is_aborted() );
		$this->assertNull( $result->abort_reason() );
		$this->assertSame( array( 10, 11 ), $result->succeeded() );
		$this->assertSame( array( 12 => 'no_authored_regular_price' ), $result->skipped() );
		$this->assertSame( array( 13 => 'unexpected error' ), $result->failed() );
		$this->assertSame( '11.50', $result->rate_used() );
		$this->assertSame( 4, $result->total_processed() );
	}

	public function test_clear_result_has_no_rate(): void {
		$result = FixedPriceOperationResult::completed( array( 10 ), array(), array() );

		$this->assertNull( $result->rate_used() );
	}
}
