<?php
/**
 * Rate provenance table report.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Immutable rate provenance report.
 */
final class RateProvenanceReport {

	/**
	 * Captures rate provenance rows.
	 *
	 * @param array<int, RateProvenanceRow> $rows Rate provenance rows.
	 */
	public function __construct( private array $rows ) {
	}

	/**
	 * Rate provenance rows.
	 *
	 * @return list<RateProvenanceRow>
	 */
	public function rows(): array {
		return $this->rows;
	}
}
