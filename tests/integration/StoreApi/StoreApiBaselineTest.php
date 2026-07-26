<?php
/**
 * Integration tests: baseline Store API behaviour before the conversion gate
 * opens, and proof that the harness exercises the real request predicates.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

/**
 * Pins what the Store API does today so the milestone's behaviour change is
 * visible in a diff rather than asserted from memory.
 *
 * These tests also stand in for the harness's own contract: if the request-URI
 * simulation ever stops reaching `WC::is_store_api_request()`, the gate
 * assertions below fail rather than silently testing the storefront path.
 */
final class StoreApiBaselineTest extends StoreApiTestCase {

	public function test_harness_presents_requests_as_store_api_requests(): void {
		$this->boot_plugin( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$this->assertTrue( WC()->is_rest_api_request(), 'Harness must look like a REST request.' );
		$this->assertTrue( WC()->is_store_api_request(), 'Harness must look like a Store API request.' );
	}

	public function test_storefront_requests_remain_distinguishable(): void {
		$this->boot_plugin( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$storefront = $this->as_storefront_request(
			static function (): array {
				return array(
					'rest'  => WC()->is_rest_api_request(),
					'store' => WC()->is_store_api_request(),
				);
			}
		);

		$this->assertSame(
			array(
				'rest'  => false,
				'store' => false,
			),
			$storefront
		);
	}

	public function test_store_api_requests_do_not_convert_before_the_gate_opens(): void {
		$this->boot_plugin( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$this->assertSame( 'SEK', $this->context->get_active_code(), 'Currency resolves; only conversion is gated.' );
		$this->assertFalse(
			$this->context->is_convertible_request(),
			'Baseline: REST requests, including the Store API, are not convertible.'
		);
	}

	public function test_store_api_product_prices_are_base_currency_before_the_gate_opens(): void {
		$this->boot_plugin( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		$data = $this->response_data( $this->store_api_request( 'GET', '/products/' . $product->get_id() ) );

		$this->assertSame( 'EUR', $data['prices']['currency_code'] );
		$this->assertSame( '10000', $data['prices']['price'], 'Baseline: unconverted base price.' );
	}

	public function test_store_api_cart_totals_are_base_currency_before_the_gate_opens(): void {
		$this->boot_plugin( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 2 );

		$data = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame( 'EUR', $data['totals']['currency_code'] );
		$this->assertSame( '20000', $data['totals']['total_price'], 'Baseline: unconverted base total.' );
	}
}
