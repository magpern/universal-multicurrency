<?php
/**
 * Aggregate compatibility scan output.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Memoized scan payload for admin rendering and support reports.
 */
final class CompatibilityScan {

	/**
	 * Ordered diagnostic results.
	 *
	 * @var array<int, CompatibilityResult>
	 */
	private array $results;

	/**
	 * Summary counts and overall status.
	 *
	 * @var CompatibilitySummary
	 */
	private CompatibilitySummary $summary;

	/**
	 * Plain-text support report.
	 *
	 * @var string
	 */
	private string $report;

	/**
	 * Creates a scan aggregate.
	 *
	 * @param array<int, CompatibilityResult> $results Ordered results.
	 * @param CompatibilitySummary            $summary Summary counts.
	 * @param string                          $report  Plain-text report.
	 */
	public function __construct( array $results, CompatibilitySummary $summary, string $report = '' ) {
		$this->results = $results;
		$this->summary = $summary;
		$this->report  = $report;
	}

	/**
	 * Ordered results.
	 *
	 * @return array<int, CompatibilityResult>
	 */
	public function results(): array {
		return $this->results;
	}

	/**
	 * Summary counts.
	 */
	public function summary(): CompatibilitySummary {
		return $this->summary;
	}

	/**
	 * Plain-text support report.
	 */
	public function report(): string {
		return $this->report;
	}

	/**
	 * Results filtered by category.
	 *
	 * @param string $category Category slug.
	 * @return array<int, CompatibilityResult>
	 */
	public function results_for_category( string $category ): array {
		return array_values(
			array_filter(
				$this->results,
				static function ( CompatibilityResult $result ) use ( $category ): bool {
					return $result->category() === $category;
				}
			)
		);
	}

	/**
	 * Results filtered by severity.
	 *
	 * @param string $severity Severity slug.
	 * @return array<int, CompatibilityResult>
	 */
	public function results_for_severity( string $severity ): array {
		return array_values(
			array_filter(
				$this->results,
				static function ( CompatibilityResult $result ) use ( $severity ): bool {
					return $result->severity() === $severity;
				}
			)
		);
	}
}
