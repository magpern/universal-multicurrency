<?php
/**
 * Integration tests for the dashboard conflict notice surface.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use WP_UnitTestCase;

/**
 * Uses install-wp.sh fixtures and the umc_conflict_detectors filter so
 * production DetectorManifest never names test fixtures.
 */
final class ConflictNoticeIntegrationTest extends WP_UnitTestCase {

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

	private const FIXTURE_B = 'umc-fixture-switcher-b/umc-fixture-switcher-b.php';

	private const FIXTURE_INERT = 'umc-fixture-inert/umc-fixture-inert.php';

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $active_plugins_backup = null;

	protected function setUp(): void {
		parent::setUp();

		$this->active_plugins_backup = get_option( 'active_plugins', array() );

		add_filter( DetectorRegistry::FILTER, array( $this, 'register_fixture_detectors' ) );

		( new Diagnostics() )->register();
	}

	protected function tearDown(): void {
		remove_all_filters( DetectorRegistry::FILTER );

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
				array(
					'kind'   => SignatureKind::FUNCTION,
					'needle' => 'umc_fixture_switcher_a_symbol',
				),
				array(
					'kind'   => SignatureKind::SHORTCODE,
					'needle' => 'umc_fixture_switcher_a',
				),
				array(
					'kind'   => SignatureKind::HOOK,
					'needle' => 'umc_fixture_switcher_a_hook',
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

	private function activate( string $plugin ): void {
		require_once WP_PLUGIN_DIR . '/' . $plugin;

		$active   = get_option( 'active_plugins', array() );
		$active   = is_array( $active ) ? $active : array();
		$active[] = $plugin;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	}

	private function render_dashboard_notice_as_admin(): string {
		$admin_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );
		set_current_screen( 'dashboard' );

		ob_start();
		do_action( 'admin_notices' );
		return (string) ob_get_clean();
	}

	public function test_active_fixture_a_renders_high_notice_on_dashboard(): void {
		$this->activate( self::FIXTURE_A );

		$output = $this->render_dashboard_notice_as_admin();

		$this->assertStringContainsString( 'umc-conflict-notice', $output );
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'Fixture Switcher A', $output );
		$this->assertStringContainsString( 'Review multicurrency settings', $output );
		$this->assertStringNotContainsString( 'is-dismissible', $output );
	}

	public function test_inactive_fixture_renders_no_notice(): void {
		$output = $this->render_dashboard_notice_as_admin();

		$this->assertStringNotContainsString( 'umc-conflict-notice', $output );
	}

	public function test_inert_fixture_renders_no_notice(): void {
		$this->activate( self::FIXTURE_INERT );

		$output = $this->render_dashboard_notice_as_admin();

		$this->assertStringNotContainsString( 'umc-conflict-notice', $output );
	}

	public function test_two_active_fixtures_render_one_notice_listing_both(): void {
		$this->activate( self::FIXTURE_A );
		$this->activate( self::FIXTURE_B );

		$output = $this->render_dashboard_notice_as_admin();

		$this->assertSame( 1, substr_count( $output, 'umc-conflict-notice' ) );
		$this->assertStringContainsString( 'Fixture Switcher A', $output );
		$this->assertStringContainsString( 'Fixture Switcher B', $output );
		$this->assertStringContainsString( ' and ', $output );
	}

	public function test_notice_is_suppressed_for_subscriber(): void {
		$this->activate( self::FIXTURE_A );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		ob_start();
		do_action( 'admin_notices' );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'umc-conflict-notice', $output );
	}

	public function test_notice_is_suppressed_for_shop_manager_without_activate_plugins(): void {
		$this->activate( self::FIXTURE_A );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'shop_manager',
			)
		);
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		ob_start();
		do_action( 'admin_notices' );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'umc-conflict-notice', $output );
	}

	public function test_deactivated_plugin_suppresses_notice_for_the_current_request(): void {
		$this->activate( self::FIXTURE_A );

		do_action( 'deactivated_plugin', self::FIXTURE_A );

		$output = $this->render_dashboard_notice_as_admin();

		$this->assertStringNotContainsString( 'umc-conflict-notice', $output );
	}

	public function test_plugin_registers_diagnostics_only_behind_admin_gate(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Plugin.php' );

		$this->assertStringContainsString( 'is_admin()', $source );
		$this->assertStringContainsString( 'wp_doing_ajax()', $source );
		$this->assertStringContainsString( 'wp_doing_cron()', $source );
		$this->assertStringContainsString( 'WP_CLI', $source );
		$this->assertStringContainsString( 'new Diagnostics()', $source );
	}

	public function test_medium_fixture_renders_warning_on_plugins_screen_only(): void {
		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['fixture-class-only'] = array(
					'label'      => 'Fixture Class Only',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::CLASS_NAME,
							'needle' => 'Fixture_Switcher_A_Class_Only',
						),
					),
				);

				return $manifest;
			}
		);

		require_once __DIR__ . '/fixtures/probe-class-only-fixture.php';

		$admin_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );

		set_current_screen( 'dashboard' );
		ob_start();
		do_action( 'admin_notices' );
		$dashboard = (string) ob_get_clean();

		set_current_screen( 'plugins' );
		ob_start();
		do_action( 'admin_notices' );
		$plugins = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'umc-conflict-notice', $dashboard );
		$this->assertStringContainsString( 'umc-conflict-notice', $plugins );
		$this->assertStringContainsString( 'notice-warning', $plugins );
		$this->assertStringContainsString( 'Fixture Class Only', $plugins );

		$findings = ( new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		) )->findings();

		$this->assertCount( 1, $findings );
		$this->assertSame( Confidence::MEDIUM, $findings[0]->confidence() );
	}
}
