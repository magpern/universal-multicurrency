<?php
/**
 * Integration tests for the Multicurrency settings-tab conflict surface.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Admin\SettingsPage;
use UMC\Currency;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\NoticeDismissal;
use UMC\Diagnostics\SignatureKind;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Covers plan cases #30 and #39 on the WooCommerce Multicurrency tab.
 */
final class ConflictNoticeSettingsIntegrationTest extends WP_UnitTestCase {

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

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
		remove_all_filters( 'umc_conflict_settings_view_model' );

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

		return $manifest;
	}

	private function activate( string $plugin ): void {
		require_once WP_PLUGIN_DIR . '/' . $plugin;

		$active   = get_option( 'active_plugins', array() );
		$active   = is_array( $active ) ? $active : array();
		$active[] = $plugin;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	}

	private function render_settings_conflict_field(): string {
		ob_start();
		do_action(
			'woocommerce_admin_field_umc_conflict',
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			)
		);
		return (string) ob_get_clean();
	}

	public function test_settings_page_prepends_the_conflict_field(): void {
		$settings = new Settings();
		$page     = new SettingsPage(
			$settings,
			new Currency( 'USD', 2, '$', 'left', true ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'USD', 'test-lock' )
		);
		$settings_fields = $page->get_settings();

		$this->assertSame( 'umc_conflict', $settings_fields[0]['type'] );
		$this->assertSame( 'umc_conflict_notice', $settings_fields[0]['id'] );
	}

	public function test_settings_tab_notice_renders_without_dismiss_link(): void {
		$this->activate( self::FIXTURE_A );

		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);

		$output = $this->render_settings_conflict_field();

		$this->assertStringContainsString( 'umc-conflict-notice--settings', $output );
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'Fixture Switcher A', $output );
		$this->assertStringContainsString( 'Detected because:', $output );
		$this->assertStringContainsString( 'the plugin &quot;' . self::FIXTURE_A . '&quot; is active', $output );
		$this->assertStringNotContainsString( 'Dismiss this notice', $output );
		$this->assertStringNotContainsString( 'is-dismissible', $output );
	}

	public function test_settings_tab_notice_remains_visible_after_dashboard_dismissal(): void {
		$this->activate( self::FIXTURE_A );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$diagnostics = new Diagnostics();
		$fingerprint = $diagnostics->conflict_detector()->fingerprint();
		$dismissal   = new NoticeDismissal( $diagnostics->conflict_detector() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness simulates a signed dismiss request.
		$_GET[ NoticeDismissal::QUERY_ARG ] = $fingerprint;
		$_GET['_wpnonce']                   = wp_create_nonce( 'umc_dismiss_' . $fingerprint );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness simulates a signed dismiss request.
		$_REQUEST = array_merge( $_REQUEST, $_GET );

		$this->assertNotNull( $dismissal->try_dismiss_from_request() );

		unset( $_GET[ NoticeDismissal::QUERY_ARG ], $_GET['_wpnonce'] );

		set_current_screen( 'dashboard' );
		ob_start();
		do_action( 'admin_notices' );
		$dashboard = (string) ob_get_clean();

		$settings = $this->render_settings_conflict_field();

		$this->assertStringNotContainsString( 'umc-conflict-notice', $dashboard );
		$this->assertStringContainsString( 'umc-conflict-notice--settings', $settings );
	}

	public function test_shop_manager_sees_administrator_resolution_copy_on_settings_tab(): void {
		$this->activate( self::FIXTURE_A );

		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'shop_manager',
				)
			)
		);

		$output = $this->render_settings_conflict_field();

		$this->assertStringContainsString( 'Ask an administrator to deactivate the other switcher', $output );
		$this->assertStringNotContainsString( 'Dismiss this notice', $output );
	}

	public function test_script_bearing_detector_label_renders_escaped_on_settings_tab(): void {
		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				if ( isset( $manifest['fixture-a'] ) ) {
					$manifest['fixture-a']['label'] = '<script>alert("x")</script>Evil Switcher';
				}

				return $manifest;
			}
		);

		remove_all_actions( 'woocommerce_admin_field_umc_conflict' );
		( new Diagnostics() )->register();

		$this->activate( self::FIXTURE_A );

		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);

		$output = $this->render_settings_conflict_field();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'Evil Switcher', $output );
		$this->assertStringContainsString( 'alert(&quot;x&quot;)Evil Switcher', $output );
	}

	public function test_settings_view_model_filter_cannot_inject_markup(): void {
		$malicious_label  = '<img src=x onerror="alert(1)">LabelBreak';
		$malicious_needle = '"><script>alert("needle")</script>';
		$malicious_class  = 'notice notice-error inline" onclick="alert(1)" data-x="';

		add_filter(
			'umc_conflict_settings_view_model',
			static function ( array $view ) use ( $malicious_label, $malicious_needle, $malicious_class ): array {
				$view['notice_class'] = $malicious_class;
				$view['labels']       = array( $malicious_label );

				if ( isset( $view['findings'][0] ) && is_array( $view['findings'][0] ) ) {
					$view['findings'][0]['label'] = $malicious_label;

					if ( isset( $view['findings'][0]['evidence'][0] ) && is_array( $view['findings'][0]['evidence'][0] ) ) {
						$view['findings'][0]['evidence'][0]['needle'] = $malicious_needle;
					}
				}

				return $view;
			}
		);

		$this->activate( self::FIXTURE_A );

		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);

		$output = $this->render_settings_conflict_field();

		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringNotContainsString( ' onclick="alert(1)"', $output );

		$this->assertStringContainsString( esc_html( $malicious_label ), $output );

		$needle_sentence = 'the plugin "' . $malicious_needle . '" is active';
		$this->assertStringContainsString( esc_html( $needle_sentence ), $output );

		$expected_wrapper_class = esc_attr( $malicious_class . ' umc-conflict-notice umc-conflict-notice--settings' );
		$this->assertStringContainsString( 'class="' . $expected_wrapper_class . '"', $output );
	}
}
