<?php
/**
 * Unit tests for CompatibilityResult ordering and invariants.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;

/**
 * Verifies result ordering and validation.
 */
final class CompatibilityResultTest extends TestCase {

	public function test_results_sort_by_severity_then_category_then_id(): void {
		$results = array(
			new CompatibilityResult(
				'z.pass',
				CompatibilityCategory::ENVIRONMENT,
				CompatibilitySeverity::PASS,
				'Pass',
				'Pass',
				CompatibilityDeterminism::FACT
			),
			new CompatibilityResult(
				'a.conflict',
				CompatibilityCategory::INTEGRATIONS,
				CompatibilitySeverity::CONFLICT,
				'Conflict',
				'Conflict',
				CompatibilityDeterminism::DETERMINISTIC
			),
			new CompatibilityResult(
				'b.warning',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::WARNING,
				'Warning',
				'Warning',
				CompatibilityDeterminism::HEURISTIC
			),
		);

		usort( $results, array( CompatibilityResult::class, 'compare' ) );

		$this->assertSame( 'a.conflict', $results[0]->id() );
		$this->assertSame( 'b.warning', $results[1]->id() );
		$this->assertSame( 'z.pass', $results[2]->id() );
	}
}
