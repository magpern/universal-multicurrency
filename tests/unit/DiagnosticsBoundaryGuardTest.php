<?php
/**
 * Structural guard: Diagnostics stays isolated from the money path in both
 * directions, and third-party knowledge stays confined to one file
 * (WordPress-free).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * This is a scoped subset of the full guard suite the architecture plan
 * designs for `tests/integration/DiagnosticsGuardTest.php` (a later
 * commit): the three checks the Milestone 6 scoring-core commit needs
 * immediately, kept WordPress-free since nothing in `src/Diagnostics/` yet
 * touches WordPress. Each assertion was verified to fail when the
 * condition it guards is violated, not merely to pass today.
 */
final class DiagnosticsBoundaryGuardTest extends TestCase {

	/**
	 * Foreign identifiers a future detector might legitimately need to
	 * name. Confined to DetectorManifest.php by guard, not by convention.
	 *
	 * @var array<int, string>
	 */
	private const FOREIGN_IDENTIFIERS = array(
		'woocs',
		'aelia',
		'wcml',
		'curcy',
		'yay_currency',
		'yaycurrency',
		// Deliberately no generic "currency-switcher" / "currencyswitcher"
		// entry: this plugin's own domain vocabulary legitimately contains
		// that phrase (src/CurrencySwitcher.php, src/Frontend/Switcher.php's
		// docblock) and every brand-specific term above already covers the
		// real third-party products that phrase would otherwise catch.
		'woocommerce-currency-switcher',
	);

	/**
	 * Money-path classes and namespaces Diagnostics must never reach.
	 *
	 * @var array<int, string>
	 */
	private const MONETARY_REFERENCES = array(
		'Converter',
		'PriceConversionService',
		'CurrencyContext',
		'RateProvider',
		'CurrencyRegistry',
		'CurrencySwitcher',
		'OrderSnapshot',
		'StoreApi\\',
		'Order\\',
		'Cart\\',
		'->convert(',
		'apply_rate',
		'->get_rate(',
		'->get_currency_signature(',
	);

	private function src_root(): string {
		return dirname( __DIR__, 2 ) . '/src';
	}

