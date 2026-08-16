<?php
/**
 * WooCommerce product CSV export/import integration for fixed prices.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

use UMC\CurrencyRegistry;
use WC_Data;
use WC_Product;

/**
 * The single WooCommerce-CSV-hook-aware adapter for M25 (ADR-0030 / the
 * fixed-pricing-csv-interchange architecture doc). Extends WooCommerce's own
 * product CSV export/import via six native extension hooks; never a parallel
 * CSV format, never a separate admin page.
 *
 * All reads/writes flow through {@see FixedPriceRepository} and
 * {@see FixedPriceDocumentMerger} — this class owns only the WC-hook plumbing
 * and CSV-specific validation (raw-cell blank/invalid distinction, live
 * currency-registry gating). It has no dependency on any FX rate-resolution
 * or currency-conversion authority (guarded by
 * {@see \UMC\Tests\Unit\Pricing\FixedPriceCsvIntegrationGuardTest}) — CSV
 * import/export is authoring and presentation of already-authored data,
 * never conversion.
 */
final class FixedPriceCsvIntegration {

	private const COLUMN_PREFIX_REGULAR = 'umc_fixed_regular_';

	private const COLUMN_PREFIX_SALE = 'umc_fixed_sale_';

	/**
	 * Shared mutation authority (ADR-0030), built from the same repository
	 * this class already receives — the same primitive the product editor
	 * and M24's catalog service delegate to.
	 *
	 * @var FixedPriceDocumentMerger
	 */
	private FixedPriceDocumentMerger $merger;

	/**
	 * Binds the integration to fixed-price persistence and live currency
	 * configuration.
	 *
	 * @param FixedPriceRepository $repository Fixed-price persistence.
	 * @param CurrencyRegistry     $registry   Currency registry, rebuilt fresh per request by the caller.
	 */
	public function __construct(
		private FixedPriceRepository $repository,
		private CurrencyRegistry $registry
	) {
		$this->merger = new FixedPriceDocumentMerger( $repository );
	}

	/**
	 * Registers every WooCommerce CSV export/import hook this integration owns.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'add_export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_export_columns' ) );
		add_filter( 'woocommerce_product_export_row_data', array( $this, 'add_export_row_data' ), 10, 2 );

		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'add_mapping_options' ), 10, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'add_mapping_default_columns' ), 10, 2 );

		// Runs on every product import, unconditionally — the raw-meta
		// resync-to-database-truth defense (architecture doc §5), not gated
		// on whether any of this plugin's own structured columns are present.
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'resync_raw_meta' ), 10, 2 );

		// The only place this class calls FixedPriceRepository/FixedPriceDocumentMerger
		// for structured-column authoring (architecture doc §6) — never at
		// pre_insert_product_object, for ID-timing safety.
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'apply_structured_columns' ), 10, 2 );
	}

	// -----------------------------------------------------------------
	// Export (architecture doc §4).
	// -----------------------------------------------------------------

	/**
	 * Adds the full id => label column set for every non-base configured
	 * currency. Shared verbatim between the column-names hook (drives an
	 * ordinary "export everything" run) and the default-columns hook (drives
	 * the individually-selectable "narrow the export" picker) — WooCommerce's
	 * column picker is opt-out, so registering in both is required for UMC
	 * data to be included by default while still being individually pickable.
	 *
	 * @param array<string, string> $columns Existing column id => label map.
	 * @return array<string, string>
	 */
	public function add_export_columns( array $columns ): array {
		foreach ( $this->non_base_currency_codes() as $code ) {
			$columns[ $this->column_id( 'regular', $code ) ] = $this->column_label( 'regular', $code );
			$columns[ $this->column_id( 'sale', $code ) ]    = $this->column_label( 'sale', $code );
		}

		return $columns;
	}

	/**
	 * Projects one exported row's fixed-price columns from a single
	 * {@see FixedPriceRepository::get()} call. A variable parent's columns
	 * are always left blank, enforced structurally by checking product type
	 * before ever reading the repository — never incidentally, via a document
	 * that merely happens to be empty.
	 *
	 * @param array<string, mixed> $row     Row data being assembled for export.
	 * @param mixed                $product Product or variation being exported.
	 * @return array<string, mixed>
	 */
	public function add_export_row_data( array $row, $product ): array {
		if ( ! $product instanceof WC_Product ) {
			return $row;
		}

		if ( 'variable' === $product->get_type() ) {
			return $row;
		}

		$document = $this->repository->get( $product->get_id() );

		foreach ( $this->non_base_currency_codes() as $code ) {
			$entry = $document->get_currency( $code );

			$row[ $this->column_id( 'regular', $code ) ] = null !== $entry ? $entry->regular() : '';
			$row[ $this->column_id( 'sale', $code ) ]    = null !== $entry ? $entry->sale() : '';
		}

		return $row;
	}

