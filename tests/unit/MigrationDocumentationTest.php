<?php
/**
 * Structural guard: merchant migration documentation stays complete and consistent.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * `docs/MIGRATION.md` is the merchant migration contract. This test binds it to
 * ADR policy and cross-references in the documentation set.
 */
final class MigrationDocumentationTest extends TestCase {

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	private function read( string $relative_path ): string {
		$path = $this->root() . '/' . ltrim( $relative_path, '/' );

		if ( ! is_readable( $path ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
			throw new RuntimeException( 'Missing documentation file: ' . $relative_path );
		}

		return (string) file_get_contents( $path );
	}

	public function test_migration_doc_exists_with_required_sections(): void {
		$source = $this->read( 'docs/MIGRATION.md' );

		$required_headings = array(
			'## Migration overview',
			'## Supported migration path',
			'## Unsupported migration path',
			'## Manual migration checklist',
			'## Recommended deployment sequence',
			'## Rollback recommendations',
			'## Verification checklist',
			'## Common pitfalls',
			'## FAQ',
			'## UMC CSV format specification (future only)',
		);

		foreach ( $required_headings as $heading ) {
			$this->assertStringContainsString( $heading, $source, 'Missing section: ' . $heading );
		}
	}

	public function test_migration_doc_states_manual_only_and_no_foreign_import(): void {
		$source = $this->read( 'docs/MIGRATION.md' );

		$this->assertStringContainsString( 'ADR-0003', $source );
		$this->assertStringContainsString( 'ADR-0007', $source );
		$this->assertStringContainsString( 'ADR-0009', $source );
		$this->assertStringContainsString( 'does not read, import, or migrate', $source );
		$this->assertStringContainsString( 'Automatic import from foreign switchers', $source );
		$this->assertStringContainsString( 'no parser, no exporter, and no', $source );
		$this->assertStringContainsString( 'admin UI', $source );
		$this->assertStringContainsString( 'WooCommerce orders, refunds, products', $source );
		$this->assertStringContainsString( 'recreated manually', $source );
	}

	public function test_migration_csv_spec_defines_version_one_columns(): void {
		$source = $this->read( 'docs/MIGRATION.md' );

		$this->assertStringContainsString( 'umc_csv_version', $source );
		$this->assertStringContainsString( 'currency_code', $source );
		$this->assertStringContainsString( 'rate', $source );
		$this->assertStringContainsString( 'umc_settings.schema_version', $source );

		foreach ( array( 'enabled', 'symbol', 'position', 'decimals', 'rate_updated_at' ) as $column ) {
			$this->assertStringContainsString( $column, $source, 'CSV column missing: ' . $column );
		}
	}

	public function test_compatibility_doc_links_migration_playbook(): void {
		$source = $this->read( 'docs/COMPATIBILITY.md' );

		$this->assertStringContainsString( '## Migrating from another currency switcher', $source );
		$this->assertStringContainsString( 'MIGRATION.md', $source );
		$this->assertStringContainsString( 'Automatic import from foreign plugin options', $source );
		$this->assertStringContainsString( 'Admin CSV import in the Release Candidate', $source );
	}

	public function test_readme_and_deployment_reference_migration_doc(): void {
		$this->assertStringContainsString( 'docs/MIGRATION.md', $this->read( 'README.md' ) );
		$this->assertStringContainsString( 'docs/MIGRATION.md', $this->read( 'docs/DEPLOYMENT.md' ) );
		$this->assertStringContainsString( 'MIGRATION.md', $this->read( 'docs/ROADMAP.md' ) );
		$this->assertStringContainsString( 'MIGRATION.md', $this->read( 'docs/ARCHITECTURE.md' ) );
	}

	public function test_readme_txt_exists_and_documents_manual_migration(): void {
		$readme = $this->read( 'readme.txt' );

		$this->assertStringContainsString( 'Stable tag: 1.1.1', $readme );
		$this->assertStringContainsString( '= 1.0.0 =', $readme );
		$this->assertStringContainsString( '= 0.19.0 =', $readme );
		$this->assertStringContainsString( '= 0.18.0 =', $readme );
		$this->assertStringContainsString( '= 0.17.0 =', $readme );
		$this->assertStringContainsString( '= 0.15.0 =', $readme );
		$this->assertStringContainsString( '= 0.14.0 =', $readme );
		$this->assertStringContainsString( '= 0.13.0 =', $readme );
		$this->assertStringContainsString( '= 0.12.1 =', $readme );
		$this->assertStringContainsString( '= 0.12.0 =', $readme );
		$this->assertStringContainsString( '= 0.11.0 =', $readme );
		$this->assertStringContainsString( '= 0.9.1 =', $readme );
		$this->assertStringContainsString( '= 0.9.0 =', $readme );
		$this->assertStringContainsString( '= 0.8.1 =', $readme );
		$this->assertStringContainsString( '= 0.8.0 =', $readme );
		$this->assertStringContainsString( '= 0.6.0 =', $readme );
		$this->assertStringContainsString( 'does not import', $readme );
		$this->assertStringContainsString( 'umc_settings', $readme );
		$this->assertStringContainsString( '_umc_', $readme );
	}
}
