<?php
/**
 * Currency metadata provider contract.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Currency;

/**
 * Exposes currency metadata from an external source of truth.
 */
interface CurrencyMetadataProvider {

	/**
	 * Returns metadata for one ISO code, if known.
	 *
	 * @param string $code ISO currency code.
	 */
	public function get( string $code ): ?CurrencyMetadata;

	/**
	 * Returns all known currencies keyed by ISO code.
	 *
	 * @return array<string, CurrencyMetadata>
	 */
	public function all(): array;

	/**
	 * Returns currencies matching a search query against name and ISO code.
	 *
	 * @param string $query Search query.
	 * @return array<string, CurrencyMetadata>
	 */
	public function search( string $query ): array;

	/**
	 * Whether the provider recognises an ISO code.
	 *
	 * @param string $code ISO currency code.
	 */
	public function is_known( string $code ): bool;
}
