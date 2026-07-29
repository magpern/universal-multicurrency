<?php
/**
 * Integration tests for the Compatibility diagnostics center.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Compatibility;

use UMC\Admin\CompatibilitySettingsField;
use UMC\Admin\SettingsPage;
use UMC\Compatibility\CompatibilityServices;
use UMC\Currency;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies Compatibility admin rendering and contracts.
 */
final class CompatibilitySettingsFieldTest extends WP_UnitTestCase {

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $active_plugins_backup = null;

	public function set_up(): void {
		parent::set_up();

		$this->active_plugins_backup = get_option( 'active_plugins', array() );
		update_option( 'woocommerce_currency', 'EUR' );
		wp_set_current_user( 1 );
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		add_filter( DetectorRegistry::FILTER, array( $this, 'register_fixture_detectors' ) );
	}

	public function tear_down(): void {
		remove_all_filters( DetectorRegistry::FILTER );

		if ( null !== $this->active_plugins_backup ) {
			update_option( 'active_plugins', $this->active_plugins_backup );
		}

		unset( $_GET['section'], $_REQUEST['section'] );
		parent::tear_down();
	}

	public function test_compatibility_section_exposes_custom_field_not_placeholder(): void {
		$page  = $this->settings_page();
		$types = array_column( $page->get_settings_for_section( SettingsPage::SECTION_COMPATIBILITY ), 'type' );

		$this->assertContains( 'umc_compatibility', $types );
		$this->assertNotContains( 'umc_placeholder', $types );
		$this->assertNotContains( 'umc_conflict', $types );
		$this->assertFalse( $page->section_has_saveable_settings( SettingsPage::SECTION_COMPATIBILITY ) );
	}

	public function test_clean_store_renders_summary_and_report_without_duplicate_conflict_notice(): void {
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					'currencies' => array(
						'EUR' => array(
							'enabled'     => true,
							'symbol'      => '€',
							'manual_rate' => '1',
						),
						'USD' => array(
							'enabled'     => true,
							'symbol'      => '$',
							'manual_rate' => '1.1',
						),
					),
				)
			)
		);
		update_option( 'permalink_structure', '/%postname%/' );

		$html = $this->capture_render();

		$this->assertStringContainsString( 'umc-compatibility', $html );
		$this->assertStringContainsString( 'All checks passed', $html );
		$this->assertStringContainsString( 'UMC Compatibility Report v1', $html );
		$this->assertStringContainsString( 'data-umc-compat-copy-report', $html );
		$this->assertStringNotContainsString( 'umc-conflict-notice', $html );
		$this->assertStringNotContainsString( 'Save changes', $html );
	}

	public function test_active_conflict_renders_conflict_summary(): void {
		update_option(
			'active_plugins',
			array_merge(
				(array) get_option( 'active_plugins', array() ),
				array( self::FIXTURE_A )
			)
		);

		require_once WP_PLUGIN_DIR . '/' . self::FIXTURE_A;

		$html = $this->capture_render();

		$this->assertStringContainsString( 'Conflict detected', $html );
		$this->assertStringContainsString( 'Fixture Switcher A', $html );
		$this->assertStringContainsString( 'Critical conflicts', $html );
	}

	public function test_markup_contract_includes_accessibility_hooks(): void {
		$html = $this->capture_render();

		$this->assertMatchesRegularExpression( '/<h3[^>]+id="umc-compat-summary-title"/', $html );
		$this->assertStringContainsString( 'aria-live="polite"', $html );
		$this->assertStringContainsString( 'for="umc-compat-report-text"', $html );
		$this->assertStringContainsString( 'data-umc-compat-copy-report', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}

	/**
	 * @param array<string, array<string, mixed>> $manifest Built-in manifest.
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

		return $manifest;
	}

	private function settings_page(): SettingsPage {
		$settings = new Settings();

		return new SettingsPage(
			$settings,
			new Currency( 'EUR', 2 ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
		);
	}

	private function capture_render(): string {
		$settings = new Settings();
		$base     = new Currency( 'EUR', 2 );
		$store    = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );
		$detector = new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		);
		$field    = new CompatibilitySettingsField(
			CompatibilityServices::scanner( $settings, $store, $base, $detector )
		);

		ob_start();
		$field->render(
			array(
				'type' => 'umc_compatibility',
				'id'   => 'umc_compatibility',
			)
		);

		return (string) ob_get_clean();
	}
}
