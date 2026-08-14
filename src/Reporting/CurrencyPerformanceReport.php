<?php
/**
 * Immutable currency performance report.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Immutable currency performance report.
 */
final class CurrencyPerformanceReport {

	/**
	 * Captures per-currency performance rows.
	 *
	 * @param array<int, CurrencyPerformanceRow> $rows Performance rows.
	 */
	public function __construct( private array $rows ) {
	}

	/**
	 * Per-currency performance rows.
	 *
	 * @return list<CurrencyPerformanceRow>
	 */
	public function rows(): array {
		return $this->rows;
	}
}
