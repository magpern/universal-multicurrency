<?php
/**
 * Thrown when a currency's formatting attributes are invalid.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Exceptions;

use InvalidArgumentException;

/**
 * A currency value object could not be constructed because a non-code attribute
 * was invalid (decimals out of range, or an unknown symbol position).
 */
final class InvalidCurrencyException extends InvalidArgumentException implements Exception {

	/**
	 * Builds the exception for an out-of-range decimals value.
	 *
	 * @param int $decimals The offending value.
	 * @param int $max      The maximum allowed decimals.
	 */
	public static function for_decimals( int $decimals, int $max ): self {
		return new self(
			sprintf( 'Invalid currency decimals "%d": expected an integer between 0 and %d.', $decimals, $max )
		);
	}

	/**
	 * Builds the exception for an unknown symbol position.
	 *
	 * @param string $position The offending value.
	 * @param string $allowed  Comma-separated list of allowed positions.
	 */
	public static function for_position( string $position, string $allowed ): self {
		return new self(
			sprintf( 'Invalid currency position "%s": expected one of %s.', $position, $allowed )
		);
	}
}
