<?php
/**
 * A single exchange-rate quote from a provider.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Immutable per-currency quote: 1 base unit equals rate target units.
 */
final class RateQuote {

	private string $base_code;

	private string $target_code;

	private string $rate;

	/**
	 * @param string $base_code   Base currency code.
	 * @param string $target_code Target currency code.
	 * @param string $rate        Positive decimal string.
	 */
	public function __construct( string $base_code, string $target_code, string $rate ) {
		$this->base_code   = strtoupper( $base_code );
		$this->target_code = strtoupper( $target_code );
		$this->rate        = $rate;
	}

	public function base_code(): string {
		return $this->base_code;
	}

	public function target_code(): string {
		return $this->target_code;
	}

	public function rate(): string {
		return $this->rate;
	}
}
