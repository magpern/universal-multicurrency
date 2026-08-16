<?php
/**
 * Shared fixed-price document mutation authority.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Extracted from {@see \UMC\Admin\ProductFixedPricesPanel::persist_submission()}'s
 * original algorithm (M20). Now the single mutation authority for every
 * surface that writes a product's/variation's fixed-price document: the
 * product editor (M20), M24's catalog seed/clear orchestration, and M25's
 * CSV importer — so there is one merge algorithm, not three independent
 * reimplementations (ADR-0030). Never references the raw storage key
 * itself — all reads and writes flow through {@see FixedPriceRepository}.
 *
 * WooCommerce-agnostic domain code: no dependency on CurrencyRegistry, WC
 * hooks, or request superglobals. A currency simply absent from the
 * `$touched` map is left exactly as read from the existing document —
 * including a disabled-but-configured currency's entry, since this class has
 * no concept of "enabled" at all, matching
 * {@see FixedPriceDocument}'s own "does not filter out disabled currencies"
 * contract.
 *
 * Algorithm, per currency present in `$touched`:
 *
 * 1. Seed the working map from every currency already in the existing
 *    document (untouched currencies are carried through unchanged).
 * 2. For a touched currency: `null` means an explicit removal (M24's
 *    `clear()`); a non-array entry is treated as malformed and the currency
 *    is left exactly as seeded (never partially applied); an array entry's
 *    `regular`/`sale` sub-fields are read with a blank default for a missing
 *    key — never backfilled from the currency's own prior stored value, so a
 *    currency actively being written is fully determined by what the caller
 *    supplied for it, never a mix of new-field-plus-stale-field.
 * 3. Each sub-field is normalized via {@see FixedPriceValidator::normalize_price()}.
 * 4. Both sub-fields normalizing to `''` clears the currency entirely
 *    (matches the product editor's long-standing "blank both fields to
 *    clear" behavior).
 * 5. Otherwise the **final merged pair** is validated via
 *    {@see FixedPriceValidator::sale_less_than_regular()}. On failure the
 *    entire currency entry is rejected atomically — the working map is left
 *    exactly as seeded, i.e. reverted to whatever was already stored, never
 *    a partial one-field write.
 * 6. The working map is rebuilt via {@see FixedPriceDocument::from_array()},
 *    which independently re-strips any base-currency entry as a second,
 *    structural defense layer beyond this class's own upfront skip.
 *
 * Prior to this extraction, {@see \UMC\Pricing\FixedPriceCatalogOperationsService::merge_and_save()}
 * never performed step 5's `sale_less_than_regular()` check — a deliberate,
 * evidence-justified M24 hardening documented in ADR-0030.
 */
final class FixedPriceDocumentMerger {

	/**
	 * Binds the merger to fixed-price persistence.
	 *
	 * @param FixedPriceRepository $repository Fixed-price persistence.
	 */
	public function __construct(
		private FixedPriceRepository $repository
	) {
	}

	/**
	 * Merges touched currency entries into a product's/variation's existing
	 * document and persists the result.
	 *
	 * @param int                                                             $product_id         Product or variation ID.
	 * @param array<int|string, array{regular?:mixed,sale?:mixed}|null|mixed> $touched Currency code => new entry, or null to remove. A non-array,
	 *                                                                         non-null entry is treated as malformed and skipped.
	 * @param string                                                          $base_currency_code Store base currency code.
	 */
	public function merge_and_save( int $product_id, array $touched, string $base_currency_code ): FixedPriceDocument {
		$existing = $this->repository->get( $product_id );
		$document = $this->merge( $existing, $touched, $base_currency_code );

		$this->repository->save( $product_id, $document );

		return $document;
	}

	/**
	 * Pure merge: computes the resulting document without reading or writing
	 * persistence. Exposed for callers that need to inspect the candidate
	 * result (e.g. to decide whether a write is even necessary) before
	 * persisting it themselves.
	 *
	 * @param FixedPriceDocument                                              $existing           Existing document to seed from.
	 * @param array<int|string, array{regular?:mixed,sale?:mixed}|null|mixed> $touched Currency code => new entry, or null to remove.
	 * @param string                                                          $base_currency_code Store base currency code.
	 */
	public function merge( FixedPriceDocument $existing, array $touched, string $base_currency_code ): FixedPriceDocument {
		$merged = array();

		foreach ( $existing->currencies() as $code => $price ) {
			$merged[ $code ] = $price->to_array();
		}

		$base = strtoupper( $base_currency_code );

		foreach ( $touched as $code => $entry ) {
			$currency_code = strtoupper( sanitize_text_field( (string) $code ) );

			if ( '' === $currency_code || $base === $currency_code ) {
				// Defense-in-depth: FixedPriceDocument::from_array() strips
				// any surviving base-currency entry again below.
				continue;
			}

			if ( null === $entry ) {
				unset( $merged[ $currency_code ] );
				continue;
			}

			if ( ! is_array( $entry ) ) {
				// Malformed entry: leave this currency exactly as seeded.
				continue;
			}

			$regular = FixedPriceValidator::normalize_price( $entry['regular'] ?? '' );
			$sale    = FixedPriceValidator::normalize_price( $entry['sale'] ?? '' );

			if ( '' === $regular && '' === $sale ) {
				unset( $merged[ $currency_code ] );
				continue;
			}

			if ( ! FixedPriceValidator::sale_less_than_regular( $regular, $sale ) ) {
				// Atomic reject: the working map is left exactly as seeded,
				// i.e. reverted to whatever was already stored for this
				// currency (or absent, if it never had an entry).
				continue;
			}

			$merged[ $currency_code ] = array(
				'regular' => $regular,
				'sale'    => $sale,
			);
		}

		return FixedPriceDocument::from_array( $merged, $base_currency_code );
	}
}
