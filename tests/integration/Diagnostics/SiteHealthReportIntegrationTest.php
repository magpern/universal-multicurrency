<?php
/**
 * Integration tests for Site Health diagnostics surfaces.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\SiteHealthReport;
use UMC\Diagnostics\VersionPolicy;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Covers plan integration cases #31–33.
 */
final class SiteHealthReportIntegrationTest extends WP_UnitTestCase {

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

	private const FIXTURE_B = 'umc-fixture-switcher-b/umc-fixture-switcher-b.php';

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $active_plugins_backup = null;

	/**
	 * @var SiteHealthReport|null
	 */
	private ?SiteHealthReport $report = null;

	protected function setUp(): void {
		parent::setUp();

		$this->active_plugins_backup = get_option( 'active_plugins', array() );

		add_filter( DetectorRegistry::FILTER, array( $this, 'register_fixture_detectors' ) );

		$diagnostics = new Diagnostics();
		$diagnostics->register();
		$this->report = new SiteHealthReport( $diagnostics->conflict_detector() );
	}

	protected function tearDown(): void {
		remove_all_filters( DetectorRegistry::FILTER );
		remove_all_filters( 'site_status_tests' );
		remove_all_filters( 'debug_information' );

		if ( null !== $this->active_plugins_backup ) {
			update_option( 'active_plugins', $this->active_plugins_backup );
		}

		parent::tearDown();
	}

