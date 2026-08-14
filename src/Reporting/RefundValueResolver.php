<?php
/**
 * Single authoritative refund value resolver for reporting.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use WC_Order;

/**
 * Uses WooCommerce's aggregated parent-order refund total.
 *
 * {@see WC_Order::get_total_refunded()} sums completed refund objects in the
 * parent order currency and is the sole reporting refund authority (ADR-0026).
 */
final class RefundValueResolver {

	/**
	 * Returns the authoritative refunded value for one order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function refunded_value( WC_Order $order ): float {
		return (float) $order->get_total_refunded();
	}
}
