<?php
/**
 * M20 acceptance: variation fixed pricing through real WooCommerce hooks.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;
use WC_Product_Variation;

/**
 * @covers \UMC\Pricing\ProductPriceResolutionService
 * @covers \UMC\Integration\PriceHooks
 */
final class M20VariableFixedPricingTest extends M20PricingTestCase {

	public function test_variation_fixed_regular_price_is_authoritative(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$variation = wc_get_product( $pair['low']->get_id() );
		$this->assertSame( '550.00', $variation->get_regular_price() );
		$this->assertSame( '550.00', $variation->get_price() );
	}

	public function test_variation_fixed_sale_follows_woocommerce_sale_state(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair      = $this->variable_product_pair( '50', '100' );
		$variation = wc_get_product( $pair['low']->get_id() );
		$variation->set_sale_price( '40' );
		$variation->save();

		$this->save_fixed(
			$variation->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '550',
						'sale'    => '440',
					),
				),
				'EUR'
			)
		);

		$variation = wc_get_product( $variation->get_id() );
		$this->assertTrue( $variation->is_on_sale() );
		$this->assertSame( '440.00', $variation->get_price() );
		$this->assertSame( '550.00', $variation->get_regular_price() );
	}

	public function test_variation_fixed_regular_wins_when_sale_inactive(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '550',
						'sale'    => '440',
					),
				),
				'EUR'
			)
		);

		$variation = wc_get_product( $pair['low']->get_id() );
		$this->assertFalse( $variation->is_on_sale() );
		$this->assertSame( '550.00', $variation->get_price() );
	}

	public function test_variation_active_sale_without_fixed_sale_converts_base_sale(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair      = $this->variable_product_pair( '50', '100' );
		$variation = wc_get_product( $pair['low']->get_id() );
		$variation->set_sale_price( '40' );
		$variation->save();

		$this->save_fixed(
			$variation->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$variation = wc_get_product( $variation->get_id() );
		$this->assertTrue( $variation->is_on_sale() );
		$this->assertSame( '460.00', $variation->get_price() );
	}

	public function test_variation_without_fixed_entry_uses_conversion(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );

		$low  = wc_get_product( $pair['low']->get_id() );
		$high = wc_get_product( $pair['high']->get_id() );

		$this->assertSame( '575.00', $low->get_price() );
		$this->assertSame( '1150.00', $high->get_price() );
	}

	public function test_mixed_fixed_and_converted_variations_expose_correct_range(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$parent = wc_get_product( $pair['parent']->get_id() );
		$prices = $parent->get_variation_prices();

		$this->assertSame( '550.00', $prices['price'][ $pair['low']->get_id() ] );
		$this->assertSame( '1150.00', $prices['price'][ $pair['high']->get_id() ] );
		$this->assertSame( '550.00', $parent->get_variation_price( 'min', true ) );
		$this->assertSame( '1150.00', $parent->get_variation_price( 'max', true ) );
	}

	public function test_selected_variation_returns_fixed_active_currency_price(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$selected = wc_get_product( $pair['low']->get_id() );
		$this->assertInstanceOf( WC_Product_Variation::class, $selected );
		$this->assertSame( '550.00', $selected->get_price() );
	}

	public function test_fixed_variation_unaffected_by_rate_change(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$this->activate( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$variation = wc_get_product( $pair['low']->get_id() );
		$this->assertSame( '550.00', $variation->get_price() );
	}

	public function test_converted_variation_changes_when_rate_changes(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair   = $this->variable_product_pair( '50', '100' );
		$before = wc_get_product( $pair['high']->get_id() )->get_price();

		$this->activate( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$after = wc_get_product( $pair['high']->get_id() )->get_price();

		$this->assertSame( '1150.00', $before );
		$this->assertSame( '2000.00', $after );
	}

	public function test_disabled_currency_ignores_retained_variation_fixed_price(): void {
		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => true,
				),
			),
			'SEK'
		);
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => false,
				),
			),
			'EUR'
		);
		$variation = wc_get_product( $pair['low']->get_id() );
		$this->assertSame( 50.0, (float) $variation->get_price() );
		$this->assertSame( '550.00', $this->repository->get( $variation->get_id() )->get_currency( 'SEK' )?->regular() );
	}

	public function test_re_enabled_currency_restores_variation_fixed_price(): void {
		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => true,
				),
			),
			'SEK'
		);
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => false,
				),
			),
			'EUR'
		);
		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => true,
				),
			),
			'SEK'
		);

		$variation = wc_get_product( $pair['low']->get_id() );
		$this->assertSame( '550.00', $variation->get_price() );
	}
}
