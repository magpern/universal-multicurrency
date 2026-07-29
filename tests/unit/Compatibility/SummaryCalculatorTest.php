<?php
/**
 * Unit tests for SummaryCalculator precedence.
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
use UMC\Compatibility\CompatibilitySummary;
use UMC\Compatibility\SummaryCalculator;

/**
 * Verifies corrected overall-status precedence.
 */
final class SummaryCalculatorTest extends TestCase {

	public function test_conflict_takes_precedence_over_configuration_warning(): void {
		$summary = SummaryCalculator::calculate(
			array(
				$this->result( 'a', CompatibilityCategory::CONFLICTS, CompatibilitySeverity::CONFLICT ),
				$this->result( 'b', CompatibilityCategory::CONFIGURATION, CompatibilitySeverity::WARNING ),
			)
		);

		$this->assertSame( CompatibilitySummary::OVERALL_CONFLICT, $summary->overall() );
	}

	public function test_configuration_warning_takes_precedence_over_other_warning(): void {
		$summary = SummaryCalculator::calculate(
			array(
				$this->result( 'a', CompatibilityCategory::CONFIGURATION, CompatibilitySeverity::WARNING ),
				$this->result( 'b', CompatibilityCategory::CACHE, CompatibilitySeverity::WARNING ),
			)
		);

		$this->assertSame( CompatibilitySummary::OVERALL_CONFIG_INCOMPLETE, $summary->overall() );
	}

	public function test_configuration_warning_plus_informational_results(): void {
		$summary = SummaryCalculator::calculate(
			array(
				$this->result( 'a', CompatibilityCategory::CONFIGURATION, CompatibilitySeverity::WARNING ),
				$this->result( 'b', CompatibilityCategory::ENVIRONMENT, CompatibilitySeverity::INFO ),
			)
		);

		$this->assertSame( CompatibilitySummary::OVERALL_CONFIG_INCOMPLETE, $summary->overall() );
		$this->assertSame( 1, $summary->warnings() );
		$this->assertSame( 1, $summary->informational() );
	}

	public function test_non_configuration_warning_yields_attention_recommended(): void {
		$summary = SummaryCalculator::calculate(
			array(
				$this->result( 'a', CompatibilityCategory::CACHE, CompatibilitySeverity::WARNING ),
			)
		);

		$this->assertSame( CompatibilitySummary::OVERALL_ATTENTION, $summary->overall() );
	}

	public function test_unavailable_plus_informational_results(): void {
		$summary = SummaryCalculator::calculate(
			array(
				$this->result( 'a', CompatibilityCategory::RUNTIME, CompatibilitySeverity::UNAVAILABLE ),
				$this->result( 'b', CompatibilityCategory::ENVIRONMENT, CompatibilitySeverity::INFO ),
			)
		);

		$this->assertSame( CompatibilitySummary::OVERALL_UNAVAILABLE, $summary->overall() );
	}

	public function test_pass_plus_informational_results(): void {
		$summary = SummaryCalculator::calculate(
			array(
				$this->result( 'a', CompatibilityCategory::CONFLICTS, CompatibilitySeverity::PASS ),
				$this->result( 'b', CompatibilityCategory::ENVIRONMENT, CompatibilitySeverity::INFO ),
			)
		);

		$this->assertSame( CompatibilitySummary::OVERALL_ALL_PASSED, $summary->overall() );
		$this->assertSame( 1, $summary->passed() );
		$this->assertSame( 1, $summary->informational() );
	}

	public function test_informational_results_do_not_downgrade_overall_state(): void {
		$this->assertSame(
			CompatibilitySummary::OVERALL_ALL_PASSED,
			SummaryCalculator::overall_status( false, false, false, false )
		);
	}

	private function result( string $id, string $category, string $severity ): CompatibilityResult {
		return new CompatibilityResult(
			$id,
			$category,
			$severity,
			$id,
			$id,
			CompatibilityDeterminism::DETERMINISTIC
		);
	}
}
