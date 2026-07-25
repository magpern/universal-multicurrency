<?php
/**
 * Thrown when an exchange rate value is not a usable positive number.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Exceptions;

use InvalidArgumentException;

/**
 * An exchange rate was non-numeric, zero, or negative.
 *
 * Settings sanitization normally blanks such values before they reach the
 * domain layer; this exception guards direct construction paths.
 */
final class InvalidRateException extends InvalidArgumentException implements Exception {

	/**
	 * Builds the exception for a rejected rate.
	 *
	 * @param string $rate The offending value.
	 */
	public static function for_rate( string $rate ): self {
		return new self(
			sprintf( 'Invalid exchange rate "%s": expected a positive number.', $rate )
		);
	}
}
