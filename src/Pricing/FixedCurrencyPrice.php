<?php
/**
 * One currency's fixed regular/sale amounts.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Immutable fixed amounts for a single foreign currency.
 */
final class FixedCurrencyPrice {

	/**
	 * Fixed regular and sale amounts for one currency.
	 *
	 * @param string $regular Normalized decimal string or ''.
	 * @param string $sale    Normalized decimal string or ''.
	 */
	public function __construct(
		private string $regular,
		private string $sale
	) {
	}

	/**
	 * Fixed regular price or '' when unset.
	 */
	public function regular(): string {
		return $this->regular;
	}

	/**
	 * Fixed sale price or '' when unset.
	 */
	public function sale(): string {
		return $this->sale;
	}

	/**
	 * Whether any fixed amount is authored.
	 */
	public function has_any(): bool {
		return '' !== $this->regular || '' !== $this->sale;
	}

	/**
	 * Serializes both amounts for storage or comparison.
	 *
	 * @return array{regular:string,sale:string}
	 */
	public function to_array(): array {
		return array(
			'regular' => $this->regular,
			'sale'    => $this->sale,
		);
	}
}
