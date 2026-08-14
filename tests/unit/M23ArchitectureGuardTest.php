<?php
/**
 * Milestone 23 architecture guard tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherBlock;
use UMC\Display\SwitcherRenderer;
use UMC\Order\OrderSnapshot;
use UMC\PersistedKeys;
use UMC\Settings;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Guards M23 invariants: no schema drift, no Node toolchain, one renderer.
 */
final class M23ArchitectureGuardTest extends TestCase {

	use SourceGuardTrait;

	public function test_persistence_baselines_unchanged(): void {
		$this->assertSame( 7, Settings::SCHEMA_VERSION );
		$this->assertSame( 5, OrderSnapshot::SCHEMA_VERSION );
		$this->assertSame( 10, PersistedKeys::INVENTORY_VERSION );
	}

	public function test_no_node_toolchain_artifacts(): void {
		$root = dirname( __DIR__, 2 );

		$this->assertFileDoesNotExist( $root . '/package.json' );
		$this->assertFileDoesNotExist( $root . '/package-lock.json' );
	}

	public function test_block_delegates_to_switcher_renderer(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Display/SwitcherBlock.php'
		);

		$this->assertStringContainsString( 'SwitcherRenderer', $source );
		$this->assertStringNotContainsString( 'class SwitcherBlockRenderer', $source );
	}

	public function test_block_name_is_frozen(): void {
		$this->assertSame( 'universal-multicurrency/currency-switcher', SwitcherBlock::BLOCK_NAME );
	}

	public function test_no_elementor_or_widget_subsystems(): void {
		$root = dirname( __DIR__, 2 ) . '/src/Display';

		$this->assertSame(
			array(),
			$this->grep_source_tree( $root, '/\\bElementor\\b|extends\\s+WP_Widget|Navigation_Link/i' )
		);
	}

	public function test_presence_scanner_has_no_unbounded_template_queries(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Display/SwitcherPresence.php'
		);

		$this->assertStringNotContainsString( 'get_block_templates', $source );
		$this->assertStringNotContainsString( 'get_posts', $source );
		$this->assertStringNotContainsString( 'wp_template_part', $source );
	}

	/**
	 * @return list<string>
	 */
	private function grep_source_tree( string $root, string $pattern ): array {
		$matches = array();
		$files   = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $files as $file ) {
			if ( ! $file instanceof \SplFileInfo || ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}

			$contents = (string) file_get_contents( $file->getPathname() );

			if ( 1 === preg_match( $pattern, $contents ) ) {
				$matches[] = str_replace( $root . '/', '', $file->getPathname() );
			}
		}

		sort( $matches );

		return $matches;
	}
}
