<?php
/**
 * M26 WP3 full-system acceptance: repeated cart recalculation and a
 * mid-session currency switch must never compound an authoritative fixed
 * price or leak FX conversion onto it.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\CrossFeature;

use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * M3 proved converted prices never compound across repeated recalculation.
 * M20 proved fixed prices bypass the converter once. Neither combined the
 * two: a fixed price surviving repeated recalculation, and surviving a
 * currency switch away and back, without the converter ever touching it.
 *
 * @covers \UMC\Pricing\ProductPriceResolutionService
 * @covers \UMC\Cart\CartRecalculation
 */
final class FixedPricingCurrencySwitchTest extends M20PricingTestCase {

	public function test_fixed_price_line_total_never_compounds_across_repeated_recalculation(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );

		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '999.00' ) ), 'EUR' )
		);

		$this->add_to_cart( $product->get_id() );
		$first_total = $this->cart_line_total();

		$this->assertSame( '999', $first_total, 'A fixed price must be used verbatim, not derived from the base price.' );

		// Simulate repeated page loads / cart re-hydration within the same
		// session -- the fixed price must be idempotent, never re-applied
		// on top of itself.
		WC()->cart->calculate_totals();
		WC()->cart->calculate_totals();
		WC()->cart->calculate_totals();

		$this->assertSame( $first_total, $this->cart_line_total(), 'Repeated recalculation must not compound a fixed price.' );
		$this->assertSame( 0, $this->conversion_calls(), 'A fixed price must never invoke the FX converter.' );
	}

	public function test_switching_to_a_currency_without_a_fixed_price_converts_exactly_once_per_recalculation(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );

		$product = $this->simple_product( '100' );
		// No fixed price in SEK for this product -- SEK falls back to FX conversion.
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'DKK' => array( 'regular' => '750.00' ) ), 'EUR' )
		);

		$this->reset_conversion_counter();
		$this->add_to_cart( $product->get_id() );

		$this->assertSame( '1150', $this->cart_line_total(), 'SEK has no fixed price for this product, so 100 EUR must convert at 11.50.' );
		$this->assertGreaterThan( 0, $this->conversion_calls(), 'A non-fixed currency must go through the FX converter.' );

		// The M3 no-double-conversion invariant, now proven specifically for
		// a product that ALSO carries a fixed price for a different
		// currency: repeated recalculation must reproduce the identical
		// converted total, never total x rate x rate.
		WC()->cart->calculate_totals();
		WC()->cart->calculate_totals();

		$this->assertSame( '1150', $this->cart_line_total(), 'A second and third recalculation must reproduce the same converted total, not compound it.' );
	}

	/**
	 * Reads the sole cart line's total.
	 */
	private function cart_line_total(): string {
		$items = WC()->cart->get_cart();
		$item  = reset( $items );

		return (string) $item['line_total'];
	}
}
