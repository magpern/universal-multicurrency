<?php
/**
 * Documentation synchronization guards — Milestone 7 Commit 9.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UMC\PersistedKeys;
use UMC\Settings;
use UMC\SettingsUpgrader;
use UMC\Tests\Support\ReleaseZipInspector;

/**
 * Structural guards binding public and developer documentation to implementation.
 *
 * @group documentation
 * @group release-audit
 */
final class DocumentationSyncTest extends TestCase {

	/**
	 * Tracked documentation sources that must exist and stay internally consistent.
	 *
	 * @var list<string>
	 */
	private const REQUIRED_DOCS = array(
		'README.md',
		'readme.txt',
		'CLAUDE.md',
		'docs/ARCHITECTURE.md',
		'docs/COMPATIBILITY.md',
		'docs/DEPLOYMENT.md',
		'docs/MIGRATION.md',
		'docs/PERFORMANCE_BASELINES.md',
		'docs/PERSISTED_DATA.md',
		'docs/RELEASE_AUDIT.md',
		'docs/ROADMAP.md',
		'docs/SECURITY_REVIEW.md',
		'docs/TEST_STRATEGY.md',
		'docs/TRANSLATION.md',
		'docs/adr/0003-standalone-no-fox-woocs-coupling.md',
		'docs/adr/0007-passive-conflict-detection.md',
		'docs/adr/0009-uninstall-retention-policy.md',
	);

	/**
	 * Composer scripts referenced from contributor documentation.
	 *
	 * @var list<string>
	 */
	private const DOCUMENTED_COMPOSER_SCRIPTS = array(
		'phpcs',
		'make-pot',
		'make-pot:check',
		'test:unit',
		'test:integration',
		'test:mutation',
		'release-audit',
	);

	/**
	 * Documentation files scanned for forbidden "released" claims about v0.7.0.
	 *
	 * @var list<string>
	 */
	private const RELEASE_CLAIM_SCAN_FILES = array(
		'README.md',
		'readme.txt',
		'docs/ROADMAP.md',
		'docs/DEPLOYMENT.md',
		'docs/RELEASE_AUDIT.md',
		'docs/COMPATIBILITY.md',
	);

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	private function read( string $relative_path ): string {
		$path = $this->root() . '/' . ltrim( $relative_path, '/' );

		if ( ! is_readable( $path ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test diagnostic.
			throw new RuntimeException( 'Missing file: ' . $relative_path );
		}

		return (string) file_get_contents( $path );
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws RuntimeException When composer scripts are missing.
	 */
	private function composer_scripts(): array {
		$composer = json_decode( $this->read( 'composer.json' ), true );

		if ( ! is_array( $composer ) || ! isset( $composer['scripts'] ) || ! is_array( $composer['scripts'] ) ) {
			throw new RuntimeException( 'composer.json scripts section is missing.' );
		}

		return $composer['scripts'];
	}

	/**
	 * @return array<string, string>
	 */
	private function parse_plugin_header_fields(): array {
		$header = $this->read( 'universal-multicurrency.php' );
		$fields = array();

		foreach ( array( 'Version', 'Text Domain', 'Requires at least', 'Requires PHP', 'Requires Plugins' ) as $field ) {
			if ( 1 === preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*(\S+)/m', $header, $matches ) ) {
				$fields[ $field ] = $matches[1];
			}
		}

		if ( 1 === preg_match( "/define\s*\(\s*'UMC_VERSION'\s*,\s*'([^']+)'/", $header, $constant ) ) {
			$fields['UMC_VERSION'] = $constant[1];
		}

		return $fields;
	}

	/**
	 * @return array<string, string>
	 */
	private function parse_readme_txt_header(): array {
		$readme = $this->read( 'readme.txt' );
		$fields = array();

		foreach (
			array(
				'Stable tag',
				'Requires at least',
				'Requires PHP',
				'License',
				'License URI',
			) as $field
		) {
			if ( 1 === preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+)$/m', $readme, $matches ) ) {
				$fields[ $field ] = trim( $matches[1] );
			}
		}

		return $fields;
	}

	public function test_required_documentation_files_exist(): void {
		foreach ( self::REQUIRED_DOCS as $doc ) {
			$this->assertFileExists( $this->root() . '/' . $doc, 'Missing required documentation: ' . $doc );
		}
	}

	public function test_readme_txt_has_wordpress_plugin_readme_sections(): void {
		$readme = $this->read( 'readme.txt' );

		foreach (
			array(
				'== Description ==',
				'== Installation ==',
				'== Frequently Asked Questions ==',
				'== Changelog ==',
				'== Upgrade Notice ==',
			) as $section
		) {
			$this->assertStringContainsString( $section, $readme, 'Missing readme section: ' . $section );
		}
	}

	public function test_readme_txt_stable_tag_remains_pre_commit_ten_version(): void {
		$header  = $this->parse_plugin_header_fields();
		$readme  = $this->parse_readme_txt_header();
		$version = $header['Version'] ?? null;

		$this->assertSame( '0.6.0', $version, 'Commit 9 must not bump the plugin header version.' );
		$this->assertSame( '0.6.0', $readme['Stable tag'] ?? null, 'readme Stable tag must match the shipped header version.' );
		$this->assertSame( $version, $readme['Stable tag'] ?? null, 'readme Stable tag must match plugin header Version.' );
	}

	public function test_readme_txt_metadata_matches_plugin_header(): void {
		$header = $this->parse_plugin_header_fields();
		$readme = $this->parse_readme_txt_header();

		$this->assertSame( $header['Requires at least'] ?? null, $readme['Requires at least'] ?? null );
		$this->assertSame( $header['Requires PHP'] ?? null, $readme['Requires PHP'] ?? null );
		$this->assertSame( 'GPLv2 or later', $readme['License'] ?? null );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $readme['License URI'] ?? null );
		$this->assertSame( 'universal-multicurrency', $header['Text Domain'] ?? null );
	}

