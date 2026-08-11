<?php
/**
 * Release Candidate audit guards — executable release-blocking gate.
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
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Deterministic repository and package invariants for Commit 8.
 *
 * @group release-audit
 */
final class ReleaseAuditTest extends TestCase {

	use SourceGuardTrait;

	/**
	 * Files allowed to name foreign switcher identifiers in runtime source.
	 *
	 * @var list<string>
	 */
	private const FOREIGN_IDENTIFIER_ALLOWLIST = array(
		'DetectorManifest.php',
	);

	/**
	 * Case-insensitive foreign coupling probes for runtime src/ (ADR-0003).
	 *
	 * @var list<string>
	 */
	private const PROHIBITED_FOREIGN_PATTERNS = array(
		'get_option\s*\(\s*[\'"]woocs',
		'get_option\s*\(\s*[\'"].*?currency_switcher',
		'class_exists\s*\(\s*[\'"]WOOCS',
		'require(?:_once)?\s*\(?\s*[\'"].*woocommerce-currency-switcher',
		'PluginUs',
		'MP WOOCS Browse Currency',
	);

	/**
	 * Required CI workflow jobs for RC gating.
	 *
	 * @var list<string>
	 */
	private const REQUIRED_CI_JOBS = array(
		'phpcs',
		'pot',
		'unit',
		'integration',
		'performance',
		'build',
		'release-audit',
	);

