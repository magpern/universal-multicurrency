<?php
/**
 * Static security guards for the whole plugin source tree.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Executable security invariants enforced without loading WordPress.
 */
final class SecuritySourceGuardTest extends TestCase {

	use SourceGuardTrait;

	/**
	 * Files permitted to read request superglobals in production code.
	 *
	 * @var array<int, string>
	 */
	private const REQUEST_INPUT_FILES = array(
		'AdminAssets.php',
		'CurrencyActionController.php',
		'CurrencyContext.php',
		'CurrencyOverviewField.php',
		'CurrencySwitcher.php',
		'NoticeDismissal.php',
		'SettingsPage.php',
		'ExchangeRateSettingsField.php',
		'OrderPayCurrencyLock.php',
		'RateUpdateController.php',
	);

	/**
	 * Files permitted to call get_option().
	 *
	 * @var array<int, string>
	 */
	private const GET_OPTION_FILES = array(
		'Settings.php',
		'Plugin.php',
		'WordPressEnvironmentProbe.php',
		'RateUpdateState.php',
		'ExchangeRateSettingsField.php',
		'CurrencyTableField.php',
		'CurrencyViewModelFactory.php',
	);

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * @return array<int, string>
	 */
	private function production_php_files(): array {
		$files = array(
			$this->root() . '/universal-multicurrency.php',
			$this->root() . '/uninstall.php',
		);

		foreach ( $this->umc_source_files() as $file ) {
			$files[] = $file;
		}

		return $files;
	}

	/**
	 * @return array<int, string>
	 */
	private function files_except( array $basenames ): array {
		return array_values(
			array_filter(
				$this->production_php_files(),
				static function ( string $file ) use ( $basenames ): bool {
					return ! in_array( basename( $file ), $basenames, true );
				}
			)
		);
	}

	public function test_src_contains_no_direct_sql(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\$wpdb\b/',
			'Persistence must use WordPress/WooCommerce CRUD, never direct SQL.'
		);
	}

	public function test_production_code_uses_safe_redirect_only(): void {
		$this->assert_pattern_absent_from(
			$this->production_php_files(),
			'/\bwp_redirect\s*\(/',
			'Only wp_safe_redirect() may be used for redirects.'
		);

		$this->assert_pattern_present_in(
			array( $this->root() . '/src/Diagnostics/NoticeDismissal.php' ),
			'/wp_safe_redirect\s*\(/',
			'Notice dismissal must redirect safely.'
		);

		$this->assert_pattern_present_in(
			array( $this->root() . '/src/CurrencySwitcher.php' ),
			'/wp_safe_redirect\s*\(/',
			'Currency switching must redirect safely.'
		);
	}

	public function test_no_dangerous_functions_in_production_code(): void {
		$this->assert_pattern_absent_from(
			$this->production_php_files(),
			'/\b(eval|assert\s*\(|shell_exec|passthru|proc_open|popen|pcntl_exec|create_function)\s*\(/',
			'Dangerous dynamic execution functions are forbidden.'
		);

		$this->assert_pattern_absent_from(
			$this->production_php_files(),
			'/\bunserialize\s*\(/',
			'Untrusted unserialize() is forbidden.'
		);
	}

	public function test_no_debug_output_in_production_code(): void {
		$this->assert_pattern_absent_from(
			$this->production_php_files(),
			'/\b(var_dump|print_r|var_export|debug_print_backtrace|error_log)\s*\(/',
			'Debug output must not ship in production paths.'
		);
	}

	public function test_request_superglobals_are_confined_to_input_boundaries(): void {
		$this->assert_pattern_absent_from(
			$this->files_except( self::REQUEST_INPUT_FILES ),
			'/\$_(GET|POST|REQUEST|COOKIE)\b/',
			'Request superglobals may only be read in approved boundary classes.'
		);
	}

	public function test_get_option_is_confined_to_approved_files(): void {
		$this->assert_pattern_absent_from(
			$this->files_except( self::GET_OPTION_FILES ),
			'/\bget_option\s*\(/',
			'get_option() is limited to settings, WooCommerce base currency, and active_plugins probe.'
		);
	}

	public function test_get_user_meta_is_confined_to_notice_dismissal(): void {
		$this->assert_pattern_absent_from(
			$this->files_except( array( 'NoticeDismissal.php' ) ),
			'/\bget_user_meta\s*\(/',
			'User meta reads are confined to NoticeDismissal.'
		);
	}

	public function test_no_runtime_filesystem_writes_in_src(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\b(file_put_contents|fopen\s*\(|fwrite\s*\(|move_uploaded_file|copy\s*\(|unlink\s*\()\s*\(/',
			'Runtime code must not write to the filesystem.'
		);
	}

	public function test_no_wildcard_metadata_deletion_in_src(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\bdelete_(post_meta|metadata|user_meta)\s*\(\s*[^)]*\*/',
			'Wildcard metadata deletion is forbidden.'
		);
	}

	public function test_no_ajax_or_rest_route_registration_in_src(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\b(wp_ajax_|register_rest_route\s*\()/',
			'The plugin registers no custom AJAX or REST routes.'
		);
	}

	public function test_uninstall_deletes_only_contracted_options(): void {
		$source = (string) file_get_contents( $this->root() . '/uninstall.php' );

		$this->assertSame( 1, preg_match_all( "/delete_option\s*\(\s*['\"]umc_settings['\"]\s*\)/", $source ) );
		$this->assertSame( 1, preg_match_all( "/delete_option\s*\(\s*['\"]umc_rate_state['\"]\s*\)/", $source ) );
		$this->assertStringNotContainsString( 'delete_user_meta', $source );
		$this->assertStringNotContainsString( 'delete_post_meta', $source );
	}

	public function test_build_zip_script_excludes_dev_artifacts(): void {
		$source = (string) file_get_contents( $this->root() . '/bin/build-zip.sh' );

		$this->assertStringNotContainsString( 'tests/', $source );
		$this->assertStringNotContainsString( 'docs/', $source );
		$this->assertStringContainsString( 'composer install --no-dev', $source );
	}

	public function test_conflict_notice_hardens_settings_admin_url(): void {
		$source = (string) file_get_contents( $this->root() . '/src/Diagnostics/ConflictNotice.php' );

		$this->assertStringContainsString( 'settings_admin_url', $source );
		$this->assertMatchesRegularExpression(
			'/admin\.php(?:\?[^#]*)?/',
			$source,
			'Conflict notice settings links must stay on admin.php paths.'
		);
	}

	public function test_currency_context_normalizes_input_codes(): void {
		$source = (string) file_get_contents( $this->root() . '/src/CurrencyContext.php' );

		$this->assertStringContainsString( 'normalize_currency_code', $source );
		$this->assertStringContainsString( "'/^[A-Z]{3}$/'", $source );
	}
}
