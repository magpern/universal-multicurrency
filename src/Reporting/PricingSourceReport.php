<?php
/**
 * Pricing source totals report.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Product-line pricing source totals report.
 */
final class PricingSourceReport {

	/**
	 * Captures product-line pricing source totals.
	 *
	 * @param float $fixed_total     Fixed product-line total.
	 * @param float $converted_total Converted product-line total.
	 * @param float $unknown_total   Unknown product-line total.
	 */
	public function __construct(
		private float $fixed_total,
		private float $converted_total,
		private float $unknown_total
	) {
	}

	/**
	 * Fixed product-line total.
	 */
	public function fixed_total(): float {
		return $this->fixed_total;
	}

	/**
	 * Converted product-line total.
	 */
	public function converted_total(): float {
		return $this->converted_total;
	}

	/**
	 * Unknown product-line total.
	 */
	public function unknown_total(): float {
		return $this->unknown_total;
	}

	/**
	 * Sum of fixed and converted product-line totals.
	 */
	public function classified_total(): float {
		return $this->fixed_total + $this->converted_total;
	}

	/**
	 * Fixed share of classified product-line value.
	 */
	public function fixed_share(): ?float {
		$classified = $this->classified_total();
		if ( $classified <= 0.0 ) {
			return null;
		}

		return $this->fixed_total / $classified;
	}
}
