<?php
/**
 * Unit tests for third-party naming allowlist guard updates.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;

/**
 * Documents the Compatibility registry allowlist used by boundary guards.
 */
final class CompatibilityNamingAllowlistTest extends TestCase {

	public function test_allowlist_contains_registry_files(): void {
		$root = dirname( __DIR__, 3 );

		$this->assertFileExists( $root . '/src/Compatibility/Registry/IntegrationRegistry.php' );
		$this->assertFileExists( $root . '/src/Compatibility/Registry/ThemeCompatibilityRegistry.php' );
		$this->assertFileExists( $root . '/src/Compatibility/Registry/CachePluginRegistry.php' );
	}
}
