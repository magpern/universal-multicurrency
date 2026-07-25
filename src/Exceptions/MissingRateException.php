<?php

/**
 * Thrown when a conversion is requested but no usable rate exists.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Exceptions;

use RuntimeException;

/**
 * No usable exchange rate is configured for a requested base-to-target pair.
 *
 * A missing rate must fail explicitly: the plugin never silently produces a
 * converted value from an absent or unusable rate.
 */
final class MissingRateException extends RuntimeException implements Exception {

	/**
	 * Builds the exception for a base-to-target pair with no usable rate.
	 *
	 * @param string $base_code   Base currency code.
	 * @param string $target_code Target currency code.
	 */
	public static function for_pair( string $base_code, string $target_code ): self {
		return new self(
			sprintf( 'No usable exchange rate configured for %s to %s.', $base_code, $target_code )
		);
	}
}
