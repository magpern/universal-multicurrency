<?php
/**
 * Characterization of PriceHooks registration baseline.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Integration\PriceHooks;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * @covers \UMC\Integration\PriceHooks
 */
final class PriceHooksCharacterizationTest extends TestCase {

	use SourceGuardTrait;

	public function test_price_hooks_register_at_priority_ten(): void {
		$source = (string) file_get_contents( $this->root() . '/src/Integration/PriceHooks.php' );

		$this->assertStringContainsString( 'public const FILTER_PRIORITY = 10;', $source );
		$this->assertStringContainsString( 'ProductPriceResolutionService', $source );
		$this->assertStringNotContainsString( 'PriceConversionService::convert', $source );
	}

	public function test_product_price_resolution_service_never_converts_fixed_amounts(): void {
		$source = (string) file_get_contents( $this->root() . '/src/Pricing/ProductPriceResolutionService.php' );

		$this->assertStringContainsString( 'return $fixed->regular();', $source );
		$this->assertStringContainsString( 'return $fixed->sale();', $source );
	}

	private function root(): string {
		return dirname( __DIR__, 2 );
	}
}
