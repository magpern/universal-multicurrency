<?php
/**
 * M20 acceptance: variation price cache invalidation for fixed pricing.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * @covers \UMC\Integration\PriceHooks
 */
final class M20VariationCacheIdentityTest extends M20PricingTestCase {

	public function test_variation_range_updates_when_fixed_regular_price_changes(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '550.00', $parent->get_variation_price( 'min', true ) );

		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '600' ) ), 'EUR' )
		);

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '600.00', $parent->get_variation_price( 'min', true ) );
	}

	public function test_variation_range_updates_when_fixed_sale_price_changes(): void {
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

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '440.00', $parent->get_variation_price( 'min', true ) );

		$this->save_fixed(
			$variation->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '550',
						'sale'    => '500',
					),
				),
				'EUR'
			)
		);

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '500.00', $parent->get_variation_price( 'min', true ) );
	}

	public function test_variation_range_updates_when_sale_transitions_inactive_to_active(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair      = $this->variable_product_pair( '50', '100' );
		$variation = wc_get_product( $pair['low']->get_id() );
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

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '550.00', $parent->get_variation_price( 'min', true ) );

		$variation->set_sale_price( '40' );
		$variation->save();
		wc_delete_product_transients( $pair['parent']->get_id() );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '440.00', $parent->get_variation_price( 'min', true ) );
	}

	public function test_variation_range_updates_when_sale_transitions_active_to_inactive(): void {
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

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '440.00', $parent->get_variation_price( 'min', true ) );

		$variation->set_sale_price( '' );
		$variation->save();
		wc_delete_product_transients( $pair['parent']->get_id() );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '550.00', $parent->get_variation_price( 'min', true ) );
	}

	public function test_variation_range_updates_when_active_currency_changes(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '550.00', $parent->get_variation_price( 'min', true ) );

		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '50.00', $parent->get_variation_price( 'min', true ) );
	}

	public function test_converted_variation_range_updates_when_fx_rate_changes(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );

		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '575.00', $parent->get_variation_price( 'min', true ) );

		$this->activate( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$parent = wc_get_product( $pair['parent']->get_id() );
		$this->assertSame( '1000.00', $parent->get_variation_price( 'min', true ) );
	}
}