	// -----------------------------------------------------------------
	// Import — column mapping (architecture doc §6).
	// -----------------------------------------------------------------

	/**
	 * Adds the structured columns to the manual "map this column to…"
	 * dropdown offered per raw CSV header during the mapping-review step.
	 *
	 * @param array<string, mixed> $options Existing id => label option map.
	 * @param string               $item    Raw header text being mapped (unused; every currency's options are always offered).
	 * @return array<string, mixed>
	 */
	public function add_mapping_options( array $options, $item ): array {
		unset( $item );

		foreach ( $this->non_base_currency_codes() as $code ) {
			$options[ $this->column_id( 'regular', $code ) ] = $this->column_label( 'regular', $code );
			$options[ $this->column_id( 'sale', $code ) ]    = $this->column_label( 'sale', $code );
		}

		return $options;
	}

	/**
	 * Adds the structured columns to the label => id map used to auto-map a
	 * raw CSV header to an internal column id by exact (case-insensitive)
	 * text match — the same mechanism WooCommerce's own native columns use,
	 * making an export → reimport round trip auto-map with no manual step.
	 *
	 * @param array<string, string> $columns     Existing label => id map.
	 * @param array<int, string>    $raw_headers Raw header row (unused; the same label set is always offered).
	 * @return array<string, string>
	 */
	public function add_mapping_default_columns( array $columns, $raw_headers ): array {
		unset( $raw_headers );

		foreach ( $this->non_base_currency_codes() as $code ) {
			$columns[ $this->column_label( 'regular', $code ) ] = $this->column_id( 'regular', $code );
			$columns[ $this->column_label( 'sale', $code ) ]    = $this->column_id( 'sale', $code );
		}

		return $columns;
	}

	// -----------------------------------------------------------------
	// Import — raw-meta resync-to-database-truth defense (architecture doc §5).
	// -----------------------------------------------------------------

	/**
	 * Resyncs the importing object's in-memory {@see FixedPriceDocument::META_KEY}
	 * entry to an independently, freshly read database value before WooCommerce
	 * persists the product. Not an unconditional delete: WooCommerce's own
	 * generic `meta:`-prefixed importer mutates the *existing* WC_Meta_Data
	 * entry's value in place (matched by key, keeping its real meta_id)
	 * rather than adding a duplicate, so an unconditional delete here would
	 * destroy a legitimate, untouched document exactly as destructively as
	 * the attack it defends against. Runs on every product import
	 * unconditionally. Never throws, never returns a WP_Error — only ever
	 * mutates $object's in-memory meta and returns it unchanged.
	 *
	 * @param mixed $product_object Product object being imported.
	 * @param mixed $data           Parsed row data (unused; this guard is unconditional).
	 * @return mixed The same object, with its fixed-price meta resynced.
	 */
	public function resync_raw_meta( $product_object, $data ) {
		unset( $data );

		if ( ! $product_object instanceof WC_Data ) {
			return $product_object;
		}

		$product_id = $product_object->get_id();
		$db_value   = ( $product_id > 0 && metadata_exists( 'post', $product_id, FixedPriceDocument::META_KEY ) )
			? get_post_meta( $product_id, FixedPriceDocument::META_KEY, true )
			: null;

		if ( null === $db_value ) {
			$product_object->delete_meta_data( FixedPriceDocument::META_KEY );
		} else {
			$product_object->update_meta_data( FixedPriceDocument::META_KEY, $db_value );
		}

		return $product_object;
	}

	// -----------------------------------------------------------------
	// Import — structured columns (architecture doc §6).
	// -----------------------------------------------------------------

