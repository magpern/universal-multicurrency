<?php
/**
 * Immutable resolved free-shipping threshold in the active (or base) currency.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

/**
 * Result of {@see FreeShippingThresholdResolver::resolve()}.
 *
 * Amount is a canonical decimal string owned by the shared resolver (and, for
 * foreign currencies, by {@see PriceConversionService} / {@see \UMC\Converter}).
 */
final class FreeShippingThreshold {

	/**
	 * Canonical decimal amount string.
	 *
	 * @var string
	 */
	private string $amount;

	/**
	 * ISO currency code for the amount.
	 *
	 * @var string
	 */
	private string $currency_code;

	/**
	 * Creates an immutable threshold value.
	 *
	 * @param string $amount        Canonical decimal string.
	 * @param string $currency_code Active or base currency code.
	 */
	public function __construct( string $amount, string $currency_code ) {
		$this->amount        = $amount;
		$this->currency_code = strtoupper( $currency_code );
	}

	/**
	 * Canonical decimal amount string.
	 */
	public function amount(): string {
		return $this->amount;
	}

	/**
	 * Currency code for the amount.
	 */
	public function currency_code(): string {
		return $this->currency_code;
	}
}
