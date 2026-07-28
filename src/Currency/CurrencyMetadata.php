<?php
/**
 * Read-only currency metadata from an external provider.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Currency;

/**
 * Immutable currency metadata snapshot.
 */
final class CurrencyMetadata {

	/**
	 * Creates a metadata snapshot.
	 *
	 * @param string $code     ISO 4217 currency code.
	 * @param string $name     Human-readable currency name.
	 * @param string $symbol   Default currency symbol.
	 * @param int    $decimals Default decimal places.
	 * @param string $position Default symbol position.
	 */
	public function __construct(
		private string $code,
		private string $name,
		private string $symbol,
		private int $decimals,
		private string $position
	) {
	}

	/**
	 * Returns the ISO currency code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Returns the currency display name.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Returns the default currency symbol.
	 */
	public function symbol(): string {
		return $this->symbol;
	}

	/**
	 * Returns the default decimal count.
	 */
	public function decimals(): int {
		return $this->decimals;
	}

	/**
	 * Returns the default symbol position.
	 */
	public function position(): string {
		return $this->position;
	}

	/**
	 * Builds a rich admin label such as "United States Dollar (USD)".
	 */
	public function option_label(): string {
		return sprintf(
			/* translators: 1: currency name, 2: ISO currency code */
			__( '%1$s (%2$s)', 'universal-multicurrency' ),
			$this->name,
			$this->code
		);
	}
}
