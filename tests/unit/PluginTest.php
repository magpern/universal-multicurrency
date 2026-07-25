<?php
/**
 * Milestone 0 smoke tests for the plugin skeleton.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Plugin;

/**
 * Verifies the PSR-4 autoloader and the composition root.
 */
final class PluginTest extends TestCase {

	public function test_plugin_class_is_autoloadable(): void {
		$this->assertTrue( class_exists( Plugin::class ) );
	}

	public function test_instance_returns_the_same_object(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	public function test_init_is_idempotent(): void {
		$plugin = Plugin::instance();
		$plugin->init();
		$plugin->init();
		$this->assertSame( $plugin, Plugin::instance() );
	}
}
