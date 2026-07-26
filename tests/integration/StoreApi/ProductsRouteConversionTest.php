<?php
/**
 * Integration tests: product prices and currency identity on the session-less
 * Store API products route.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\CurrencyContext;

/**
 * `/products` extends AbstractRoute, so WooCommerce loads no session for it.
 * Currency has to resolve from the cookie or an explicit argument, and the
 * whole `currency_*` identity has to match what the storefront would show.
 */
final class ProductsRouteConversionTest extends StoreApiTestCase {

	private const CURRENCIES = array(
		'SEK' => array(
			'rate'     => '11.50',
			'symbol'   => 'kr',
			'position' => 'right_space',
		),
		'JPY' => array(
			'rate'     => '160',
			'decimals' => 0,
		),
	);

	public function test_simple_product_price_is_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( '115000', $prices['price'] );
		$this->assertSame( '115000', $prices['regular_price'] );
	}

	public function test_sale_price_is_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100', '80' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( '92000', $prices['price'], '80 EUR * 11.50 = 920.00 SEK.' );
		$this->assertSame( '115000', $prices['regular_price'] );
		$this->assertSame( '92000', $prices['sale_price'] );
	}

	public function test_variable_product_price_range_is_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->variable_product( '100', '200' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( '115000', $prices['price_range']['min_amount'] );
		$this->assertSame( '230000', $prices['price_range']['max_amount'] );
	}

	public function test_currency_identity_matches_the_active_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( 'SEK', $prices['currency_code'] );
		$this->assertSame( 'kr', $prices['currency_symbol'] );
		$this->assertSame( 2, $prices['currency_minor_unit'] );
	}

	public function test_symbol_position_reaches_the_response_prefix_and_suffix(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$prices = $this->product_prices( $product->get_id() );

		// SEK is configured right_space. WooCommerce derives these two fields from
		// a raw option read, so they only follow the active currency because the
		// plugin filters the option itself.
		$this->assertSame( '', $prices['currency_prefix'] );
		$this->assertSame( ' kr', $prices['currency_suffix'] );
	}

	public function test_zero_decimal_currency_uses_its_own_minor_unit(): void {
		$this->boot_plugin( self::CURRENCIES, 'JPY' );
		$product = $this->simple_product( '100' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( 'JPY', $prices['currency_code'] );
		$this->assertSame( 0, $prices['currency_minor_unit'] );
		$this->assertSame( '16000', $prices['price'], '100 EUR * 160 = 16000 JPY, no minor unit.' );
	}

	public function test_rounding_sensitive_amount_rounds_once(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		// 19.99 * 11.50 = 229.885, which must round half up to 229.89 exactly once.
		$product = $this->simple_product( '19.99' );

		$this->assertSame( '22989', $this->product_prices( $product->get_id() )['price'] );
	}

	public function test_base_currency_response_is_unchanged(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$product = $this->simple_product( '100' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( 'EUR', $prices['currency_code'] );
		$this->assertSame( '10000', $prices['price'] );
	}

	public function test_repeated_requests_return_identical_payloads(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$first  = $this->product_prices( $product->get_id() );
		$second = $this->product_prices( $product->get_id() );

		$this->assertSame( $first, $second, 'A second read must not convert an already converted price.' );
	}

	public function test_cookie_selects_the_currency_without_a_session(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$product = $this->simple_product( '100' );

		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ), 'No session preference is set.' );
		$this->assertSame( 'SEK', $this->product_prices( $product->get_id() )['currency_code'] );
	}

	public function test_unknown_currency_cookie_falls_back_to_base(): void {
		$this->boot_plugin( self::CURRENCIES, 'XXX' );
		$product = $this->simple_product( '100' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( 'EUR', $prices['currency_code'] );
		$this->assertSame( '10000', $prices['price'] );
	}

	public function test_currency_without_a_rate_falls_back_to_base(): void {
		$this->boot_plugin( array( 'NOK' => array( 'rate' => '' ) ), 'NOK' );
		$product = $this->simple_product( '100' );

		$prices = $this->product_prices( $product->get_id() );

		$this->assertSame( 'EUR', $prices['currency_code'] );
		$this->assertSame( '10000', $prices['price'] );
	}

	public function test_disabled_currency_falls_back_to_base(): void {
		$this->boot_plugin(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => false,
				),
			),
			'SEK'
		);
		$product = $this->simple_product( '100' );

		$this->assertSame( 'EUR', $this->product_prices( $product->get_id() )['currency_code'] );
	}

	/**
	 * Fetches the `prices` object for a product through the Store API.
	 *
	 * @param int $product_id Product to read.
	 *
	 * @return array<string, mixed>
	 */
	private function product_prices( int $product_id ): array {
		$data = $this->response_data( $this->store_api_request( 'GET', '/products/' . $product_id ) );

		return (array) $data['prices'];
	}
}
