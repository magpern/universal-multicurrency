<?php
/**
 * Integration tests: which requests convert, and the switcher's behaviour on
 * REST requests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\CurrencyContext;
use UMC\CurrencySwitcher;

/**
 * Proves the conversion gate admits the Store API and nothing else that was
 * previously excluded, and that the switcher never mutates state or redirects
 * on a REST request.
 */
final class RequestGateTest extends StoreApiTestCase {

	private const CURRENCIES = array( 'SEK' => array( 'rate' => '11.50' ) );

	public function test_harness_presents_requests_as_store_api_requests(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertTrue( WC()->is_rest_api_request(), 'Harness must look like a REST request.' );
		$this->assertTrue( WC()->is_store_api_request(), 'Harness must look like a Store API request.' );
	}

	public function test_store_api_requests_convert(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertTrue( $this->converts_under_uri( '/wp-json/wc/store/v1/cart' ) );
		$this->assertTrue( $this->converts_under_uri( '/wp-json/wc/store/v1/products' ) );
		$this->assertTrue( $this->converts_under_uri( '/wp-json/wc/store/v1/checkout' ) );
	}

	public function test_store_api_batch_requests_convert(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertTrue(
			$this->converts_under_uri( '/wp-json/wc/store/v1/batch' ),
			'Batched cart operations run in-process and must convert like their individual routes.'
		);
	}

	public function test_route_detection_is_anchored_not_a_substring_match(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		// A query argument can legitimately contain a Store API path — a redirect
		// target, a search term. Detection must read the route WordPress parsed,
		// not look for the namespace anywhere in the URI, or an admin REST call
		// would convert.
		$this->assertFalse(
			$this->converts_under_route(
				'/wp-json/wc/v3/orders?search=%2Fwp-json%2Fwc%2Fstore%2Fv1%2Fcart',
				'/wc/v3/orders'
			),
			'A Store API path inside a query argument must not open the boundary.'
		);

		$this->assertFalse(
			$this->converts_under_route( '/wp-json/wp/v2/posts?s=/wp-json/wc/store/', '/wp/v2/posts' )
		);
	}

	public function test_genuine_store_api_routes_still_convert_when_parsed(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertTrue( $this->converts_under_route( '/wp-json/wc/store/v1/cart', '/wc/store/v1/cart' ) );
		$this->assertTrue( $this->converts_under_route( '/wp-json/wc/store/v1/batch', '/wc/store/v1/batch' ) );
		$this->assertTrue(
			$this->converts_under_route( '/index.php?rest_route=/wc/store/v1/cart', '/wc/store/v1/cart' ),
			'The plain permalink form of a Store API request must convert too.'
		);
	}

	public function test_other_rest_namespaces_do_not_convert(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertFalse(
			$this->converts_under_uri( '/wp-json/wc/v3/orders' ),
			'The admin REST API reports stored values, not a shopper presentation.'
		);
		$this->assertFalse( $this->converts_under_uri( '/wp-json/wp/v2/posts' ) );
		$this->assertFalse( $this->converts_under_uri( '/wp-json/some-plugin/v1/thing' ) );
	}

	public function test_storefront_page_views_still_convert(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertTrue( $this->converts_under_uri( null ) );
		$this->assertTrue( $this->converts_under_uri( '/shop/' ) );
	}

	public function test_hosts_can_still_close_the_gate(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		add_filter( 'umc_is_request_convertible', '__return_false' );

		$this->assertFalse(
			$this->converts_under_uri( '/wp-json/wc/store/v1/cart' ),
			'umc_is_request_convertible remains the host-level override.'
		);
	}

	public function test_store_api_prices_convert_once_the_gate_is_open(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$data = $this->response_data( $this->store_api_request( 'GET', '/products/' . $product->get_id() ) );

		$this->assertSame( 'SEK', $data['prices']['currency_code'] );
		$this->assertSame( '115000', $data['prices']['price'], '100 EUR * 11.50 = 1150.00 SEK.' );
	}

	public function test_store_api_cart_totals_convert_once_the_gate_is_open(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		WC()->cart->add_to_cart( $this->simple_product( '100' )->get_id(), 2 );

		$data = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame( 'SEK', $data['totals']['currency_code'] );
		$this->assertSame( '230000', $data['totals']['total_price'] );
	}

	public function test_switcher_never_persists_or_redirects_on_a_rest_request(): void {
		$this->boot_plugin( self::CURRENCIES, null );

		$_GET[ CurrencyContext::QUERY_VAR ] = 'SEK';

		try {
			// A redirect or exit here would fail the test outright: halt() is not
			// stubbed, so reaching it would terminate the run.
			( new CurrencySwitcher( $this->context ) )->maybe_switch();

			$this->assertNull(
				WC()->session->get( CurrencyContext::SESSION_KEY ),
				'REST requests must not persist a currency preference.'
			);
			$this->assertArrayNotHasKey( CurrencyContext::COOKIE_NAME, $_COOKIE );
		} finally {
			unset( $_GET[ CurrencyContext::QUERY_VAR ] );
		}
	}

	public function test_switcher_still_accepts_a_selectable_code_on_a_storefront_request(): void {
		$this->boot_plugin( self::CURRENCIES, null );

		$_GET[ CurrencyContext::QUERY_VAR ] = 'SEK';

		try {
			// maybe_switch() ends in a redirect and exit, which the test process
			// cannot survive, so its decision step is exercised instead: on a
			// storefront request the switcher still resolves a selectable code
			// and would go on to persist it. What changed for REST is the early
			// return asserted above, not this.
			$requested = $this->as_storefront_request(
				function (): ?string {
					return ( new CurrencySwitcher( $this->new_context() ) )->requested_code();
				}
			);

			$this->assertSame( 'SEK', $requested, 'Storefront switching must keep working.' );
		} finally {
			unset( $_GET[ CurrencyContext::QUERY_VAR ] );
		}
	}

	public function test_explicit_currency_argument_converts_one_response_without_persisting(): void {
		$this->boot_plugin( self::CURRENCIES, null );
		$product = $this->simple_product( '100' );

		$_GET[ CurrencyContext::QUERY_VAR ] = 'SEK';

		try {
			$data = $this->response_data( $this->store_api_request( 'GET', '/products/' . $product->get_id() ) );

			$this->assertSame( 'SEK', $data['prices']['currency_code'] );
			$this->assertSame( '115000', $data['prices']['price'] );
			$this->assertNull(
				WC()->session->get( CurrencyContext::SESSION_KEY ),
				'A per-request override must leave no persisted state behind.'
			);
		} finally {
			unset( $_GET[ CurrencyContext::QUERY_VAR ] );
		}
	}
}
