<?php
/**
 * Integration tests: switching currency while a block cart already exists.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\Cart\CartRecalculation;

/**
 * The switcher reloads the page, so every block refetches its cart. What has
 * to hold on the server is that the refetched cart is recalculated from base
 * prices rather than from the amounts already shown, and that switching back
 * lands exactly where it started.
 */
final class CartCurrencySwitchTest extends StoreApiTestCase {

	private const CURRENCIES = array(
		'SEK' => array( 'rate' => '11.50' ),
		'USD' => array( 'rate' => '1.20' ),
	);

	public function test_switch_recalculates_the_existing_cart(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->add_item( 2 );

		$this->assertSame( '20000', $this->cart_total() );

		$this->switch_currency( 'SEK' );

		$this->assertSame( '230000', $this->cart_total() );
		$this->assertSame( 'SEK', $this->cart_currency() );
	}

	public function test_switch_marks_the_new_rate_identity(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->add_item( 1 );

		$this->switch_currency( 'SEK' );
		$this->store_api_request( 'GET', '/cart' );

		$this->assertSame( 'SEK:11.50', WC()->session->get( CartRecalculation::SESSION_KEY ) );
	}

	public function test_switching_back_restores_the_original_totals(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->add_item( 3 );

		$original = $this->cart_total();

		$this->switch_currency( 'SEK' );
		$this->cart_total();

		$this->switch_currency( 'EUR' );

		$this->assertSame(
			$original,
			$this->cart_total(),
			'Totals are re-derived from base prices, so a round trip cannot drift.'
		);
	}

	public function test_switching_between_two_non_base_currencies_does_not_compound(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->add_item( 1 );

		$this->assertSame( '115000', $this->cart_total() );

		$this->switch_currency( 'USD' );

		// 100 EUR * 1.20, not 1150 SEK * 1.20.
		$this->assertSame( '12000', $this->cart_total() );
	}

	public function test_switch_after_applying_a_coupon_converts_both(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->make_coupon( 'fixed10', 'fixed_cart', '10' );
		$this->add_item( 1 );
		$this->store_api_request( 'POST', '/cart/apply-coupon', array( 'code' => 'fixed10' ) );

		$this->assertSame( '9000', $this->cart_total(), '100 - 10 EUR.' );

		$this->switch_currency( 'SEK' );

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame( '11500', $cart['totals']['total_discount'], 'The coupon converts too.' );
		$this->assertSame( '103500', $cart['totals']['total_price'] );
		$this->assertSame( array( 'fixed10' ), wp_list_pluck( $cart['coupons'], 'code' ), 'The coupon stays applied.' );
	}

	public function test_cart_contents_survive_the_switch(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->add_item( 2 );

		$this->switch_currency( 'SEK' );

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertCount( 1, $cart['items'] );
		$this->assertSame( 2, $cart['items'][0]['quantity'] );
	}

	public function test_repeated_reads_after_a_switch_stay_stable(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->add_item( 1 );

		$this->switch_currency( 'SEK' );

		$first  = $this->cart_total();
		$second = $this->cart_total();

		$this->assertSame( '115000', $first );
		$this->assertSame( $first, $second, 'Recalculation must be idempotent once the identity matches.' );
	}

	public function test_rate_change_alone_recalculates_the_cart(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->add_item( 1 );

		$this->assertSame( '115000', $this->cart_total() );

		// Same currency, new rate: the identity carries the rate, so the cart
		// must follow an administrator's correction on the shopper's next request.
		$this->boot_plugin( array( 'SEK' => array( 'rate' => '12.00' ) ), 'SEK' );
		$this->rehydrate_cart();

		$this->assertSame( '120000', $this->cart_total() );
	}

	/**
	 * Adds the same product to the cart.
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
	 * Reads the cart total through the Store API.
	 */
	private function cart_total(): string {
		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		return (string) $cart['totals']['total_price'];
	}

	/**
	 * Reads the cart currency code through the Store API.
	 */
	private function cart_currency(): string {
		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		return (string) $cart['totals']['currency_code'];
	}
}
