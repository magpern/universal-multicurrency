<?php
/**
 * Catalog-wide fixed-price seed/clear orchestration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Integration\DisplayPriceConverter;
use UMC\Rates\RateProvider;
use WC_Product;

/**
 * Single orchestration path for M24 catalog-wide fixed-price operations
 * (ADR-0029). Shared unchanged by the dedicated admin screen (WP3) and
 * `wp umc prices seed|clear` (WP4) — neither has its own seed/clear
 * implementation.
 *
 * Reuses, never reimplements:
 *
 * - {@see FixedPriceRepository} / {@see FixedPriceValidator} /
 *   {@see FixedPriceDocument} for all persistence, via the exact merge
 *   algorithm {@see \UMC\Admin\ProductFixedPricesPanel::persist_submission()}
 *   already uses, so output is byte-identical to manual single-product
 *   authoring for equivalent input.
 * - {@see DisplayPriceConverter}'s `convert_to()` method (bound to
 *   {@see \UMC\Integration\PriceConversionService} in production) for all
 *   arithmetic. The underlying conversion class itself is a strict seam:
 *   only `PriceConversionService` may reference it directly
 *   ({@see \UMC\Tests\Integration\StorefrontGuardTest::test_converter_is_only_used_through_the_seam}),
 *   so this service calls through the same `convert_to( $amount, $target,
 *   $rate )` seam method the storefront path already uses for an explicit
 *   target currency — no second conversion engine, no direct reference to
 *   the underlying conversion class.
 * - {@see FixedPriceCoverageReport::eligible_targets()} to resolve which
 *   variations a variable product's operation actually touches, so scope
 *   can never disagree with coverage classification.
 *
 * Resolves exactly one `RateProvider::get_rate()` value at the start of a
 * seed operation and threads it through every product, every variation, and
 * every batch belonging to that one call — never re-resolved mid-operation
 * (ADR-0029 § Single execution-rate snapshot).
 *
 * Never catches `Throwable`/`Exception` — this codebase's guard
 * ({@see \UMC\Tests\Integration\StorefrontGuardTest::test_no_broad_exception_is_swallowed})
 * requires programming errors to surface rather than being swallowed. Every
 * foreseeable bad-data condition (missing/malformed authored price) is
 * pre-validated and reported as a *skip*, never caught after the fact — so
 * {@see FixedPriceOperationResult::failed()} is always empty in the current
 * implementation; the accessor exists for API completeness.
 */
final class FixedPriceCatalogOperationsService {

	/**
	 * Default batch size for CLI catalog iteration (within the 100-250
	 * range used elsewhere in the plugin, e.g. ReportingService).
	 */
	public const DEFAULT_BATCH_SIZE = 200;

	/**
	 * Binds the orchestration service to its collaborators.
	 *
	 * @param FixedPriceRepository     $repository Fixed-price persistence.
	 * @param FixedPriceCoverageReport $coverage   Variable-product population resolution.
	 * @param RateProvider             $rates      Exchange-rate source.
	 * @param DisplayPriceConverter    $converter  Sanctioned Converter seam.
	 * @param CurrencyRegistry         $registry   Currency configuration.
	 */
	public function __construct(
		private FixedPriceRepository $repository,
		private FixedPriceCoverageReport $coverage,
		private RateProvider $rates,
		private DisplayPriceConverter $converter,
		private CurrencyRegistry $registry
	) {
	}

	/**
	 * Seeds fixed prices for every product in scope from the current FX
	 * conversion of each product's/variation's own authored native price.
	 *
	 * Never reads `get_price()` or WooCommerce's current sale-active state;
	 * only `get_regular_price('edit')` / `get_sale_price('edit')`. Never
	 * sources a variation's amounts from its parent or a sibling variation.
	 *
	 * @param iterable $products      Top-level catalog scope of WC_Product (simple or variable).
	 * @param string   $currency_code Target non-base currency code.
	 * @param bool     $persist       False for dry-run: identical computation, zero writes.
	 */
	public function seed( iterable $products, string $currency_code, bool $persist = true ): FixedPriceOperationResult {
		$currency_code = strtoupper( $currency_code );

		if ( $this->registry->is_base( $currency_code ) ) {
			return FixedPriceOperationResult::aborted( FixedPriceOperationResult::ABORT_BASE_CURRENCY );
		}

		$target = $this->registry->get_currency( $currency_code );

		if ( null === $target ) {
			return FixedPriceOperationResult::aborted( FixedPriceOperationResult::ABORT_UNKNOWN_CURRENCY );
		}

		// Resolved exactly once for this operation; never re-fetched below.
		$rate = $this->rates->get_rate( $this->registry->get_base_code(), $currency_code );

		if ( null === $rate ) {
			return FixedPriceOperationResult::aborted( FixedPriceOperationResult::ABORT_NO_RATE );
		}

		$succeeded = array();
		$skipped   = array();

		foreach ( $this->flatten_targets( $products ) as $item ) {
			$outcome = $this->seed_one( $item, $currency_code, $target, $rate, $persist );

			if ( null === $outcome ) {
				$succeeded[] = $item->get_id();
			} else {
				$skipped[ $item->get_id() ] = $outcome;
			}
		}

		return FixedPriceOperationResult::completed( $succeeded, $skipped, array(), $rate );
	}

