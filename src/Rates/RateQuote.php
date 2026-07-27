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

	/**
	 * Base currency code.
	 *
	 * @var string
	 */
	private string $base_code;

	/**
	 * Target currency code.
	 *
	 * @var string
	 */
	private string $target_code;

	/**
	 * Positive decimal string rate.
	 *
	 * @var string
	 */
	private string $rate;

	/**
	 * Builds a single exchange-rate quote.
	 *
	 * @param string $base_code   Base currency code.
	 * @param string $target_code Target currency code.
	 * @param string $rate        Positive decimal string.
	 */
	public function __construct( string $base_code, string $target_code, string $rate ) {
		$this->base_code   = strtoupper( $base_code );
		$this->target_code = strtoupper( $target_code );
		$this->rate        = $rate;
	}

	/**
	 * The base currency code.
	 */
	public function base_code(): string {
		return $this->base_code;
	}

	/**
	 * The target currency code.
	 */
	public function target_code(): string {
		return $this->target_code;
	}

	/**
	 * The positive decimal string rate.
	 */
	public function rate(): string {
		return $this->rate;
	}
}
