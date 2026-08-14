<?php
/**
 * Unit tests for bundled presentation asset registry.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\CurrencyPresentationAssetRegistry;

/**
 * Covers registry-only validation and safe asset resolution.
 */
final class CurrencyPresentationAssetRegistryTest extends TestCase {

	protected function setUp(): void {
		if ( ! defined( 'UMC_PLUGIN_FILE' ) ) {
			define( 'UMC_PLUGIN_FILE', dirname( __DIR__, 3 ) . '/universal-multicurrency.php' );
		}

		if ( ! defined( 'UMC_VERSION' ) ) {
			define( 'UMC_VERSION', '0.21.0' );
		}
	}

	public function test_valid_regions_resolve_to_readable_assets(): void {
		foreach ( CurrencyPresentationAssetRegistry::region_ids() as $region ) {
			$this->assertTrue( CurrencyPresentationAssetRegistry::is_valid_region( $region ) );
			$this->assertNotNull( CurrencyPresentationAssetRegistry::asset_path( $region ) );
			$this->assertStringContainsString( $region . '.svg', (string) CurrencyPresentationAssetRegistry::asset_url( $region ) );
		}
	}

	public function test_arbitrary_region_strings_are_rejected(): void {
		$this->assertFalse( CurrencyPresentationAssetRegistry::is_valid_region( '../SE' ) );
		$this->assertFalse( CurrencyPresentationAssetRegistry::is_valid_region( 'XX' ) );
		$this->assertNull( CurrencyPresentationAssetRegistry::asset_path( '../SE' ) );
		$this->assertNull( CurrencyPresentationAssetRegistry::asset_url( 'javascript:alert(1)' ) );
	}

	public function test_eu_region_is_registered(): void {
		$this->assertTrue( CurrencyPresentationAssetRegistry::is_valid_region( CurrencyPresentationAssetRegistry::REGION_EU ) );
	}
}
