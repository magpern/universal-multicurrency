<?php
/**
 * M20 acceptance: fixed-vs-converted resolution instrumentation.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\ProductPriceResolution;
use UMC\Pricing\ProductPriceResolutionService;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * @covers \UMC\Pricing\ProductPriceResolutionService
 */
final class M20ProductPriceResolutionServiceTest extends M20PricingTestCase {

	public function test_fixed_regular_resolution_never_calls_converter(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$product = wc_get_product( $product->get_id() );
		$this->reset_conversion_counter();
		$product->get_regular_price();
		$this->assertSame( 0, $this->conversion_calls() );
	}

	public function test_converted_resolution_calls_converter(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		$this->reset_conversion_counter();
		wc_get_product( $product->get_id() )->get_regular_price();
		$this->assertGreaterThan( 0, $this->conversion_calls() );
	}

	public function test_variation_fixed_resolution_never_calls_converter(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$pair = $this->variable_product_pair( '50', '100' );
		$this->save_fixed(
			$pair['low']->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '550' ) ), 'EUR' )
		);

		$this->reset_conversion_counter();
		wc_get_product( $pair['low']->get_id() )->get_price();
		$this->assertSame( 0, $this->conversion_calls() );
	}
}
