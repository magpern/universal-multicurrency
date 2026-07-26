<?php
/**
 * Unit tests for the confidence levels and scoring thresholds.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Confidence;

/**
 * Covers score-to-level boundaries and the rank comparison helper.
 */
final class ConfidenceTest extends TestCase {

	/**
	 * @dataProvider boundary_cases
	 */
	public function test_from_score_boundaries_on_both_sides( int $score, string $expected ): void {
		$this->assertSame( $expected, Confidence::from_score( $score ) );
	}

	/**
	 * @return array<string, array{0: int, 1: string}>
	 */
	public static function boundary_cases(): array {
		return array(
			'0 is none'    => array( 0, Confidence::NONE ),
			'9 is none'    => array( 9, Confidence::NONE ),
			'10 is low'    => array( 10, Confidence::LOW ),
			'29 is low'    => array( 29, Confidence::LOW ),
			'30 is medium' => array( 30, Confidence::MEDIUM ),
			'59 is medium' => array( 59, Confidence::MEDIUM ),
			'60 is high'   => array( 60, Confidence::HIGH ),
			'100 is high'  => array( 100, Confidence::HIGH ),
		);
	}

	public function test_from_score_rejects_a_negative_score(): void {
		$this->expectException( InvalidArgumentException::class );
		Confidence::from_score( -1 );
	}

	public function test_is_valid_accepts_every_defined_level(): void {
		$this->assertTrue( Confidence::is_valid( Confidence::NONE ) );
		$this->assertTrue( Confidence::is_valid( Confidence::LOW ) );
		$this->assertTrue( Confidence::is_valid( Confidence::MEDIUM ) );
		$this->assertTrue( Confidence::is_valid( Confidence::HIGH ) );
	}

	public function test_is_valid_rejects_an_unknown_level(): void {
		$this->assertFalse( Confidence::is_valid( 'critical' ) );
		$this->assertFalse( Confidence::is_valid( '' ) );
	}

	public function test_at_least_is_monotone_on_the_rank_order(): void {
		$this->assertTrue( Confidence::at_least( Confidence::HIGH, Confidence::LOW ) );
		$this->assertTrue( Confidence::at_least( Confidence::MEDIUM, Confidence::MEDIUM ) );
		$this->assertFalse( Confidence::at_least( Confidence::LOW, Confidence::HIGH ) );
		$this->assertFalse( Confidence::at_least( Confidence::NONE, Confidence::LOW ) );
	}

	public function test_at_least_rejects_an_unknown_level(): void {
		$this->expectException( InvalidArgumentException::class );
		Confidence::at_least( 'critical', Confidence::LOW );
	}

	public function test_at_least_rejects_an_unknown_minimum(): void {
		$this->expectException( InvalidArgumentException::class );
		Confidence::at_least( Confidence::HIGH, 'critical' );
	}
}
