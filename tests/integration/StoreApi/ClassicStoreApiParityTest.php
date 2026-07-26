<?php
/**
 * Integration tests: the classic flow and the Store API must agree.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\Order\OrderSnapshot;
use WC_Order;

/**
 * Runs one scenario twice — once through WC_Cart as a storefront page would,
 * once through the Store API as a Cart block would — and requires the two to
 * agree. This is the milestone's central claim: the transport differs, the
 * conversion engine does not.
 *
 * Amounts are compared after normalising the classic flow's decimal strings to
 * the Store API's minor-unit integers, which is a representation difference
 * rather than a value one.
 */
final class ClassicStoreApiParityTest extends StoreApiTestCase {

	private const CURRENCIES = array( 'SEK' => array( 'rate' => '11.50' ) );

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
		update_option( 'woocommerce_calc_taxes', 'no' );
		WC()->payment_gateways()->init();

		parent::tear_down();
	}

	public function test_converted_cart_totals_agree(): void {
		$this->assert_totals_agree( 'SEK' );
	}

	public function test_base_currency_cart_totals_agree(): void {
		$this->assert_totals_agree( 'EUR' );
	}

	public function test_totals_agree_with_a_coupon(): void {
		$this->assert_totals_agree( 'SEK', 'fixed_cart' );
	}

	public function test_totals_agree_with_exclusive_tax(): void {
		$this->enable_tax( 25.0, false );
		$this->assert_totals_agree( 'SEK' );
	}

	public function test_totals_agree_with_inclusive_tax(): void {
		$this->enable_tax( 25.0, true );
		$this->assert_totals_agree( 'SEK' );
	}

	public function test_gateway_availability_agrees(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		add_filter(
			'umc_gateway_supported_currencies',
			static function ( $supported, $gateway ) {
				return 'cheque' === $gateway->id ? array( 'EUR' ) : $supported;
			},
			10,
			2
		);

		$this->add_item( 1 );

		$blocks = $this->response_data( $this->store_api_request( 'GET', '/cart' ) )['payment_methods'];

		$classic = $this->as_storefront_request(
			static function (): array {
				return array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
			}
		);

		$this->assertNotContains( 'cheque', $blocks );
		$this->assertSame( array_values( $classic ), array_values( (array) $blocks ) );
	}

	public function test_snapshot_metadata_agrees_between_flows(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$blocks_order  = $this->place_block_order();
		$classic_order = $this->place_classic_order();

		$keys = array(
			OrderSnapshot::META_BASE_CURRENCY,
			OrderSnapshot::META_TRANSACTION_CURRENCY,
			OrderSnapshot::META_EXCHANGE_RATE,
			OrderSnapshot::META_RATE_SOURCE,
			OrderSnapshot::META_RATE_IDENTITY,
			OrderSnapshot::META_SNAPSHOT_VERSION,
			OrderSnapshot::META_TRANSACTION_DECIMALS,
			OrderSnapshot::META_PLUGIN_VERSION,
		);

		foreach ( $keys as $key ) {
			$this->assertSame(
				(string) $classic_order->get_meta( $key ),
				(string) $blocks_order->get_meta( $key ),
				"Snapshot key {$key} must not depend on which checkout was used."
			);
		}

		$this->assertSame( $classic_order->get_currency(), $blocks_order->get_currency() );
		$this->assertSame( $classic_order->get_total(), $blocks_order->get_total() );
	}

	/**
	 * Runs the same cart through both flows and compares every total.
	 *
	 * @param string $currency Currency to select.
	 * @param string $coupon   Coupon type to apply, or '' for none.
	 */
	private function assert_totals_agree( string $currency, string $coupon = '' ): void {
		$this->boot_plugin( self::CURRENCIES, $currency );

		if ( '' !== $coupon ) {
			$this->make_coupon( 'parity', $coupon, '10' );
		}

		$classic = $this->classic_totals( $coupon );
		$blocks  = $this->blocks_totals( $coupon );

		foreach ( array( 'subtotal', 'discount', 'tax', 'total' ) as $field ) {
			$this->assertSame(
				$this->minor_units( $classic[ $field ], $this->base_decimals ),
				$blocks[ $field ],
				"The {$field} must be identical in both flows."
			);
		}

		$this->assertSame( $currency, $blocks['currency'] );
		$this->assertSame( $currency, $classic['currency'] );
	}

	/**
	 * Totals produced by WC_Cart on a storefront request.
	 *
	 * @param string $coupon Coupon code to apply, or '' for none.
	 *
	 * @return array<string, string>
	 */
	private function classic_totals( string $coupon ): array {
		return (array) $this->as_storefront_request(
			function () use ( $coupon ): array {
				$this->reset_cart();
				WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 2 );

				if ( '' !== $coupon ) {
					WC()->cart->apply_coupon( 'parity' );
				}

				WC()->cart->calculate_totals();

				return array(
					'subtotal' => (string) WC()->cart->get_subtotal(),
					'discount' => (string) WC()->cart->get_discount_total(),
					'tax'      => (string) WC()->cart->get_total_tax(),
					'total'    => (string) WC()->cart->get_total( 'edit' ),
					'currency' => get_woocommerce_currency(),
				);
			}
		);
	}

	/**
	 * Totals produced by the Store API cart route.
	 *
	 * @param string $coupon Coupon code to apply, or '' for none.
	 *
	 * @return array<string, string>
	 */
	private function blocks_totals( string $coupon ): array {
		$this->reset_cart();
		$this->add_item( 2 );

		if ( '' !== $coupon ) {
			$this->store_api_request( 'POST', '/cart/apply-coupon', array( 'code' => 'parity' ) );
		}

		$totals = $this->response_data( $this->store_api_request( 'GET', '/cart' ) )['totals'];

		return array(
			'subtotal' => (string) $totals['total_items'],
			'discount' => (string) $totals['total_discount'],
			'tax'      => (string) $totals['total_tax'],
			'total'    => (string) $totals['total_price'],
			'currency' => (string) $totals['currency_code'],
		);
	}

	/**
	 * Places an order through the Store API checkout route.
	 */
	private function place_block_order(): WC_Order {
		$this->reset_cart();
		$this->add_item( 2 );

		$data = $this->response_data(
			$this->store_api_request(
				'POST',
				'/checkout',
				array(
					'billing_address'  => $this->address(),
					'shipping_address' => $this->address(),
					'payment_method'   => 'cheque',
				)
			)
		);

		return wc_get_order( $data['order_id'] );
	}

	/**
	 * Places an order the way classic checkout does.
	 *
	 * WC_Checkout cannot be driven without a full form POST, so the order is
	 * assembled from the same cart and the classic creation hook is fired at the
	 * point WC_Checkout fires it — which is what the snapshot writer listens to.
	 */
	private function place_classic_order(): WC_Order {
		$order = $this->as_storefront_request(
			function (): WC_Order {
				$this->reset_cart();
				WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 2 );
				WC()->cart->calculate_totals();

				$order = new WC_Order();
				$order->set_created_via( 'checkout' );
				$order->set_currency( get_woocommerce_currency() );
				$order->set_total( WC()->cart->get_total( 'edit' ) );

				do_action( 'woocommerce_checkout_create_order', $order, array() );

				$order->save();

				return $order;
			}
		);

		$this->assertInstanceOf( WC_Order::class, $order );

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Enables a single standard tax rate.
	 *
	 * @param float $rate      Percentage rate.
	 * @param bool  $inclusive Whether entered prices include tax.
	 */
	private function enable_tax( float $rate, bool $inclusive ): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', $inclusive ? 'yes' : 'no' );
		update_option( 'woocommerce_tax_display_cart', $inclusive ? 'incl' : 'excl' );

		\WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country' => '',
				'tax_rate'         => (string) $rate,
				'tax_rate_name'    => 'VAT',
			)
		);

		WC()->customer->set_billing_country( 'SE' );
		WC()->customer->set_shipping_country( 'SE' );
	}

	/**
	 * Adds the shared fixture product to the cart through the Store API.
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
