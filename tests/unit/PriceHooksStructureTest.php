<?php
/**
 * Structural guarantees for the price hooks (WordPress-free).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Asserts the price-hook layer covers the required filters and never swallows
 * exceptions. Behavioural conversion is covered by the integration suite.
 */
final class PriceHooksStructureTest extends TestCase {

	private function source(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Integration/PriceHooks.php' );
	}

	/**
	 * @dataProvider required_hooks
	 */
	public function test_registers_required_hook( string $hook ): void {
		$this->assertStringContainsString( $hook, $this->source() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function required_hooks(): array {
		return array(
			'simple price'          => array( 'woocommerce_product_get_price' ),
			'simple regular'        => array( 'woocommerce_product_get_regular_price' ),
			'simple sale'           => array( 'woocommerce_product_get_sale_price' ),
			'variation price'       => array( 'woocommerce_product_variation_get_price' ),
			'variation regular'     => array( 'woocommerce_product_variation_get_regular_price' ),
			'variation sale'        => array( 'woocommerce_product_variation_get_sale_price' ),
			'bulk price'            => array( 'woocommerce_variation_prices_price' ),
			'bulk regular'          => array( 'woocommerce_variation_prices_regular_price' ),
			'bulk sale'             => array( 'woocommerce_variation_prices_sale_price' ),
			'variation prices hash' => array( 'woocommerce_get_variation_prices_hash' ),
		);
	}

	public function test_does_not_swallow_exceptions(): void {
		// Narrow exception handling: no try/catch of any kind — unrelated
		// programming errors must propagate, not be masked as raw prices.
		$this->assertStringNotContainsString( 'catch', $this->source() );
	}

	public function test_variation_prices_path_documents_reentrancy_bypass(): void {
		$this->assertStringContainsString(
			'Intentionally bypasses',
			$this->source(),
			'Bulk variation-price filters must document the ADR-0033 re-entrancy bypass.'
		);
	}
}
