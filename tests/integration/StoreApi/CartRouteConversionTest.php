<?php
/**
 * Integration tests: the Store API cart lifecycle in the selected currency.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

/**
 * Drives the real cart routes rather than WC_Cart directly, so the assertions
 * cover the whole path a Cart block takes: session load, recalculation, and
 * schema serialization into minor units.
 */
final class CartRouteConversionTest extends StoreApiTestCase {

	private const CURRENCIES = array( 'SEK' => array( 'rate' => '11.50' ) );

	public function test_added_item_is_priced_in_the_active_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$cart = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product->get_id(),
					'quantity' => 2,
				)
			)
		);

		$this->assertSame( 'SEK', $cart['totals']['currency_code'] );
		$this->assertSame( '115000', $cart['items'][0]['prices']['price'] );
		$this->assertSame( '230000', $cart['items'][0]['totals']['line_total'] );
		$this->assertSame( '230000', $cart['totals']['total_price'] );
	}

	public function test_quantity_update_recalculates_in_the_active_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$key = $this->add_item( $this->simple_product( '100' )->get_id(), 1 );

		$cart = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/update-item',
				array(
					'key'      => $key,
					'quantity' => 3,
				)
			)
		);

		$this->assertSame( '345000', $cart['totals']['total_price'] );
	}

	public function test_removing_an_item_empties_the_totals(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$key = $this->add_item( $this->simple_product( '100' )->get_id(), 1 );

		$cart = $this->response_data(
			$this->store_api_request( 'POST', '/cart/remove-item', array( 'key' => $key ) )
		);

		$this->assertSame( array(), $cart['items'] );
		$this->assertSame( '0', $cart['totals']['total_price'] );
	}

	public function test_repeated_cart_reads_do_not_convert_again(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->add_item( $this->simple_product( '100' )->get_id(), 1 );

		$first  = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );
		$second = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );
		$third  = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame( '115000', $first['totals']['total_price'] );
		$this->assertSame( $first['totals'], $second['totals'] );
		$this->assertSame( $first['totals'], $third['totals'] );
	}

	public function test_cart_restored_from_session_stays_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->add_item( $this->simple_product( '100' )->get_id(), 2 );

		// What the next request sees: a new cart hydrated from the stored session.
		// Driving WC_Cart_Session directly is necessary because the in-process
		// did_action() guards would otherwise skip the reload.
		WC()->cart = new \WC_Cart();
		( new \WC_Cart_Session( WC()->cart ) )->get_cart_from_session();

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame( '230000', $cart['totals']['total_price'] );
	}

	public function test_base_currency_cart_is_untouched(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->add_item( $this->simple_product( '100' )->get_id(), 2 );

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame( 'EUR', $cart['totals']['currency_code'] );
		$this->assertSame( '20000', $cart['totals']['total_price'] );
	}

	public function test_fixed_cart_coupon_amount_is_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->make_coupon( 'fixed10', 'fixed_cart', '10' );
		$this->add_item( $this->simple_product( '100' )->get_id(), 1 );

		$cart = $this->apply_coupon( 'fixed10' );

		// 10 EUR off becomes 115 SEK off: 1150 - 115 = 1035.
		$this->assertSame( '11500', $cart['totals']['total_discount'] );
		$this->assertSame( '103500', $cart['totals']['total_price'] );
	}

	public function test_fixed_product_coupon_amount_is_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->make_coupon( 'fixedprod', 'fixed_product', '10' );
		$this->add_item( $this->simple_product( '100' )->get_id(), 2 );

		$cart = $this->apply_coupon( 'fixedprod' );

		// 10 EUR per item becomes 115 SEK per item, twice.
		$this->assertSame( '23000', $cart['totals']['total_discount'] );
	}

	public function test_percentage_coupon_is_not_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->make_coupon( 'tenpct', 'percent', '10' );
		$this->add_item( $this->simple_product( '100' )->get_id(), 1 );

		$cart = $this->apply_coupon( 'tenpct' );

		// A ratio has no currency: 10% of 1150 SEK is 115 SEK.
		$this->assertSame( '11500', $cart['totals']['total_discount'] );
		$this->assertSame( '103500', $cart['totals']['total_price'] );
	}

	public function test_minimum_spend_threshold_is_evaluated_in_the_active_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->make_coupon( 'min100', 'fixed_cart', '10', array( 'minimum_amount' => 100.0 ) );

		// 100 EUR of stock is exactly the converted 1150 SEK threshold.
		$this->add_item( $this->simple_product( '100' )->get_id(), 1 );

		$cart = $this->apply_coupon( 'min100' );

		$this->assertSame( array( 'min100' ), wp_list_pluck( $cart['coupons'], 'code' ) );
	}

	public function test_minimum_spend_threshold_still_rejects_a_small_cart(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->make_coupon( 'min100', 'fixed_cart', '10', array( 'minimum_amount' => 100.0 ) );
		$this->add_item( $this->simple_product( '50' )->get_id(), 1 );

		$response = $this->store_api_request( 'POST', '/cart/apply-coupon', array( 'code' => 'min100' ) );

		$this->assertGreaterThanOrEqual(
			400,
			$response->get_status(),
			'A 575 SEK cart is below the converted 1150 SEK minimum.'
		);
	}

	public function test_coupon_can_be_removed_and_reapplied(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->make_coupon( 'fixed10', 'fixed_cart', '10' );
		$this->add_item( $this->simple_product( '100' )->get_id(), 1 );

		$this->apply_coupon( 'fixed10' );

		$removed = $this->response_data(
			$this->store_api_request( 'POST', '/cart/remove-coupon', array( 'code' => 'fixed10' ) )
		);

		$this->assertSame( '0', $removed['totals']['total_discount'] );
		$this->assertSame( '115000', $removed['totals']['total_price'] );

		$reapplied = $this->apply_coupon( 'fixed10' );

		$this->assertSame( '11500', $reapplied['totals']['total_discount'], 'Reapplying must not compound.' );
		$this->assertSame( '103500', $reapplied['totals']['total_price'] );
	}

	/**
	 * Adds a product through the Store API and returns its cart item key.
	 *
	 * @param int $product_id Product to add.
	 * @param int $quantity   Quantity to add.
	 */
	private function add_item( int $product_id, int $quantity ): string {
		$cart = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product_id,
					'quantity' => $quantity,
				)
			)
		);

		return (string) $cart['items'][0]['key'];
	}

	/**
	 * Applies a coupon through the Store API and returns the cart response.
	 *
	 * @param string $code Coupon code.
	 *
	 * @return array<string, mixed>
	 */
	private function apply_coupon( string $code ): array {
		return $this->response_data(
			$this->store_api_request( 'POST', '/cart/apply-coupon', array( 'code' => $code ) )
		);
	}
}
