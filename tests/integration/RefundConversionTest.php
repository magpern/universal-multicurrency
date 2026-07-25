<?php
/**
 * Integration tests: refunds stay in the parent order currency.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\CurrencyContext;
use UMC\Order\OrderSnapshot;
use UMC\Order\OrderSnapshotReader;
use UMC\Order\RefundSnapshot;
use UMC\Settings;
use WC_Order;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Verifies WooCommerce creates refunds in the parent currency, the entered
 * amounts are stored unchanged (no conversion), reconciliation never drifts, and
 * the M4 audit metadata links each refund to its parent snapshot — under HPOS,
 * across rate edits and session switches.
 */
final class RefundConversionTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_create_refund',
		'umc_refund_snapshot_created',
	);

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );

		// Exactly one refund-snapshot writer, isolated from the booted instance.
		remove_all_filters( 'woocommerce_create_refund' );
		( new RefundSnapshot( new OrderSnapshotReader() ) )->register();
	}

	public function tear_down(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	// -- Currency & amount preservation --------------------------------------

	public function test_full_refund_inherits_parent_currency(): void {
		$parent = $this->create_parent_order( 'SEK', '100.00', 'SEK', '11.50' );

		$refund = $this->refund( $parent, '100.00' );

		$this->assertSame( 'SEK', $refund->get_currency() );
		$this->assertSame( '100.00', $refund->get_amount() );
	}

	public function test_partial_refund_amount_is_stored_unchanged(): void {
		$parent = $this->create_parent_order( 'JPY', '2500', 'JPY', '0.0064' );

		$refund = $this->refund( $parent, '500' );

		$this->assertSame( 'JPY', $refund->get_currency() );
		// Stored exactly as entered — never multiplied by any rate.
		$this->assertSame( '500', $refund->get_amount() );
	}

	public function test_line_level_refund_stays_in_parent_currency(): void {
		$parent = $this->create_parent_order_with_product( 'SEK', '20.00' );
		$items  = $parent->get_items();
		$item   = reset( $items );

		$refund = $this->refund(
			$parent,
			'20.00',
			array(
				(int) $item->get_id() => array(
					'qty'          => 1,
					'refund_total' => '20.00',
				),
			)
		);

		$this->assertSame( 'SEK', $refund->get_currency() );
		$this->assertSame( '20.00', $refund->get_amount() );
	}

	// -- Audit metadata ------------------------------------------------------

	public function test_refund_records_parent_currency_and_rate_identity(): void {
		$parent = $this->create_parent_order( 'SEK', '100.00', 'SEK', '11.50' );

		$refund   = $this->refund( $parent, '25.00' );
		$reloaded = wc_get_order( $refund->get_id() );

		$this->assertSame( 'SEK', $reloaded->get_meta( '_umc_parent_transaction_currency' ) );
		$this->assertSame( 'SEK:11.50', $reloaded->get_meta( '_umc_parent_rate_identity' ) );
	}

	public function test_legacy_parent_without_snapshot_still_refundable(): void {
		// No _umc_* snapshot on the parent (pre-M3 order).
		$parent = new WC_Order();
		$parent->set_currency( 'SEK' );
		$parent->set_total( '40.00' );
		$parent->save();

		$refund = $this->refund( $parent, '15.00' );

		$this->assertSame( 'SEK', $refund->get_currency() );
		$this->assertSame( '15.00', $refund->get_amount() );
		// Audit currency falls back to the parent order currency; identity is absent.
		$reloaded = wc_get_order( $refund->get_id() );
		$this->assertSame( 'SEK', $reloaded->get_meta( '_umc_parent_transaction_currency' ) );
		$this->assertSame( '', (string) $reloaded->get_meta( '_umc_parent_rate_identity' ) );
	}

	// -- Reconciliation ------------------------------------------------------

	public function test_multiple_partial_refunds_reconcile_without_drift(): void {
		$parent = $this->create_parent_order( 'SEK', '100.00', 'SEK', '11.50' );

		$this->refund( $parent, '30.00' );
		$this->refund( $parent, '20.50' );

		$reloaded = wc_get_order( $parent->get_id() );

		// parent − refunds == remaining, with no rounding drift.
		$this->assertEqualsWithDelta( 50.50, (float) $reloaded->get_total_refunded(), 0.0001 );
		$this->assertEqualsWithDelta( 49.50, (float) $reloaded->get_remaining_refund_amount(), 0.0001 );

		// Repeat reads never drift.
		$again = wc_get_order( $parent->get_id() );
		$this->assertEqualsWithDelta( 50.50, (float) $again->get_total_refunded(), 0.0001 );
	}

	public function test_zero_decimal_reconciliation_has_no_drift(): void {
		$parent = $this->create_parent_order( 'JPY', '2500', 'JPY', '0.0064' );

		$this->refund( $parent, '500' );
		$this->refund( $parent, '1000' );

		$reloaded = wc_get_order( $parent->get_id() );
		$this->assertEqualsWithDelta( 1500.0, (float) $reloaded->get_total_refunded(), 0.0001 );
		$this->assertEqualsWithDelta( 1000.0, (float) $reloaded->get_remaining_refund_amount(), 0.0001 );
	}

	// -- Immutability across environment changes -----------------------------

	public function test_rate_change_after_order_does_not_affect_refunds(): void {
		$parent = $this->create_parent_order( 'SEK', '100.00', 'SEK', '11.50' );
		$refund = $this->refund( $parent, '40.00' );

		// Change the live SEK rate afterwards.
		( new Settings() )->save( array( 'currencies' => array( 'SEK' => array( 'rate' => '99.00' ) ) ) );

		$reloaded = wc_get_order( $refund->get_id() );
		$this->assertSame( 'SEK', $reloaded->get_currency() );
		$this->assertSame( '40.00', $reloaded->get_amount() );
		$this->assertSame( 'SEK:11.50', $reloaded->get_meta( '_umc_parent_rate_identity' ) );
	}

	public function test_session_switch_does_not_affect_refunds(): void {
		$parent = $this->create_parent_order( 'SEK', '100.00', 'SEK', '11.50' );

		// A different storefront currency is selected at refund time.
		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = 'JPY';

		$refund = $this->refund( $parent, '10.00' );

		$this->assertSame( 'SEK', $refund->get_currency() );
		$this->assertSame( '10.00', $refund->get_amount() );
	}

	public function test_refund_audit_meta_is_stable_across_reads(): void {
		$parent = $this->create_parent_order( 'SEK', '100.00', 'SEK', '11.50' );
		$refund = $this->refund( $parent, '10.00' );

		$first  = wc_get_order( $refund->get_id() )->get_meta( '_umc_parent_rate_identity' );
		$second = wc_get_order( $refund->get_id() )->get_meta( '_umc_parent_rate_identity' );

		$this->assertSame( 'SEK:11.50', $first );
		$this->assertSame( $first, $second );
	}

	// -- Helpers -------------------------------------------------------------

	/**
	 * Creates a saved parent order carrying an M4 snapshot.
	 *
	 * @param string $currency      Order currency code.
	 * @param string $total         Order total as a decimal string.
	 * @param string $transaction   Snapshot transaction currency.
	 * @param string $rate          Snapshot exchange rate string.
	 */
	private function create_parent_order( string $currency, string $total, string $transaction, string $rate ): WC_Order {
		$order = new WC_Order();
		$order->set_currency( $currency );
		$order->set_total( $total );

		$meta = OrderSnapshot::snapshot_meta(
			'EUR',
			$transaction,
			$rate,
			1_700_000_000,
			OrderSnapshot::SOURCE_MANUAL,
			'0.4.0',
			$transaction . ':' . $rate,
			2,
			'JPY' === $transaction ? 0 : 2
		);

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( (string) $key, $value );
		}

		$order->save();

		return $order;
	}

	/**
	 * Creates a saved parent order with one product line item.
	 *
	 * @param string $currency Order currency code.
	 * @param string $price    Unit price as a decimal string.
	 */
	private function create_parent_order_with_product( string $currency, string $price ): WC_Order {
		$product = new WC_Product_Simple();
		$product->set_regular_price( $price );
		$product->set_price( $price );
		$product->save();

		$order = new WC_Order();
		$order->set_currency( $currency );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * Creates a refund on a parent order and returns it.
	 *
	 * @param WC_Order                         $order      Parent order.
	 * @param string                           $amount     Refund amount as a string.
	 * @param array<int, array<string, mixed>> $line_items Optional line-item refunds.
	 * @return \WC_Order_Refund
	 */
	private function refund( WC_Order $order, string $amount, array $line_items = array() ): \WC_Order_Refund {
		$refund = wc_create_refund(
			array(
				'order_id'   => $order->get_id(),
				'amount'     => $amount,
				'line_items' => $line_items,
			)
		);

		$this->assertInstanceOf( \WC_Order_Refund::class, $refund );

		return $refund;
	}
}
