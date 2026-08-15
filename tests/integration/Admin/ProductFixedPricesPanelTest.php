<?php
/**
 * M20 acceptance: admin fixed-price persistence security.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\ProductFixedPricesPanel;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * @covers \UMC\Admin\ProductFixedPricesPanel
 */
final class ProductFixedPricesPanelTest extends WP_UnitTestCase {

	/**
	 * @var FixedPriceRepository
	 */
	private FixedPriceRepository $repository;

	/**
	 * @var ProductFixedPricesPanel
	 */
	private ProductFixedPricesPanel $panel;

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );
		( new Settings() )->save(
			array(
				'currencies' => array(
					'SEK' => array(
						'rate'    => '11.50',
						'enabled' => true,
					),
					'GBP' => array(
						'rate'    => '0.85',
						'enabled' => false,
					),
				),
			)
		);

		$settings         = new Settings();
		$registry         = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$this->repository = new FixedPriceRepository( 'EUR' );
		$this->panel      = new ProductFixedPricesPanel( $settings, $registry, $this->repository );
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_unauthorized_user_cannot_persist_fixed_prices(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$_POST['umc_fixed_prices'] = array(
			'SEK' => array(
				'regular' => '1100',
			),
		);

		$this->panel->save_simple_product( $product_id );

		$this->assertSame( '', (string) get_post_meta( $product_id, FixedPriceDocument::META_KEY, true ) );
	}

	public function test_malformed_payload_does_not_corrupt_existing_valid_entries(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$this->repository->save(
			$product_id,
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '1100',
						'sale'    => '900',
					),
				),
				'EUR'
			)
		);

		$_POST['umc_fixed_prices'] = array(
			'SEK' => 'not-an-array',
			'XXX' => array(
				'regular' => 'bad',
				'sale'    => 'worse',
			),
		);

		$this->panel->save_simple_product( $product_id );

		$document = $this->repository->get( $product_id );
		$this->assertSame( '1100.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '900.00', $document->get_currency( 'SEK' )?->sale() );
	}

	public function test_base_currency_injection_is_stripped_on_save(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$_POST['umc_fixed_prices'] = array(
			'EUR' => array(
				'regular' => '1',
			),
			'SEK' => array(
				'regular' => '1100',
			),
		);

		$this->panel->save_simple_product( $product_id );

		$document = $this->repository->get( $product_id );
		$this->assertNull( $document->get_currency( 'EUR' ) );
		$this->assertSame( '1100.00', $document->get_currency( 'SEK' )?->regular() );
	}

	public function test_invalid_decimal_and_sale_pair_are_rejected(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$_POST['umc_fixed_prices'] = array(
			'SEK' => array(
				'regular' => 'foo',
			),
			'GBP' => array(
				'regular' => '79',
				'sale'    => '99',
			),
		);

		$this->panel->save_simple_product( $product_id );

		$document = $this->repository->get( $product_id );
		$this->assertNull( $document->get_currency( 'SEK' ) );
		$this->assertNull( $document->get_currency( 'GBP' ) );
	}

	public function test_disabled_currency_values_are_retained_on_partial_save(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$this->repository->save(
			$product_id,
			FixedPriceDocument::from_array(
				array(
					'GBP' => array(
						'regular' => '79',
					),
				),
				'EUR'
			)
		);

		$_POST['umc_fixed_prices'] = array(
			'SEK' => array(
				'regular' => '1100',
			),
		);

		$this->panel->save_simple_product( $product_id );

		$document = $this->repository->get( $product_id );
		$this->assertSame( '79.00', $document->get_currency( 'GBP' )?->regular() );
		$this->assertSame( '1100.00', $document->get_currency( 'SEK' )?->regular() );
	}

	/**
	 * M24 WP1 characterization: locks the exact document shape produced by a
	 * variation save through {@see ProductFixedPricesPanel::save_variation()}.
	 * M24's bulk seed/clear orchestration must produce byte-identical
	 * documents for equivalent input on the same variation.
	 */
	public function test_variation_save_persists_regular_and_sale_price(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '50' );
		$variation->save();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$_POST['umc_fixed_prices_var'] = array(
			0 => array(
				'SEK' => array(
					'regular' => '575',
					'sale'    => '460',
				),
			),
		);

		$this->panel->save_variation( $variation->get_id(), 0 );

		$document = $this->repository->get( $variation->get_id() );
		$this->assertSame( '575.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '460.00', $document->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * M24 WP1 characterization: a variation save must never write to the
	 * parent product's or a sibling variation's fixed-price meta. M24's
	 * variation-native seeding (ADR-0029) depends on this isolation holding.
	 */
	public function test_variation_save_does_not_touch_parent_or_sibling(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation_a = new \WC_Product_Variation();
		$variation_a->set_parent_id( $parent->get_id() );
		$variation_a->set_regular_price( '50' );
		$variation_a->save();

		$variation_b = new \WC_Product_Variation();
		$variation_b->set_parent_id( $parent->get_id() );
		$variation_b->set_regular_price( '100' );
		$variation_b->save();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$_POST['umc_fixed_prices_var'] = array(
			0 => array(
				'SEK' => array(
					'regular' => '575',
				),
			),
		);

		$this->panel->save_variation( $variation_a->get_id(), 0 );

		$this->assertSame( '575.00', $this->repository->get( $variation_a->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertNull( $this->repository->get( $variation_b->get_id() )->get_currency( 'SEK' ) );
		$this->assertNull( $this->repository->get( $parent->get_id() )->get_currency( 'SEK' ) );
	}

	/**
	 * M24 WP1 characterization: base-currency injection is stripped on the
	 * variation save path exactly as it is on the simple-product path.
	 */
	public function test_variation_base_currency_injection_is_stripped(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '50' );
		$variation->save();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$_POST['umc_fixed_prices_var'] = array(
			0 => array(
				'EUR' => array(
					'regular' => '1',
				),
				'SEK' => array(
					'regular' => '575',
				),
			),
		);

		$this->panel->save_variation( $variation->get_id(), 0 );

		$document = $this->repository->get( $variation->get_id() );
		$this->assertNull( $document->get_currency( 'EUR' ) );
		$this->assertSame( '575.00', $document->get_currency( 'SEK' )?->regular() );
	}

	/**
	 * M24 WP1 characterization: a malformed amount on the variation save
	 * path is rejected exactly as it is on the simple-product path.
	 */
	public function test_variation_malformed_amount_is_rejected(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '50' );
		$variation->save();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$_POST['umc_fixed_prices_var'] = array(
			0 => array(
				'SEK' => array(
					'regular' => 'not-a-number',
				),
			),
		);

		$this->panel->save_variation( $variation->get_id(), 0 );

		$document = $this->repository->get( $variation->get_id() );
		$this->assertNull( $document->get_currency( 'SEK' ) );
	}

	public function test_arbitrary_meta_keys_cannot_be_mass_assigned(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$admin_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $admin_id );

		$_POST['umc_fixed_prices'] = array(
			'SEK' => array(
				'regular'        => '1100',
				'evil_meta'      => 'owned',
				'schema_version' => '999',
			),
		);

		$this->panel->save_simple_product( $product_id );

		$raw = (string) get_post_meta( $product_id, FixedPriceDocument::META_KEY, true );
		$this->assertStringNotContainsString( 'evil_meta', $raw );
		$this->assertStringNotContainsString( 'owned', $raw );
	}
}
