<?php
/**
 * Order line-item pricing provenance writer.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

use UMC\Pricing\ProductPriceProvenanceRegistry;
use UMC\Pricing\ProductPriceResolution;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Writes immutable line-item meta describing fixed vs converted product pricing.
 */
final class LineItemPriceProvenance {

	public const META_SOURCE   = '_umc_line_price_source';
	public const META_CURRENCY = '_umc_line_price_currency';

	/**
	 * Binds the checkout provenance registry.
	 *
	 * @param ProductPriceProvenanceRegistry $provenance Request-scoped resolution map.
	 */
	public function __construct(
		private ProductPriceProvenanceRegistry $provenance
	) {
	}

	/**
	 * Registers checkout hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'write_for_line_item' ), 10, 4 );
	}

	/**
	 * Stages provenance meta on a product line item.
	 *
	 * @param mixed $item          Order line item.
	 * @param mixed $cart_item_key Cart item key.
	 * @param mixed $values        Cart item values.
	 * @param mixed $order         Order being created.
	 */
	public function write_for_line_item( $item, $cart_item_key, $values, $order ): void {
		unset( $cart_item_key, $values );

		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$product_id = (int) $item->get_variation_id();

		if ( $product_id <= 0 ) {
			$product_id = (int) $item->get_product_id();
		}

		$record = $this->provenance->get( $product_id );

		if ( null === $record ) {
			$fallback_currency = $order instanceof WC_Order ? (string) $order->get_currency() : '';
			$record            = array(
				'source'   => ProductPriceResolution::SOURCE_CONVERTED,
				'currency' => $fallback_currency,
			);
		}

		$source = ProductPriceResolution::SOURCE_FIXED === $record['source']
			? ProductPriceResolution::SOURCE_FIXED
			: ProductPriceResolution::SOURCE_CONVERTED;

		$item->add_meta_data( self::META_SOURCE, $source, true );
		$item->add_meta_data( self::META_CURRENCY, $record['currency'], true );
	}
}
