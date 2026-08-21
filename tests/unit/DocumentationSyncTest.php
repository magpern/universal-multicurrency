<?php
/**
 * Documentation synchronization guards — Milestone 7 Commit 9–10.
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
use ZipArchive;

/**
 * Structural guards binding public and developer documentation to implementation.
 *
 * @group documentation
 * @group release-audit
 */
final class DocumentationSyncTest extends TestCase {

	private const CURRENT_VERSION = '1.1.1';

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
		'docs/adr/0010-automatic-rate-providers.md',
		'docs/adr/0011-action-scheduler-rate-updates.md',
		'docs/adr/0012-operational-rate-state-separation.md',
		'docs/adr/0013-conditional-http-rate-caching.md',
		'docs/adr/0016-geo-detection-ordered-routing.md',
		'docs/adr/0017-geocontext-admin-hub.md',
		'docs/adr/0018-visitor-location-boundary-alignment.md',
		'docs/GEO_DETECTION.md',
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
	 * Documentation files scanned for forbidden premature publication claims.
	 *
	 * @var list<string>
	 */
	private const PUBLICATION_CLAIM_SCAN_FILES = array(
		'README.md',
		'readme.txt',
		'docs/ROADMAP.md',
		'docs/DEPLOYMENT.md',
		'docs/RELEASE_AUDIT.md',
	);

