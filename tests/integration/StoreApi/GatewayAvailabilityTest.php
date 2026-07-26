<?php
/**
 * Integration tests: payment method availability in Store API cart responses.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

/**
 * The Cart and Checkout blocks read their payment methods from a plain pluck
 * over WooCommerce's available gateways, so the existing availability filter
 * governs them with no Store API specific code. These tests hold that, and
 * pin the one behaviour that had to change: no session notices from REST.
 */
final class GatewayAvailabilityTest extends StoreApiTestCase {

	private const CURRENCIES = array( 'SEK' => array( 'rate' => '11.50' ) );

	public function set_up(): void {
		parent::set_up();

		$this->enable_gateway( 'bacs' );
		$this->enable_gateway( 'cheque' );

		WC()->payment_gateways()->init();
	}

	public function tear_down(): void {
		delete_option( 'woocommerce_bacs_settings' );
		delete_option( 'woocommerce_cheque_settings' );

		wc_clear_notices();
		WC()->payment_gateways()->init();

		parent::tear_down();
	}

	public function test_all_gateways_are_offered_by_default(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->add_item();

		$methods = $this->payment_methods();

		$this->assertContains( 'bacs', $methods );
		$this->assertContains( 'cheque', $methods );
	}

	public function test_gateway_not_supporting_the_currency_is_withheld(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->add_item();

		$methods = $this->payment_methods();

		$this->assertNotContains( 'bacs', $methods, 'BACS accepts EUR only.' );
		$this->assertContains( 'cheque', $methods, 'Unrestricted gateways are unaffected.' );
	}

	public function test_the_same_gateway_returns_when_its_currency_is_active(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->add_item();

		$this->assertNotContains( 'bacs', $this->payment_methods() );

		$this->switch_currency( 'EUR' );

		$this->assertContains( 'bacs', $this->payment_methods(), 'Switching currency refreshes availability.' );
	}

	public function test_no_compatible_gateway_yields_an_empty_list(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->restrict_gateway( 'cheque', array( 'EUR' ) );
		$this->add_item();

		$this->assertSame( array(), $this->payment_methods() );
	}

	public function test_no_compatible_gateway_raises_no_session_notice(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->restrict_gateway( 'cheque', array( 'EUR' ) );
		$this->add_item();

		wc_clear_notices();

		$this->assertSame( array(), $this->payment_methods() );
		$this->assertSame(
			0,
			wc_notice_count( 'error' ),
			'A notice raised here would surface on an unrelated later page view.'
		);
	}

	public function test_storefront_still_explains_why_no_gateway_is_available(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->restrict_gateway( 'cheque', array( 'EUR' ) );

		wc_clear_notices();

		$remaining = $this->as_storefront_request(
			function () {
				return WC()->payment_gateways()->get_available_payment_gateways();
			}
		);

		$this->assertSame( array(), $remaining );
		$this->assertSame( 1, wc_notice_count( 'error' ), 'Storefront shoppers still get told.' );
	}

	public function test_filtering_never_alters_gateway_amounts(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->add_item();

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		// Availability filtering is the whole intervention: the totals a gateway
		// would charge come from the cart, untouched by gateway handling.
		$this->assertSame( '115000', $cart['totals']['total_price'] );
		$this->assertSame( 'SEK', $cart['totals']['currency_code'] );
	}

	/**
	 * Restricts a gateway to a set of currency codes.
	 *
	 * @param string             $gateway_id Gateway id to restrict.
	 * @param array<int, string> $codes      Supported currency codes.
	 */
	private function restrict_gateway( string $gateway_id, array $codes ): void {
		add_filter(
			'umc_gateway_supported_currencies',
			static function ( $supported, $gateway ) use ( $gateway_id, $codes ) {
				return $gateway_id === $gateway->id ? $codes : $supported;
			},
			10,
			2
		);
	}

	/**
	 * Enables one of WooCommerce's built-in offline gateways.
	 *
	 * @param string $gateway_id Gateway id.
	 */
	private function enable_gateway( string $gateway_id ): void {
		update_option(
			'woocommerce_' . $gateway_id . '_settings',
			array(
				'enabled' => 'yes',
				'title'   => strtoupper( $gateway_id ),
			)
		);
	}

	/**
	 * Reads the payment method ids offered by the cart response.
	 *
	 * @return array<int, string>
	 */
	private function payment_methods(): array {
		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		return array_values( (array) $cart['payment_methods'] );
	}

	/**
	 * Puts something in the cart so payment methods are meaningful.
	 */
	private function add_item(): void {
		$this->store_api_request(
			'POST',
			'/cart/add-item',
			array(
				'id'       => $this->simple_product( '100' )->get_id(),
				'quantity' => 1,
			)
		);
	}
}
