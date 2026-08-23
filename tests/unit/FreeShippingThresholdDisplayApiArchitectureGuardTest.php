<?php
/**
 * Architecture guard for the free-shipping threshold display API (v1.2.0).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Order\OrderSnapshot;
use UMC\PersistedKeys;
use UMC\Settings;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Standing “one calculator” + public-API purity + persistence freeze guards.
 */
final class FreeShippingThresholdDisplayApiArchitectureGuardTest extends TestCase {

	use SourceGuardTrait;

	public function test_persistence_baselines_unchanged_from_v111(): void {
		$this->assertSame( 7, Settings::SCHEMA_VERSION );
		$this->assertSame( 5, OrderSnapshot::SCHEMA_VERSION );
		$this->assertSame( 11, PersistedKeys::INVENTORY_VERSION );
	}

	public function test_public_api_layer_contains_no_monetary_arithmetic(): void {
		$root  = dirname( __DIR__, 2 );
		$files = array(
			$root . '/src/api.php',
			$root . '/src/PublicApi/FreeShippingThresholdDisplayService.php',
		);

		$this->assert_pattern_absent_from(
			$files,
			'/\bConverter::apply_rate\b/',
			'Public API must not call Converter::apply_rate directly.'
		);
		$this->assert_pattern_absent_from(
			$files,
			'/\*\s*\$rate\b|\/\s*\$rate\b/',
			'Public API must not multiply/divide by rates.'
		);
		$this->assert_pattern_absent_from(
			$files,
			'/\bround\s*\(/',
			'Public API must not round money.'
		);
		$this->assert_pattern_absent_from(
			$files,
			'/update_option\s*\(\s*[\'\"]woocommerce_free_shipping/',
			'Public API must not write shipping settings.'
		);
	}

	public function test_shipping_conversion_and_display_share_resolver_type(): void {
		$root = dirname( __DIR__, 2 );

		$shipping = (string) file_get_contents( $root . '/src/Integration/ShippingConversion.php' );
		$display  = (string) file_get_contents( $root . '/src/PublicApi/FreeShippingThresholdDisplayService.php' );
		$plugin   = (string) file_get_contents( $root . '/src/Plugin.php' );

		$this->assertStringContainsString( 'FreeShippingThresholdResolver', $shipping );
		$this->assertStringContainsString( 'FreeShippingThresholdResolver', $display );
		$this->assertStringContainsString( 'new FreeShippingThresholdResolver', $plugin );
		$this->assertStringContainsString( 'FreeShippingThresholdDisplayService', $plugin );
		$this->assertSame(
			1,
			substr_count( $plugin, 'new FreeShippingThresholdResolver' ),
			'Plugin must compose exactly one shared FreeShippingThresholdResolver instance.'
		);
	}

	public function test_api_facade_file_exists_for_function_detection(): void {
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/src/api.php' );
		$composer = (string) file_get_contents( dirname( __DIR__, 2 ) . '/composer.json' );
		$this->assertStringContainsString( 'src/api.php', $composer );
	}
}
