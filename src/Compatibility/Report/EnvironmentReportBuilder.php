<?php
/**
 * Plain-text support report builder for the Compatibility center.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Report;

use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilityScan;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\CompatibilitySummary;

/**
 * Builds deterministic, redacted support reports.
 */
final class EnvironmentReportBuilder {

	public const FORMAT_VERSION = '1';

	/**
	 * Redaction helper.
	 *
	 * @var ReportRedactor
	 */
	private ReportRedactor $redactor;

	/**
	 * Creates the builder.
	 *
	 * @param ReportRedactor|null $redactor Optional redactor.
	 */
	public function __construct( ?ReportRedactor $redactor = null ) {
		$this->redactor = $redactor ?? new ReportRedactor();
	}

	/**
	 * Builds a plain-text report from one scan.
	 *
	 * @param CompatibilityScan $scan Compatibility scan aggregate.
	 */
	public function build( CompatibilityScan $scan ): string {
		$lines   = array( 'UMC Compatibility Report v' . self::FORMAT_VERSION );
		$lines[] = 'Generated (UTC): ' . gmdate( 'c' );
		$lines[] = '';

		$summary = $scan->summary();
		$lines[] = 'Summary';
		$lines[] = 'Overall: ' . $this->overall_label( $summary );
		$lines[] = 'Passed: ' . $summary->passed();
		$lines[] = 'Informational: ' . $summary->informational();
		$lines[] = 'Warnings: ' . $summary->warnings();
		$lines[] = 'Conflicts: ' . $summary->conflicts();
		$lines[] = 'Unavailable: ' . $summary->unavailable();
		$lines[] = '';

		foreach ( CompatibilityCategory::ORDER as $category ) {
			$items = $scan->results_for_category( $category );
			if ( array() === $items ) {
				continue;
			}

			$lines[] = ucfirst( $category );
			foreach ( $items as $result ) {
				$lines[] = $this->format_result_line( $result );
			}
			$lines[] = '';
		}

		$report = implode( "\n", $lines );

		return $this->redactor->redact( $report );
	}

	/**
	 * Formats one result for the report.
	 *
	 * @param CompatibilityResult $result Compatibility result.
	 */
	private function format_result_line( CompatibilityResult $result ): string {
		$line = sprintf(
			'- [%s] %s: %s',
			strtoupper( $result->severity() ),
			$result->title(),
			$result->summary()
		);

		if ( array() !== $result->evidence() ) {
			$evidence = array();
			foreach ( $result->evidence() as $key => $value ) {
				$evidence[] = $key . '=' . $value;
			}
			$line .= ' (' . implode( ', ', $evidence ) . ')';
		}

		return $line;
	}

	/**
	 * Human-readable overall label.
	 *
	 * @param CompatibilitySummary $summary Summary aggregate.
	 */
	private function overall_label( CompatibilitySummary $summary ): string {
		return match ( $summary->overall() ) {
			CompatibilitySummary::OVERALL_CONFLICT => 'Conflict detected',
			CompatibilitySummary::OVERALL_CONFIG_INCOMPLETE => 'Configuration incomplete',
			CompatibilitySummary::OVERALL_ATTENTION => 'Attention recommended',
			CompatibilitySummary::OVERALL_UNAVAILABLE => 'Some checks unavailable',
			default => 'All checks passed',
		};
	}
}
