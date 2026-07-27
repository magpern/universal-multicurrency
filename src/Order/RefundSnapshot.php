<?php
/**
 * Refund parent-currency metadata.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

/**
 * Writes audit metadata to refunds, linking them to their parent order's currency snapshot.
 *
 * When a refund is created, this class writes `_umc_parent_transaction_currency`
 * and `_umc_parent_rate_identity` to the refund's metadata, read from the parent
 * order's snapshot. These are write-once (never updated) and exist only for audit
 * purposes — they do not affect refund amount calculation or formatting.
 *
 * Refund amounts and formatting already use the parent order's currency natively
 * (WC_Order_Refund::get_currency() returns the parent's). This class adds context
 * linking refunds to the M4 snapshot metadata for completeness.
 *
 * No amounts are read or written; no conversion occurs.
 */
final class RefundSnapshot {

	public const META_PARENT_TRANSACTION_CURRENCY = '_umc_parent_transaction_currency';
	public const META_PARENT_RATE_IDENTITY        = '_umc_parent_rate_identity';

	/**
	 * Snapshot reader.
	 *
	 * @var OrderSnapshotReader
	 */
	private OrderSnapshotReader $reader;

	/**
	 * Binds the refund handler to the reader.
	 *
	 * @param OrderSnapshotReader $reader Snapshot reader.
	 */
	public function __construct( OrderSnapshotReader $reader ) {
		$this->reader = $reader;
	}

	/**
	 * Registers the refund creation hook.
	 */
	public function register(): void {
		add_action( 'woocommerce_create_refund', array( $this, 'write_refund_metadata' ), 10, 2 );
	}

	/**
	 * Writes refund metadata on creation.
	 *
	 * @param mixed $refund Refund being created.
	 * @param mixed $args   Arguments passed to wc_create_refund().
	 */
	public function write_refund_metadata( $refund, $args = array() ): void {
		unset( $args );

		if ( ! $refund instanceof \WC_Order_Refund ) {
			return;
		}

		// Get the parent order.
		$parent_id = $refund->get_parent_id();
		if ( ! $parent_id ) {
			return;
		}

		$parent = wc_get_order( $parent_id );
		if ( ! $parent instanceof \WC_Order ) {
			return;
		}

		// Read the parent snapshot.
		$snapshot = $this->reader->read( $parent );

		// Build and write refund metadata. Legacy parents carry no snapshot
		// currency, so the parent's own order currency is the audit fallback.
		$meta = self::refund_meta( $snapshot, $parent->get_currency() );

		foreach ( $meta as $key => $value ) {
			$refund->update_meta_data( (string) $key, $value );
		}

		/**
		 * Fires after refund metadata has been staged.
		 *
		 * @since 0.4.0
		 *
		 * @param \WC_Order_Refund      $refund  Refund being created.
		 * @param array<string, scalar> $meta    Metadata written.
		 * @param OrderCurrencySnapshot $snapshot Parent order's snapshot.
		 */
		do_action( 'umc_refund_snapshot_created', $refund, $meta, $snapshot );
	}

	/**
	 * Builds refund metadata from the parent order's snapshot.
	 *
	 * Pure and WordPress-free for unit testability. The parent transaction
	 * currency falls back to the parent order's own currency when the snapshot
	 * has none (legacy/pre-M3 parents), so the audit trail is always populated.
	 * The rate identity has no such fallback: a legacy parent simply has none.
	 *
	 * @param OrderCurrencySnapshot $snapshot        Parent order's snapshot.
	 * @param string                $parent_currency Parent order currency fallback.
	 * @return array<string, scalar|null>
	 */
	public static function refund_meta( OrderCurrencySnapshot $snapshot, string $parent_currency = '' ): array {
		$currency = $snapshot->transaction_currency();

		if ( null === $currency && '' !== $parent_currency ) {
			$currency = $parent_currency;
		}

		return array(
			self::META_PARENT_TRANSACTION_CURRENCY => $currency,
			self::META_PARENT_RATE_IDENTITY        => $snapshot->rate_identity(),
		);
	}
}
