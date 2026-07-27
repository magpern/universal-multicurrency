<?php
/**
 * Integration tests for per-user conflict notice dismissal.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\NoticeDismissal;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use WP_UnitTestCase;

/**
 * Exercises plan cases #24–#29 for persisted dashboard notice dismissal.
 */
final class NoticeDismissalIntegrationTest extends WP_UnitTestCase {

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

	private const FIXTURE_B = 'umc-fixture-switcher-b/umc-fixture-switcher-b.php';

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $active_plugins_backup = null;

	/**
	 * @var Diagnostics
	 */
	private Diagnostics $diagnostics;

	protected function setUp(): void {
		parent::setUp();

		$this->active_plugins_backup = get_option( 'active_plugins', array() );
		$_SERVER['REQUEST_URI']      = '/wp-admin/index.php';

		add_filter( DetectorRegistry::FILTER, array( $this, 'register_fixture_detectors' ) );

		$this->diagnostics = new Diagnostics();
		$this->diagnostics->register();
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

	private function create_admin(): int {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		return $user_id;
	}

	private function detector(): ConflictDetector {
		return $this->diagnostics->conflict_detector();
	}

	private function dismissal(): NoticeDismissal {
		return new NoticeDismissal( $this->detector() );
	}

	private function render_notice_on( string $screen_id ): string {
		set_current_screen( $screen_id );

		ob_start();
		do_action( 'admin_notices' );
		return (string) ob_get_clean();
	}

	private function request_dismissal( string $fingerprint ): ?string {
		$this->assertGreaterThan( 0, get_current_user_id(), 'Dismiss tests require a logged-in user.' );

		$submitted = sanitize_key( $fingerprint );

		$_GET[ NoticeDismissal::QUERY_ARG ] = $submitted;
		$_GET['_wpnonce']                   = wp_create_nonce( 'umc_dismiss_' . $submitted );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness simulates a signed dismiss request.
		$_REQUEST = array_merge( $_REQUEST, $_GET );

		return $this->dismissal()->try_dismiss_from_request();
	}

	public function test_valid_dismissal_writes_user_meta_and_hides_notice_on_reload(): void {
		$this->activate( self::FIXTURE_A );
		$user_id     = $this->create_admin();
		$fingerprint = $this->detector()->fingerprint();

		$this->assertNotSame( '', $fingerprint );
		$this->assertStringContainsString( 'umc-conflict-notice', $this->render_notice_on( 'dashboard' ) );

		$redirect = $this->request_dismissal( $fingerprint );

		$this->assertNotNull( $redirect );
		$this->assertStringNotContainsString( NoticeDismissal::QUERY_ARG, $redirect );
		$this->assertStringNotContainsString( '_wpnonce', $redirect );

		$stored = get_user_meta( $user_id, NoticeDismissal::META_KEY, true );
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( $fingerprint, $stored );

		unset( $_GET[ NoticeDismissal::QUERY_ARG ], $_GET['_wpnonce'] );

		$this->assertStringNotContainsString( 'umc-conflict-notice', $this->render_notice_on( 'dashboard' ) );
	}

	public function test_dismissal_without_nonce_does_not_write(): void {
		$this->activate( self::FIXTURE_A );
		$this->create_admin();
		$fingerprint = $this->detector()->fingerprint();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exercises the missing-nonce failure path.
		$_GET[ NoticeDismissal::QUERY_ARG ] = $fingerprint;
		unset( $_GET['_wpnonce'] );
		$_SERVER['HTTP_REFERER'] = admin_url( 'index.php' );

		$this->expectException( \WPDieException::class );
		$this->dismissal()->try_dismiss_from_request();
	}

	public function test_dismissal_without_activate_plugins_does_not_write(): void {
		$this->activate( self::FIXTURE_A );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'shop_manager',
			)
		);
		wp_set_current_user( $user_id );

		$fingerprint = $this->detector()->fingerprint();

		$this->assertNull( $this->request_dismissal( $fingerprint ) );
		$this->assertSame( '', get_user_meta( $user_id, NoticeDismissal::META_KEY, true ) );
	}

	public function test_dismissal_is_per_user(): void {
		$this->activate( self::FIXTURE_A );

		$user_a = $this->create_admin();
		$user_b = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$fingerprint = $this->detector()->fingerprint();
		$this->request_dismissal( $fingerprint );

		wp_set_current_user( $user_b );
		$this->assertStringContainsString( 'umc-conflict-notice', $this->render_notice_on( 'dashboard' ) );

		wp_set_current_user( $user_a );
		$this->assertStringNotContainsString( 'umc-conflict-notice', $this->render_notice_on( 'dashboard' ) );
	}

	public function test_new_conflict_resurfaces_after_dismissal(): void {
		$this->activate( self::FIXTURE_A );
		$this->create_admin();

		$first = $this->detector()->fingerprint();
		$this->request_dismissal( $first );

		unset( $_GET[ NoticeDismissal::QUERY_ARG ], $_GET['_wpnonce'] );
		$this->assertStringNotContainsString( 'umc-conflict-notice', $this->render_notice_on( 'dashboard' ) );

		$this->activate( self::FIXTURE_B );

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'admin_init' );

		$this->diagnostics = new Diagnostics();
		$this->diagnostics->register();

		$second = $this->detector()->fingerprint();
		$this->assertNotSame( $first, $second );
		$this->assertStringContainsString( 'umc-conflict-notice', $this->render_notice_on( 'dashboard' ) );
	}

	public function test_high_on_plugins_screen_ignores_dismissal(): void {
		$this->activate( self::FIXTURE_A );
		$this->create_admin();

		$fingerprint = $this->detector()->fingerprint();
		$this->request_dismissal( $fingerprint );

		unset( $_GET[ NoticeDismissal::QUERY_ARG ], $_GET['_wpnonce'] );

		$output = $this->render_notice_on( 'plugins' );

		$this->assertStringContainsString( 'umc-conflict-notice', $output );
		$this->assertStringNotContainsString( 'is-dismissible', $output );
	}

	public function test_maybe_dismiss_uses_check_admin_referer(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Diagnostics/NoticeDismissal.php' );

		$this->assertStringContainsString( 'check_admin_referer', $source );
	}
}
