<?php
/**
 * Unit tests for the admin component renderer.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use UMC\Admin\AdminComponentRenderer;

/**
 * Verifies design-system component markup contracts.
 */
final class AdminComponentRendererTest extends TestCase {

	/**
	 * Component renderer under test.
	 *
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $ui;

	protected function setUp(): void {
		parent::setUp();
		$this->ui = new AdminComponentRenderer();
	}

	public function test_feature_section_renders_landmark_structure(): void {
		$html = $this->ui->feature_section_open( 'Currency selection', 'Section description' )
			. '<div>card</div>'
			. $this->ui->feature_section_close();

		$this->assertStringContainsString( 'umc-ui-feature-section', $html );
		$this->assertStringContainsString( 'umc-ui-feature-section__title', $html );
		$this->assertStringContainsString( 'umc-ui-feature-section__content', $html );
		$this->assertStringContainsString( 'Currency selection', $html );
		$this->assertStringContainsString( 'Section description', $html );
	}

	public function test_settings_card_anatomy_includes_title_divider_and_body(): void {
		$html = $this->ui->settings_card_open( 'Card title', 'Card description' ) . 'controls' . $this->ui->settings_card_close();

		$this->assertStringContainsString( 'umc-ui-settings-card__title', $html );
		$this->assertStringContainsString( 'Card title', $html );
		$this->assertStringContainsString( 'umc-ui-settings-card__description', $html );
		$this->assertStringContainsString( 'umc-ui-settings-card__divider', $html );
		$this->assertStringContainsString( 'umc-ui-settings-card__body', $html );
		$this->assertStringContainsString( 'controls', $html );
	}

	public function test_toggle_row_renders_hidden_zero_value(): void {
		$html = $this->ui->toggle_row( 'umc_geo[enabled]', true, 'Enable feature' );

		$this->assertStringContainsString( 'type="hidden" name="umc_geo[enabled]" value="0"', $html );
		$this->assertStringContainsString( 'type="checkbox" name="umc_geo[enabled]" value="1"', $html );
		$this->assertStringContainsString( 'checked="checked"', $html );
	}

	public function test_select_row_preserves_select_name_and_selected_value(): void {
		$select = '<select name="umc_geo[fallback_currency]" id="umc_geo_fallback_currency"><option value="EUR" selected="selected">EUR</option></select>';
		$html   = $this->ui->select_row( 'umc_geo[fallback_currency]', 'Fallback currency', 'Description', $select );

		$this->assertStringContainsString( 'name="umc_geo[fallback_currency]"', $html );
		$this->assertStringContainsString( 'selected="selected"', $html );
	}

	public function test_status_badge_variants_render_accessible_label(): void {
		foreach ( AdminComponentRenderer::BADGE_VARIANTS as $variant ) {
			$html = $this->ui->status_badge( 'Status label', $variant );
			$this->assertStringContainsString( 'umc-ui-status-badge--' . $variant, $html );
			$this->assertStringContainsString( 'Status label', $html );
			$this->assertStringContainsString( 'umc-ui-status-badge__label', $html );
		}
	}

	public function test_empty_state_renders_icon_title_and_message(): void {
		$html = $this->ui->empty_state( 'dashicons-admin-site', 'Title', 'Message body' );

		$this->assertStringContainsString( 'umc-ui-empty-state', $html );
		$this->assertStringContainsString( 'dashicons-admin-site', $html );
		$this->assertStringContainsString( 'Title', $html );
		$this->assertStringContainsString( 'Message body', $html );
	}

	public function test_quick_actions_panel_renders_links(): void {
		$html = $this->ui->quick_actions_panel(
			'Quick actions',
			array(
				array(
					'label' => 'Open sandbox',
					'url'   => 'https://example.test/sandbox',
				),
			)
		);

		$this->assertStringContainsString( 'umc-ui-quick-action', $html );
		$this->assertStringContainsString( 'Open sandbox', $html );
		$this->assertStringContainsString( 'https://example.test/sandbox', $html );
	}

	public function test_sticky_save_bar_includes_dirty_saved_and_discard_markers(): void {
		$html = $this->ui->sticky_save_bar( 'visitor-location' );

		$this->assertStringContainsString( 'data-umc-sticky-save', $html );
		$this->assertStringContainsString( 'data-umc-unsaved-indicator', $html );
		$this->assertStringContainsString( 'data-umc-sticky-discard', $html );
		$this->assertStringContainsString( 'data-umc-sticky-saved', $html );
		$this->assertStringContainsString( 'name="save"', $html );
	}

	public function test_pill_navigation_renders_active_aria_current(): void {
		$html = $this->ui->pill_navigation(
			'Panels',
			array(
				array(
					'label'  => 'Overview',
					'url'    => 'https://example.test/overview',
					'icon'   => 'dashicons-dashboard',
					'active' => true,
				),
			)
		);

		$this->assertStringContainsString( 'umc-ui-pill-nav__item--active', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( 'dashicons-dashboard', $html );
	}

	public function test_loading_skeleton_renders_lines(): void {
		$html = $this->ui->loading_skeleton( 2 );

		$this->assertStringContainsString( 'umc-ui-skeleton', $html );
		$this->assertSame( 2, substr_count( $html, 'umc-ui-skeleton__line' ) );
	}

	public function test_choice_card_escapes_title(): void {
		$html = $this->ui->choice_card( 'mode', 'first', false, '<script>alert(1)</script>', 'Safe description' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}
}