	public function test_readme_files_do_not_imply_automatic_foreign_import(): void {
		$readme_txt = $this->read( 'readme.txt' );
		$readme_md  = $this->read( 'README.md' );

		$this->assertStringContainsString( 'manual', $readme_txt );
		$this->assertStringContainsString( 'does not import', $readme_txt );
		$this->assertStringContainsString( 'Automatic import', $readme_txt );

		$this->assertStringContainsString( 'manual', $readme_md );
		$this->assertStringContainsString( 'no foreign import', $readme_md );
		$this->assertStringContainsString( 'no automatic import', strtolower( $readme_md ) );
	}

	public function test_readme_files_document_uninstall_retention_policy(): void {
		foreach ( array( 'README.md', 'readme.txt' ) as $file ) {
			$source = $this->read( $file );

			$this->assertStringContainsString( 'umc_settings', $source );
			$this->assertStringContainsString( '_umc_', $source );
			$this->assertStringContainsString( 'preserv', $source, $file . ' must state order meta is preserved on uninstall.' );
		}
	}

	public function test_roadmap_shows_milestone_seven_open_and_commit_ten_pending(): void {
		$roadmap = $this->read( 'docs/ROADMAP.md' );

		$this->assertStringContainsString( 'Documentation synchronization', $roadmap );
		$this->assertStringContainsString( 'Complete', $roadmap );
		$this->assertStringContainsString( 'Commit 10', $roadmap );
		$this->assertStringContainsString( 'Pending', $roadmap );
		$this->assertStringNotContainsString( 'Milestone 7 complete', $roadmap );
		$this->assertStringNotContainsString( 'Milestone 7 — complete', $roadmap );
		$this->assertStringNotContainsString( 'v0.7.0 released', $roadmap );
	}

	public function test_no_documentation_claims_v070_has_been_released(): void {
		$forbidden = array(
			'/Stable tag:\s*0\.7\.0/i',
			'/Version:\s*0\.7\.0/m',
			"/UMC_VERSION',\s*'0\.7\.0'/",
			'/v0\.7\.0 has been released/i',
			'/released v0\.7\.0/i',
			'/Milestone 7 complete/i',
		);

		foreach ( self::RELEASE_CLAIM_SCAN_FILES as $file ) {
			$source = $this->read( $file );

			foreach ( $forbidden as $pattern ) {
				$this->assertDoesNotMatchRegularExpression(
					$pattern,
					$source,
					$file . ' must not claim v0.7.0 is released or Milestone 7 complete.'
				);
			}
		}
	}

