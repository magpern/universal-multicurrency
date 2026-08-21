<?php
/**
 * WooCommerce-native product sale state.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

use WC_Product;

/**
 * Delegates sale activation to WooCommerce (including scheduled sales).
 *
 * Variable parents are special: {@see \WC_Product_Variable::is_on_sale()}
 * ignores the context argument and always calls {@see \WC_Product_Variable::get_variation_prices()},
 * which re-enters {@see \UMC\Integration\PriceHooks} while a parent getter is
 * resolving and can poison `wc_var_prices_*` with base amounts under a foreign
 * currency hash (ADR-0033).
 */
final class ProductSaleStateResolver {

	/**
	 * Whether WooCommerce considers the product on sale right now.
	 *
	 * @param WC_Product $product Product or variation.
	 */
	public function is_on_sale( WC_Product $product ): bool {
		if ( $product->is_type( 'variable' ) ) {
			return $this->variable_has_active_sale_variation( $product );
		}

		return $product->is_on_sale( 'edit' );
	}

	/**
	 * Sale state for a variable parent without calling get_variation_prices().
	 *
	 * Mirrors the spirit of WooCommerce's variable on-sale check by inspecting
	 * each variation in edit context only.
	 *
	 * @param WC_Product $product Variable parent product.
	 */
	private function variable_has_active_sale_variation( WC_Product $product ): bool {
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( (int) $child_id );

			if ( $child instanceof WC_Product && $child->is_on_sale( 'edit' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Token for variation price cache identity.
	 *
	 * @param WC_Product $product Product or variation.
	 */
	public function cache_token( WC_Product $product ): string {
		if ( ! $this->is_on_sale( $product ) ) {
			return 'not_on_sale';
		}

		$from = $product->get_date_on_sale_from( 'edit' );
		$to   = $product->get_date_on_sale_to( 'edit' );

		return sprintf(
			'on_sale:%s:%s',
			null !== $from ? (string) $from->getTimestamp() : '0',
			null !== $to ? (string) $to->getTimestamp() : '0'
		);
	}
}
