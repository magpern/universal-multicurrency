<?php
/**
 * M20 acceptance: Classic and Store API fixed-price parity.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;

/**
 * @covers \UMC\Integration\PriceHooks
 */
final class M20FixedPriceStoreApiTest extends StoreApiTestCase {

	/**
	 * @var FixedPriceRepository
	 */
	private FixedPriceRepository $repository;

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

		$this->repository = new FixedPriceRepository( 'EUR' );
	}

	public function tear_down(): void {
		delete_option( 'woocommerce_cheque_settings' );
		WC()->payment_gateways()->init();
		parent::tear_down();
	}

	public function test_store_api_simple_fixed_regular_price(): void {
		$this->boot_plugin_with_repository( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$cart = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product->get_id(),
					'quantity' => 1,
				)
			)
		);

		$this->assertSame( $this->minor_units( 1100, 2 ), $cart['items'][0]['prices']['price'] );
	}

	public function test_store_api_simple_converted_fallback(): void {
		$this->boot_plugin_with_repository( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		$cart = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product->get_id(),
					'quantity' => 1,
				)
			)
		);

		$this->assertSame( $this->minor_units( 1150, 2 ), $cart['items'][0]['prices']['price'] );
	}

	public function test_store_api_fixed_sale_price(): void {
		$this->boot_plugin_with_repository( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100', '80' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '1100',
						'sale'    => '880',
					),
				),
				'EUR'
			)
		);

		$cart = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product->get_id(),
					'quantity' => 1,
				)
			)
		);

		$this->assertSame( $this->minor_units( 880, 2 ), $cart['items'][0]['prices']['price'] );
	}

	public function test_store_api_variation_fixed_price(): void {
		$this->boot_plugin_with_repository( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$parent   = $this->variable_product( '50', '100' );
		$children = $parent->get_children();
		$this->repository->save(
			(int) $children[0],
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$cart = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => (int) $children[0],
					'quantity' => 1,
				)
			)
		);

		$this->assertSame( $this->minor_units( 550, 2 ), $cart['items'][0]['prices']['price'] );
	}

	public function test_classic_and_store_api_fixed_cart_totals_agree(): void {
		$this->boot_plugin_with_repository( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$blocks = $this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product->get_id(),
					'quantity' => 2,
				)
			)
		)['totals']['total_items'];

		$classic = $this->as_storefront_request(
			function () use ( $product ): string {
				$this->reset_cart();
				WC()->cart->add_to_cart( $product->get_id(), 2 );
				WC()->cart->calculate_totals();

				return (string) WC()->cart->get_subtotal();
			}
		);

		$this->assertSame( $this->minor_units( 2200, 2 ), (string) $blocks );
		$this->assertSame( 2200.0, (float) $classic );
	}

	public function test_store_api_currency_transition_recalculates_fixed_price(): void {
		$this->boot_plugin_with_repository( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$this->response_data(
			$this->store_api_request(
				'POST',
				'/cart/add-item',
				array(
					'id'       => $product->get_id(),
					'quantity' => 1,
				)
			)
		);

		$this->switch_currency( 'EUR' );
		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );
		$this->assertSame( $this->minor_units( 100, 2 ), $cart['items'][0]['prices']['price'] );
	}

	/**
	 * Boots plugin graph with a shared fixed-price repository.
	 *
	 * @param array<string, array<string, mixed>> $currencies Currency config.
	 * @param string                              $active     Active currency.
	 */
	private function boot_plugin_with_repository( array $currencies, string $active ): void {
		$this->boot_plugin( $currencies, $active );
		// Repository reads meta directly; boot_plugin already registers ProductPricingTestGraph.
		unset( $this->repository );
		$this->repository = new FixedPriceRepository( 'EUR' );
	}
}
