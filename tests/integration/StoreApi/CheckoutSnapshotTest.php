<?php
/**
 * Integration tests: the currency snapshot on Store API checkout orders.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\Order\OrderSnapshot;
use WC_Order;

/**
 * Store API checkout builds orders through its own controller and never fires
 * the classic creation hook, so without an adapter a block order would carry no
 * snapshot at all. These tests cover the write, the narrow refresh window while
 * an order is still unpaid, and the permanence that begins at payment.
 */
final class CheckoutSnapshotTest extends StoreApiTestCase {

	private const CURRENCIES = array(
		'SEK' => array( 'rate' => '11.50' ),
		'USD' => array( 'rate' => '1.20' ),
	);

	public function set_up(): void {
		parent::set_up();

		update_option(
			'woocommerce_cheque_settings',
			array(
				'enabled' => 'yes',
				'title'   => 'Cheque',
			)
		);

		WC()->payment_gateways()->init();
	}

	public function tear_down(): void {
		delete_option( 'woocommerce_cheque_settings' );
		WC()->payment_gateways()->init();

		parent::tear_down();
	}

	public function test_block_checkout_order_receives_a_snapshot(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->place_order();

		$this->assertSame( 'SEK', $order->get_currency(), 'WooCommerce stamps the transaction currency.' );
		$this->assertSame( 'SEK', $order->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
		$this->assertSame( 'EUR', $order->get_meta( OrderSnapshot::META_BASE_CURRENCY ) );
		$this->assertSame( '11.50', $order->get_meta( OrderSnapshot::META_EXCHANGE_RATE ) );
		$this->assertSame( 'SEK:11.50', $order->get_meta( OrderSnapshot::META_RATE_IDENTITY ) );
		$this->assertSame( 'manual', $order->get_meta( OrderSnapshot::META_RATE_SOURCE ) );
		$this->assertSame( self::PLUGIN_VERSION, $order->get_meta( OrderSnapshot::META_PLUGIN_VERSION ) );
	}

	public function test_snapshot_is_schema_version_two(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->place_order();

		$this->assertSame( 2, (int) $order->get_meta( OrderSnapshot::META_SNAPSHOT_VERSION ) );
		$this->assertSame( 2, (int) $order->get_meta( OrderSnapshot::META_TRANSACTION_DECIMALS ) );
	}

	public function test_snapshot_survives_a_reload_from_storage(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order_id = $this->place_order()->get_id();

		// Reading the order back proves the adapter staged meta that WooCommerce
		// then persisted, rather than leaving it on an in-memory object.
		$reloaded = wc_get_order( $order_id );

		$this->assertInstanceOf( WC_Order::class, $reloaded );
		$this->assertSame( 'SEK', $reloaded->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
		$this->assertSame( 'store-api', $reloaded->get_created_via() );
	}

	public function test_order_totals_match_the_converted_cart(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->place_order( 2 );

		$this->assertSame( '2300.00', $order->get_total() );
	}

	public function test_base_currency_order_records_a_unit_rate(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$order = $this->place_order();

		$this->assertSame( 'EUR', $order->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
		$this->assertSame( '1', $order->get_meta( OrderSnapshot::META_EXCHANGE_RATE ) );
	}

	public function test_zero_decimal_currency_records_its_own_decimals(): void {
		$this->boot_plugin(
			array(
				'JPY' => array(
					'rate'     => '160',
					'decimals' => 0,
				),
			),
			'JPY'
		);

		$order = $this->place_order();

		$this->assertSame( 'JPY', $order->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
		$this->assertSame( 0, (int) $order->get_meta( OrderSnapshot::META_TRANSACTION_DECIMALS ) );
	}

	public function test_unpaid_draft_snapshot_follows_a_currency_change(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->draft_order();

		$this->assertSame( 'SEK', $order->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );

		$refreshed = $this->retry_after_switch( $order, 'USD' );

		$this->assertSame( 'USD', $refreshed->get_currency() );
		$this->assertSame(
			'USD',
			$refreshed->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ),
			'A persisted order must never contradict its own snapshot.'
		);
		$this->assertSame( 'USD:1.20', $refreshed->get_meta( OrderSnapshot::META_RATE_IDENTITY ) );
	}

	public function test_refreshing_an_unpaid_draft_fires_an_action(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->draft_order();

		$fired = 0;
		add_action(
			'umc_order_snapshot_refreshed',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$this->retry_after_switch( $order, 'USD' );

		$this->assertSame( 1, $fired );
	}

	public function test_unchanged_currency_does_not_rewrite_the_snapshot(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->draft_order();

		$fired = 0;
		add_action(
			'umc_order_snapshot_refreshed',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$this->replay_cart_resync( $order );

		$this->assertSame( 0, $fired, 'Routine cart updates must not rewrite the snapshot.' );
	}

	public function test_paid_order_snapshot_is_permanent(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->place_order();

		$order->set_status( 'processing' );
		$order->set_date_paid( time() );
		$order->save();

		$this->switch_currency( 'USD' );

		$this->replay_cart_resync( $order );
		$this->replay_checkout_meta( $order );

		$reloaded = wc_get_order( $order->get_id() );

		$this->assertSame(
			'SEK',
			$reloaded->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ),
			'Once payment has begun the snapshot is history and must not move.'
		);
		$this->assertSame( 'SEK:11.50', $reloaded->get_meta( OrderSnapshot::META_RATE_IDENTITY ) );
	}

	public function test_orders_from_other_sources_are_never_refreshed(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$order = new WC_Order();
		$order->set_created_via( 'checkout' );
		$order->set_status( 'pending' );
		$order->set_currency( 'SEK' );
		$order->update_meta_data( OrderSnapshot::META_TRANSACTION_CURRENCY, 'SEK' );
		$order->update_meta_data( OrderSnapshot::META_RATE_IDENTITY, 'SEK:11.50' );
		$order->save();

		$this->switch_currency( 'USD' );

		$this->replay_cart_resync( $order );

		$this->assertSame(
			'SEK',
			wc_get_order( $order->get_id() )->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ),
			'A classic-checkout order is not the adapter\'s to touch.'
		);
	}

	/**
	 * Places a completed Store API checkout order.
	 *
	 * @param int $quantity Quantity of the single line item.
	 */
	private function place_order( int $quantity = 1 ): WC_Order {
		$this->add_item( $quantity );

		$response = $this->store_api_request(
			'POST',
			'/checkout',
			array(
				'billing_address'  => $this->address(),
				'shipping_address' => $this->address(),
				'payment_method'   => 'cheque',
			)
		);

		$data = $this->response_data( $response );

		$order = wc_get_order( $data['order_id'] );

		$this->assertInstanceOf( WC_Order::class, $order );

		return $order;
	}

	/**
	 * Creates an unpaid Store API draft order, as a failed payment would leave.
	 */
	private function draft_order(): WC_Order {
		$order = $this->place_order();

		$order->set_status( 'failed' );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Switches currency and replays the draft re-sync WooCommerce performs.
	 *
	 * @param WC_Order $order Draft order being retried.
	 * @param string   $code  Currency the shopper switched to.
	 */
	private function retry_after_switch( WC_Order $order, string $code ): WC_Order {
		$this->switch_currency( $code );

		// What a mutating cart request does to a live draft: re-sync from the
		// cart, which restamps currency and totals, then notify extensions.
		$order->set_currency( $code );
		$order->save();

		$this->replay_cart_resync( $order );

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Replays the draft re-sync WooCommerce performs on a mutating cart request.
	 *
	 * @param WC_Order $order Draft order being re-synced.
	 */
	private function replay_cart_resync( WC_Order $order ): void {
		do_action( 'woocommerce_store_api_cart_update_order_from_request', $order, null );
	}

	/**
	 * Replays the checkout meta hook WooCommerce fires while building an order.
	 *
	 * @param WC_Order $order Order being built.
	 */
	private function replay_checkout_meta( WC_Order $order ): void {
		do_action( 'woocommerce_store_api_checkout_update_order_meta', $order );
	}

	/**
	 * Adds a product to the cart through the Store API.
	 *
	 * @param int $quantity Quantity to add.
	 */
	private function add_item( int $quantity ): void {
		$this->store_api_request(
			'POST',
			'/cart/add-item',
			array(
				'id'       => $this->simple_product( '100' )->get_id(),
				'quantity' => $quantity,
			)
		);
	}

	/**
	 * A complete address accepted by checkout validation.
	 *
	 * @return array<string, string>
	 */
	private function address(): array {
		return array(
			'first_name' => 'Test',
			'last_name'  => 'Shopper',
			'address_1'  => '1 Test Street',
			'city'       => 'Stockholm',
			'postcode'   => '11122',
			'country'    => 'SE',
			'email'      => 'shopper@example.com',
			'phone'      => '0700000000',
		);
	}
}