	/**
	 * Applies this row's mapped structured UMC columns to the just-persisted
	 * product's fixed-price document. The only place this class calls
	 * {@see FixedPriceRepository}/{@see FixedPriceDocumentMerger} for
	 * structured-column authoring. Zero repository interaction when no UMC
	 * column is mapped/present on the row; at most one `get()` once anything
	 * is mapped/present; a `save()` only when the merged result differs from
	 * what was read.
	 *
	 * @param mixed $product_object Product object, already saved with a real, stable ID.
	 * @param mixed $data           Parsed row data (mapped column id => cleaned cell string).
	 */
	public function apply_structured_columns( $product_object, $data ): void {
		if ( ! $product_object instanceof WC_Product || ! is_array( $data ) ) {
			return;
		}

		$product_id = $product_object->get_id();

		if ( $product_id <= 0 ) {
			return;
		}

		$candidates = $this->extract_candidate_currency_codes( $data );

		if ( array() === $candidates ) {
			return;
		}

		$existing = $this->repository->get( $product_id );
		$touched  = array();

		foreach ( $candidates as $code ) {
			$entry = $this->build_touched_entry( $product_id, $code, $data, $existing );

			if ( null !== $entry ) {
				$touched[ $code ] = $entry;
			}
		}

		if ( array() === $touched ) {
			return;
		}

		$candidate_document = $this->merger->merge( $existing, $touched, $this->registry->get_base_code() );

		$this->log_atomic_rejections( $product_id, $touched, $candidate_document );

		if ( $candidate_document->fingerprint() === $existing->fingerprint() ) {
			return;
		}

		$this->repository->save( $product_id, $candidate_document );
	}

	/**
	 * Scans a parsed row for every currency code referenced by a UMC
	 * structured column id, regardless of whether that currency is currently
	 * configured — currency-registry gating happens per candidate afterward,
	 * so a currency removed/rebased since export is rejected explicitly
	 * (logged), never silently ignored by never being scanned for.
	 *
	 * @param array<string, mixed> $data Parsed row data.
	 * @return array<int, string> Unique uppercase currency codes.
	 */
	private function extract_candidate_currency_codes( array $data ): array {
		$codes = array();

		foreach ( array_keys( $data ) as $key ) {
			if ( 1 === preg_match( '/^umc_fixed_(?:regular|sale)_([a-z0-9]+)$/', (string) $key, $matches ) ) {
				$codes[ strtoupper( $matches[1] ) ] = true;
			}
		}

		return array_keys( $codes );
	}

	/**
	 * Builds one currency's `$touched` entry for {@see FixedPriceDocumentMerger::merge()},
	 * or null when the currency must not be touched at all this row.
	 *
	 * Per sub-field: mapped+genuinely-blank clears; mapped+valid sets;
	 * mapped+non-blank-invalid is skipped (logged) and the field is backfilled
	 * from the existing document so it survives unchanged, never coerced to
	 * blank; unmapped this session is likewise backfilled from the existing
	 * document — the merger never backfills on its own, so this class must
	 * supply the untouched sub-field explicitly whenever the currency as a
	 * whole is touched, or a same-currency skip/omission would otherwise wipe
	 * it.
	 *
	 * @param int                 $product_id Product or variation ID (for logging).
	 * @param string              $code       Uppercase candidate currency code.
	 * @param array<string,mixed> $data       Parsed row data.
	 * @param FixedPriceDocument  $existing   Existing document, read once by the caller.
	 * @return array{regular:string,sale:string}|null
	 */
	private function build_touched_entry( int $product_id, string $code, array $data, FixedPriceDocument $existing ): ?array {
		$regular_key = $this->column_id( 'regular', $code );
		$sale_key    = $this->column_id( 'sale', $code );

		$regular_present = array_key_exists( $regular_key, $data );
		$sale_present    = array_key_exists( $sale_key, $data );

		if ( ! $regular_present && ! $sale_present ) {
			return null;
		}

		if ( ! $this->registry->has_currency( $code ) || $this->registry->is_base( $code ) ) {
			if ( $regular_present ) {
				$this->log_skip( $product_id, $code, 'regular', 'currency is not a configured, non-base currency at import time' );
			}

			if ( $sale_present ) {
				$this->log_skip( $product_id, $code, 'sale', 'currency is not a configured, non-base currency at import time' );
			}

			return null;
		}

		$existing_entry = $existing->get_currency( $code );
		$regular        = null !== $existing_entry ? $existing_entry->regular() : '';
		$sale           = null !== $existing_entry ? $existing_entry->sale() : '';

		if ( $regular_present ) {
			$regular = $this->resolve_field( $product_id, $code, 'regular', (string) $data[ $regular_key ], $regular );
		}

		if ( $sale_present ) {
			$sale = $this->resolve_field( $product_id, $code, 'sale', (string) $data[ $sale_key ], $sale );
		}

		return array(
			'regular' => $regular,
			'sale'    => $sale,
		);
	}

