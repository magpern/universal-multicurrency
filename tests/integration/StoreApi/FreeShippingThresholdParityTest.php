<?php
/**
 * Integration tests: free-shipping min_amount eligibility via the Store API,
 * with optional classic-path parity.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\Tests\Support\GoldenTransactionFixtures as Golden;
use WC_Shipping_Zone;

/**
 * Proves Invariant K for free shipping on the Blocks cart: the converted
 * threshold is compared to converted cart totals, and Classic agrees when
 * the storefront harness is available.
 */
final class FreeShippingThresholdParityTest extends StoreApiTestCase {

	/**
	 * Shipping zone under test.
	 *
	 * @var WC_Shipping_Zone|null
	 */
	private ?WC_Shipping_Zone $zone = null;

	/**
	 * Free-shipping instance id.
	 *
	 * @var int
	 */
	private int $instance_id = 0;

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_ship_to_countries', 'all' );
		update_option( 'woocommerce_enable_shipping_calc', 'yes' );
		update_option( 'woocommerce_shipping_cost_requires_address', 'no' );
		update_option( 'woocommerce_calc_taxes', 'no' );

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

	public function test_store_api_cart_below_threshold_omits_free_shipping(): void {
		$this->boot_foreign();
		$this->free_shipping_with_min();

		// 999 SEK → ~89.03 EUR; converted threshold ≈ 89.12 EUR → not eligible.
		$this->add_shippable_item( '999' );

		$this->assertFalse(
			$this->store_api_offers_free_shipping(),
			'Store API cart below the converted threshold must not offer free shipping.'
		);
	}

	public function test_store_api_cart_at_threshold_offers_free_shipping(): void {
		$this->boot_foreign();
		$this->free_shipping_with_min();

		$this->add_shippable_item( Golden::FREE_SHIPPING_MIN );

		$this->assertTrue(
			$this->store_api_offers_free_shipping(),
			'Store API cart meeting the converted threshold must offer free shipping.'
		);
	}

	public function test_classic_and_store_api_agree_on_free_shipping_eligibility(): void {
		$this->boot_foreign();
		$this->free_shipping_with_min();

		$below_id = $this->shippable_product( '999' )->get_id();
		$at_id    = $this->shippable_product( Golden::FREE_SHIPPING_MIN )->get_id();

		foreach (
			array(
				'below' => array( $below_id, false ),
				'at'    => array( $at_id, true ),
			) as $label => $case
		) {
			[ $product_id, $expected ] = $case;

			\WC_Cache_Helper::get_transient_version( 'shipping', true );
			WC()->shipping()->unregister_shipping_methods();
			$this->reset_cart();
			$this->set_sweden_shipping_address();

			$classic = $this->classic_offers_free_shipping( $product_id );

			\WC_Cache_Helper::get_transient_version( 'shipping', true );
			WC()->shipping()->unregister_shipping_methods();
			$this->reset_cart();
			$this->set_sweden_shipping_address();

			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product_id,
					'quantity' => 1,
				)
			);
			// Store API may hydrate destination from session independently of WC_Customer.
			$this->store_api_request(
				'POST',
				'/cart/update-customer',
				array(
					'shipping_address' => array(
						'country'   => 'SE',
						'postcode'  => '11122',
						'city'      => 'Stockholm',
						'address_1' => '1 Test Street',
					),
					'billing_address'  => array(
						'country'   => 'SE',
						'postcode'  => '11122',
						'city'      => 'Stockholm',
						'address_1' => '1 Test Street',
						'email'     => 'shopper@example.com',
					),
				)
			);
			$blocks = $this->store_api_offers_free_shipping();

			$this->assertSame(
				$classic,
				$blocks,
				"Classic and Store API must agree on free-shipping eligibility ({$label})."
			);
			$this->assertSame( $expected, $blocks, "Unexpected eligibility for {$label}." );
		}
	}

	/**
	 * Pins the customer to the free-shipping zone location.
	 *
	 * {@see StoreApiTestCase::reset_cart()} replaces the customer with defaults
	 * (often US), which would miss a Sweden-only shipping zone.
	 */
	private function set_sweden_shipping_address(): void {
		WC()->customer->set_shipping_country( 'SE' );
		WC()->customer->set_shipping_state( '' );
		WC()->customer->set_shipping_postcode( '11122' );
		WC()->customer->set_billing_country( 'SE' );
		WC()->customer->set_billing_postcode( '11122' );
	}

	/**
	 * Boots SEK base / EUR active from golden fixtures.
	 */
	private function boot_foreign(): void {
		$this->boot_plugin( Golden::currencies(), Golden::FOREIGN, Golden::BASE, 2 );
	}

	/**
	 * Creates a free-shipping zone requiring the golden base-currency min amount.
	 */
	private function free_shipping_with_min(): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'M18 Free Shipping Parity' );
		$zone->add_location( 'SE', 'country' );
		$this->instance_id = (int) $zone->add_shipping_method( 'free_shipping' );
		$zone->save();

		update_option(
			'woocommerce_free_shipping_' . $this->instance_id . '_settings',
			array(
				'enabled'          => 'yes',
				'title'            => 'Free shipping',
				'requires'         => 'min_amount',
				'min_amount'       => Golden::FREE_SHIPPING_MIN,
				'ignore_discounts' => 'no',
			)
		);

		$this->zone = $zone;

		WC()->shipping()->unregister_shipping_methods();
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * Creates a physical product priced in the store base currency.
	 *
	 * @param string $regular Regular price in base currency.
	 */
	private function shippable_product( string $regular ): \WC_Product_Simple {
		$product = $this->simple_product( $regular );
		$product->set_virtual( false );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	/**
	 * Adds a physical product to the cart through the Store API.
	 *
	 * @param string $regular Regular price in base currency.
	 */
	private function add_shippable_item( string $regular ): void {
		$product = $this->shippable_product( $regular );

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
	 * Whether free shipping appears among Store API cart shipping rates.
	 */
	private function store_api_offers_free_shipping(): bool {
		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		if ( empty( $cart['shipping_rates'] ) ) {
			return false;
		}

		foreach ( $cart['shipping_rates'] as $package ) {
			foreach ( (array) ( $package['shipping_rates'] ?? array() ) as $rate ) {
				$rate_id = (string) ( $rate['rate_id'] ?? $rate['method_id'] ?? '' );
				if ( 0 === strpos( $rate_id, 'free_shipping' ) || 'free_shipping' === ( $rate['method_id'] ?? '' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether free shipping is offered on the classic cart path.
	 *
	 * @param int $product_id Product to place in the cart.
	 */
	private function classic_offers_free_shipping( int $product_id ): bool {
		return (bool) $this->as_storefront_request(
			function () use ( $product_id ): bool {
				$this->reset_cart();
				WC()->customer->set_shipping_country( 'SE' );
				WC()->customer->set_shipping_postcode( '11122' );
				WC()->cart->add_to_cart( $product_id, 1 );
				WC()->cart->calculate_totals();

				$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );

				foreach ( $packages as $package ) {
					foreach ( $package['rates'] as $rate ) {
						if ( 'free_shipping' === $rate->get_method_id() ) {
							return true;
						}
					}
				}

				return false;
			}
		);
	}
}
