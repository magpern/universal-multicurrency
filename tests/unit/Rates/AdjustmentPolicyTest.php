<?php
/**
 * Unit tests for adjustment normalization and policy.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Settings;

/**
 * Split adjustment parser/policy tests.
 */
final class AdjustmentPolicyTest extends TestCase {

	public function test_normalize_adjustment_accepts_negative_and_zero(): void {
		$this->assertSame( '-2.5', Settings::normalize_adjustment( '-2.5' ) );
		$this->assertSame( '0', Settings::normalize_adjustment( '0' ) );
		$this->assertSame( '0', Settings::normalize_adjustment( 'abc' ) );
	}

	public function test_enforce_adjustment_range_clamps_to_boundaries(): void {
		$this->assertSame( '50', Settings::enforce_adjustment_range( '999' ) );
		$this->assertSame( '-50', Settings::enforce_adjustment_range( '-999' ) );
		$this->assertSame( '2', Settings::enforce_adjustment_range( '2' ) );
	}
}
