<?php
/**
 * Monetary conversion.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

use InvalidArgumentException;
use UMC\Exceptions\InvalidRateException;
use UMC\Exceptions\MissingRateException;
use UMC\Rates\RateProvider;

/**
 * Converts base-currency amounts into a target currency.
 *
 * Stateless: it holds only its collaborators, keeps no mutable state, caches
 * nothing, and is fully deterministic. It is the single owner of monetary
 * arithmetic in the plugin — no other class multiplies or rounds money.
 *
 * Rounding deliberately mirrors WooCommerce's own arithmetic (PHP `round()`
 * with `PHP_ROUND_HALF_UP` on floats) so a converted price rounds identically
 * to a native WooCommerce price. See docs/adr/0002.
 */
final class Converter {

	/**
	 * Rate source.
	 *
	 * @var RateProvider
	 */
	private RateProvider $rates;

	/**
	 * Currency registry (base + configured currencies).
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Binds the converter to its rate source and currency registry.
	 *
	 * @param RateProvider     $rates    Rate source.
	 * @param CurrencyRegistry $registry Currency registry.
	 */
	public function __construct( RateProvider $rates, CurrencyRegistry $registry ) {
		$this->rates    = $rates;
		$this->registry = $registry;
	}

	/**
	 * Converts a base-currency amount into the target currency.
	 *
	 * Converting to the base currency is a rate-1 no-op. A missing or unusable
	 * rate, or an unknown target currency, fails explicitly — a converted value
	 * is never produced from an absent rate.
	 *
	 * A non-numeric amount propagates an InvalidArgumentException from the
	 * arithmetic helpers.
	 *
	 * @param string|int|float $amount      Amount in the base currency.
	 * @param string           $target_code Target currency code.
	 *
	 * @throws MissingRateException When the target is unknown or has no usable rate.
	 */
	public function convert( string|int|float $amount, string $target_code ): string {
		$base_code   = $this->registry->get_base_code();
		$target_code = strtoupper( $target_code );

		if ( $target_code === $base_code ) {
			return self::round_to_string( $amount, $this->registry->get_base_currency()->decimals() );
		}

		$target = $this->registry->get_currency( $target_code );

		if ( null === $target ) {
			throw MissingRateException::for_pair( $base_code, $target_code );
		}

		$rate = $this->rates->get_rate( $base_code, $target_code );

		if ( null === $rate ) {
			throw MissingRateException::for_pair( $base_code, $target_code );
		}

		return self::apply_rate( $amount, $rate, $target->decimals() );
	}

	/**
	 * Multiplies an amount by a rate and rounds to the given decimals.
	 *
	 * Pure and WordPress-free.
	 *
	 * @param string|int|float $amount   Amount in the base currency.
	 * @param string           $rate     Positive decimal rate string.
	 * @param int              $decimals Target fraction digits.
	 *
	 * @throws InvalidArgumentException When the amount is not numeric.
	 * @throws InvalidRateException When the rate is not a positive number.
	 */
	public static function apply_rate( string|int|float $amount, string $rate, int $decimals ): string {
		if ( ! is_numeric( $rate ) ) {
			throw InvalidRateException::for_rate( $rate );
		}

		$rate_float = (float) $rate;

		if ( ! is_finite( $rate_float ) || $rate_float <= 0.0 ) {
			throw InvalidRateException::for_rate( $rate );
		}

		if ( ! is_numeric( $amount ) ) {
			throw new InvalidArgumentException( sprintf( 'Conversion amount "%s" must be numeric.', (string) $amount ) );
		}

		return self::round_to_string( (float) $amount * $rate_float, $decimals );
	}

	/**
	 * Rounds a numeric value half-up and formats it as a fixed-decimals string.
	 *
	 * Pure and WordPress-free. Output has exactly `$decimals` fraction digits,
	 * a `.` decimal point and no thousands separator (e.g. "1150.00", "14730",
	 * "-11.50").
	 *
	 * @param string|int|float $value    Value to round.
	 * @param int              $decimals Fraction digits (negative treated as 0).
	 *
	 * @throws InvalidArgumentException When the value is not numeric.
	 */
	public static function round_to_string( string|int|float $value, int $decimals ): string {
		if ( ! is_numeric( $value ) ) {
			throw new InvalidArgumentException( sprintf( 'Amount "%s" must be numeric.', (string) $value ) );
		}

		$decimals = max( 0, $decimals );
		$rounded  = round( (float) $value, $decimals, PHP_ROUND_HALF_UP );

		return number_format( $rounded, $decimals, '.', '' );
	}
}
