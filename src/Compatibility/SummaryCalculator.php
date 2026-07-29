<?php
/**
 * Derives overall compatibility summary from scan results.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Pure summary calculator with corrected precedence rules.
 */
final class SummaryCalculator {

	/**
	 * Calculates summary counts and overall status.
	 *
	 * @param array<int, CompatibilityResult> $results Scan results.
	 */
	public static function calculate( array $results ): CompatibilitySummary {
		$passed        = 0;
		$informational = 0;
		$warnings      = 0;
		$conflicts     = 0;
		$unavailable   = 0;

		$has_config_warning = false;
		$has_other_warning  = false;

		foreach ( $results as $result ) {
			switch ( $result->severity() ) {
				case CompatibilitySeverity::PASS:
					++$passed;
					break;
				case CompatibilitySeverity::INFO:
					++$informational;
					break;
				case CompatibilitySeverity::WARNING:
					++$warnings;
					if ( CompatibilityCategory::CONFIGURATION === $result->category() ) {
						$has_config_warning = true;
					} else {
						$has_other_warning = true;
					}
					break;
				case CompatibilitySeverity::CONFLICT:
					++$conflicts;
					break;
				case CompatibilitySeverity::UNAVAILABLE:
					++$unavailable;
					break;
			}
		}

		$overall = self::overall_status(
			$conflicts > 0,
			$has_config_warning,
			$warnings > 0,
			$unavailable > 0
		);

		return new CompatibilitySummary(
			$overall,
			$passed,
			$informational,
			$warnings,
			$conflicts,
			$unavailable
		);
	}

	/**
	 * Resolves overall status with corrected precedence.
	 *
	 * @param bool $has_conflict          Whether any conflict exists.
	 * @param bool $has_config_warning    Whether any configuration warning exists.
	 * @param bool $has_warning           Whether any warning exists.
	 * @param bool $has_unavailable       Whether any unavailable result exists.
	 */
	public static function overall_status(
		bool $has_conflict,
		bool $has_config_warning,
		bool $has_warning,
		bool $has_unavailable
	): string {
		if ( $has_conflict ) {
			return CompatibilitySummary::OVERALL_CONFLICT;
		}

		if ( $has_config_warning ) {
			return CompatibilitySummary::OVERALL_CONFIG_INCOMPLETE;
		}

		if ( $has_warning ) {
			return CompatibilitySummary::OVERALL_ATTENTION;
		}

		if ( $has_unavailable ) {
			return CompatibilitySummary::OVERALL_UNAVAILABLE;
		}

		return CompatibilitySummary::OVERALL_ALL_PASSED;
	}
}
