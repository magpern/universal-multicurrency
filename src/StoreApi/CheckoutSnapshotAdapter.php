<?php
/**
 * Store API checkout adapter for the order currency snapshot.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\StoreApi;

use UMC\Order\OrderSnapshot;
use WC_Order;

/**
 * Runs the order snapshot writer at the right points in the Store API's
 * checkout lifecycle.
 *
 * The Store API does not use `WC_Checkout`, so `woocommerce_checkout_create_order`
 * never fires for it and an order placed through the Checkout block would carry
 * no snapshot at all. This adapter supplies the missing timing. It owns *when*
 * the snapshot is written and whether an existing one may be refreshed; the
 * metadata itself, and the sole authority to write it, stay in
 * {@see OrderSnapshot}.
 *
 * Refreshing exists because Store API checkout reuses a draft order. After a
 * failed payment the draft persists, and every subsequent mutating cart request
 * re-syncs it from the cart — restamping its currency and totals. A shopper who
 * changes currency mid-retry would otherwise leave behind a persisted order
 * whose snapshot describes a currency the order no longer has. Refreshing is
 * confined to orders that are still unpaid: once payment has begun the snapshot
 * is permanent, which is the guarantee historical orders and refunds rely on.
 */
final class CheckoutSnapshotAdapter {

	/**
	 * Order statuses that may still have their snapshot refreshed.
	 *
	 * `on-hold` is excluded deliberately: the gateway has acknowledged the
	 * payment intent even though no money has moved yet.
	 *
	 * @var array<int, string>
	 */
	private const REFRESHABLE_STATUSES = array( 'checkout-draft', 'pending', 'failed' );

	/**
	 * Snapshot writer.
	 *
	 * @var OrderSnapshot
	 */
	private OrderSnapshot $snapshot;

	/**
	 * Binds the adapter to the snapshot writer.
	 *
	 * @param OrderSnapshot $snapshot Snapshot writer.
	 */
	public function __construct( OrderSnapshot $snapshot ) {
		$this->snapshot = $snapshot;
	}

	/**
	 * Registers the two Store API lifecycle hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'stage_snapshot' ), 10, 1 );
		add_action( 'woocommerce_store_api_cart_update_order_from_request', array( $this, 'refresh_draft_snapshot' ), 10, 1 );
	}

	/**
	 * Stages the snapshot while the checkout order is being built.
	 *
	 * WooCommerce saves the order after this action, so nothing is saved here —
	 * the same contract the classic checkout hook has.
	 *
	 * @param mixed $order Order being created or updated.
	 */
	public function stage_snapshot( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->snapshot->write_snapshot_for( $order, $this->is_refreshable( $order ) );
	}

	/**
	 * Realigns an unpaid draft's snapshot after the cart re-stamped its currency.
	 *
	 * Unlike the hook above, this one fires *after* WooCommerce has already saved
	 * the draft, so a change made here has to be saved explicitly or it is lost.
	 * The writer only reports a change when the currency or rate actually moved,
	 * so the ordinary cart update saves nothing.
	 *
	 * @param mixed $order Draft order re-synced from the cart.
	 */
	public function refresh_draft_snapshot( $order ): void {
		if ( ! $order instanceof WC_Order || ! $this->is_refreshable( $order ) ) {
			return;
		}

		if ( $this->snapshot->write_snapshot_for( $order, true ) ) {
			$order->save();
		}
	}

	/**
	 * Whether an order's snapshot may still be rewritten.
	 *
	 * @param WC_Order $order Order to inspect.
	 */
	private function is_refreshable( WC_Order $order ): bool {
		if ( 'store-api' !== $order->get_created_via() ) {
			return false;
		}

		if ( ! $order->has_status( self::REFRESHABLE_STATUSES ) ) {
			return false;
		}

		return ! $order->is_paid() && null === $order->get_date_paid();
	}
}
