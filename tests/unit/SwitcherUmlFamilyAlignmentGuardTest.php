<?php
/**
 * Architecture guards for UML-family selector alignment (ADR-0037).
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
 * Persistence freeze, no UML runtime dependency, no authority-layer coupling.
 */
final class SwitcherUmlFamilyAlignmentGuardTest extends TestCase {

	use SourceGuardTrait;

	public function test_persistence_baselines_unchanged(): void {
		$this->assertSame( 8, Settings::SCHEMA_VERSION );
		$this->assertSame( 5, OrderSnapshot::SCHEMA_VERSION );
		$this->assertSame( 12, PersistedKeys::INVENTORY_VERSION );
	}

	public function test_runtime_php_does_not_import_uml_hooks(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\baiml-|\bdata-aiml-|\b--aiml-fs-|\baiml_floating/',
			'UMC runtime PHP must not reference UML private hooks.'
		);
	}

	public function test_currency_resolver_source_is_presentation_free(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/CurrencyResolver.php' );

		$this->assertStringNotContainsString( 'Presentation', $source );
		$this->assertStringNotContainsString( 'icon_overrides', $source );
		$this->assertStringNotContainsString( 'flag', $source );
		$this->assertStringContainsString( 'explicit', $source );
		$this->assertStringContainsString( 'session', $source );
		$this->assertStringContainsString( 'cookie', $source );
		$this->assertStringContainsString( 'user_preferred', $source );
	}

	public function test_cache_state_hash_source_excludes_presentation(): void {
		$service = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/CacheState/CacheStateService.php' );

		$this->assertStringNotContainsString( 'icon_overrides', $service );
		$this->assertStringNotContainsString( 'uml-family', $service );
		$this->assertStringNotContainsString( 'design.presentation', $service );
	}

	public function test_adr_and_convention_docs_exist(): void {
		$root = dirname( __DIR__, 2 );

		$this->assertFileExists( $root . '/docs/adr/0037-switcher-uml-family-alignment.md' );
		$this->assertFileExists( $root . '/docs/architecture/switcher-uml-family-alignment.md' );
		$this->assertFileExists( $root . '/docs/EDGE_CONTROL_CONVENTION.md' );
	}
}
