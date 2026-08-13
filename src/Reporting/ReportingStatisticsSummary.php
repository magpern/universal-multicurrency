<?php
/**
 * Summary statistics for admin cards.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Summary statistics for admin reporting cards.
 */
final class ReportingStatisticsSummary {

	/**
	 * Captures summary card metrics.
	 *
	 * @param int        $qualifying_orders Qualifying order count.
	 * @param float      $net_order_value   Net order value across currencies.
	 * @param int        $active_currencies Distinct transaction currency count.
	 * @param float|null $fixed_price_share Fixed share of classified product lines.
	 */
	public function __construct(
		private int $qualifying_orders,
		private float $net_order_value,
		private int $active_currencies,
		private ?float $fixed_price_share
	) {
	}

	/**
	 * Qualifying order count.
	 */
	public function qualifying_orders(): int {
		return $this->qualifying_orders;
	}

	/**
	 * Net order value across currencies.
	 */
	public function net_order_value(): float {
		return $this->net_order_value;
	}

	/**
	 * Distinct transaction currency count.
	 */
	public function active_currencies(): int {
		return $this->active_currencies;
	}

	/**
	 * Fixed share of classified product-line value.
	 */
	public function fixed_price_share(): ?float {
		return $this->fixed_price_share;
	}
}
