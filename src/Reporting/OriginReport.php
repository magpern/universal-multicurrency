<?php
/**
 * Currency origin counts report.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Currency origin counts report.
 */
final class OriginReport {

	/**
	 * Captures currency origin counts.
	 *
	 * @param int $customer_count         Customer-selected origin count.
	 * @param int $visitor_location_count Visitor-location origin count.
	 * @param int $unknown_count          Unknown or legacy origin count.
	 */
	public function __construct(
		private int $customer_count,
		private int $visitor_location_count,
		private int $unknown_count
	) {
	}

	/**
	 * Customer-selected origin count.
	 */
	public function customer_count(): int {
		return $this->customer_count;
	}

	/**
	 * Visitor-location origin count.
	 */
	public function visitor_location_count(): int {
		return $this->visitor_location_count;
	}

	/**
	 * Unknown or legacy origin count.
	 */
	public function unknown_count(): int {
		return $this->unknown_count;
	}
}
