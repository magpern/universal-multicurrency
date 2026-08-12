<?php
/**
 * Integration tests: /wc/v3 stays base; /wc/store participates in shopper FX.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Tests\Integration\StoreApi\StoreApiTestCase;

/**
 * Proves Invariant I end-to-end: the conversion graph is registered (as on the
 * storefront), a foreign-currency cookie is present, yet admin REST still
 * reports stored base prices while Store API converts.
 *
 * {@see CurrencyContext} memoizes convertibility for the request identity under
 * which the graph was built, matching production's per-request lifetime.
 */
final class RestCurrencyBoundaryTest extends StoreApiTestCase {

	private const CURRENCIES = array( 'SEK' => array( 'rate' => '11.50' ) );

	/**
	 * Admin user used for /wc/v3 permission checks.
	 *
	 * @var int
	 */
	private int $admin_user_id = 0;

	public function set_up(): void {
		parent::set_up();

		$this->admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_wc_v3_products_return_base_prices_with_foreign_cookie(): void {
		// Build the graph under the admin REST request identity and lock the
		// convertibility memo closed before the harness restores its Store API URI.
		$this->with_request_uri(
			'/wp-json/wc/v3/products',
			function (): void {
				$this->boot_plugin( self::CURRENCIES, 'SEK' );
				$this->assertFalse(
					$this->context->is_convertible_request(),
					'Admin REST must not open the conversion gate.'
				);
			}
		);

		$product = $this->simple_product( '100' );

		wp_set_current_user( $this->admin_user_id );

		$data = $this->response_data(
			$this->rest_request( 'GET', '/wc/v3/products/' . $product->get_id() )
		);

		$this->assertSame(
			'100',
			(string) $data['regular_price'],
			'/wc/v3 must report the stored base price, not a shopper conversion.'
		);
		$this->assertSame( '100', (string) $data['price'] );
	}

	public function test_store_api_products_convert_when_store_api_uri_is_set(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$data = $this->response_data(
			$this->store_api_request( 'GET', '/products/' . $product->get_id() )
		);

		$this->assertSame( 'SEK', $data['prices']['currency_code'] );
		$this->assertSame(
			'115000',
			$data['prices']['price'],
			'/wc/store must convert once: 100 EUR * 11.50 = 1150.00 SEK.'
		);
		$this->assertTrue(
			$this->context->is_convertible_request(),
			'Store API must open the conversion gate.'
		);
	}

	public function test_gate_admits_store_api_and_rejects_wc_v3(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertTrue( $this->converts_under_uri( '/wp-json/wc/store/v1/products' ) );
		$this->assertFalse(
			$this->converts_under_uri( '/wp-json/wc/v3/products' ),
			'/wc/v3 is administrative: conversion must stay closed.'
		);
	}
}