	/**
	 * Required audit and gate documents.
	 *
	 * @var list<string>
	 */
	private const REQUIRED_DOCS = array(
		'docs/RELEASE_AUDIT.md',
		'docs/SECURITY_REVIEW.md',
		'docs/PERFORMANCE_BASELINES.md',
		'docs/PERSISTED_DATA.md',
		'docs/MIGRATION.md',
		'docs/TRANSLATION.md',
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
	 * @return list<string>
	 *
	 * @throws RuntimeException When tracked files cannot be enumerated.
	 */
	private function tracked_files(): array {
		shell_exec( 'git config --global --add safe.directory ' . escapeshellarg( $this->root() ) . ' 2>/dev/null' );

		$command = 'git -C ' . escapeshellarg( $this->root() ) . ' ls-files 2>/dev/null';
		$output  = shell_exec( $command );

		if ( is_string( $output ) && '' !== trim( $output ) ) {
			return array_values( array_filter( array_map( 'trim', explode( "\n", $output ) ) ) );
		}

		if ( ! is_dir( $this->root() . '/.git' ) || ! shell_exec( 'command -v git >/dev/null 2>&1 && echo ok' ) ) {
			$this->markTestSkipped( 'git is required to audit tracked files.' );
		}

		throw new RuntimeException( 'Could not enumerate tracked files via git ls-files.' );
	}

	/**
	 * Tracked text files that could ship or define release behaviour.
	 *
	 * Test sources are excluded because guard tests intentionally embed probe
	 * patterns and fixture tokens that must not be treated as repository secrets.
	 *
	 * @return list<string>
	 */
	private function tracked_text_files_for_secret_scan(): array {
		$allowed_prefixes = array(
			'src/',
			'bin/',
			'docs/',
			'languages/',
			'.github/',
		);

		$allowed_root_files = array(
			'universal-multicurrency.php',
			'uninstall.php',
			'composer.json',
			'composer.lock',
			'phpcs.xml.dist',
			'phpunit.xml.dist',
			'phpunit-integration.xml.dist',
		);

		return array_values(
			array_filter(
				$this->tracked_files(),
				static function ( string $file ) use ( $allowed_prefixes, $allowed_root_files ): bool {
					if ( ! preg_match( '/\.(php|md|yml|yaml|json|sh|txt|pot|xml|dist)$/', $file ) ) {
						return false;
					}

					foreach ( $allowed_prefixes as $prefix ) {
						if ( str_starts_with( $file, $prefix ) ) {
							return true;
						}
					}

					return in_array( $file, $allowed_root_files, true );
				}
			)
		);
	}

	public function test_release_audit_document_exists_and_records_gate(): void {
		$doc = $this->read( 'docs/RELEASE_AUDIT.md' );

		foreach (
			array(
				'Release-blocking criteria',
				'composer release-audit',
				'Repository hygiene',
				'Security gate',
				'Performance gate',
				'Release ZIP',
			) as $section
		) {
			$this->assertStringContainsString( $section, $doc, 'RELEASE_AUDIT.md must document ' . $section );
		}
	}

	public function test_required_gate_documents_exist(): void {
		foreach ( self::REQUIRED_DOCS as $doc ) {
			$this->assertFileExists( $this->root() . '/' . $doc, 'Missing required document: ' . $doc );
		}
	}

	public function test_security_review_records_zero_open_critical_and_high_findings(): void {
		$doc = $this->read( 'docs/SECURITY_REVIEW.md' );

		$this->assertMatchesRegularExpression(
			'/Critical\s+—\s+none/i',
			$doc,
			'SECURITY_REVIEW.md must record zero open Critical findings.'
		);
		$this->assertMatchesRegularExpression(
			'/High\s+—\s+none/i',
			$doc,
			'SECURITY_REVIEW.md must record zero open High findings.'
		);
		$this->assertStringContainsString( 'Accepted', $doc, 'Accepted Medium/Low risks must remain documented.' );
	}

	public function test_performance_baselines_document_is_present_and_linked(): void {
		$doc = $this->read( 'docs/PERFORMANCE_BASELINES.md' );

		$this->assertStringContainsString( 'CEILING_SETTINGS_WRITE_CANONICAL_LOAD', $doc );
		$this->assertStringContainsString( 'wall-clock', $doc );
		$this->assertStringNotContainsString( 'sleep(', $doc );
	}

	public function test_ci_workflow_declares_required_jobs(): void {
		$workflow = $this->read( '.github/workflows/ci.yml' );

		foreach ( self::REQUIRED_CI_JOBS as $job ) {
			$this->assertMatchesRegularExpression(
				'/^\s{2}' . preg_quote( $job, '/' ) . ':/m',
				$workflow,
				'CI workflow must declare job: ' . $job
			);
		}
	}

	public function test_composer_declares_release_audit_command(): void {
		$composer = json_decode( $this->read( 'composer.json' ), true );

		$this->assertIsArray( $composer );
		$this->assertSame(
			'bash bin/release-audit.sh',
			$composer['scripts']['release-audit'] ?? null,
			'composer.json must expose scripts.release-audit.'
		);
	}

	public function test_gitignore_excludes_generated_and_local_artifacts(): void {
		$ignore = $this->read( '.gitignore' );

		foreach ( array( '/vendor/', '/dist/', '.phpunit.result.cache', '/tests/tmp/' ) as $rule ) {
			$this->assertStringContainsString( $rule, $ignore );
		}
	}

	public function test_tracked_files_contain_no_hygiene_violations(): void {
		$forbidden = array(
			'/.phpunit.result.cache$/',
			'#/dist/#',
			'#/vendor/#',
			'#/tests/tmp/#',
			'/\.(swp|bak|tmp|log|env|pem|key)$/',
			'/(^|\/)id_rsa/',
		);

		$offenders = array();

		foreach ( $this->tracked_files() as $file ) {
			foreach ( $forbidden as $pattern ) {
				if ( 1 === preg_match( $pattern, $file ) ) {
					$offenders[] = $file;
				}
			}
		}

		$this->assertSame( array(), $offenders, 'Tracked files must not include local or generated artifacts.' );

		$plans = array_values(
			array_filter(
				$this->tracked_files(),
				static fn( string $file ): bool => str_starts_with( $file, 'docs/plans/' )
			)
		);
		$this->assertSame( array(), $plans, 'docs/plans/ must remain local-only and untracked.' );
	}

	public function test_tracked_files_contain_no_secret_like_content(): void {
		$patterns = array(
			'/BEGIN (RSA |OPENSSH )?PRIVATE KEY/',
			'/aws_secret_access_key/i',
			'/ghp_[A-Za-z0-9]{20,}/',
			'/xox[baprs]-[A-Za-z0-9-]{10,}/',
		);

		$offenders = array();

		foreach ( $this->tracked_text_files_for_secret_scan() as $file ) {
			$contents = $this->read( $file );

			foreach ( $patterns as $pattern ) {
				if ( 1 === preg_match( $pattern, $contents ) ) {
					$offenders[] = $file;
					break;
				}
			}
		}

		$this->assertSame( array(), $offenders, 'Tracked text files must not contain secret-like material.' );
	}

	public function test_src_outside_manifest_has_no_prohibited_foreign_coupling(): void {
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( in_array( basename( $file ), self::FOREIGN_IDENTIFIER_ALLOWLIST, true ) ) {
				continue;
			}

			$source = (string) file_get_contents( $file );

			foreach ( self::PROHIBITED_FOREIGN_PATTERNS as $pattern ) {
				if ( 1 === preg_match( '/' . $pattern . '/i', $source ) ) {
					$offenders[] = basename( $file ) . ' matches ' . $pattern;
				}
			}
		}

		$this->assertSame( array(), $offenders, 'Runtime src/ must not couple to foreign switchers (ADR-0003).' );
	}

	public function test_plugin_metadata_is_internally_consistent(): void {
		$header = $this->read( 'universal-multicurrency.php' );

		preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $header, $version );
		preg_match( "/define\s*\(\s*'UMC_VERSION'\s*,\s*'([^']+)'/", $header, $constant );
		preg_match( '/Text Domain:\s*(\S+)/', $header, $domain );
		preg_match( '/Requires PHP:\s*(\S+)/', $header, $php );
		preg_match( '/Requires Plugins:\s*(\S+)/', $header, $requires_plugins );

