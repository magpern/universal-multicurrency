<?php
/**
 * Fixed-price catalog coverage classification.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

use WC_Product;
use WC_Product_Variation;

/**
 * Classifies a product's fixed-price coverage for one currency, and resolves
 * the exact set of priceable targets (itself, or its structurally eligible
 * variations) a catalog operation must act on.
 *
 * Single source of truth for the M24 variable-product population (ADR-0029):
 * every variation that is structurally enabled
 * ({@see WC_Product_Variation::get_status()} === 'publish' — WooCommerce's
 * own merchant-controlled "Enabled" checkbox state, never stock status or
 * is_purchasable()) and has an authored native regular price. Both
 * {@see classify()} and {@see eligible_targets()} are built on the same
 * {@see population()} so coverage classification and catalog-operation scope
 * can never disagree about which variations are in play.
 */
final class FixedPriceCoverageReport {

	public const STATUS_FIXED                   = 'fixed';
	public const STATUS_PARTIAL                 = 'partial';
	public const STATUS_FX_FALLBACK             = 'fx';
	public const STATUS_NO_PRICEABLE_VARIATIONS = 'no_priceable_variations';

	/**
	 * Binds the report to fixed-price persistence.
	 *
	 * @param FixedPriceRepository $repository Fixed price meta access.
	 */
	public function __construct(
		private FixedPriceRepository $repository
	) {
	}

	/**
	 * Coverage status for one product/currency pair.
	 *
	 * @param WC_Product $product      Simple or variable product.
	 * @param string     $currency_code Non-base currency code.
	 */
	public function classify( WC_Product $product, string $currency_code ): string {
		$currency_code = strtoupper( $currency_code );

		if ( ! $product->is_type( 'variable' ) ) {
			return $this->has_fixed_regular( $product->get_id(), $currency_code )
				? self::STATUS_FIXED
				: self::STATUS_FX_FALLBACK;
		}

		$population = $this->population( $product );

		if ( array() === $population ) {
			return self::STATUS_NO_PRICEABLE_VARIATIONS;
		}

		$fixed_count = 0;

		foreach ( $population as $variation ) {
			if ( $this->has_fixed_regular( $variation->get_id(), $currency_code ) ) {
				++$fixed_count;
			}
		}

		if ( count( $population ) === $fixed_count ) {
			return self::STATUS_FIXED;
		}

		if ( 0 === $fixed_count ) {
			return self::STATUS_FX_FALLBACK;
		}

		return self::STATUS_PARTIAL;
	}

	/**
	 * The exact set of products a catalog operation must act on for one
	 * top-level catalog entry: the product itself when simple, or its
	 * structurally eligible variations when variable.
	 *
	 * @param WC_Product $product Simple or variable product.
	 * @return array<int, WC_Product> Priceable targets, keyed numerically.
	 */
	public function eligible_targets( WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return array( $product );
		}

		return array_values( $this->population( $product ) );
	}

	/**
	 * The structural variation population for a variable product: variations
	 * that are enabled (WooCommerce's own "Enabled" checkbox, via
	 * `get_status('edit') === 'publish'`) and have an authored native regular
	 * price. Never derived from stock status or `is_purchasable()`.
	 *
	 * @param WC_Product $product Variable product.
	 * @return array<int, WC_Product_Variation> Keyed by variation ID.
	 */
	public function population( WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return array();
		}

		$population = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}

			if ( 'publish' !== $variation->get_status( 'edit' ) ) {
				continue;
			}

			if ( '' === $variation->get_regular_price( 'edit' ) ) {
				continue;
			}

			$population[ $variation_id ] = $variation;
		}

		return $population;
	}

	/**
	 * Whether a fixed regular price is authored for a product/variation ID.
	 *
	 * @param int    $product_id    Product or variation ID.
	 * @param string $currency_code Uppercase currency code.
	 */
	private function has_fixed_regular( int $product_id, string $currency_code ): bool {
		$entry = $this->repository->get( $product_id )->get_currency( $currency_code );

		return null !== $entry && '' !== $entry->regular();
	}
}
