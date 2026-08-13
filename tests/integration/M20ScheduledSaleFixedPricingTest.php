<?php
/**
 * M20 acceptance: WooCommerce scheduled sale boundaries for fixed pricing.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * @covers \UMC\Pricing\ProductSaleStateResolver
 * @covers \UMC\Pricing\ProductPriceResolutionService
 */
final class M20ScheduledSaleFixedPricingTest extends M20PricingTestCase {

	public function test_simple_fixed_price_before_sale_schedule(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100', '80' );
		$product->set_date_on_sale_from( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$product->set_date_on_sale_to( gmdate( 'Y-m-d H:i:s', time() + ( 10 * DAY_IN_SECONDS ) ) );
		$product->save();

		$this->save_fixed(
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

		$product = wc_get_product( $product->get_id() );
		$this->assertFalse( $product->is_on_sale() );
		$this->assertSame( '1100.00', $product->get_price() );
	}

	public function test_simple_fixed_price_during_sale_schedule(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100', '80' );
		$product->set_date_on_sale_from( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		$product->set_date_on_sale_to( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$product->save();

		$this->save_fixed(
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

		$product = wc_get_product( $product->get_id() );
		$this->assertTrue( $product->is_on_sale() );
		$this->assertSame( '880.00', $product->get_price() );
	}

	public function test_simple_fixed_price_after_sale_schedule(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100', '80' );
		$product->set_date_on_sale_from( gmdate( 'Y-m-d H:i:s', time() - ( 10 * DAY_IN_SECONDS ) ) );
		$product->set_date_on_sale_to( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		$product->save();

		$this->save_fixed(
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

		$product = wc_get_product( $product->get_id() );
		$this->assertFalse( $product->is_on_sale() );
		$this->assertSame( '1100.00', $product->get_price() );
	}

	public function test_active_sale_without_fixed_sale_converts_base_sale(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100', '80' );
		$product->set_date_on_sale_from( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		$product->set_date_on_sale_to( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$product->save();

		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$product = wc_get_product( $product->get_id() );
		$this->assertTrue( $product->is_on_sale() );
		$this->assertSame( '920.00', $product->get_price() );
	}

	public function test_variation_scheduled_sale_gates_fixed_sale_amount(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair      = $this->variable_product_pair( '50', '100' );
		$variation = wc_get_product( $pair['low']->get_id() );
		$variation->set_sale_price( '40' );
		$variation->set_date_on_sale_from( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		$variation->set_date_on_sale_to( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
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
		$this->assertSame( '440.00', $variation->get_price() );
	}
}