		$this->assertSame( $version[1] ?? null, $constant[1] ?? null, 'Header Version must match UMC_VERSION.' );
		$this->assertSame( 'universal-multicurrency', $domain[1] ?? null );
		$this->assertSame( '8.1', $php[1] ?? null );
		$this->assertSame( 'woocommerce', $requires_plugins[1] ?? null );

		$composer = json_decode( $this->read( 'composer.json' ), true );
		$this->assertSame( 'GPL-2.0-or-later', $composer['license'] ?? null );
		$this->assertSame( '>=8.1', $composer['require']['php'] ?? null );
	}

	public function test_settings_schema_is_v6_with_production_migrations(): void {
		$this->assertSame( 6, Settings::SCHEMA_VERSION );

		$migrations = SettingsUpgrader::production_migrations();
		$this->assertSame( array( 1, 2, 3, 4, 5, 6 ), array_keys( $migrations ) );
		$this->assertSame( SettingsUpgrader::MIGRATE_0_TO_1, $migrations[1] );
		$this->assertSame( SettingsUpgrader::MIGRATE_1_TO_2, $migrations[2] );
		$this->assertSame( SettingsUpgrader::MIGRATE_2_TO_3, $migrations[3] );
		$this->assertSame( SettingsUpgrader::MIGRATE_3_TO_4, $migrations[4] );
		$this->assertSame( SettingsUpgrader::MIGRATE_4_TO_5, $migrations[5] );
		$this->assertSame( SettingsUpgrader::MIGRATE_5_TO_6, $migrations[6] );
	}

	public function test_persisted_keys_inventory_version_is_documented(): void {
		$doc = $this->read( 'docs/PERSISTED_DATA.md' );

		$this->assertStringContainsString(
			(string) PersistedKeys::INVENTORY_VERSION,
			$doc,
			'PERSISTED_DATA.md must mention the current inventory version.'
		);
	}

	public function test_uninstall_php_deletes_only_contracted_options(): void {
		$source = $this->read( 'uninstall.php' );

		$this->assertSame( 1, preg_match_all( "/delete_option\s*\(\s*['\"]umc_settings['\"]\s*\)/", $source ) );
		$this->assertSame( 1, preg_match_all( "/delete_option\s*\(\s*['\"]umc_rate_state['\"]\s*\)/", $source ) );
		$this->assertStringNotContainsString( 'delete_user_meta', $source );
		$this->assertStringNotContainsString( 'delete_post_meta', $source );
	}

	public function test_build_zip_script_excludes_non_shipping_paths(): void {
		$source = $this->read( 'bin/build-zip.sh' );

		$this->assertStringNotContainsString( 'tests/', $source );
		$this->assertStringNotContainsString( 'docs/', $source );
		$this->assertStringContainsString( 'composer install --no-dev', $source );
	}

	public function test_src_contains_no_debug_logging_or_stale_todo_fixme(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\b(error_log|var_dump|print_r|var_export)\s*\(/',
			'Production src/ must not ship debug logging.'
		);

		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\b(TODO|FIXME)\b/',
			'Production src/ must not contain stale TODO/FIXME markers at RC audit.'
		);
	}

	public function test_languages_pot_template_is_present(): void {
		$pot = $this->root() . '/languages/universal-multicurrency.pot';

		$this->assertFileExists( $pot );
		$this->assertStringContainsString( 'msgid ""', (string) file_get_contents( $pot ) );
	}

	public function test_release_zip_inspector_detects_forbidden_entries(): void {
		if ( ! class_exists( \ZipArchive::class ) ) {
			$this->markTestSkipped( 'ext-zip is required for ZIP audit tests.' );
		}

		$zip_path = sys_get_temp_dir() . '/umc-release-audit-' . uniqid( '', true ) . '.zip';
		$zip      = new \ZipArchive();

		$this->assertTrue( $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) );
		$zip->addFromString( 'universal-multicurrency/universal-multicurrency.php', '<?php' );
		$zip->addFromString( 'universal-multicurrency/tests/bad.php', '<?php' );
		$zip->close();

		try {
			$result = ReleaseZipInspector::inspect( $zip_path );
			$this->assertNotSame( array(), $result['violations'] );
		} finally {
			if ( is_file( $zip_path ) ) {
				unlink( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Ephemeral test artifact outside WordPress.
			}
		}
	}

	public function test_release_zip_audit_passes_when_env_points_at_built_artifact(): void {
		$zip = getenv( 'UMC_RELEASE_ZIP' );

		if ( ! is_string( $zip ) || '' === $zip ) {
			$this->markTestSkipped( 'UMC_RELEASE_ZIP is set by bin/release-audit.sh after building the archive.' );
		}

		if ( ! str_starts_with( $zip, '/' ) ) {
			$zip = $this->root() . '/' . ltrim( $zip, '/' );
		}

		$result = ReleaseZipInspector::assert_clean( $zip );

		$this->assertGreaterThan( 0, count( $result['entries'] ) );
		$this->assertSame( array(), $result['violations'] );
	}
}
