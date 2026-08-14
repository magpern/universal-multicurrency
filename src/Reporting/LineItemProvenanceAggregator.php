<?php
/**
 * Aggregates immutable line-item pricing provenance.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use UMC\Order\LineItemPriceProvenance;
use UMC\Pricing\ProductPriceResolution;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Aggregates immutable line-item pricing provenance.
 */
final class LineItemProvenanceAggregator {

	/**
	 * Returns product-line pricing sources and totals for one order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return list<array{source: string, total: float}>
	 */
	public function product_line_sources( WC_Order $order ): array {
		$lines = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$raw_source = (string) $item->get_meta( LineItemPriceProvenance::META_SOURCE );
			$source     = ReportingConstants::SOURCE_UNKNOWN;

			if ( ProductPriceResolution::SOURCE_FIXED === $raw_source ) {
				$source = ReportingConstants::SOURCE_FIXED;
			} elseif ( ProductPriceResolution::SOURCE_CONVERTED === $raw_source ) {
				$source = ReportingConstants::SOURCE_CONVERTED;
			}

			$lines[] = array(
				'source' => $source,
				'total'  => (float) $item->get_total(),
			);
		}

		return $lines;
	}
}
