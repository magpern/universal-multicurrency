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
 */
final class ProductSaleStateResolver {

	/**
	 * Whether WooCommerce considers the product on sale right now.
	 *
	 * @param WC_Product $product Product or variation.
	 */
	public function is_on_sale( WC_Product $product ): bool {
		return $product->is_on_sale( 'edit' );
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
