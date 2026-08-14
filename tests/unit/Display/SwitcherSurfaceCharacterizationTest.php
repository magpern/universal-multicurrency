<?php
/**
 * Unit tests documenting current switcher surface hooks and assets.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherAssets;
use UMC\Display\SwitcherShortcode;
use UMC\Display\SwitcherViewModelFactory;

/**
 * Characterizes the pre-M23 switcher surface architecture.
 */
final class SwitcherSurfaceCharacterizationTest extends TestCase {

	public function test_switcher_assets_registers_expected_hooks(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Display/SwitcherAssets.php'
		);

		$this->assertStringContainsString( "add_action( 'wp_enqueue_scripts', array( \$this, 'register_assets' ), 5 )", $source );
		$this->assertStringContainsString( "add_action( 'wp_enqueue_scripts', array( \$this, 'maybe_enqueue_when_present' ), 20 )", $source );
	}

	public function test_shortcode_enqueues_assets_and_uses_shared_renderer(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Display/SwitcherShortcode.php'
		);

		$this->assertStringContainsString( '$this->assets->ensure_enqueued()', $source );
		$this->assertStringContainsString( '$this->renderer->render', $source );
	}

	public function test_view_model_factory_exposes_instance_counter_reset(): void {
		$this->assertTrue( method_exists( SwitcherViewModelFactory::class, 'reset_instance_counter' ) );
	}

	public function test_shortcode_tags_are_stable(): void {
		$this->assertSame( 'universal_multicurrency_switcher', SwitcherShortcode::TAG_PRIMARY );
		$this->assertSame( 'umc_switcher', SwitcherShortcode::TAG_LEGACY );
	}

	public function test_switcher_assets_exposes_shared_handles(): void {
		$this->assertSame( 'umc-switcher', SwitcherAssets::STYLE_HANDLE );
		$this->assertSame( 'umc-switcher', SwitcherAssets::SCRIPT_HANDLE );
	}
}
