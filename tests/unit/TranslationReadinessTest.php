<?php
/**
 * Structural guard: translation readiness and POT drift protection.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Binds the canonical text domain, POT template, and i18n documentation contract.
 */
final class TranslationReadinessTest extends TestCase {

	private const TEXT_DOMAIN = 'universal-multicurrency';

	/**
	 * Shipped JavaScript files reviewed for i18n boundaries.
	 *
	 * @var array<int, string>
	 */
	private const APPROVED_JS_FILES = array(
		'assets/admin/umc-settings.js',
		'assets/admin/umc-compatibility.js',
		'assets/js/switcher.js',
		'assets/js/checkout-notice.js',
	);

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	private function read( string $relative_path ): string {
		$path = $this->root() . '/' . ltrim( $relative_path, '/' );

		if ( ! is_readable( $path ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
			throw new RuntimeException( 'Missing file: ' . $relative_path );
		}

		return (string) file_get_contents( $path );
	}

	/**
	 * @return list<string>
	 */
	private function php_sources(): array {
		$files = array(
			$this->root() . '/universal-multicurrency.php',
			$this->root() . '/uninstall.php',
		);

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root() . '/src', RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file instanceof SplFileInfo && $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files, SORT_STRING );

		return $files;
	}

	public function test_plugin_header_text_domain_matches_canonical_domain(): void {
		preg_match( '/Text Domain:\s*(\S+)/', $this->read( 'universal-multicurrency.php' ), $match );

		$this->assertSame( self::TEXT_DOMAIN, $match[1] ?? null );
	}

	public function test_claude_md_documents_canonical_text_domain(): void {
		$source = $this->read( 'CLAUDE.md' );

		$this->assertStringContainsString( 'textdomain `universal-multicurrency`', $source );
		$this->assertStringContainsString( 'composer make-pot', $source );
	}

	public function test_plugin_loads_text_domain_on_init(): void {
		$source = $this->read( 'src/Plugin.php' );

		$this->assertStringContainsString( 'load_plugin_textdomain', $source );
		$this->assertStringContainsString( "'universal-multicurrency'", $source );
		$this->assertStringContainsString( '/languages', $source );
	}

	public function test_translation_calls_in_src_use_canonical_domain(): void {
		$pattern = '/(?:__|_e|_x|_n|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\s*\([^;]+,\s*[\'"]([^\'"]+)[\'"]\s*\)/';

		foreach ( $this->php_sources() as $file ) {
			$source = (string) file_get_contents( $file );

			if ( 1 !== preg_match_all( $pattern, $source, $matches ) ) {
				continue;
			}

			foreach ( $matches[1] as $domain ) {
				$this->assertSame(
					self::TEXT_DOMAIN,
					$domain,
					'Non-canonical text domain in ' . basename( $file ) . ': ' . $domain
				);
			}
		}
	}

	public function test_pot_file_exists_with_domain_metadata(): void {
		$pot = $this->read( 'languages/universal-multicurrency.pot' );

		$this->assertStringContainsString( 'X-Domain: universal-multicurrency', $pot );
		$this->assertStringContainsString(
			'Report-Msgid-Bugs-To: https://github.com/magpern/universal-multicurrency/issues',
			$pot
		);
		$this->assertStringNotContainsString( 'POT-Creation-Date:', $pot );
	}

	/**
	 * @return list<string>
	 */
	private function pot_msgids(): array {
		$pot    = $this->read( 'languages/universal-multicurrency.pot' );
		$msgids = array();

		if ( preg_match_all( '/^msgid "(.*)"$/m', $pot, $matches ) ) {
			foreach ( $matches[1] as $msgid ) {
				if ( '' !== $msgid ) {
					$msgids[] = stripcslashes( $msgid );
				}
			}
		}

		return $msgids;
	}

	public function test_pot_contains_representative_user_facing_strings(): void {
		$msgids = $this->pot_msgids();

		foreach (
			array(
				'Multicurrency',
				'Currency & Exchange Rate',
				'No payment method is available for %s. Please choose a different currency to continue.',
				'the plugin "%s" is active',
				'Manual',
			) as $expected
		) {
			$this->assertContains( $expected, $msgids, 'Missing POT msgid: ' . $expected );
		}
	}

	public function test_pot_excludes_internal_meta_key_literals_as_msgids(): void {
		$msgids = $this->pot_msgids();

		foreach ( array( '_umc_base_currency', 'umc_settings', 'umc_currency' ) as $internal ) {
			$this->assertNotContains( $internal, $msgids, 'Internal identifier must not be a POT msgid.' );
		}
	}

	public function test_no_shipped_javascript_files_with_user_facing_strings(): void {
		$roots = array( 'src', 'assets' );

		foreach ( $roots as $relative ) {
			$path = $this->root() . '/' . $relative;

			if ( ! is_dir( $path ) ) {
				continue;
			}

			$iterator = new RegexIterator(
				new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS )
				),
				'/\.js$/i'
			);

			$js_files   = iterator_to_array( $iterator, false );
			$unexpected = array();

			foreach ( $js_files as $file ) {
				if ( ! $file instanceof SplFileInfo ) {
					continue;
				}

				$relative_path = ltrim( str_replace( $this->root() . '/', '', $file->getPathname() ), '/' );

				if ( ! in_array( $relative_path, self::APPROVED_JS_FILES, true ) ) {
					$unexpected[] = $relative_path;
				}
			}

			$this->assertSame( array(), $unexpected, 'Shipped JavaScript is not allowed without i18n review.' );
		}
	}

	public function test_translation_documentation_covers_workflow_js_and_rtl(): void {
		$source = $this->read( 'docs/TRANSLATION.md' );

		$this->assertStringContainsString( 'universal-multicurrency', $source );
		$this->assertStringContainsString( 'composer make-pot', $source );
		$this->assertStringContainsString( 'JavaScript translation status', $source );
		$this->assertStringContainsString( 'RTL readiness audit', $source );
		$this->assertStringContainsString( 'assets/admin/umc-settings.js', $source );
	}

	public function test_pot_is_current(): void {
		if ( ! $this->can_run_make_pot_check() ) {
			$this->markTestSkipped( 'wp-cli or docker is required for POT drift validation.' );
		}

		$script = $this->root() . '/bin/make-pot.sh';

		if ( ! is_executable( $script ) ) {
			$this->markTestSkipped( 'bin/make-pot.sh is not executable in this environment.' );
		}

		$command = 'bash ' . escapeshellarg( $script ) . ' --check 2>&1';
		$output  = array();
		$code    = 0;
		exec( $command, $output, $code );

		$this->assertSame(
			0,
			$code,
			"POT drift detected. Run: composer make-pot\n" . implode( "\n", $output )
		);
	}

	private function can_run_make_pot_check(): bool {
		$wp_code = 0;
		exec( 'command -v wp >/dev/null 2>&1', $unused, $wp_code );

		if ( 0 === $wp_code ) {
			return true;
		}

		$docker_code = 0;
		exec( 'command -v docker >/dev/null 2>&1', $unused, $docker_code );

		return 0 === $docker_code;
	}
}
