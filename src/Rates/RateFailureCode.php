<?php
/**
 * Closed vocabulary of operational rate-update failure codes.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Enum-like constants for provider/HTTP failure categories.
 */
final class RateFailureCode {

	public const PROVIDER_UNAVAILABLE = 'provider_unavailable';

	public const NETWORK_ERROR = 'network_error';

	public const TIMEOUT = 'timeout';

	public const INVALID_RESPONSE = 'invalid_response';

	public const UNSUPPORTED_CURRENCY = 'unsupported_currency';

	public const RATE_LIMITED = 'rate_limited';

	public const NOT_RETURNED_BY_PROVIDER = 'not_returned_by_provider';

	public const UPDATE_IN_PROGRESS = 'update_in_progress';

	public const STORAGE_FAILURE = 'storage_failure';

	/**
	 * Known failure codes in stable order.
	 *
	 * @var list<string>
	 */
	private const KNOWN = array(
		self::PROVIDER_UNAVAILABLE,
		self::NETWORK_ERROR,
		self::TIMEOUT,
		self::INVALID_RESPONSE,
		self::UNSUPPORTED_CURRENCY,
		self::RATE_LIMITED,
		self::NOT_RETURNED_BY_PROVIDER,
		self::UPDATE_IN_PROGRESS,
		self::STORAGE_FAILURE,
	);

	/**
	 * Whether a code is in the closed operational vocabulary.
	 *
	 * @param string $code Failure code.
	 */
	public static function is_known( string $code ): bool {
		return in_array( $code, self::KNOWN, true );
	}

	/**
	 * Returns a known code, or provider_unavailable when the value is unknown.
	 *
	 * @param string $code Raw failure code.
	 */
	public static function sanitize( string $code ): string {
		$code = strtolower( trim( $code ) );

		if ( self::is_known( $code ) ) {
			return $code;
		}

		return self::PROVIDER_UNAVAILABLE;
	}
}
