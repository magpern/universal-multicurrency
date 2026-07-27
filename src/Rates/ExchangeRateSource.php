<?php
/**
 * Exchange-rate fetch provider contract.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Fetches exchange rates from an external provider.
 *
 * Distinct from {@see RateProvider}, which resolves rates for conversion at
 * runtime. Capability methods exist for future provider-aware callers (M9+);
 * nothing in Milestone 8's control flow consults them.
 */
interface ExchangeRateSource {

	/**
	 * Stable provider identifier (stored in settings).
	 */
	public function id(): string;

	/**
	 * Human-readable provider label for admin UI.
	 */
	public function label(): string;

	/**
	 * Whether conditional HTTP requests are supported.
	 */
	public function supports_conditional_requests(): bool;

	/**
	 * Whether historical (non-latest) rate endpoints exist.
	 */
	public function supports_historical_rates(): bool;

	/**
	 * Whether every requested currency code is supported by this provider.
	 *
	 * @param string[] $codes Currency codes to check.
	 */
	public function supports_currencies( array $codes ): bool;

	/**
	 * Fetches rates for the given base→target pairs in one batch request.
	 *
	 * @param string                $base_code    Store base currency.
	 * @param string[]              $target_codes Target currency codes (never empty).
	 * @param ProviderMetadata|null $previous     Prior fetch metadata for conditional requests.
	 */
	public function fetch( string $base_code, array $target_codes, ?ProviderMetadata $previous = null ): RateFetchResult;
}
