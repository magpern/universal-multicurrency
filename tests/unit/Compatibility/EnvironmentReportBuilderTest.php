<?php
/**
 * Unit tests for support report formatting.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilityScan;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\CompatibilitySummary;
use UMC\Compatibility\Report\EnvironmentReportBuilder;

/**
 * Verifies deterministic report sections.
 */
final class EnvironmentReportBuilderTest extends TestCase {

	public function test_builds_versioned_report_with_summary_and_results(): void {
		$results = array(
			new CompatibilityResult(
				'conflict.active.test',
				CompatibilityCategory::CONFLICTS,
				CompatibilitySeverity::CONFLICT,
				'Fixture Switcher',
				'Conflict summary',
				CompatibilityDeterminism::DETERMINISTIC,
				array( 'plugin' => 'fixture/switcher.php' )
			),
			new CompatibilityResult(
				'environment.wordpress',
				CompatibilityCategory::ENVIRONMENT,
				CompatibilitySeverity::INFO,
				'WordPress',
				'WordPress: 6.5',
				CompatibilityDeterminism::FACT,
				array( 'value' => '6.5' )
			),
		);

		$summary = new CompatibilitySummary(
			CompatibilitySummary::OVERALL_CONFLICT,
			0,
			1,
			0,
			1,
			0
		);

		$report = ( new EnvironmentReportBuilder() )->build( new CompatibilityScan( $results, $summary ) );

		$this->assertStringStartsWith( 'UMC Compatibility Report v1', $report );
		$this->assertStringContainsString( 'Overall: Conflict detected', $report );
		$this->assertStringContainsString( 'Conflicts', $report );
		$this->assertStringContainsString( '[CONFLICT] Fixture Switcher', $report );
		$this->assertStringContainsString( 'Environment', $report );
	}
}