	public function test_documented_composer_commands_exist(): void {
		$scripts = $this->composer_scripts();

		foreach ( self::DOCUMENTED_COMPOSER_SCRIPTS as $script ) {
			$this->assertArrayHasKey( $script, $scripts, 'composer.json is missing documented script: ' . $script );
		}

		$deployment = $this->read( 'docs/DEPLOYMENT.md' );

		foreach ( self::DOCUMENTED_COMPOSER_SCRIPTS as $script ) {
			$this->assertStringContainsString( 'composer ' . $script, $deployment, 'DEPLOYMENT.md must document: composer ' . $script );
		}
	}

	public function test_deployment_documents_canonical_build_and_release_audit(): void {
		$deployment = $this->read( 'docs/DEPLOYMENT.md' );

		$this->assertStringContainsString( 'composer release-audit', $deployment );
		$this->assertStringContainsString( 'bin/build-zip.sh', $deployment );
		$this->assertStringContainsString( 'composer install --no-dev', $deployment );
		$this->assertStringContainsString( 'Commit 10', $deployment );
	}

	public function test_settings_schema_documentation_matches_implementation(): void {
		$this->assertSame( 1, Settings::SCHEMA_VERSION );
		$this->assertSame( array( 1 ), array_keys( SettingsUpgrader::production_migrations() ) );

		foreach ( array( 'docs/ARCHITECTURE.md', 'docs/MIGRATION.md' ) as $file ) {
			$source = $this->read( $file );

			$this->assertStringContainsString( 'schema', $source, $file );
			$this->assertStringContainsString( 'SettingsUpgrader', $source, $file );
		}

		$this->assertStringContainsString( 'schema_version', $this->read( 'docs/PERSISTED_DATA.md' ) );
	}

	public function test_persisted_data_documentation_matches_inventory(): void {
		$doc = $this->read( 'docs/PERSISTED_DATA.md' );

		$this->assertStringContainsString( (string) PersistedKeys::INVENTORY_VERSION, $doc );
		$this->assertStringContainsString( 'umc_settings', $doc );
		$this->assertStringContainsString( 'umc_dismissed_notices', $doc );
		$this->assertStringContainsString( 'Preserved', $doc );
		$this->assertStringContainsString( 'ADR-0009', $doc );
	}

	public function test_architecture_documents_release_governance_surfaces(): void {
		$architecture = $this->read( 'docs/ARCHITECTURE.md' );

		foreach (
			array(
				'ADR-0003',
				'ADR-0009',
				'Settings::SCHEMA_VERSION',
				'SettingsUpgrader',
				'MIGRATION.md',
				'SECURITY_REVIEW.md',
				'PERFORMANCE_BASELINES.md',
				'RELEASE_AUDIT.md',
				'composer release-audit',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $architecture, 'ARCHITECTURE.md missing: ' . $needle );
		}
	}

	public function test_security_review_records_accepted_findings_and_zero_open_critical_high(): void {
		$security = $this->read( 'docs/SECURITY_REVIEW.md' );

		$this->assertStringContainsString( 'Critical', $security );
		$this->assertStringContainsString( 'High', $security );
		$this->assertStringContainsString( 'Medium', $security );
		$this->assertStringContainsString( 'SettingsUpgrader', $security );
		$this->assertStringContainsString( 'Zero unresolved Critical or High', $security );
	}

	public function test_documentation_relative_links_resolve(): void {
		$broken = array();

		foreach ( self::REQUIRED_DOCS as $doc ) {
			$source   = $this->read( $doc );
			$base_dir = dirname( $this->root() . '/' . $doc );

			if ( ! preg_match_all( '/\]\(([^)]+)\)/', $source, $matches ) ) {
				continue;
			}

			foreach ( $matches[1] as $target ) {
				if (
					'' === $target
					|| str_starts_with( $target, 'http://' )
					|| str_starts_with( $target, 'https://' )
					|| str_starts_with( $target, 'mailto:' )
					|| str_starts_with( $target, '#' )
				) {
					continue;
				}

				$target_path = preg_replace( '/#.*$/', '', $target );
				$resolved    = realpath( $base_dir . '/' . $target_path );

				if ( false === $resolved || ! is_readable( $resolved ) ) {
					$broken[] = $doc . ' -> ' . $target;
				}
			}
		}

		$this->assertSame( array(), $broken, "Broken documentation links:\n" . implode( "\n", $broken ) );
	}

	public function test_release_zip_inspector_requires_readme_txt(): void {
		$this->assertContains(
			'universal-multicurrency/readme.txt',
			ReleaseZipInspector::REQUIRED_ENTRIES,
			'Release ZIP must ship readme.txt after Commit 9.'
		);
	}
}
