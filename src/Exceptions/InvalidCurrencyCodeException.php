<?php
/**
 * Thrown when a currency code does not match the required format.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Exceptions;

use InvalidArgumentException;

/**
 * A currency code failed the uppercase three-letter format rule (^[A-Z]{3}$).
 *
 * The format is validated, not membership in a real ISO-4217 list, because
 * WooCommerce permits custom currency codes.
 */
final class InvalidCurrencyCodeException extends InvalidArgumentException implements Exception {

	/**
	 * Builds the exception for a rejected code.
	 *
	 * @param string $code The offending value.
	 */
	public static function for_code( string $code ): self {
		return new self(
			sprintf( 'Invalid currency code "%s": expected three uppercase letters (e.g. "EUR").', $code )
		);
	}
}
