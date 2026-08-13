<?php
/**
 * Product meta access for fixed prices.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Reads and writes `_umc_fixed_prices` with request-local memoization.
 */
final class FixedPriceRepository {

	/**
	 * Request-scoped parsed documents keyed by product ID.
	 *
	 * @var array<int, FixedPriceDocument>
	 */
	private array $cache = array();

	/**
	 * Store base currency code for document parsing.
	 *
	 * @param string $base_currency_code Store base currency code.
	 */
	public function __construct(
		private string $base_currency_code
	) {
	}

	/**
	 * Reads the fixed-price document for a product ID.
	 *
	 * @param int $product_id Product or variation ID.
	 */
	public function get( int $product_id ): FixedPriceDocument {
		if ( isset( $this->cache[ $product_id ] ) ) {
			return $this->cache[ $product_id ];
		}

		$raw = get_post_meta( $product_id, FixedPriceDocument::META_KEY, true );

		$document = FixedPriceDocument::from_storage( $raw, $this->base_currency_code );

		$this->cache[ $product_id ] = $document;

		return $document;
	}

	/**
	 * Persists a document for a product ID.
	 *
	 * @param int                $product_id Product or variation ID.
	 * @param FixedPriceDocument $document   Document to store.
	 */
	public function save( int $product_id, FixedPriceDocument $document ): void {
		$json = $document->to_storage_json();

		if ( '' === $json ) {
			delete_post_meta( $product_id, FixedPriceDocument::META_KEY );
		} else {
			update_post_meta( $product_id, FixedPriceDocument::META_KEY, $json );
		}

		$this->cache[ $product_id ] = $document;
	}

	/**
	 * Clears request cache (tests).
	 */
	public function clear_cache(): void {
		$this->cache = array();
	}

	/**
	 * Whether raw stored meta contains an effective base-currency override.
	 *
	 * @param int $product_id Product ID.
	 */
	public function stored_meta_contains_base_override( int $product_id ): bool {
		$raw = get_post_meta( $product_id, FixedPriceDocument::META_KEY, true );

		return FixedPriceDocument::raw_contains_base_currency( $raw, $this->base_currency_code );
	}
}
