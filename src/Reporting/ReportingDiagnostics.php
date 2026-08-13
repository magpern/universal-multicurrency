<?php
/**
 * Reporting diagnostics counters.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Data-quality diagnostics for a reporting result.
 */
final class ReportingDiagnostics {

	/**
	 * Captures data-quality counters.
	 *
	 * @param int $legacy_orders                 Legacy orders without snapshots.
	 * @param int $partial_snapshots             Orders with partial snapshots.
	 * @param int $unresolvable_currency_orders  Orders with unresolvable currency.
	 * @param int $unknown_origin_orders         Orders with unknown origin.
	 */
	public function __construct(
		private int $legacy_orders,
		private int $partial_snapshots,
		private int $unresolvable_currency_orders,
		private int $unknown_origin_orders
	) {
	}

	/**
	 * Legacy orders without snapshots.
	 */
	public function legacy_orders(): int {
		return $this->legacy_orders;
	}

	/**
	 * Orders with partial snapshots.
	 */
	public function partial_snapshots(): int {
		return $this->partial_snapshots;
	}

	/**
	 * Orders with unresolvable transaction currency.
	 */
	public function unresolvable_currency_orders(): int {
		return $this->unresolvable_currency_orders;
	}

	/**
	 * Orders with unknown currency origin.
	 */
	public function unknown_origin_orders(): int {
		return $this->unknown_origin_orders;
	}

	/**
	 * Whether any diagnostic counters are non-zero.
	 */
	public function has_warnings(): bool {
		return $this->legacy_orders > 0
			|| $this->partial_snapshots > 0
			|| $this->unresolvable_currency_orders > 0
			|| $this->unknown_origin_orders > 0;
	}
}