	/**
	 * @return array<int, string>
	 */
	private function source_files( ?string $subdirectory = null ): array {
		$root = null === $subdirectory ? $this->src_root() : $this->src_root() . '/' . $subdirectory;

		if ( ! is_dir( $root ) ) {
			return array();
		}

		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = (string) $file->getPathname();
			}
		}

		return $files;
	}

	private function assert_pattern_absent_from( array $files, string $pattern, string $message ): void {
		$offenders = array();

		foreach ( $files as $file ) {
			if ( 1 === preg_match( $pattern, (string) file_get_contents( $file ) ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame( array(), $offenders, $message );
	}

	public function test_third_party_identifiers_are_confined_to_the_manifest(): void {
		$all_files = $this->source_files();
		$this->assertNotSame( array(), $all_files, 'Expected source files to scan.' );

		$outside_manifest = array_values(
			array_filter(
				$all_files,
				static function ( string $file ): bool {
					return false === strpos( $file, 'DetectorManifest.php' );
				}
			)
		);

		$this->assertNotSame( array(), $outside_manifest, 'Expected source files outside DetectorManifest.php.' );

		$pattern = '/(' . implode( '|', array_map( 'preg_quote', self::FOREIGN_IDENTIFIERS ) ) . ')/i';

		$this->assert_pattern_absent_from(
			$outside_manifest,
			$pattern,
			'Third-party plugin identifiers may only appear in DetectorManifest.php.'
		);
	}

	public function test_monetary_namespaces_do_not_import_diagnostics(): void {
		$all_files = $this->source_files();
		$this->assertNotSame( array(), $all_files, 'Expected source files to scan.' );

		$non_diagnostics = array_values(
			array_filter(
				$all_files,
				static function ( string $file ): bool {
					if ( false !== strpos( $file, '/Diagnostics/' ) ) {
						return false;
					}

					// Plugin.php is the sole registration seam for Diagnostics (M6).
					if ( false !== strpos( $file, '/Plugin.php' ) ) {
						return false;
					}

					return true;
				}
			)
		);

		$this->assertNotSame( array(), $non_diagnostics, 'Expected source files outside src/Diagnostics/.' );

		$this->assert_pattern_absent_from(
			$non_diagnostics,
			'/UMC\\\\Diagnostics|Diagnostics\\\\/',
			'No file outside src/Diagnostics/ may reference the Diagnostics namespace.'
		);
	}

	public function test_diagnostics_does_not_import_monetary_services(): void {
		$diagnostics_files = $this->source_files( 'Diagnostics' );
		$this->assertNotSame( array(), $diagnostics_files, 'Expected source files under src/Diagnostics/.' );

		$pattern = '/\b(' . implode( '|', array_map( 'preg_quote', self::MONETARY_REFERENCES ) ) . ')/';

		$this->assert_pattern_absent_from(
			$diagnostics_files,
			$pattern,
			'src/Diagnostics/ must never reference conversion, pricing, snapshot, Store API, cart, or order-monetary services.'
		);
	}

	public function test_diagnostics_never_reads_cookies_or_sessions(): void {
		$files = array_values(
			array_filter(
				$this->source_files( 'Diagnostics' ),
				static function ( string $file ): bool {
					return false === strpos( $file, 'NoticeDismissal.php' );
				}
			)
		);

		$this->assert_pattern_absent_from(
			$files,
			'/\$_COOKIE|\$_SESSION|get_transient\s*\(|set_transient\s*\(/',
			'Diagnostics must never read cookies, sessions, or transients.'
		);
	}

	public function test_only_notice_dismissal_persists_user_state(): void {
		$files = array_values(
			array_filter(
				$this->source_files( 'Diagnostics' ),
				static function ( string $file ): bool {
					return false === strpos( $file, 'NoticeDismissal.php' );
				}
			)
		);

		$this->assert_pattern_absent_from(
			$files,
			'/\b(update_user_meta|update_user_option|add_user_meta|delete_user_meta)\s*\(/',
			'Only NoticeDismissal.php may persist user state under src/Diagnostics/.'
		);
	}

	public function test_settings_page_does_not_reference_diagnostics(): void {
		$source = (string) file_get_contents( $this->src_root() . '/Admin/SettingsPage.php' );

		$this->assertDoesNotMatchRegularExpression( '/UMC\\\\Diagnostics|Diagnostics\\\\/', $source );
		$this->assertStringContainsString( "'umc_conflict'", $source );
	}

	public function test_probe_code_does_not_read_foreign_runtime_state(): void {
		$probe_files = array_filter(
			$this->source_files( 'Diagnostics' ),
			static function ( string $file ): bool {
				return false !== strpos( $file, 'WordPressEnvironmentProbe.php' );
			}
		);

		$this->assertCount( 1, $probe_files );

		$source = (string) file_get_contents( array_values( $probe_files )[0] );

		$this->assertDoesNotMatchRegularExpression( '/\$_COOKIE|\$_SESSION|get_transient\s*\(|set_transient\s*\(/', $source );
		$this->assertDoesNotMatchRegularExpression( '/(?<!`)\bconstant\s*\(/', $source );
		$this->assertSame( 1, preg_match_all( '/\bget_option\s*\(/', $source ), 'WordPressEnvironmentProbe may call get_option only for active_plugins.' );
		$this->assertStringContainsString( "'active_plugins'", $source );
	}

	public function test_class_exists_uses_the_non_autoload_flag(): void {
		$probe_files = array_filter(
			$this->source_files( 'Diagnostics' ),
			static function ( string $file ): bool {
				return false !== strpos( $file, 'WordPressEnvironmentProbe.php' );
			}
		);

		$source = (string) file_get_contents( array_values( $probe_files )[0] );

		$this->assertMatchesRegularExpression( '/class_exists\s*\(\s*\$signature->needle\(\)\s*,\s*false\s*\)/', $source );
	}

	public function test_diagnostics_never_calls_deactivation_apis(): void {
		$this->assert_pattern_absent_from(
			$this->source_files( 'Diagnostics' ),
			'/deactivate_plugins\s*\(|delete_plugins\s*\(/',
			'Diagnostics must remain advisory and never deactivate another plugin.'
		);
	}

	public function test_fixture_identifiers_are_confined_to_tests(): void {
		$src_files = $this->source_files();
		$this->assert_pattern_absent_from(
			$src_files,
			'/umc-fixture|UMC_Fixture/',
			'Fixture identifiers must not appear under src/.'
		);
	}
}
