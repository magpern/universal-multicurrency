<?php
/**
 * Integration tests: checkout notice payload on Store API responses.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Checkout;

use UMC\Checkout\CheckoutSettings;
use UMC\Checkout\CheckoutTransitionState;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\StoreApi\CartExtensionData;
use UMC\Tests\Integration\StoreApi\StoreApiTestCase;

/**
 * Store API extension exposes structured checkout_notice payloads for Blocks.
 */
final class CheckoutStoreApiNoticePayloadTest extends StoreApiTestCase {

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
		WC()->payment_gateways()->init();

		parent::tear_down();
	}

	public function test_checkout_route_includes_notice_when_store_mode_transitions(): void {
		$this->boot_plugin(
			self::CURRENCIES,
			'SEK',
			'EUR',
			2,
			array(
				'checkout' => array(
					'mode'        => CheckoutSettings::MODE_STORE,
					'show_notice' => true,
				),
			)
		);
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->restrict_gateway( 'cheque', array( 'EUR' ) );
		$this->add_item();

		$notice = $this->checkout_extension()['checkout_notice'];

		$this->assertTrue( $notice['show'] );
		$this->assertSame( 'info', $notice['status'] );
		$this->assertStringContainsString( 'SEK', (string) $notice['message'] );
		$this->assertStringContainsString( 'EUR', (string) $notice['message'] );
		$this->assertSame(
			sprintf(
				'%s|%s|%s|%s',
				CheckoutSettings::MODE_STORE,
				'SEK',
				'EUR',
				CheckoutTransitionState::REASON_STORE_CURRENCY
			),
			$notice['signature']
		);
	}

	public function test_checkout_notice_hidden_when_show_notice_disabled(): void {
		$this->boot_plugin(
			self::CURRENCIES,
			'SEK',
			'EUR',
			2,
			array(
				'checkout' => array(
					'mode'        => CheckoutSettings::MODE_STORE,
					'show_notice' => false,
				),
			)
		);
		$this->add_item();

		$this->assertSame(
			array( 'show' => false ),
			$this->checkout_extension()['checkout_notice']
		);
	}

	public function test_cart_route_exposes_checkout_policy_fields_without_notice_when_no_transition(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->add_item();

		$umc = $this->cart_extension();

		$this->assertSame( CheckoutSettings::MODE_SELECTED, $umc['checkout_mode'] );
		$this->assertSame( 'EUR', $umc['shopper_currency'] );
		$this->assertSame( 'EUR', $umc['effective_currency'] );
		$this->assertSame( '', $umc['transition_reason'] );
		$this->assertFalse( $umc['fallback_applied'] );
		$this->assertSame( array( 'show' => false ), $umc['checkout_notice'] );
	}

	public function test_fallback_notice_signature_reflects_unsupported_selected_reason(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'USD' ) );
		$this->restrict_gateway( 'cheque', array( 'USD' ) );
		$this->add_item();

		$umc = $this->checkout_extension();

		$this->assertTrue( $umc['fallback_applied'] );
		$this->assertSame( CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED, $umc['transition_reason'] );
		$this->assertTrue( $umc['checkout_notice']['show'] );
		$this->assertSame(
			sprintf(
				'%s|%s|%s|%s',
				CheckoutSettings::MODE_SELECTED,
				'SEK',
				'EUR',
				CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED
			),
			$umc['checkout_notice']['signature']
		);
	}

	/**
	 * Restricts a gateway to a set of currency codes.
	 *
	 * @param string             $gateway_id Gateway id.
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
	 * Adds a product to the cart.
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

	/**
	 * Reads checkout extension data after hitting the checkout route.
	 *
	 * @return array<string, mixed>
	 */
	private function checkout_extension(): array {
		if ( CartExtensionData::supports_checkout_endpoint_extension() ) {
			$data = $this->response_data( $this->store_api_request( 'GET', '/checkout' ) );

			$this->assertArrayHasKey( 'extensions', $data );

			return (array) $data['extensions'][ CartExtensionData::NAMESPACE_KEY ];
		}

		return $this->cart_extension_during_checkout();
	}

	/**
	 * Reads cart extension data while presenting a checkout route identity.
	 *
	 * WooCommerce 8.2 exposes checkout policy fields on the cart endpoint only.
	 *
	 * @return array<string, mixed>
	 */
	private function cart_extension_during_checkout(): array {
		$previous_uri   = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Test harness restores a URI it set itself.
		$previous_route = $GLOBALS['wp']->query_vars['rest_route'] ?? null;

		$_SERVER['REQUEST_URI']                  = '/wp-json/wc/store/v1/checkout';
		$GLOBALS['wp']->query_vars['rest_route'] = '/wc/store/v1/checkout';

		try {
			if ( function_exists( 'WC' ) && WC()->cart ) {
				do_action( 'woocommerce_cart_loaded_from_session' );
			}

			$data = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );
		} finally {
			if ( null === $previous_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $previous_uri;
			}

			if ( null === $previous_route ) {
				unset( $GLOBALS['wp']->query_vars['rest_route'] );
			} else {
				$GLOBALS['wp']->query_vars['rest_route'] = $previous_route;
			}
		}

		$this->assertArrayHasKey( 'extensions', $data );

		return (array) $data['extensions'][ CartExtensionData::NAMESPACE_KEY ];
	}

	/**
	 * Reads cart extension data.
	 *
	 * @return array<string, mixed>
	 */
	private function cart_extension(): array {
		$data = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertArrayHasKey( 'extensions', $data );

		return (array) $data['extensions'][ CartExtensionData::NAMESPACE_KEY ];
	}
}
