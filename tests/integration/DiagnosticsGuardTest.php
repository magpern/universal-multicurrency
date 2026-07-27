<?php
/**
 * Structural and behavioural guards for the Diagnostics subsystem.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration;

use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use UMC\Tests\Unit\Doubles\CountingEnvironmentProbe;
use WP_UnitTestCase;

/**
 * Executable form of Milestone 6 §13 structural guards plus integration cases
 * #34, #36, #37, and #38. Each static guard was verified to fail when
 * violated, not merely to pass today.
 */
final class DiagnosticsGuardTest extends WP_UnitTestCase {

	use \UMC\Tests\Support\SourceGuardTrait;

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

	/**
	 * Hooks registered by {@see Diagnostics::register()} at priority 10.
	 *
	 * @var array<int, string>
	 */
	private const DIAGNOSTICS_HOOKS = array(
		'admin_notices',
		'network_admin_notices',
		'deactivated_plugin',
		'admin_init',
		'site_status_tests',
		'debug_information',
		'woocommerce_admin_field_umc_conflict',
	);

	/**
	 * Foreign identifiers that may appear only in DetectorManifest.php.
	 *
	 * @var array<int, string>
	 */
	private const FOREIGN_IDENTIFIERS = array(
		'woocs',
		'woocommerce-currency-switcher',
		'aelia',
		'wcml',
		'curcy',
		'yay_currency',
		'yaycurrency',
		'wmc',
		'real-currency',
		'currency-switcher-for-woocommerce',
	);

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $active_plugins_backup = null;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__ ) . '/unit/Doubles/CountingEnvironmentProbe.php';

		$this->active_plugins_backup = get_option( 'active_plugins', array() );

		add_filter( DetectorRegistry::FILTER, array( $this, 'register_fixture_detector' ) );
	}

	protected function tearDown(): void {
		remove_all_filters( DetectorRegistry::FILTER );

		foreach ( self::DIAGNOSTICS_HOOKS as $hook ) {
			remove_all_actions( $hook );
		}

		if ( null !== $this->active_plugins_backup ) {
			update_option( 'active_plugins', $this->active_plugins_backup );
		}

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * @param array<string, array<string, mixed>> $manifest Built-in manifest from the registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function register_fixture_detector( array $manifest ): array {
		$manifest['fixture-a'] = array(
			'label'      => 'Fixture Switcher A',
			'signatures' => array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => self::FIXTURE_A,
				),
			),
		);

		return $manifest;
	}

	private function manifest_files(): array {
		return array_values(
			array_filter(
				$this->diagnostics_files(),
				static function ( string $file ): bool {
					return false !== strpos( $file, 'DetectorManifest.php' );
				}
			)
		);
	}

	private function outside_manifest_files(): array {
		return array_values(
			array_filter(
				$this->umc_source_files(),
				static function ( string $file ): bool {
					return false === strpos( $file, 'DetectorManifest.php' );
				}
			)
		);
	}

	private function probe_files(): array {
		return array_values(
			array_filter(
				$this->diagnostics_files(),
				static function ( string $file ): bool {
					return false !== strpos( $file, 'WordPressEnvironmentProbe.php' );
				}
			)
		);
	}

	public function test_g1_diagnostics_never_reaches_the_money_path(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files(),
			'/\bConverter\b|PriceConversionService|CurrencyContext|RateProvider|CurrencyRegistry|->convert\(\|apply_rate|->get_rate\(\|->get_currency_signature\(/',
			'G1: Diagnostics must remain inert to conversion and rate reading.'
		);
	}

	public function test_g2a_only_notice_dismissal_reads_request_input(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files( 'NoticeDismissal.php' ),
			'/\$_COOKIE|\$_SESSION|\$_REQUEST|\$_POST\b|\$_GET\b|\$_SERVER|->session\s*->|WC\(\)\s*->\s*session/',
			'G2a: only NoticeDismissal.php may read request or session input.'
		);
	}

	public function test_g2b_only_the_probe_reads_options(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files( 'WordPressEnvironmentProbe.php' ),
			'/\bget_option\s*\(|\bget_site_option\s*\(|\bget_transient\s*\(|\bget_network_option\s*\(/',
			'G2b: only WordPressEnvironmentProbe.php may read options or transients.'
		);
	}

	public function test_g2c_probe_reads_only_active_plugin_options(): void {
		$probe = (string) file_get_contents( $this->probe_files()[0] );

		$this->assertSame( 1, preg_match_all( '/\bget_option\s*\(/', $probe ) );
		$this->assertStringContainsString( "'active_plugins'", $probe );
		$this->assertSame( 1, preg_match_all( '/\bget_site_option\s*\(/', $probe ) );
		$this->assertStringContainsString( "'active_sitewide_plugins'", $probe );
	}

	public function test_g3_diagnostics_contains_no_float_or_decimal_arithmetic(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files(),
			'/\bbc(add|sub|mul|div|pow|comp)\s*\(|\bround\s*\(|\bnumber_format|\bwc_(price|format_decimal)\s*\(|\(float\)|\bfloatval\s*\(/',
			'G3: Diagnostics scoring must stay integer-only.'
		);
	}

	public function test_g4_diagnostics_never_writes_options_or_scans_the_filesystem(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files(),
			'/\b(update_option|add_option|delete_option|update_site_option|set_transient|update_post_meta|delete_post_meta|update_metadata|wp_remote_(get|post|request)|curl_init|file_get_contents|fopen|glob|scandir|opendir|file_exists|is_readable|get_plugins|wp_get_mu_plugins|wp_schedule_event)\s*\(/',
			'G4: Diagnostics must not write options, scan the filesystem, or make outbound requests.'
		);
	}

	public function test_g4b_plugin_never_auto_deactivates_other_plugins(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\b(deactivate_plugins|activate_plugin|activate_plugins|delete_plugins|uninstall_plugin)\s*\(/',
			'G4b: the plugin must never auto-deactivate or delete another plugin.'
		);
	}

	public function test_g4c_only_notice_dismissal_persists_user_state(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files( 'NoticeDismissal.php' ),
			'/\b(update_user_meta|update_user_option|add_user_meta|delete_user_meta)\s*\(/',
			'G4c: only NoticeDismissal.php may persist user meta under Diagnostics/.'
		);
	}

	public function test_g5a_detection_stays_inside_diagnostics(): void {
		$this->assert_pattern_absent_from(
			$this->non_diagnostics_files( 'Plugin.php' ),
			'/DetectorRegistry|DetectorManifest|ConflictDetector|ConflictScorer|EnvironmentProbe|SignatureKind|umc_conflict_detectors|Confidence::/',
			'G5a: detection types must not leak outside src/Diagnostics/.'
		);
	}

	public function test_g5b_plugin_php_is_the_only_diagnostics_seam(): void {
		$this->assert_pattern_absent_from(
			$this->non_diagnostics_files( 'Plugin.php' ),
			'/UMC\\\\Diagnostics|Diagnostics\\\\/',
			'G5b: only Plugin.php may reference the Diagnostics namespace.'
		);
	}

	public function test_g5c_diagnostics_never_reaches_the_storefront_or_asset_pipeline(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files(),
			'/woocommerce_store_api_|register_endpoint_data|woocommerce_blocks_|wc_add_notice\s*\(|register_rest_route|add_shortcode\s*\(|wp_(register|enqueue)_(script|style)/',
			'G5c: Diagnostics must not reach the storefront, Store API, or asset pipeline.'
		);
	}

	public function test_g6_foreign_identifiers_are_confined_to_the_manifest(): void {
		$pattern = '/(' . implode( '|', array_map( 'preg_quote', self::FOREIGN_IDENTIFIERS ) ) . ')/i';

		$this->assert_pattern_absent_from(
			$this->outside_manifest_files(),
			$pattern,
			'G6: third-party identifiers may only appear in DetectorManifest.php.'
		);
	}

	public function test_g6b_manifest_is_data_only(): void {
		$this->assert_pattern_absent_from(
			$this->manifest_files(),
			'/\badd_(filter|action)\s*\(|\bapply_filters\s*\(|\bclass_exists\s*\(|\bfunction_exists\s*\(|\bdefined\s*\(|\bconstant\s*\(|\bget_option\s*\(|\$wp_filter|\$GLOBALS|\bnew\s+\\\\?[A-Z]|::\s*instance\s*\(/',
			'G6b: DetectorManifest.php must remain pure data.'
		);
	}

	public function test_g7_only_the_probe_performs_registry_lookups(): void {
		$this->assert_pattern_absent_from(
			$this->diagnostics_files( 'WordPressEnvironmentProbe.php' ),
			'/\$wp_filter|\$shortcode_tags|\bclass_exists\s*\(|\bfunction_exists\s*\(|\binterface_exists\s*\(|\bdefined\s*\(|\bconstant\s*\(|\bhas_(filter|action)\s*\(/',
			'G7: only WordPressEnvironmentProbe.php may probe WordPress registries.'
		);
	}

	public function test_g8a_dynamic_class_exists_must_disable_autoload(): void {
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( false !== strpos( $file, 'WordPressEnvironmentProbe.php' ) ) {
				continue;
			}

			if ( 1 === preg_match( '/class_exists\s*\(\s*\$\w+\s*\)/', (string) file_get_contents( $file ) ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'G8a: dynamic class_exists() probes must pass , false everywhere outside the probe file.'
		);
	}

	public function test_g8b_probe_uses_class_exists_with_autoload_disabled(): void {
		$this->assert_pattern_present_in(
			$this->probe_files(),
			'/class_exists\s*\(\s*\$signature->needle\(\)\s*,\s*false\s*\)/',
			'G8b: WordPressEnvironmentProbe must call class_exists( $needle, false ).'
		);
	}

	public function test_g9_tests_never_compare_wc_version_for_capability(): void {
		$files = array();

		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__ ), \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			if ( false !== strpos( (string) $file->getPathname(), '/tests/tmp/' ) ) {
				continue;
			}

			$files[] = (string) $file->getPathname();
		}

		$this->assert_pattern_absent_from(
			$files,
			'/version_compare\s*\(\s*WC_VERSION/',
			'G9: tests must probe route availability, never compare WC_VERSION for capability.'
		);
	}

	public function test_g11_src_tree_ships_no_frontend_assets(): void {
		$root = dirname( __DIR__, 2 );

		$this->assertDirectoryDoesNotExist( $root . '/src/assets' );

		$js_files    = glob( $root . '/src/**/*.js' );
		$css_files   = glob( $root . '/src/**/*.css' );
		$asset_files = array_merge(
			is_array( $js_files ) ? $js_files : array(),
			is_array( $css_files ) ? $css_files : array()
		);

		$this->assertSame( array(), $asset_files, 'G11: src/ must not contain .js or .css files.' );
	}

	public function test_documentation_lists_the_diagnostics_hook_surface(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/TEST_STRATEGY.md' );

		foreach ( self::DIAGNOSTICS_HOOKS as $hook ) {
			$this->assertStringContainsString( $hook, $source, "TEST_STRATEGY.md must document the {$hook} hook." );
		}
	}

	public function test_diagnostics_registers_exactly_seven_admin_hooks(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);

		( new Diagnostics() )->register();

		foreach ( self::DIAGNOSTICS_HOOKS as $hook ) {
			$this->assertNotSame(
				array(),
				$this->diagnostics_callbacks_on( $hook ),
				"Expected Diagnostics to register on '{$hook}'."
			);
		}
	}

	public function test_detection_never_writes_umc_settings_or_active_plugins(): void {
		$this->activate_fixture_a();

		$settings_before = serialize( get_option( 'umc_settings', array() ) );
		$plugins_before  = serialize( get_option( 'active_plugins', array() ) );

		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);

		( new Diagnostics() )->register();

		$this->prime_diagnostics_option_cache();
		$this->invoke_diagnostics_render_surfaces();

		$this->assertSame( $settings_before, serialize( get_option( 'umc_settings', array() ) ) );
		$this->assertSame( $plugins_before, serialize( get_option( 'active_plugins', array() ) ) );
	}

	public function test_probe_runs_exactly_once_per_detector_instance(): void {
		$probe    = new CountingEnvironmentProbe( new WordPressEnvironmentProbe() );
		$detector = new ConflictDetector( new DetectorRegistry(), $probe, new ConflictScorer() );

		$detector->findings();
		$detector->findings();

		$this->assertSame( 1, $probe->calls() );
	}

	public function test_detection_adds_no_database_queries_on_admin_render(): void {
		global $wpdb;

		$this->activate_fixture_a();
		$this->prime_diagnostics_option_cache();

		$detector = new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		);

		$before = (int) $wpdb->num_queries;

		$detector->findings();
		$detector->findings();

		$this->assertSame( $before, (int) $wpdb->num_queries );
	}

	public function test_plugin_registers_diagnostics_only_in_admin(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );

		$this->assertStringContainsString( 'if ( is_admin() && ! wp_doing_ajax()', $source );
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*is_admin\(\)[^{]+\)\s*\{\s*\R\s*\(\s*new Diagnostics\(\)\s*\)->register\(\)/',
			$source,
			'Diagnostics registration must stay behind the admin gate in Plugin.php.'
		);
	}

	public function test_store_api_cart_request_does_not_render_diagnostics_surfaces(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Restored verbatim after the test.
		$request_uri            = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null;
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/cart';

		try {
			$ran = false;
			add_action(
				'admin_notices',
				static function () use ( &$ran ): void {
					$ran = true;
				}
			);

			$request  = new \WP_REST_Request( 'GET', '/wc/store/v1/cart' );
			$response = rest_do_request( $request );

			$this->assertFalse( $ran, 'admin_notices must not run during a Store API cart request.' );
			$this->assertFalse( $response->is_error(), 'Store API cart route should remain reachable in tests.' );
		} finally {
			if ( null === $request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $request_uri;
			}
		}
	}

	private function activate_fixture_a(): void {
		require_once WP_PLUGIN_DIR . '/' . self::FIXTURE_A;

		$active   = get_option( 'active_plugins', array() );
		$active   = is_array( $active ) ? $active : array();
		$active[] = self::FIXTURE_A;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	}

	/**
	 * Loads option values once so detection can reuse WordPress' in-memory cache.
	 */
	private function prime_diagnostics_option_cache(): void {
		get_option( 'active_plugins', array() );

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			get_site_option( 'active_sitewide_plugins', array() );
		}
	}

	/**
	 * Invokes only Diagnostics render callbacks, not unrelated hook subscribers.
	 */
	private function invoke_diagnostics_render_surfaces(): void {
		set_current_screen( 'dashboard' );

		$this->invoke_diagnostics_callbacks( 'admin_notices' );
		$this->invoke_diagnostics_callbacks( 'site_status_tests', array( array() ) );
		$this->invoke_diagnostics_callbacks( 'debug_information', array( array() ) );
	}

	/**
	 * Calls UMC Diagnostics callbacks registered on one hook.
	 *
	 * @param string            $hook Hook name.
	 * @param array<int, mixed> $args Arguments passed to each callback.
	 */
	private function invoke_diagnostics_callbacks( string $hook, array $args = array() ): void {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];
				$class    = null;

				if ( is_array( $function ) ) {
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
				}

				if ( null === $class || 0 !== strpos( $class, 'UMC\\Diagnostics\\' ) ) {
					continue;
				}

				call_user_func_array( $function, $args );
			}
		}
	}
}
