<?php
/**
 * ISO-4217 currency decimals fallback.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Support;

/**
 * Pure immutable lookup for ISO-4217 decimal places.
 *
 * Used to resolve decimal precision for historical orders whose currency is
 * no longer in the storefront configuration.
 *
 * Zero-decimal currencies: BIF, BYR (pre-2016), CLP, CVE, DJF, GNF, JPY, KMF, KRW,
 * PYG, RWF, VEF (pre-2018), VND, XAF, XOF, XPF, ISK, HUF (sometimes), TWD, UGX.
 *
 * Three-decimal currencies: BHD, JOD, KWD, OMR, TND, IQD.
 *
 * All others default to two decimals.
 */
final class IsoCurrencyDecimals {

	/**
	 * ISO codes that use zero decimal places.
	 *
	 * @var array<int, string>
	 */
	private const ZERO_DECIMALS = array(
		'BIF',
		'BYR',
		'CLP',
		'CVE',
		'DJF',
		'GNF',
		'JPY',
		'KMF',
		'KRW',
		'PYG',
		'RWF',
		'VEF',
		'VND',
		'XAF',
		'XOF',
		'XPF',
		'ISK',
		'HUF',
		'TWD',
		'UGX',
	);

	/**
	 * ISO codes that use three decimal places.
	 *
	 * @var array<int, string>
	 */
	private const THREE_DECIMALS = array(
		'BHD',
		'JOD',
		'KWD',
		'OMR',
		'TND',
		'IQD',
	);

	/**
	 * Resolves the decimal places for an ISO currency code.
	 *
	 * @param string $code ISO currency code (case-insensitive).
	 * @return int Number of decimal places (0, 2, or 3).
	 */
	public static function decimals( string $code ): int {
		$code = strtoupper( trim( $code ) );

		if ( in_array( $code, self::ZERO_DECIMALS, true ) ) {
			return 0;
		}

		if ( in_array( $code, self::THREE_DECIMALS, true ) ) {
			return 3;
		}

		return 2;
	}
}
