<?php
/**
 * Integration tests: shipping rates and taxes in Store API cart responses.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use WC_Shipping_Rate;
use WC_Shipping_Zone;

/**
 * Exercises real shipping zones through the cart route, so rate conversion,
 * per-currency cache isolation and tax derivation are all observed where the
 * Cart block would see them.
 */
final class ShippingRateParityTest extends StoreApiTestCase {

	private const CURRENCIES = array( 'SEK' => array( 'rate' => '11.50' ) );

	/**
	 * Shipping zone created for the test, removed on teardown.
	 *
	 * @var WC_Shipping_Zone|null
	 */
	private ?WC_Shipping_Zone $zone = null;

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_ship_to_countries', 'all' );
		update_option( 'woocommerce_enable_shipping_calc', 'yes' );
		update_option( 'woocommerce_shipping_cost_requires_address', 'no' );

		WC()->customer->set_shipping_country( 'SE' );
		WC()->customer->set_shipping_postcode( '11122' );
	}

	public function tear_down(): void {
		if ( $this->zone instanceof WC_Shipping_Zone ) {
			$this->zone->delete( true );
			$this->zone = null;
		}

		\WC_Cache_Helper::get_transient_version( 'shipping', true );

		parent::tear_down();
	}

	public function test_flat_rate_cost_is_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->flat_rate_zone( '10' );
		$this->add_shippable_item();

		$rate = $this->first_shipping_rate();

		$this->assertSame( '11500', $rate['price'], '10 EUR flat rate becomes 115.00 SEK.' );
		$this->assertSame( 'SEK', $rate['currency_code'] );
	}

	public function test_flat_rate_is_untouched_in_base_currency(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->flat_rate_zone( '10' );
		$this->add_shippable_item();

		$this->assertSame( '1000', $this->first_shipping_rate()['price'] );
	}

	public function test_free_shipping_stays_free(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->zone_with_method( 'free_shipping', array() );
		$this->add_shippable_item();

		$this->assertSame( '0', $this->first_shipping_rate()['price'], 'Zero converts to zero.' );
	}

	public function test_local_pickup_cost_is_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->zone_with_method( 'local_pickup', array( 'cost' => '5' ) );
		$this->add_shippable_item();

		$this->assertSame( '5750', $this->first_shipping_rate()['price'] );
	}

	public function test_third_party_shipping_method_is_not_converted(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->flat_rate_zone( '10' );

		// A method the plugin does not recognise keeps its authored cost: the
		// plugin cannot know which currency a third party priced it in. Injected
		// before the first calculation, since rates are cached per package.
		add_filter(
			'woocommerce_package_rates',
			static function () {
				return array( 'courier:1' => new WC_Shipping_Rate( 'courier:1', 'Courier', 20.0, array(), 'courier', 1 ) );
			},
			5
		);

		$this->add_shippable_item();

		$rate = $this->first_shipping_rate();

		$this->assertSame( 'courier:1', $rate['rate_id'] );
		$this->assertSame( '2000', $rate['price'] );
	}

	public function test_currency_switch_produces_freshly_converted_rates(): void {
		$this->boot_plugin( self::CURRENCIES, 'EUR' );
		$this->flat_rate_zone( '10' );
		$this->add_shippable_item();

		$this->assertSame( '1000', $this->first_shipping_rate()['price'] );

		$this->switch_currency( 'SEK' );

		$this->assertSame(
			'11500',
			$this->first_shipping_rate()['price'],
			'The package hash carries the rate identity, so cached EUR rates must not be reused.'
		);

		$this->switch_currency( 'EUR' );

		$this->assertSame( '1000', $this->first_shipping_rate()['price'], 'Switching back must not compound.' );
	}

	public function test_shipping_tax_derives_from_the_converted_cost(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->enable_tax( 25.0 );
		$this->flat_rate_zone( '10' );
		$this->add_shippable_item();

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		// 25% of the converted 115.00 SEK shipping cost, computed by WooCommerce
		// from the converted amount rather than by any conversion of tax itself.
		$this->assertSame( '2875', $cart['totals']['total_shipping_tax'] );
	}

	public function test_item_tax_derives_from_the_converted_price(): void {
		$this->boot_plugin( self::CURRENCIES, 'SEK' );
		$this->enable_tax( 25.0 );
		$this->add_shippable_item();

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		// 100 EUR -> 1150 SEK, 25% of which is 287.50 SEK.
		$this->assertSame( '115000', $cart['totals']['total_items'] );
		$this->assertSame( '28750', $cart['totals']['total_items_tax'] );
	}

	/**
	 * Enables a single standard tax rate.
	 *
	 * @param float $rate Percentage rate.
	 */
	private function enable_tax( float $rate ): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );

		\WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate'          => (string) $rate,
				'tax_rate_name'     => 'VAT',
				'tax_rate_shipping' => 1,
			)
		);

		WC()->customer->set_shipping_country( 'SE' );
	}

	/**
	 * Creates a zone offering flat rate shipping at the given base-currency cost.
	 *
	 * @param string $cost Flat rate cost in the store base currency.
	 */
	private function flat_rate_zone( string $cost ): void {
		$this->zone_with_method( 'flat_rate', array( 'cost' => $cost ) );
	}

	/**
	 * Creates a zone covering Sweden offering one shipping method.
	 *
	 * @param string               $method_id Shipping method id.
	 * @param array<string, mixed> $settings  Instance settings.
	 */
	private function zone_with_method( string $method_id, array $settings ): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Test Zone' );
		$zone->add_location( 'SE', 'country' );
		$instance_id = $zone->add_shipping_method( $method_id );
		$zone->save();

		if ( array() !== $settings ) {
			update_option(
				'woocommerce_' . $method_id . '_' . $instance_id . '_settings',
				array_merge( array( 'enabled' => 'yes' ), $settings )
			);
		}

		$this->zone = $zone;

		WC()->shipping()->unregister_shipping_methods();
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * Adds a physical product to the cart.
	 */
	private function add_shippable_item(): void {
		$product = $this->simple_product( '100' );

		$this->store_api_request(
			'POST',
			'/cart/add-item',
			array(
				'id'       => $product->get_id(),
				'quantity' => 1,
			)
		);
	}

	/**
	 * Reads the first shipping rate offered in the cart response.
	 *
	 * @return array<string, mixed>
	 */
	private function first_shipping_rate(): array {
		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertNotEmpty( $cart['shipping_rates'], 'Expected a shipping package.' );
		$this->assertNotEmpty( $cart['shipping_rates'][0]['shipping_rates'], 'Expected an offered rate.' );

		return (array) $cart['shipping_rates'][0]['shipping_rates'][0];
	}
}
