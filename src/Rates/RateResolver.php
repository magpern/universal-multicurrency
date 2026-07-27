<?php
/**
 * Pure effective-rate derivation from persisted inputs.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

use UMC\Settings;

/**
 * Derives the storefront rate from manual/automatic inputs without I/O.
 */
final class RateResolver {

	/**
	 * Computes the effective storefront rate from persisted inputs.
	 *
	 * @param string $mode                Resolved mode: manual | automatic.
	 * @param string $manual_rate         Decimal string or '' (unset).
	 * @param string $provider_rate       Decimal string or '' (never fetched).
	 * @param string $merchant_adjustment Decimal string percentage, e.g. '2.5' or '-1'.
	 */
	public static function effective_rate(
		string $mode,
		string $manual_rate,
		string $provider_rate,
		string $merchant_adjustment
	): ?string {
		if ( 'manual' === $mode ) {
			return '' === $manual_rate ? null : $manual_rate;
		}

		if ( '' === $provider_rate ) {
			return null;
		}

		$base = (float) $provider_rate;
		$adj  = (float) $merchant_adjustment;
		$rate = $base * ( 1.0 + ( $adj / 100.0 ) );

		if ( ! is_finite( $rate ) || $rate <= 0.0 ) {
			return null;
		}

		return Settings::normalize_rate( $rate );
	}
}
