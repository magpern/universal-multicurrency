<?php
/**
 * Validates fixed-price input.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Sanitizes merchant-authored fixed prices.
 */
final class FixedPriceValidator {

	/**
	 * Normalizes a price to a non-negative decimal string, or '' when blank/invalid.
	 *
	 * @param mixed $raw Raw input.
	 */
	public static function normalize_price( mixed $raw ): string {
		if ( is_bool( $raw ) || null === $raw ) {
			return '';
		}

		$value = trim( (string) $raw );

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'wc_format_decimal' ) ) {
			$formatted = wc_format_decimal( $value, wc_get_price_decimals() );

			if ( '' === $formatted || ! is_numeric( $formatted ) ) {
				return '';
			}

			$float = (float) $formatted;

			if ( ! is_finite( $float ) || $float < 0.0 ) {
				return '';
			}

			return $formatted;
		}

		if ( ! is_numeric( $value ) ) {
			return '';
		}

		$float = (float) $value;

		if ( ! is_finite( $float ) || $float < 0.0 ) {
			return '';
		}

		return $value;
	}

	/**
	 * Whether sale is strictly less than regular when both are set.
	 *
	 * @param string $regular Normalized regular price.
	 * @param string $sale    Normalized sale price.
	 */
	public static function sale_less_than_regular( string $regular, string $sale ): bool {
		if ( '' === $regular || '' === $sale ) {
			return true;
		}

		return (float) $sale <= (float) $regular;
	}
}
