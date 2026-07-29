<?php
/**
 * Heuristics for default currency symbol placement.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Currency;

use UMC\Currency as CurrencyVo;

/**
 * Suggests symbol position defaults from ISO code and display symbol.
 *
 * Single-character symbols such as € and $ typically precede the amount.
 * Multi-character symbols and Nordic conventions (kr) typically follow it.
 */
final class CurrencyPositionDefaults {

	/**
	 * ISO codes that conventionally place the unit after the amount.
	 *
	 * @var array<int, string>
	 */
	private const SUFFIX_CODES = array(
		'SEK',
		'NOK',
		'DKK',
		'ISK',
	);

	/**
	 * Returns the recommended symbol position for one currency.
	 *
	 * @param string $code   ISO currency code.
	 * @param string $symbol Display symbol (may be empty).
	 */
	public static function for_currency( string $code, string $symbol ): string {
		$code   = strtoupper( trim( $code ) );
		$symbol = trim( $symbol );

		if ( in_array( $code, self::SUFFIX_CODES, true ) ) {
			return 'right_space';
		}

		if ( self::uses_suffix_symbol( $symbol ) ) {
			return 'right_space';
		}

		if ( self::uses_prefix_symbol( $symbol ) ) {
			return 'left_space';
		}

		return CurrencyVo::DEFAULT_POSITION;
	}

	/**
	 * Whether the symbol should appear after the amount with spacing.
	 *
	 * @param string $symbol Display symbol.
	 */
	private static function uses_suffix_symbol( string $symbol ): bool {
		if ( '' === $symbol ) {
			return false;
		}

		if ( mb_strlen( $symbol ) > 1 ) {
			return true;
		}

		return 1 === preg_match( '/^\p{L}$/u', $symbol );
	}

	/**
	 * Whether the symbol should appear before the amount with spacing.
	 *
	 * @param string $symbol Display symbol.
	 */
	private static function uses_prefix_symbol( string $symbol ): bool {
		if ( '' === $symbol || mb_strlen( $symbol ) !== 1 ) {
			return false;
		}

		return 1 === preg_match( '/[^\p{L}\d\s]/u', $symbol );
	}
}
