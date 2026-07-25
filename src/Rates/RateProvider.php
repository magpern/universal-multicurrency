<?php
/**
 * Exchange-rate provider contract.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Rates;

/**
 * Resolves the exchange rate between two currencies.
 *
 * A rate means: 1 unit of the base currency equals `rate` units of the target
 * currency (e.g. base EUR, target SEK, rate "11.50" → 1 EUR = 11.50 SEK).
 *
 * This is the plugin's only rate abstraction. It exists because automatic rate
 * sources are a known future; the manual provider is the only implementation
 * for now.
 */
interface RateProvider {

	/**
	 * The rate to convert an amount from the base currency to the target.
	 *
	 * Returns `'1'` when base and target are the same currency, a positive
	 * decimal string when a usable rate exists, or `null` when none does.
	 * Implementations never return a zero, negative, or non-numeric rate.
	 *
	 * @param string $base_code   Base (source) currency code.
	 * @param string $target_code Target currency code.
	 */
	public function get_rate( string $base_code, string $target_code ): ?string;

	/**
	 * Whether a usable rate exists for the given pair.
	 *
	 * @param string $base_code   Base (source) currency code.
	 * @param string $target_code Target currency code.
	 */
	public function has_rate( string $base_code, string $target_code ): bool;
}
