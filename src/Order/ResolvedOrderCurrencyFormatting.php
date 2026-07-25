<?php

/**
 * Resolved order currency formatting (immutable).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

/**
 * Immutable readonly value object representing the resolved formatting
 * (decimals, symbol, position) for an order's currency.
 *
 * Built by HistoricalFormattingResolver from an OrderCurrencySnapshot using
 * the fallback chain: stored decimals → current config → ISO map → 2.
 */
final class ResolvedOrderCurrencyFormatting {

	/**
	 * Currency code (e.g., 'EUR', 'JPY').
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Number of decimal places.
	 *
	 * @var int
	 */
	private int $decimals;

	/**
	 * Currency symbol (e.g., '€', '¥').
	 *
	 * @var string
	 */
	private string $symbol;

	/**
	 * Symbol position: 'left', 'left_space', 'right', 'right_space'.
	 *
	 * @var string
	 */
	private string $position;

	/**
	 * Builds a resolved formatting object.
	 *
	 * @param string $code       Currency code.
	 * @param int    $decimals   Number of decimals (0, 2, 3, etc.).
	 * @param string $symbol     Currency symbol.
	 * @param string $position   Symbol position.
	 */
	public function __construct(
		string $code,
		int $decimals,
		string $symbol,
		string $position
	) {
		$this->code     = $code;
		$this->decimals = $decimals;
		$this->symbol   = $symbol;
		$this->position = $position;
	}

	/**
	 * Currency code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Number of decimal places.
	 */
	public function decimals(): int {
		return $this->decimals;
	}

	/**
	 * Currency symbol.
	 */
	public function symbol(): string {
		return $this->symbol;
	}

	/**
	 * Symbol position: 'left', 'left_space', 'right', 'right_space'.
	 */
	public function position(): string {
		return $this->position;
	}
}
