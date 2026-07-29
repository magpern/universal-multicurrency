<?php
/**
 * Integration tests for the Multicurrency admin page shell.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\AdminAssets;
use UMC\Admin\SettingsPage;
use UMC\Currency;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\SignatureKind;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies shell navigation, headers, save-button behavior, and asset scoping.
 */
final class AdminPageShellTest extends WP_UnitTestCase {

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
		unset( $GLOBALS['hide_save_button'] );

		if ( null !== $this->active_plugins_backup ) {
			update_option( 'active_plugins', $this->active_plugins_backup );
		}

		unset( $_GET['section'], $_GET['page'], $_GET['tab'] );

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
			),
		);

		return $manifest;
	}

	public function test_shell_navigation_renders_six_items_with_active_state(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_DISPLAY;
		$output          = $this->render_shell_sections();

		$this->assertSame( 6, preg_match_all( '/class="umc-shell-nav__item(?:\s|")/', $output ) );
		$this->assertStringContainsString( 'aria-current="page"', $output );
		$this->assertStringContainsString( 'umc-shell-nav__item--active', $output );
		$this->assertStringContainsString( 'Exchange Rates', $output );
	}

	public function test_navigation_urls_preserve_page_tab_and_section_slug(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_EXCHANGE_RATES;
		$output          = $this->render_shell_sections();

		$this->assertStringContainsString( 'page=wc-settings', $output );
		$this->assertStringContainsString( 'tab=umc', $output );
		$this->assertStringContainsString( 'section=currencies', $output );
		$this->assertStringContainsString( 'section=exchange_rates', $output );
		$this->assertStringContainsString( 'section=display', $output );
	}

	public function test_section_header_renders_title_description_and_version_badge(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_DISPLAY;
		$output          = $this->render_shell_sections() . $this->render_shell_output();

		$this->assertStringContainsString( 'umc-section-card__title', $output );
		$this->assertStringContainsString( 'Display', $output );
		$this->assertStringContainsString( 'Configure how prices and currency information are displayed across your store.', $output );
		$this->assertStringContainsString( 'umc-shell-header__version', $output );

		if ( defined( 'UMC_VERSION' ) ) {
			$this->assertStringContainsString( 'v' . UMC_VERSION, $output );
		}
	}

	public function test_placeholder_section_hides_save_button_and_renders_info_panel(): void {
		global $current_section, $hide_save_button;

		$current_section = SettingsPage::SECTION_CHECKOUT;
		$output          = $this->render_shell_output();

		$this->assertTrue( ! empty( $hide_save_button ) );
		$this->assertStringNotContainsString( 'umc-shell-header__save', $this->render_shell_sections() );
		$this->assertStringContainsString( 'umc-placeholder-panel', $output );
		$this->assertStringContainsString( 'This section will be implemented in a future milestone.', $output );
		$this->assertStringContainsString( 'We&#039;re working on checkout currency behavior options for your store.', $output );
	}

	public function test_saveable_section_exposes_header_save_button(): void {
		global $current_section, $hide_save_button;

		$current_section = SettingsPage::SECTION_CURRENCIES;
		$sections        = $this->render_shell_sections();
		$this->render_shell_output();

		$this->assertTrue( ! empty( $hide_save_button ) );
		$this->assertStringContainsString( 'umc-shell-header__save', $sections );
		$this->assertStringContainsString( 'form="mainform"', $sections );
	}

	public function test_display_section_hides_header_save_and_renders_sticky_bar(): void {
		global $current_section, $hide_save_button;

		$current_section = SettingsPage::SECTION_DISPLAY;
		$sections        = $this->render_shell_sections();
		$output          = $this->render_shell_output();

		$this->assertTrue( ! empty( $hide_save_button ) );
		$this->assertStringNotContainsString( 'umc-shell-header__save', $sections );
		$this->assertStringContainsString( 'umc-display-actions', $output );
		$this->assertSame( 1, substr_count( $output, 'class="button button-primary umc-display-actions__save"' ) );
		$this->assertStringNotContainsString( 'umc-section-card__submit', $output );
	}

	public function test_shell_output_does_not_introduce_nested_forms(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_CURRENCIES;
		$output          = $this->render_shell_output();

		$this->assertSame( 0, substr_count( strtolower( $output ), '<form' ) );
	}

	public function test_conflict_notice_renders_in_header_not_section_body(): void {
		$this->activate_fixture();

		global $current_section;

		$current_section = SettingsPage::SECTION_CURRENCIES;
		$sections        = $this->render_shell_sections();
		$content         = $this->render_shell_output();

		$this->assertStringContainsString( 'umc-shell-header__notice', $sections );
		$this->assertStringContainsString( 'umc-conflict-notice--settings', $sections );
		$this->assertStringNotContainsString( 'umc-conflict-notice--settings', $content );
	}

	public function test_admin_assets_remain_scoped_to_umc_settings_tab(): void {
		$assets = new AdminAssets();
		$assets->register();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET['page'] = 'wc-settings';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET['tab'] = 'other-tab';

		set_current_screen( 'woocommerce_page_wc-settings' );

		$assets->enqueue( 'woocommerce_page_wc-settings' );

		$this->assertFalse( wp_style_is( 'umc-admin-settings', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'umc-admin-settings', 'enqueued' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET['tab'] = 'umc';

		$assets->enqueue( 'woocommerce_page_wc-settings' );

		$this->assertTrue( wp_style_is( 'umc-admin-settings', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'umc-admin-settings', 'enqueued' ) );
		$this->assertStringContainsString( 'umc-settings-page', $assets->body_class( '' ) );
	}

	public function test_exchange_rates_section_renders_global_rate_fields(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_EXCHANGE_RATES;
		$output          = $this->render_shell_output();

		$this->assertStringContainsString( 'name="umc_rate_mode"', $output );
		$this->assertStringContainsString( 'name="umc_rate_update_interval"', $output );
		$this->assertStringContainsString( 'name="umc_rate_max_age_hours"', $output );
		$this->assertStringContainsString( 'Update all automatic rates', $output );
	}

	public function test_currency_save_behavior_remains_unchanged(): void {
		$settings = new Settings();
		$page     = new SettingsPage(
			$settings,
			new Currency( 'EUR', 2 ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
		);
		$settings->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'SEK' => array(
							'code'         => 'SEK',
							'enabled'      => true,
							'mode'         => 'manual',
							'position'     => 'left_space',
							'decimals'     => 2,
							'manual_rate'  => '10.00',
							'symbol'       => 'kr',
							'symbol_space' => true,
						),
					),
				)
			)
		);

		global $current_section;

		$current_section = SettingsPage::SECTION_CURRENCIES;
		$_POST           = array(
			'umc_currencies' => array(
				array(
					'code'         => 'SEK',
					'enabled'      => '1',
					'mode'         => 'manual',
					'manual_rate'  => '11.50',
					'position'     => 'left_space',
					'decimals'     => '2',
					'symbol'       => 'kr',
					'symbol_space' => '1',
				),
			),
		);

		$page->save();

		$saved = $settings->get_currency_config( 'SEK' );

		$this->assertNotNull( $saved );
		$this->assertSame( '11.50', $saved['manual_rate'] );
	}

	private function render_shell_sections(): string {
		ob_start();
		$this->page()->output_sections();

		return (string) ob_get_clean();
	}

	private function render_shell_output(): string {
		ob_start();
		$this->page()->output();

		return (string) ob_get_clean();
	}

	private function page(): SettingsPage {
		$settings = new Settings();

		return new SettingsPage(
			$settings,
			new Currency( 'EUR', 2 ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
		);
	}

	private function activate_fixture(): void {
		require_once WP_PLUGIN_DIR . '/' . self::FIXTURE_A;

		$active   = get_option( 'active_plugins', array() );
		$active   = is_array( $active ) ? $active : array();
		$active[] = self::FIXTURE_A;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );

		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);
	}
}