	/**
	 * Resolves one mapped+present cell to its effective value: blank clears,
	 * a valid value sets, a non-blank invalid value is skipped (logged) and
	 * falls back to the field's current value so it is never cleared or
	 * coerced to zero.
	 *
	 * @param int    $product_id      Product or variation ID.
	 * @param string $code            Uppercase currency code.
	 * @param string $field           'regular'|'sale'.
	 * @param string $raw             Raw (already WC-cleaned) cell string.
	 * @param string $fallback_value  Value to keep when the cell is invalid.
	 */
	private function resolve_field( int $product_id, string $code, string $field, string $raw, string $fallback_value ): string {
		if ( '' === trim( $raw ) ) {
			return '';
		}

		$normalized = FixedPriceValidator::normalize_price( $raw );

		if ( '' === $normalized ) {
			$this->log_skip( $product_id, $code, $field, sprintf( 'non-blank invalid value "%s"', $raw ) );

			return $fallback_value;
		}

		return $normalized;
	}

	/**
	 * Logs any currency whose merged pair was rejected by
	 * {@see FixedPriceDocumentMerger}'s atomic `sale_less_than_regular()`
	 * check — detected by comparing the attempted entry against what the
	 * merger actually produced for that currency. An intended explicit
	 * removal (both sub-fields resolved blank) is never a rejection.
	 *
	 * @param int                                              $product_id Product or variation ID.
	 * @param array<string, array{regular:string,sale:string}> $touched    Entries attempted this row.
	 * @param FixedPriceDocument                               $candidate  Document the merger produced.
	 */
	private function log_atomic_rejections( int $product_id, array $touched, FixedPriceDocument $candidate ): void {
		foreach ( $touched as $code => $entry ) {
			if ( '' === $entry['regular'] && '' === $entry['sale'] ) {
				continue;
			}

			$actual         = $candidate->get_currency( $code );
			$actual_regular = null !== $actual ? $actual->regular() : '';
			$actual_sale    = null !== $actual ? $actual->sale() : '';

			if ( $actual_regular !== $entry['regular'] || $actual_sale !== $entry['sale'] ) {
				$this->log_skip(
					$product_id,
					$code,
					'regular/sale',
					sprintf(
						'merged pair (regular=%1$s, sale=%2$s) rejected — sale must not exceed regular; currency reverted to its previous stored state',
						$entry['regular'],
						$entry['sale']
					)
				);
			}
		}
	}

	/**
	 * Logs one field-level skip on the dedicated `umc-csv-import` channel.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $code       Uppercase currency code.
	 * @param string $field      Field name.
	 * @param string $reason     Human-readable reason.
	 */
	private function log_skip( int $product_id, string $code, string $field, string $reason ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->warning(
			sprintf(
				'UMC fixed-price CSV import: product #%1$d, currency %2$s, field %3$s skipped — %4$s.',
				$product_id,
				$code,
				$field,
				$reason
			),
			array( 'source' => 'umc-csv-import' )
		);
	}

	// -----------------------------------------------------------------
	// Shared column-id/label helpers.
	// -----------------------------------------------------------------

	/**
	 * Every non-base currency currently configured, regenerated from the live
	 * registry on every call — never cached across requests.
	 *
	 * @return array<int, string>
	 */
	private function non_base_currency_codes(): array {
		$codes = array();

		foreach ( $this->registry->get_currencies() as $currency ) {
			if ( $this->registry->is_base( $currency->code() ) ) {
				continue;
			}

			$codes[] = $currency->code();
		}

		return $codes;
	}

	/**
	 * Internal column id for a field/currency pair.
	 *
	 * @param string $field 'regular'|'sale'.
	 * @param string $code  Currency code.
	 */
	private function column_id( string $field, string $code ): string {
		$prefix = 'regular' === $field ? self::COLUMN_PREFIX_REGULAR : self::COLUMN_PREFIX_SALE;

		return $prefix . strtolower( $code );
	}

	/**
	 * Merchant-visible column label for a field/currency pair. Shared
	 * verbatim between export column registration and import auto-mapping so
	 * the two stay byte-identical (required for header-text auto-mapping to
	 * work on an export → reimport round trip).
	 *
	 * @param string $field 'regular'|'sale'.
	 * @param string $code  Currency code.
	 */
	private function column_label( string $field, string $code ): string {
		if ( 'regular' === $field ) {
			return sprintf(
				/* translators: %s: currency code, e.g. SEK */
				__( 'UMC Fixed Regular Price (%s)', 'universal-multicurrency' ),
				$code
			);
		}

		return sprintf(
			/* translators: %s: currency code, e.g. SEK */
			__( 'UMC Fixed Sale Price (%s)', 'universal-multicurrency' ),
			$code
		);
	}
}