	/**
	 * Temporary RC wording that must not remain after Commit 10.
	 *
	 * @var list<string>
	 */
	private const FORBIDDEN_PENDING_WORDING = array(
		'Commit 10 pending',
		'pending Commit 10',
		'Stable tag remains 0.6.0',
		'NB1 version deferred',
		'NB2 readme deferred',
		'version bump pending',
		'RC closure pending',
		'target 0.8.0 — Commit 13',
		'Unreleased (target 0.8.0',
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

	public function test_canonical_version_is_070_everywhere_required(): void {
		$header  = $this->parse_plugin_header_fields();
		$readme  = $this->parse_readme_txt_header();
		$version = $header['Version'] ?? null;

		$this->assertSame( self::CURRENT_VERSION, $version );
		$this->assertSame( self::CURRENT_VERSION, $header['UMC_VERSION'] ?? null );
		$this->assertSame( self::CURRENT_VERSION, $readme['Stable tag'] ?? null );
		$this->assertStringContainsString( 'Stable tag: ' . self::CURRENT_VERSION, $this->read( 'readme.txt' ) );
		$this->assertStringContainsString( self::CURRENT_VERSION, $this->read( 'docs/RELEASE_AUDIT.md' ) );
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

	public function test_readme_txt_changelog_includes_current_and_retains_history(): void {
		$readme = $this->read( 'readme.txt' );

		$this->assertStringContainsString( '= ' . self::CURRENT_VERSION . ' =', $readme );
		$this->assertStringContainsString( '= 0.8.0 =', $readme );
		$this->assertStringContainsString( '= 0.7.0 =', $readme );
		$this->assertStringContainsString( '= 0.6.0 =', $readme );
		$this->assertStringContainsString( 'Frankfurter', $readme );
		$this->assertStringContainsString( 'release audit', strtolower( $readme ) );
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

	public function test_roadmap_shows_milestone_eight_complete_at_080(): void {
		$roadmap = $this->read( 'docs/ROADMAP.md' );

		$this->assertStringContainsString( 'Milestone 8', $roadmap );
		$this->assertStringContainsString( 'complete', strtolower( $roadmap ) );
		$this->assertStringContainsString( 'v0.8.0', $roadmap );
	}

	public function test_key_docs_contain_no_temporary_pending_commit_ten_wording(): void {
		foreach ( array( 'README.md', 'docs/ROADMAP.md', 'docs/RELEASE_AUDIT.md', 'docs/ARCHITECTURE.md' ) as $file ) {
			$source = $this->read( $file );

			foreach ( self::FORBIDDEN_PENDING_WORDING as $phrase ) {
				$this->assertStringNotContainsString(
					$phrase,
					$source,
					$file . ' must not retain temporary RC pending wording: ' . $phrase
				);
			}
		}
	}

	public function test_no_documentation_claims_an_unreleased_version_has_shipped(): void {
		$forbidden = array(
			'/`?v0\.11\.0`?[^\n]{0,60}(?:tagged|published|released|shipped)/i',
			'/Milestone 12[^\n]{0,60}(?:complete|shipped|released)/i',
		);

		foreach ( self::PUBLICATION_CLAIM_SCAN_FILES as $file ) {
			$source = $this->read( $file );

			foreach ( $forbidden as $pattern ) {
				$this->assertDoesNotMatchRegularExpression(
					$pattern,
					$source,
					$file . ' must not claim an unreleased version has shipped.'
				);
			}
		}
	}

	public function test_no_documentation_describes_milestone_eight_as_pending(): void {
		$forbidden = array(
			'/Git tag `?v0\.8\.0`?[^\n]{0,40}Not yet created/i',
			'/v0\.8\.0[^\n]{0,60}pending explicit approval/i',
			'/Milestone 8[^\n]{0,60}(?:pending|not yet)/i',
		);

		foreach (
			array(
				'README.md',
				'docs/ROADMAP.md',
				'docs/RELEASE_AUDIT.md',
				'docs/ARCHITECTURE.md',
			) as $file
		) {
			$source = $this->read( $file );

			foreach ( $forbidden as $pattern ) {
				$this->assertDoesNotMatchRegularExpression(
					$pattern,
					$source,
					$file . ' must not describe the shipped Milestone 8 as pending.'
				);
			}
		}
	}

	public function test_release_audit_records_milestone_eight_closure_state(): void {
		$audit = $this->read( 'docs/RELEASE_AUDIT.md' );

		$this->assertStringContainsString( 'Release closure record', $audit );
		$this->assertStringContainsString( 'Version | **' . self::CURRENT_VERSION . '**', $audit );
		$this->assertStringContainsString( 'Unresolved release blockers | **0**', $audit );
		$this->assertStringContainsString( 'Open Milestone 8 review findings | **0**', $audit );
		$this->assertMatchesRegularExpression(
			'/Git tag `v' . preg_quote( self::CURRENT_VERSION, '/' ) . '` \| \*\*(?:Created|Not yet created)\*\*/',
			$audit,
			'Current version tag must be recorded as Created (released) or Not yet created (prepared).'
		);
		$this->assertMatchesRegularExpression(
			'/GitHub release `v' . preg_quote( self::CURRENT_VERSION, '/' ) . '` \| \*\*(?:Published|Not yet published)\*\*/',
			$audit,
			'Current version GitHub release must be recorded as Published or Not yet published.'
		);
		$this->assertStringContainsString( 'Git tag `v0.8.0` | **Created**', $audit );
		$this->assertStringContainsString( 'Milestone 8 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 17 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 18 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 19 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 20 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 21 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 22 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 23 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 24 | **Complete**', $audit );
		$this->assertStringContainsString( 'Milestone 25 | **Complete**', $audit );
		$this->assertStringContainsString( '## Post-release review findings', $audit );
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
		$this->assertStringContainsString( 'universal-multicurrency-' . self::CURRENT_VERSION . '.zip', $deployment );
	}

	public function test_settings_schema_documentation_matches_implementation(): void {
		$this->assertSame( 7, Settings::SCHEMA_VERSION );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6, 7 ), array_keys( SettingsUpgrader::production_migrations() ) );

		foreach ( array( 'docs/ARCHITECTURE.md', 'docs/MIGRATION.md' ) as $file ) {
			$source = $this->read( $file );

			$this->assertStringContainsString( 'schema', $source, $file );
			$this->assertStringContainsString( 'SettingsUpgrader', $source, $file );
			$this->assertStringContainsString( 'migrate_1_to_2', $source, $file );
			$this->assertStringContainsString( 'migrate_2_to_3', $source, $file );
			$this->assertStringContainsString( 'migrate_3_to_4', $source, $file );
			$this->assertStringContainsString( 'migrate_4_to_5', $source, $file );
			$this->assertStringContainsString( 'migrate_5_to_6', $source, $file );
			$this->assertStringContainsString( 'migrate_6_to_7', $source, $file );
		}

		$this->assertStringContainsString( 'schema_version', $this->read( 'docs/PERSISTED_DATA.md' ) );
	}

	public function test_migration_doc_describes_the_v1_to_v2_conversion_contract(): void {
		$migration = $this->read( 'docs/MIGRATION.md' );

		foreach (
			array(
				'manual_rate',
				'provider_rate',
				'merchant_adjustment',
				'schema_version: 2',
				'Conversion-fidelity guarantee',
				'SettingsMigrationFidelityTest',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $migration, 'MIGRATION.md must document: ' . $needle );
		}
	}

	public function test_architecture_documents_the_automatic_rate_layer(): void {
		$architecture = $this->read( 'docs/ARCHITECTURE.md' );

		foreach (
			array(
				'ExchangeRateSource',
				'ExchangeRateStore',
				'RateResolver',
				'ProviderMetadata',
				'RateUpdateService',
				'Scheduler',
				'RateUpdateState',
				'ADR-0010',
				'ADR-0011',
				'ADR-0012',
				'ADR-0013',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $architecture, 'ARCHITECTURE.md missing: ' . $needle );
		}
	}

	public function test_security_review_covers_the_milestone_eight_surfaces(): void {
		$security = $this->read( 'docs/SECURITY_REVIEW.md' );

		foreach (
			array(
				'admin_post_umc_update_rates',
				'manage_woocommerce',
				'check_admin_referer',
				'wp_safe_remote_get',
				'Lock behaviour',
				'Diagnostic redaction',
				'Secret persistence',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $security, 'SECURITY_REVIEW.md missing: ' . $needle );
		}
	}

	public function test_persisted_data_documentation_matches_inventory(): void {
		$doc = $this->read( 'docs/PERSISTED_DATA.md' );

		$this->assertStringContainsString( (string) PersistedKeys::INVENTORY_VERSION, $doc );
		$this->assertStringContainsString( 'umc_settings', $doc );
		$this->assertStringContainsString( 'umc_rate_state', $doc );
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
			'Release ZIP must ship readme.txt.'
		);
	}