	/**
	 * Clears the fixed price for one currency across every product in scope.
	 * Preserves every other currency's entries and every other product.
	 *
	 * @param iterable $products      Top-level catalog scope of WC_Product (simple or variable).
	 * @param string   $currency_code Target non-base currency code.
	 * @param bool     $persist       False for dry-run: identical computation, zero writes.
	 */
	public function clear( iterable $products, string $currency_code, bool $persist = true ): FixedPriceOperationResult {
		$currency_code = strtoupper( $currency_code );

		if ( $this->registry->is_base( $currency_code ) ) {
			return FixedPriceOperationResult::aborted( FixedPriceOperationResult::ABORT_BASE_CURRENCY );
		}

		if ( null === $this->registry->get_currency( $currency_code ) ) {
			return FixedPriceOperationResult::aborted( FixedPriceOperationResult::ABORT_UNKNOWN_CURRENCY );
		}

		$succeeded = array();
		$skipped   = array();

		foreach ( $this->flatten_targets( $products ) as $item ) {
			$outcome = $this->clear_one( $item, $currency_code, $persist );

			if ( null === $outcome ) {
				$succeeded[] = $item->get_id();
			} else {
				$skipped[ $item->get_id() ] = $outcome;
			}
		}

		return FixedPriceOperationResult::completed( $succeeded, $skipped, array() );
	}

	/**
	 * Expands top-level catalog products into the flat set of priceable
	 * targets (itself for simple products; its structural population for
	 * variable products), via the shared {@see FixedPriceCoverageReport}.
	 *
	 * @param iterable $products Top-level catalog scope of WC_Product.
	 * @return iterable Flattened WC_Product targets.
	 */
	private function flatten_targets( iterable $products ): iterable {
		foreach ( $products as $product ) {
			foreach ( $this->coverage->eligible_targets( $product ) as $target ) {
				yield $target;
			}
		}
	}

	/**
	 * Seeds one product/variation. Returns null on success, or a skip reason.
	 *
	 * @param WC_Product $item          Simple product or eligible variation.
	 * @param string     $currency_code Uppercase target currency code.
	 * @param Currency   $target        Target currency (decimals for rounding).
	 * @param string     $rate          Single rate resolved for this operation.
	 * @param bool       $persist       False for dry-run.
	 */
	private function seed_one( WC_Product $item, string $currency_code, Currency $target, string $rate, bool $persist ): ?string {
		$regular_native = $item->get_regular_price( 'edit' );

		if ( '' === $regular_native || ! is_numeric( $regular_native ) ) {
			return 'no_authored_regular_price';
		}

		$sale_native = $item->get_sale_price( 'edit' );

		$regular_target = (string) $this->converter->convert_to( $regular_native, $target, $rate );
		$sale_target    = ( '' !== $sale_native && is_numeric( $sale_native ) )
			? (string) $this->converter->convert_to( $sale_native, $target, $rate )
			: '';

		if ( $persist ) {
			$this->merge_and_save(
				$item->get_id(),
				$currency_code,
				array(
					'regular' => $regular_target,
					'sale'    => $sale_target,
				)
			);
		}

		return null;
	}

	/**
	 * Clears one product's/variation's currency entry. Returns null on
	 * success, or a skip reason.
	 *
	 * @param WC_Product $item          Simple product or eligible variation.
	 * @param string     $currency_code Uppercase target currency code.
	 * @param bool       $persist       False for dry-run.
	 */
	private function clear_one( WC_Product $item, string $currency_code, bool $persist ): ?string {
		$existing = $this->repository->get( $item->get_id() );

		if ( null === $existing->get_currency( $currency_code ) ) {
			return 'no_fixed_price_set';
		}

		if ( $persist ) {
			$this->merge_and_save( $item->get_id(), $currency_code, null );
		}

		return null;
	}

	/**
	 * Merges one currency entry into a product's existing document and
	 * saves — the identical merge algorithm
	 * {@see \UMC\Admin\ProductFixedPricesPanel::persist_submission()} uses:
	 * read existing currencies, overlay or remove the target entry, rebuild
	 * via {@see FixedPriceDocument::from_array()} (which re-validates every
	 * entry through {@see FixedPriceValidator}), save.
	 *
	 * @param int                                    $product_id    Product or variation ID.
	 * @param string                                 $currency_code Uppercase currency code.
	 * @param array{regular:string,sale:string}|null $entry          New entry, or null to remove.
	 */
	private function merge_and_save( int $product_id, string $currency_code, ?array $entry ): void {
		$existing = $this->repository->get( $product_id );
		$merged   = array();

		foreach ( $existing->currencies() as $code => $price ) {
			$merged[ $code ] = $price->to_array();
		}

		if ( null === $entry ) {
			unset( $merged[ $currency_code ] );
		} else {
			$merged[ $currency_code ] = $entry;
		}

		$document = FixedPriceDocument::from_array( $merged, $this->registry->get_base_code() );
		$this->repository->save( $product_id, $document );

		/**
		 * Fires after fixed prices are saved. Also fired by
		 * {@see \UMC\Admin\ProductFixedPricesPanel::persist_submission()} for
		 * single-product authoring.
		 *
		 * @since 0.19.0
		 *
		 * @param int                $product_id Product or variation ID.
		 * @param FixedPriceDocument $document   Saved document.
		 */
		do_action( 'umc_fixed_prices_saved', $product_id, $document );
	}
}