	/**
	 * @param array<string, array<string, mixed>> $manifest Built-in manifest from the registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function register_fixture_detectors( array $manifest ): array {
		$manifest['fixture-a'] = array(
			'label'      => 'Fixture Switcher A',
			'signatures' => array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => self::FIXTURE_A,
				),
				array(
					'kind'   => SignatureKind::CLASS_NAME,
					'needle' => 'UMC_Fixture_Switcher_A',
				),
				array(
					'kind'   => SignatureKind::CONSTANT,
					'needle' => 'UMC_FIXTURE_SWITCHER_A_VERSION',
				),
			),
		);

		$manifest['fixture-b'] = array(
			'label'      => 'Fixture Switcher B',
			'signatures' => array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => self::FIXTURE_B,
				),
			),
		);

		return $manifest;
	}

	private function register_partial_fixture_detector(): void {
		require_once __DIR__ . '/fixtures/partial-site-health-fn.php';

		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['fixture-partial'] = array(
					'label'      => 'Partial Switcher',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::FUNCTION,
							'needle' => 'fixture_partial_site_health_fn',
						),
					),
				);

				return $manifest;
			}
		);
	}

	private function activate( string $plugin ): void {
		require_once WP_PLUGIN_DIR . '/' . $plugin;

		$active   = get_option( 'active_plugins', array() );
		$active   = is_array( $active ) ? $active : array();
		$active[] = $plugin;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	}

	private function as_activate_plugins_user(): int {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function registered_tests(): array {
		return apply_filters( 'site_status_tests', array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function run_conflict_test(): array {
		$tests = $this->registered_tests();
		$this->assertArrayHasKey( 'direct', $tests );
		$this->assertArrayHasKey( SiteHealthReport::TEST_CONFLICTS, $tests['direct'] );

		$callback = $tests['direct'][ SiteHealthReport::TEST_CONFLICTS ]['test'];
		$this->assertIsCallable( $callback );

		return (array) call_user_func( $callback );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function run_environment_test(): array {
		$tests = $this->registered_tests();
		$this->assertArrayHasKey( SiteHealthReport::TEST_ENVIRONMENT, $tests['direct'] );

		$callback = $tests['direct'][ SiteHealthReport::TEST_ENVIRONMENT ]['test'];

		return (array) call_user_func( $callback );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function debug_sections(): array {
		return apply_filters( 'debug_information', array() );
	}

	public function test_site_health_tests_are_registered_for_activate_plugins_user(): void {
		$this->as_activate_plugins_user();

		$tests = $this->registered_tests();

		$this->assertArrayHasKey( SiteHealthReport::TEST_CONFLICTS, $tests['direct'] );
		$this->assertArrayHasKey( SiteHealthReport::TEST_ENVIRONMENT, $tests['direct'] );
	}

	public function test_site_health_tests_are_not_registered_for_shop_manager(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'shop_manager',
				)
			)
		);

		$tests = $this->registered_tests();

		$this->assertArrayNotHasKey( SiteHealthReport::TEST_CONFLICTS, $tests['direct'] ?? array() );
		$this->assertArrayNotHasKey( SiteHealthReport::TEST_ENVIRONMENT, $tests['direct'] ?? array() );
	}

	public function test_no_conflicts_reports_good(): void {
		$this->as_activate_plugins_user();

		$result = $this->run_conflict_test();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( SiteHealthReport::TEST_CONFLICTS, $result['test'] );
	}

	public function test_high_confidence_conflict_is_critical(): void {
		$this->as_activate_plugins_user();
		$this->activate( self::FIXTURE_A );

		$result = $this->run_conflict_test();

		$this->assertSame( SiteHealthReport::TEST_CONFLICTS, $result['test'] );
		$this->assertArrayHasKey( 'label', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'badge', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'actions', $result );
		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'red', $result['badge']['color'] );
		$this->assertStringContainsString( 'Fixture Switcher A', $result['description'] );
	}

	public function test_medium_confidence_conflict_is_recommended(): void {
		$this->as_activate_plugins_user();
		$this->register_partial_fixture_detector();

		$result = $this->run_conflict_test();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Partial Switcher', $result['description'] );
	}

	public function test_low_confidence_conflict_is_good_with_description(): void {
		$this->as_activate_plugins_user();

		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['leftover'] = array(
					'label'      => 'Leftover Constant',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::CONSTANT,
							'needle' => 'FIXTURE_LEFTOVER_SITE_HEALTH_ONLY',
						),
					),
				);

				return $manifest;
			}
		);

		if ( ! defined( 'FIXTURE_LEFTOVER_SITE_HEALTH_ONLY' ) ) {
			define( 'FIXTURE_LEFTOVER_SITE_HEALTH_ONLY', '1' );
		}

		$result = $this->run_conflict_test();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'Leftover Constant', $result['description'] );
		$this->assertStringContainsString( 'false positive', $result['description'] );
	}

	public function test_debug_section_contains_expected_fields_without_exchange_rates(): void {
		$this->as_activate_plugins_user();

		$settings = new Settings();
		$settings->save(
			array(
				'schema_version' => Settings::SCHEMA_VERSION,
				'currencies'     => array(
					'EUR' => array(
						'enabled' => true,
						'rate'    => '0.912345',
						'symbol'  => '€',
					),
				),
			)
		);

		$this->activate( self::FIXTURE_A );

		$sections = $this->debug_sections();
		$this->assertArrayHasKey( SiteHealthReport::SECTION, $sections );

		$fields = $sections[ SiteHealthReport::SECTION ]['fields'];

		foreach (
			array(
				'plugin_version',
				'base_currency',
				'currencies_configured',
				'currencies_enabled_and_rated',
				'hpos_enabled',
				'snapshot_schema_version',
				'declared_min_php',
				'declared_min_wp',
				'declared_min_wc',
				'running_php',
				'running_wp',
				'running_wc',
				'conflicts_detected',
				'store_api_conversion',
			) as $key
		) {
			$this->assertArrayHasKey( $key, $fields, "Missing debug field '{$key}'." );
		}

		$this->assertSame( '1', $fields['currencies_configured']['value'] );
		$this->assertSame( '1', $fields['currencies_enabled_and_rated']['value'] );
		$this->assertStringNotContainsString( '0.912345', wp_json_encode( $fields ) );
		$this->assertStringContainsString( 'fixture-a', $fields['conflicts_detected']['value'] );
		$this->assertStringContainsString( 'high', $fields['conflicts_detected']['value'] );
	}

	public function test_debug_section_is_hidden_without_activate_plugins(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'shop_manager',
				)
			)
		);

		$this->assertArrayNotHasKey( SiteHealthReport::SECTION, $this->debug_sections() );
	}

	public function test_environment_test_is_good_when_in_box_and_hpos_enabled(): void {
		$this->as_activate_plugins_user();

		$declared = SiteHealthReport::declared_versions();
		$running  = SiteHealthReport::running_versions();
		$axes     = SiteHealthReport::evaluate_environment_axes( new VersionPolicy(), $declared, $running );
		$expected = SiteHealthReport::environment_status(
			$axes,
			SiteHealthReport::is_hpos_enabled(),
			SiteHealthReport::is_below_announced_floor( $running, array() )
		);

		$result = $this->run_environment_test();

		$this->assertSame( $expected, $result['status'] );
	}

	public function test_environment_test_is_critical_when_wc_is_below_floor(): void {
		$this->as_activate_plugins_user();

		$result = SiteHealthReport::environment_test_result(
			array(
				'php' => VersionPolicy::SUPPORTED,
				'wp'  => VersionPolicy::SUPPORTED,
				'wc'  => VersionPolicy::BELOW_FLOOR,
			),
			true,
			false
		);

		$this->assertSame( 'critical', $result['status'] );
	}

	public function test_environment_test_is_recommended_when_hpos_disabled(): void {
		$this->as_activate_plugins_user();

		update_option( 'woocommerce_custom_orders_table_enabled', 'no' );

		$result = $this->run_environment_test();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'High-Performance Order Storage', $result['description'] );
	}

	public function test_malicious_detector_label_in_conflict_test_is_escaped(): void {
		$this->as_activate_plugins_user();

		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['evil'] = array(
					'label'      => 'Evil & "Switcher"',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::CONSTANT,
							'needle' => 'FIXTURE_EVIL_CONFLICT_LABEL',
						),
					),
				);

				return $manifest;
			}
		);

		if ( ! defined( 'FIXTURE_EVIL_CONFLICT_LABEL' ) ) {
			define( 'FIXTURE_EVIL_CONFLICT_LABEL', '1' );
		}

		$result = $this->run_conflict_test();

		$this->assertStringNotContainsString( '<script>', $result['description'] );
		$this->assertStringContainsString( 'Evil &amp; &quot;Switcher&quot;', $result['description'] );
	}

	public function test_malicious_detector_label_in_debug_is_escaped(): void {
		$this->as_activate_plugins_user();

		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['evil'] = array(
					'label'      => 'Evil & "Switcher"',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::CONSTANT,
							'needle' => 'FIXTURE_EVIL_DEBUG_LABEL',
						),
					),
				);

				return $manifest;
			}
		);

		if ( ! defined( 'FIXTURE_EVIL_DEBUG_LABEL' ) ) {
			define( 'FIXTURE_EVIL_DEBUG_LABEL', '1' );
		}

		$sections = $this->debug_sections();
		$value    = $sections[ SiteHealthReport::SECTION ]['fields']['conflicts_detected']['value'];

		$this->assertStringNotContainsString( '<script>', $value );
		$this->assertStringContainsString( 'Evil &amp; &quot;Switcher&quot;', $value );
	}
}