	public function test_packaged_plugin_and_readme_report_070_when_zip_built(): void {
		if ( ! class_exists( ZipArchive::class ) ) {
			$this->markTestSkipped( 'ext-zip is required for packaged version inspection.' );
		}

		$zip_path = getenv( 'UMC_RELEASE_ZIP' );

		if ( ! is_string( $zip_path ) || '' === $zip_path ) {
			$this->markTestSkipped( 'UMC_RELEASE_ZIP is set by bin/release-audit.sh after building the archive.' );
		}

		if ( ! str_starts_with( $zip_path, '/' ) ) {
			$zip_path = $this->root() . '/' . ltrim( $zip_path, '/' );
		}

		$this->assertStringEndsWith( 'universal-multicurrency-' . self::CURRENT_VERSION . '.zip', $zip_path );

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $zip_path ) );

		$plugin_source = (string) $zip->getFromName( 'universal-multicurrency/universal-multicurrency.php' );
		$readme_source = (string) $zip->getFromName( 'universal-multicurrency/readme.txt' );
		$zip->close();

		$this->assertStringContainsString( 'Version: ' . self::CURRENT_VERSION, $plugin_source );
		$this->assertStringContainsString( "UMC_VERSION', '" . self::CURRENT_VERSION . "'", $plugin_source );
		$this->assertStringContainsString( 'Stable tag: ' . self::CURRENT_VERSION, $readme_source );
	}
}
