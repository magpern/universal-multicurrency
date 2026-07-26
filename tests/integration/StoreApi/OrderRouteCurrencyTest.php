<?php
/**
 * Integration tests: stored orders served through the Store API order routes.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\Order\OrderSnapshot;
use WC_Order;

/**
 * The order routes serialize stored totals but take their currency identity
 * from the session, so a shopper browsing in one currency could be shown a
 * historical order's amounts labelled with another. These tests cover the lock
 * that makes an order describe itself.
 */
final class OrderRouteCurrencyTest extends StoreApiTestCase {

	private const CURRENCIES = array(
		'SEK' => array(
			'rate'     => '11.50',
			'symbol'   => 'kr',
			'position' => 'right_space',
		),
		'USD' => array( 'rate' => '1.20' ),
	);

	public function test_order_is_reported_in_its_own_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'USD' );
		$order = $this->stored_order( 'SEK', '1150.00' );

		$data = $this->fetch_order( $order );

		$this->assertSame(
			'SEK',
			$data['totals']['currency_code'],
			'A stored order must not be relabelled with the browsing currency.'
		);
		$this->assertSame( '115000', $data['totals']['total_price'], 'Stored totals are served raw.' );
	}

	public function test_order_formatting_follows_the_order_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'USD' );
		$order = $this->stored_order( 'SEK', '1150.00' );

		$totals = $this->fetch_order( $order )['totals'];

		$this->assertSame( 'kr', $totals['currency_symbol'] );
		$this->assertSame( '', $totals['currency_prefix'] );
		$this->assertSame( ' kr', $totals['currency_suffix'], 'Symbol position follows the order, not the store.' );
	}

	public function test_stored_totals_are_never_reconverted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->stored_order( 'SEK', '1150.00' );

		$data = $this->fetch_order( $order );

		// Browsing in the same currency the order used is the case where a
		// stray conversion would be invisible in the currency code alone.
		$this->assertSame( '115000', $data['totals']['total_price'] );
	}

	public function test_order_in_a_since_disabled_currency_still_reports_itself(): void {
		$this->boot_plugin( self::CURRENCIES, 'USD' );
		$order = $this->stored_order( 'NOK', '1200.00' );

		$data = $this->fetch_order( $order );

		$this->assertSame( 'NOK', $data['totals']['currency_code'] );
		$this->assertSame( '120000', $data['totals']['total_price'] );
		$this->assertSame( 2, $data['totals']['currency_minor_unit'], 'Falls back to the ISO decimals.' );
	}

	public function test_context_does_not_leak_into_later_requests(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->stored_order( 'USD', '120.00' );

		$this->fetch_order( $order );

		// The cart that follows belongs to the shopper, not to the order just read.
		$this->store_api_request(
			'POST',
			'/cart/add-item',
			array(
				'id'       => $this->simple_product( '100' )->get_id(),
				'quantity' => 1,
			)
		);

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame( 'SEK', $cart['totals']['currency_code'] );
		$this->assertSame( '115000', $cart['totals']['total_price'] );
	}

	public function test_reading_an_order_does_not_rewrite_its_snapshot(): void {
		$this->boot_plugin( self::CURRENCIES, 'USD' );
		$order = $this->stored_order( 'SEK', '1150.00' );

		$this->fetch_order( $order );

		$reloaded = wc_get_order( $order->get_id() );

		$this->assertSame( 'SEK', $reloaded->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
		$this->assertSame( 'SEK:11.50', $reloaded->get_meta( OrderSnapshot::META_RATE_IDENTITY ) );
	}

	public function test_gateways_are_filtered_by_the_order_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'USD' );
		$order = $this->stored_order( 'SEK', '1150.00' );

		// Only valid for SEK: under the session currency alone this gateway would
		// have been discarded before the order's own currency was considered.
		add_filter(
			'umc_gateway_supported_currencies',
			static function ( $supported, $gateway ) {
				return 'cheque' === $gateway->id ? array( 'SEK' ) : $supported;
			},
			10,
			2
		);

		$available = $this->gateways_during_order_request( $order );

		$this->assertContains( 'cheque', $available, 'The order currency decides, not the session.' );
	}

	public function test_gateway_filtering_returns_to_the_session_after_the_request(): void {
		$this->boot_plugin( self::CURRENCIES, 'USD' );
		$order = $this->stored_order( 'SEK', '1150.00' );

		add_filter(
			'umc_gateway_supported_currencies',
			static function ( $supported, $gateway ) {
				return 'cheque' === $gateway->id ? array( 'SEK' ) : $supported;
			},
			10,
			2
		);

		$this->fetch_order( $order );

		$after = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );

		$this->assertNotContains(
			'cheque',
			$after,
			'Once the order request is over, the session currency governs again.'
		);
	}

	/**
	 * Creates a stored, paid order in a given currency.
	 *
	 * @param string $currency Order currency code.
	 * @param string $total    Order total in that currency.
	 */
	private function stored_order( string $currency, string $total ): WC_Order {
		$order = new WC_Order();
		$order->set_currency( $currency );
		$order->set_total( $total );
		$order->set_status( 'completed' );
		$order->set_created_via( 'store-api' );
		$order->set_billing_email( 'shopper@example.com' );
		$order->update_meta_data( OrderSnapshot::META_TRANSACTION_CURRENCY, $currency );
		$order->update_meta_data( OrderSnapshot::META_BASE_CURRENCY, 'EUR' );
		$order->update_meta_data( OrderSnapshot::META_RATE_IDENTITY, $currency . ':11.50' );
		$order->update_meta_data( OrderSnapshot::META_SNAPSHOT_VERSION, 2 );
		$order->update_meta_data( OrderSnapshot::META_TRANSACTION_DECIMALS, 2 );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Reads an order through the Store API order route.
	 *
	 * @param WC_Order $order Order to fetch.
	 *
	 * @return array<string, mixed>
	 */
	private function fetch_order( WC_Order $order ): array {
		$request = $this->store_api_request(
			'GET',
			'/order/' . $order->get_id(),
			array(),
			array(
				'key'           => $order->get_order_key(),
				'billing_email' => (string) $order->get_billing_email(),
			)
		);

		return $this->response_data( $request );
	}

	/**
	 * Captures the gateways available while an order route request is in flight.
	 *
	 * @param WC_Order $order Order being requested.
	 *
	 * @return array<int, string>
	 */
	private function gateways_during_order_request( WC_Order $order ): array {
		$captured = array();

		add_action(
			'umc_order_currency_context_entered',
			static function () use ( &$captured ): void {
				$captured = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
			}
		);

		$this->fetch_order( $order );

		return $captured;
	}

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
}
