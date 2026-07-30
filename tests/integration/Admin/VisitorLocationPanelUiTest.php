<?php
/**
 * Integration tests for Visitor Location admin UI.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Admin\SettingsPage;
use UMC\Currency;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies Visitor Location design-system markup and save contracts.
 */
final class VisitorLocationPanelUiTest extends WP_UnitTestCase {

	/**
	 * Cached settings page for this test case.
	 *
	 * @var SettingsPage|null
	 */
	private ?SettingsPage $page = null;

	protected function setUp(): void {
		parent::setUp();

		remove_all_actions( 'woocommerce_admin_field_umc_geo_detection' );
		remove_all_actions( 'woocommerce_admin_field_umc_checkout' );
		remove_all_actions( 'woocommerce_admin_field_umc_display' );
		remove_all_actions( 'woocommerce_admin_field_umc_exchange_rates' );
		remove_all_actions( 'woocommerce_admin_field_umc_compatibility' );
		remove_all_actions( 'woocommerce_admin_field_umc_currencies' );
		remove_all_actions( 'woocommerce_admin_field_umc_placeholder' );
	}

	protected function tearDown(): void {
		unset( $_GET['section'], $_GET['page'], $_GET['tab'], $_GET[ GeoPanelRegistry::QUERY_VAR ] );
		unset( $GLOBALS['hide_save_button'] );
		$this->page = null;

		parent::tearDown();
	}

	public function test_main_navigation_uses_visitor_location_label(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		$output          = $this->render_shell_sections();

		$this->assertStringContainsString( 'Visitor Location', $output );
		$this->assertStringNotContainsString( '>Geo Detection<', $output );
		$this->assertStringContainsString( 'section=geo_detection', $output );
	}

	public function test_pill_navigation_renders_seven_items_with_icons_and_active_state(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_SETTINGS;

		$output = $this->render_geo_field();

		$this->assertSame( 7, preg_match_all( '/class="umc-ui-pill-nav__item(?:\s|")/', $output ) );
		$this->assertStringContainsString( 'umc-ui-pill-nav__icon', $output );
		$this->assertStringContainsString( 'aria-current="page"', $output );
	}

	public function test_settings_panel_preserves_field_names_and_hidden_zero_values(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_SETTINGS;

		$output = $this->render_geo_field();

		$this->assertStringContainsString( 'name="umc_geo[enabled]"', $output );
		$this->assertStringContainsString( 'name="umc_geo[mode]"', $output );
		$this->assertStringContainsString( 'name="umc_geo[allow_wc_geolocation_fallback]"', $output );
		$this->assertStringContainsString( 'name="umc_geo[fallback_currency]"', $output );
		$this->assertStringContainsString( 'name="umc_geo[checkout][lock_on_entry]"', $output );
		$this->assertStringContainsString( 'type="hidden" name="umc_geo[enabled]" value="0"', $output );
		$this->assertStringContainsString( 'umc-ui-settings-card__divider', $output );
	}

	public function test_detection_panel_preserves_rules_hooks(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_DETECTION;

		$output = $this->render_geo_field();

		$this->assertStringContainsString( 'data-umc-geo-rules', $output );
		$this->assertStringContainsString( 'data-umc-geo-add="country"', $output );
		$this->assertStringContainsString( 'id="umc-geo-rule-template"', $output );
	}

	public function test_saveable_panel_renders_sticky_save_and_not_header_save(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_SETTINGS;

		$sections = $this->render_shell_sections();
		$output   = $this->render_geo_field();

		$this->assertStringNotContainsString( 'umc-shell-header__save', $sections );
		$this->assertStringContainsString( 'data-umc-sticky-save', $output );
		$this->assertStringNotContainsString( 'umc-section-card__submit', $this->render_shell_output() );
	}

	public function test_overview_panel_does_not_render_sticky_save(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_OVERVIEW;

		$output = $this->render_geo_field();

		$this->assertStringNotContainsString( 'data-umc-sticky-save', $output );
		$this->assertStringContainsString( 'umc-ui-statistics-grid', $output );
		$this->assertStringContainsString( 'umc-ui-quick-actions', $output );
	}

	public function test_sandbox_panel_does_not_nest_form_inside_mainform(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_SANDBOX;

		$output = $this->render_shell_output();

		$this->assertStringNotContainsString( '<form method="post"', $output );
		$this->assertStringContainsString( 'form="umc-geo-sandbox-form"', $output );
	}

	public function test_stub_panel_renders_empty_state(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_PROXIES;

		$output = $this->render_geo_field();

		$this->assertStringContainsString( 'umc-ui-empty-state', $output );
	}

	public function test_checkout_section_uses_sticky_save_and_preserves_field_names(): void {
		global $current_section;

		$current_section = SettingsPage::SECTION_CHECKOUT;
		$output          = $this->render_shell_output();
		$sections        = $this->render_shell_sections();

		$this->assertStringContainsString( 'name="umc_checkout[mode]"', $output );
		$this->assertStringContainsString( 'name="umc_checkout[show_notice]"', $output );
		$this->assertStringContainsString( 'type="hidden" name="umc_checkout[show_notice]" value="0"', $output );
		$this->assertStringContainsString( 'data-umc-sticky-save', $output );
		$this->assertStringNotContainsString( 'umc-section-card__submit', $output );
		$this->assertStringNotContainsString( 'umc-shell-header__save', $sections );
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

	private function render_geo_field(): string {
		ob_start();
		$this->page()->output();

		return (string) ob_get_clean();
	}

	private function page(): SettingsPage {
		if ( null === $this->page ) {
			$settings = new Settings();

			$this->page = new SettingsPage(
				$settings,
				new Currency( 'EUR', 2 ),
				new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
			);
		}

		return $this->page;
	}
}
