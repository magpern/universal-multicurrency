<?php
/**
 * Currency performance row.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * One currency's performance metrics in a reporting result.
 */
final class CurrencyPerformanceRow {

	/**
	 * Captures per-currency performance counters.
	 *
	 * @param string $currency        ISO currency code.
	 * @param int    $order_count     Qualifying order count.
	 * @param float  $order_value     Gross order value.
	 * @param float  $refunded_value  Refunded value.
	 * @param float  $net_order_value Order value minus refunds.
	 */
	public function __construct(
		private string $currency,
		private int $order_count,
		private float $order_value,
		private float $refunded_value,
		private float $net_order_value
	) {
	}

	/**
	 * ISO currency code.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Qualifying order count.
	 */
	public function order_count(): int {
		return $this->order_count;
	}

	/**
	 * Gross order value.
	 */
	public function order_value(): float {
		return $this->order_value;
	}

	/**
	 * Refunded value.
	 */
	public function refunded_value(): float {
		return $this->refunded_value;
	}

	/**
	 * Order value minus refunds.
	 */
	public function net_order_value(): float {
		return $this->net_order_value;
	}

	/**
	 * Average gross order value.
	 */
	public function average_order_value(): float {
		if ( $this->order_count <= 0 ) {
			return 0.0;
		}

		return $this->order_value / $this->order_count;
	}
}
