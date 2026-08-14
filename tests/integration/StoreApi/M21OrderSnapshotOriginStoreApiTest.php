<?php
/**
 * Store API integration: schema-5 origin capture at block checkout.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\StoreApi;

use UMC\CurrencySwitcher;
use UMC\Order\OrderSnapshot;
use WC_Order;

/**
 * Verifies Store API checkout writes the same factual origin metadata as classic checkout.
 */
final class M21OrderSnapshotOriginStoreApiTest extends StoreApiTestCase {

	private const CURRENCIES = array(
		'SEK' => array( 'rate' => '11.50' ),
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
		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, null );

		parent::tear_down();
	}

	public function test_block_checkout_persists_customer_origin_and_schema_five(): void {
		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, CurrencySwitcher::ORIGIN_CUSTOMER );

		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$order = $this->place_order();

		$this->assertSame( OrderSnapshot::SCHEMA_VERSION, (int) $order->get_meta( OrderSnapshot::META_SNAPSHOT_VERSION ) );
		$this->assertSame( CurrencySwitcher::ORIGIN_CUSTOMER, $order->get_meta( OrderSnapshot::META_CURRENCY_ORIGIN ) );
	}

	/**
	 * Places a completed Store API checkout order.
	 */
	private function place_order(): WC_Order {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Store API product' );
		$product->set_regular_price( '100' );
		$product->save();

		$this->store_api_request(
			'POST',
			'/cart/add-item',
			array(
				'id'       => $product->get_id(),
				'quantity' => 1,
			)
		);

		$response = $this->store_api_request(
			'POST',
			'/checkout',
			array(
				'billing_address'  => $this->address(),
				'shipping_address' => $this->address(),
				'payment_method'   => 'cheque',
			)
		);

		$data  = $response->get_data();
		$order = wc_get_order( $data['order_id'] ?? 0 );

		$this->assertInstanceOf( WC_Order::class, $order );

		return $order;
	}

	/**
	 * @return array<string, string>
	 */
	private function address(): array {
		return array(
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'address_1'  => '1 Test Street',
			'city'       => 'Stockholm',
			'postcode'   => '11122',
			'country'    => 'SE',
			'email'      => 'test@example.com',
		);
	}
}
