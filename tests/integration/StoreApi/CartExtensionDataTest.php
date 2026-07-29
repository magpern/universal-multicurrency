<?php
/**
 * Integration tests: the plugin's currency state on the Store API cart.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\StoreApi\CartExtensionData;

/**
 * The extension exposes currency state, never money: amounts and the currency
 * identity already reach clients through WooCommerce's own fields.
 */
final class CartExtensionDataTest extends StoreApiTestCase {

	private const CURRENCIES = array(
		'SEK' => array( 'rate' => '11.50' ),
		'USD' => array( 'rate' => '1.20' ),
	);

	public function test_cart_response_carries_the_currency_state(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$umc = $this->extension_data();

		$this->assertSame( 'SEK', $umc['active_currency'] );
		$this->assertSame( 'EUR', $umc['base_currency'] );
	}

	public function test_selectable_currencies_are_listed(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$selectable = $this->extension_data()['selectable_currencies'];

		$this->assertContains( 'EUR', $selectable, 'The base currency is always selectable.' );
		$this->assertContains( 'SEK', $selectable );
		$this->assertContains( 'USD', $selectable );
	}

	public function test_currency_without_a_rate_is_not_offered(): void {
		$this->boot_plugin(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'NOK' => array( 'rate' => '' ),
			),
			'SEK'
		);

		$this->assertNotContains(
			'NOK',
			$this->extension_data()['selectable_currencies'],
			'A currency with no rate cannot be selected.'
		);
	}

	public function test_state_tracks_the_selected_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertSame( 'SEK', $this->extension_data()['active_currency'] );

		$this->switch_currency( 'USD' );

		$this->assertSame( 'USD', $this->extension_data()['active_currency'] );
	}

	public function test_base_currency_state_reports_the_base_code(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );

		$umc = $this->extension_data();

		$this->assertSame( 'EUR', $umc['active_currency'] );
		$this->assertSame( 'EUR', $umc['base_currency'] );
	}

	public function test_extension_namespace_is_prefixed_and_alone(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$extensions = (array) $this->response_data( $this->store_api_request( 'GET', '/cart' ) )['extensions'];

		$this->assertSame( 'umc', CartExtensionData::NAMESPACE_KEY );
		$this->assertArrayHasKey( 'umc', $extensions );

		$plugin_keys = array_values(
			array_filter(
				array_keys( $extensions ),
				static function ( string $key ): bool {
					return 0 === strpos( $key, 'umc' );
				}
			)
		);

		$this->assertSame( array( 'umc' ), $plugin_keys, 'Exactly one prefixed namespace.' );
	}

	public function test_extension_does_not_publish_the_exchange_rate(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$payload = (string) wp_json_encode( $this->extension_data() );

		// The rate is admin-configured commercial information and an internal
		// cache identity; neither belongs in an unauthenticated response.
		$this->assertStringNotContainsString( '11.50', $payload );
		$this->assertStringNotContainsString( 'rate', $payload );
	}

	public function test_extension_carries_no_monetary_values(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );

		$this->assertSame(
			array(
				'active_currency',
				'base_currency',
				'selectable_currencies',
				'checkout_mode',
				'shopper_currency',
				'effective_currency',
				'settlement_currency',
				'transition_reason',
				'fallback_applied',
				'checkout_notice',
			),
			array_keys( $this->extension_data() ),
			'Amounts belong to the core fields, and the rate is an implementation detail.'
		);
	}

	/**
	 * Reads the plugin's extension payload from the cart response.
	 *
	 * @return array<string, mixed>
	 */
	private function extension_data(): array {
		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertArrayHasKey( 'extensions', $cart );

		return (array) $cart['extensions'][ CartExtensionData::NAMESPACE_KEY ];
	}
}
