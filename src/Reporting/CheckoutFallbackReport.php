<?php
/**
 * Checkout fallback summary report.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Checkout fallback summary report.
 */
final class CheckoutFallbackReport {

	/**
	 * Captures checkout fallback counters.
	 *
	 * @param int $fallback_count              Orders where fallback occurred.
	 * @param int $shopper_mismatch_count      Orders with shopper/transaction mismatch.
	 * @param int $selected_mode_count         Orders with selected checkout mode.
	 * @param int $store_mode_count            Orders with store checkout mode.
	 * @param int $unknown_checkout_data_count Orders with unknown checkout data.
	 */
	public function __construct(
		private int $fallback_count,
		private int $shopper_mismatch_count,
		private int $selected_mode_count,
		private int $store_mode_count,
		private int $unknown_checkout_data_count
	) {
	}

	/**
	 * Orders where checkout fallback occurred.
	 */
	public function fallback_count(): int {
		return $this->fallback_count;
	}

	/**
	 * Orders with shopper and transaction currency mismatch.
	 */
	public function shopper_mismatch_count(): int {
		return $this->shopper_mismatch_count;
	}

	/**
	 * Orders with selected checkout mode.
	 */
	public function selected_mode_count(): int {
		return $this->selected_mode_count;
	}

	/**
	 * Orders with store checkout mode.
	 */
	public function store_mode_count(): int {
		return $this->store_mode_count;
	}

	/**
	 * Orders with unknown checkout metadata.
	 */
	public function unknown_checkout_data_count(): int {
		return $this->unknown_checkout_data_count;
	}
}
