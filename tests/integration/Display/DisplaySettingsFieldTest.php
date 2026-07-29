<?php
/**
 * Integration tests for the Display settings field.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Display;

use UMC\Admin\AdminAssets;
use UMC\Admin\DisplaySettingsField;
use UMC\Admin\SettingsPage;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\CurrencyContext;
use UMC\CurrencyResolver;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\ManualRateProvider;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use UMC\Tests\Support\RedirectCapturedException;
use WP_UnitTestCase;

/**
 * Verifies Display admin rendering, parsing, and save routing.
 */
final class DisplaySettingsFieldTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );

		add_filter(
			'wp_redirect',
			static function ( $location ): string {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test control flow.
				throw new RedirectCapturedException( (string) $location );
			}
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_redirect' );
		unset( $_POST['umc_display'], $_GET['section'], $_REQUEST['section'] );
		unset( $GLOBALS['current_section'] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_display_section_exposes_display_field_not_placeholder(): void {
		$page = $this->settings_page();

		$types = array_column( $page->get_settings_for_section( SettingsPage::SECTION_DISPLAY ), 'type' );

		$this->assertContains( 'umc_display', $types );
		$this->assertNotContains( 'umc_placeholder', $types );
		$this->assertTrue( $page->section_has_saveable_settings( SettingsPage::SECTION_DISPLAY ) );
	}

	public function test_render_outputs_preview_shell_and_switcher_markup(): void {
		$this->save_display(
			array(
				'enabled' => true,
			)
		);

		$html = $this->capture_render();

		$this->assertStringContainsString( 'umc-display-layout', $html );
		$this->assertStringContainsString( 'umc-display-preview-frame', $html );
		$this->assertStringContainsString( 'umc-switcher', $html );
		$this->assertStringContainsString( 'umc-display-card--position umc-display-card--hidden', $html );
	}

	public function test_parse_post_sanitizes_display_settings(): void {
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'placement'  => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			'style'      => SwitcherSettings::STYLE_DROPDOWN,
			'position'   => array(
				'edge_offset' => '999',
			),
			'visibility' => array(
				'desktop' => '1',
				'mobile'  => '1',
			),
		);

		$parsed = $this->display_field()->parse_post();

		$this->assertIsArray( $parsed );
		$this->assertSame( 200, $parsed['display']['position']['edge_offset'] );
		$this->assertTrue( $parsed['display']['enabled'] );
	}

	public function test_parse_post_returns_null_when_visibility_invalid(): void {
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'visibility' => array(
				'desktop' => '0',
				'mobile'  => '0',
			),
		);

		$this->assertNull( $this->display_field()->parse_post() );
	}

	public function test_parse_post_reports_style_coercion(): void {
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'placement'  => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			'style'      => SwitcherSettings::STYLE_HORIZONTAL_LIST,
			'visibility' => array(
				'desktop' => '1',
				'mobile'  => '1',
			),
		);

		$parsed = $this->display_field()->parse_post();

		$this->assertIsArray( $parsed );
		$this->assertTrue( $parsed['show_coercion_notice'] );
		$this->assertSame( SwitcherSettings::STYLE_DROPDOWN, $parsed['display']['style'] );
	}

	public function test_save_rejects_invalid_visibility_without_persisting_settings(): void {
		global $current_section;

		$this->save_display(
			array(
				'enabled'    => true,
				'visibility' => array(
					'desktop' => true,
					'mobile'  => true,
				),
			)
		);

		$current_section      = SettingsPage::SECTION_DISPLAY;
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'visibility' => array(
				'desktop' => '0',
				'mobile'  => '0',
			),
		);

		$this->settings_page()->save();

		$stored = ( new Settings() )->get()['display']['visibility'];
		$this->assertTrue( $stored['desktop'] );
		$this->assertTrue( $stored['mobile'] );
	}

	public function test_save_persists_display_settings_from_request_section(): void {
		$this->save_display(
			array(
				'enabled'    => true,
				'visibility' => array(
					'desktop' => true,
					'mobile'  => true,
				),
				'content'    => array(
					'show_name' => false,
				),
			)
		);

		unset( $GLOBALS['current_section'] );

		$_GET['section']      = SettingsPage::SECTION_DISPLAY;
		$_REQUEST['section']  = SettingsPage::SECTION_DISPLAY;
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'placement'  => SwitcherSettings::PLACEMENT_MANUAL,
			'style'      => SwitcherSettings::STYLE_DROPDOWN,
			'content'    => array(
				'show_code'   => '1',
				'show_symbol' => '1',
				'show_name'   => '1',
			),
			'visibility' => array(
				'desktop' => '1',
				'mobile'  => '1',
			),
		);

		$this->settings_page()->save();

		$content = ( new Settings() )->get()['display']['content'];
		$this->assertTrue( $content['show_name'] );
	}

	public function test_admin_assets_enqueue_switcher_styles_only_on_display_section(): void {
		$_GET['page']    = 'wc-settings';
		$_GET['tab']     = 'umc';
		$_GET['section'] = SettingsPage::SECTION_DISPLAY;

		( new AdminAssets() )->enqueue( 'woocommerce_page_wc-settings' );

		$this->assertTrue( wp_style_is( 'umc-switcher', 'enqueued' ) );
	}

	private function settings_page(): SettingsPage {
		$settings = new Settings();

		return new SettingsPage(
			$settings,
			new Currency( 'EUR', 2 ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
		);
	}

	private function display_field(): DisplaySettingsField {
		$settings = new Settings();
		$base     = new Currency( 'EUR', 2 );
		$registry = new CurrencyRegistry( $settings, $base );
		$context  = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );
		$repo     = new SwitcherSettingsRepository( $settings );

		return new DisplaySettingsField(
			$settings,
			new SwitcherViewModelFactory( $context, new WooCommerceCurrencyProvider(), $repo ),
			new SwitcherRenderer(),
			$repo
		);
	}

	/**
	 * @param array<string, mixed> $display Display settings override.
	 */
	private function save_display( array $display ): void {
		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'display' => array_merge( SwitcherSettings::default_array(), $display ),
				)
			)
		);
	}

	private function capture_render(): string {
		ob_start();
		$this->display_field()->render();

		return (string) ob_get_clean();
	}
}
