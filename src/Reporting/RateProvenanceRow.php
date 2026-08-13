<?php
/**
 * Rate provenance table row.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * One rate provenance bucket in a reporting result.
 */
final class RateProvenanceRow {

	/**
	 * Captures one rate provenance bucket.
	 *
	 * @param string $rate_source Rate source identifier.
	 * @param string $provider    Rate provider label.
	 * @param int    $order_count Qualifying order count.
	 */
	public function __construct(
		private string $rate_source,
		private string $provider,
		private int $order_count
	) {
	}

	/**
	 * Rate source identifier.
	 */
	public function rate_source(): string {
		return $this->rate_source;
	}

	/**
	 * Rate provider label.
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * Qualifying order count.
	 */
	public function order_count(): int {
		return $this->order_count;
	}
}
