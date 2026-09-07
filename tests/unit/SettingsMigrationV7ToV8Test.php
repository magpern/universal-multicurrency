<?php
/**
 * Unit tests for schema 7 → 8 Display presentation migration.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherViewModel;
use UMC\Display\SwitcherOptionViewModel;
use UMC\Settings;
use UMC\SettingsUpgrader;

/**
 * Proves migrate_7_to_8 is visually neutral and maps presentations correctly.
 */
final class SettingsMigrationV7ToV8Test extends TestCase {

	public function test_migrate_7_to_8_constant_points_to_callable(): void {
		$this->assertSame( SettingsUpgrader::MIGRATE_7_TO_8, SettingsUpgrader::production_migrations()[8] );
	}

	/**
	 * @dataProvider presentation_mapping_provider
	 *
	 * @param string $placement     Schema-7 placement.
	 * @param string $preset        Schema-7 design.preset.
	 * @param string $presentation  Expected schema-8 presentation.
	 * @param string $mobile        Expected mobile_behavior.
	 */
	public function test_migrate_7_to_8_maps_presentation(
		string $placement,
		string $preset,
		string $presentation,
		string $mobile
	): void {
		$fixture = $this->v7_fixture();
		$fixture['display']['placement']     = $placement;
		$fixture['display']['design']['preset'] = $preset;

		$display = SettingsUpgrader::migrate_7_to_8( $fixture )['display'];

		$this->assertSame( $presentation, $display['design']['presentation'] );
		$this->assertSame( $preset, $display['design']['preset'] );
		$this->assertSame( $mobile, $display['responsive']['mobile_behavior'] );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
	 */
	public function presentation_mapping_provider(): array {
		return array(
			'manual default'           => array(
				SwitcherSettings::PLACEMENT_MANUAL,
				SwitcherSettings::PRESET_DEFAULT,
				SwitcherSettings::PRESENTATION_CLASSIC_DROPDOWN,
				SwitcherSettings::MOBILE_BEHAVIOR_RETAIN,
			),
			'floating default'         => array(
				SwitcherSettings::PLACEMENT_FLOATING_SIDE,
				SwitcherSettings::PRESET_DEFAULT,
				SwitcherSettings::PRESENTATION_CLASSIC_DROPDOWN,
				SwitcherSettings::MOBILE_BEHAVIOR_RETAIN,
			),
			'floating floating preset' => array(
				SwitcherSettings::PLACEMENT_FLOATING_SIDE,
				SwitcherSettings::PRESET_FLOATING,
				SwitcherSettings::PRESENTATION_FLOATING_CARD,
				SwitcherSettings::MOBILE_BEHAVIOR_RETAIN,
			),
			'floating minimal preset'  => array(
				SwitcherSettings::PLACEMENT_FLOATING_SIDE,
				SwitcherSettings::PRESET_MINIMAL,
				SwitcherSettings::PRESENTATION_MINIMAL_ICON,
				SwitcherSettings::MOBILE_BEHAVIOR_BOTTOM_SHEET,
			),
			'sticky footer'            => array(
				SwitcherSettings::PLACEMENT_STICKY_FOOTER,
				SwitcherSettings::PRESET_PILL,
				SwitcherSettings::PRESENTATION_STICKY_FOOTER,
				SwitcherSettings::MOBILE_BEHAVIOR_RETAIN,
			),
		);
	}

	public function test_migrate_7_to_8_normalizes_motion_aliases(): void {
		$subtle = $this->v7_fixture();
		$subtle['display']['design']['motion'] = SwitcherSettings::MOTION_SUBTLE;

		$none = $this->v7_fixture();
		$none['display']['design']['motion'] = SwitcherSettings::MOTION_NONE;

		$this->assertSame(
			SwitcherSettings::MOTION_STANDARD,
			SettingsUpgrader::migrate_7_to_8( $subtle )['display']['design']['motion']
		);
		$this->assertSame(
			SwitcherSettings::MOTION_OFF,
			SettingsUpgrader::migrate_7_to_8( $none )['display']['design']['motion']
		);
	}

	public function test_migrate_7_to_8_preserves_custom_floating_fixture_fields(): void {
		$fixture = $this->canonical_floating_fixture();
		$display = SettingsUpgrader::migrate_7_to_8( $fixture )['display'];

		$this->assertSame( SwitcherSettings::PRESET_BORDERLESS, $display['design']['preset'] );
		$this->assertSame( 24, $display['position']['edge_offset'] );
		$this->assertSame( 40, $display['position']['vertical_offset'] );
		$this->assertSame(
			array( 'symbol', 'code', 'name' ),
			$display['content']['trigger']['order']
		);
		$this->assertSame(
			array( 'code', 'name', 'symbol' ),
			$display['content']['menu']['order']
		);
		$this->assertSame( '.umc-switcher { color: #111; }', $display['custom_css'] );
		$this->assertSame( SwitcherSettings::PRESENTATION_CLASSIC_DROPDOWN, $display['design']['presentation'] );
		$this->assertNotSame( SwitcherSettings::PRESENTATION_EDGE_PILL, $display['design']['presentation'] );
	}

	public function test_canonical_fixture_render_equivalence_apart_from_additive_chrome(): void {
		$fixture_v7 = $this->canonical_floating_fixture()['display'];
		$v7_settings = SwitcherSettings::from_array( $fixture_v7 );

		$upgraded    = SettingsUpgrader::migrate_7_to_8( $this->canonical_floating_fixture() )['display'];
		$v8_settings = SwitcherSettings::from_array( $upgraded );

		$v7_html = ( new SwitcherRenderer() )->render( $this->view_model_for( $v7_settings ) );
		$v8_html = ( new SwitcherRenderer() )->render( $this->view_model_for( $v8_settings ) );

		// Schema-8 adds presentation modifiers; strip additive attrs/classes for comparison baseline.
		$normalize = static function ( string $html ): string {
			$html = preg_replace( '/\s*data-umc-presentation="[^"]*"/', '', $html ) ?? $html;
			$html = preg_replace( '/\s*data-umc-mobile-behavior="[^"]*"/', '', $html ) ?? $html;
			$html = preg_replace( '/\s*umc-switcher--presentation-[a-z0-9-]+/', '', $html ) ?? $html;
			$html = preg_replace( '/\s*umc-switcher--mobile-[a-z0-9-]+/', '', $html ) ?? $html;
			$html = preg_replace( '/\s*aria-label="[^"]*"/', '', $html ) ?? $html;
			$html = preg_replace( '/<div class="umc-switcher__backdrop"[^>]*><\/div>/', '', $html ) ?? $html;
			$html = preg_replace( '/<div class="umc-switcher__panel"[^>]*>/', '', $html ) ?? $html;
			$html = preg_replace( '/<\/div>(?=\s*<\/div>\s*$)/', '', $html ) ?? $html;
			$html = preg_replace( '/<h2 class="umc-switcher__sheet-title"[^>]*>.*?<\/h2>/', '', $html ) ?? $html;
			$html = preg_replace( '/<button type="button" class="umc-switcher__close"[^>]*>.*?<\/button>/', '', $html ) ?? $html;
			$html = preg_replace( '/<span class="umc-switcher__sheet-divider"[^>]*><\/span>/', '', $html ) ?? $html;
			return preg_replace( '/\s+/', ' ', $html ) ?? $html;
		};

		$this->assertStringContainsString( 'umc-switcher--preset-borderless', $v8_html );
		$this->assertSame( $normalize( $v7_html ), $normalize( $v8_html ) );

		$v7_vars = $v7_settings->css_variables();
		$v8_vars = $v8_settings->css_variables();
		$this->assertSame( $v7_vars['--umc-switcher-edge-offset'], $v8_vars['--umc-switcher-edge-offset'] );
		$this->assertSame( $v7_vars['--umc-switcher-vertical-offset'], $v8_vars['--umc-switcher-vertical-offset'] );
		$this->assertSame( $v7_vars['--umc-switcher-z-index'], $v8_vars['--umc-switcher-z-index'] );
	}

	public function test_migrate_7_to_8_is_idempotent(): void {
		$once  = SettingsUpgrader::migrate_7_to_8( $this->canonical_floating_fixture() );
		$twice = SettingsUpgrader::migrate_7_to_8( $once );

		$this->assertSame( $once, $twice );
	}

	public function test_upgrade_from_schema_seven_reaches_schema_eight(): void {
		$result = ( new SettingsUpgrader() )->upgrade( $this->v7_fixture() );

		$this->assertFalse( $result->is_failed() );
		$this->assertSame( 8, $result->settings()['schema_version'] );
	}

	public function test_sticky_compact_does_not_change_placement(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled'    => true,
				'placement'  => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
				'design'     => array(
					'presentation' => SwitcherSettings::PRESENTATION_EDGE_PILL,
				),
				'responsive' => array(
					'mobile_behavior' => SwitcherSettings::MOBILE_BEHAVIOR_STICKY_COMPACT,
				),
			)
		);

		$this->assertSame( SwitcherSettings::PLACEMENT_FLOATING_SIDE, $settings->placement() );
		$this->assertSame( SwitcherSettings::MOBILE_BEHAVIOR_STICKY_COMPACT, $settings->mobile_behavior() );
		$this->assertTrue( $settings->should_render_automatic() );
		$this->assertNotSame( SwitcherSettings::PLACEMENT_STICKY_FOOTER, $settings->placement() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function v7_fixture(): array {
		return array(
			'schema_version'       => 7,
			'rate_mode'            => Settings::RATE_MODE_MANUAL,
			'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => array(),
			'display'              => SwitcherSettings::default_array(),
			'checkout'             => array(),
			'geo'                  => array(),
		);
	}

	/**
	 * Real schema-7 floating configuration with custom preset, offsets, content, CSS.
	 *
	 * @return array<string, mixed>
	 */
	private function canonical_floating_fixture(): array {
		$fixture = $this->v7_fixture();

		$fixture['display'] = array(
			'enabled'    => true,
			'placement'  => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			'style'      => SwitcherSettings::STYLE_DROPDOWN,
			'position'   => array(
				'side'               => SwitcherSettings::SIDE_LEFT,
				'vertical_alignment' => SwitcherSettings::ALIGN_TOP,
				'vertical_offset'    => 40,
				'edge_offset'        => 24,
				'bottom_offset'      => 16,
			),
			'content'    => array(
				'trigger'      => array(
					'show_code'   => true,
					'show_symbol' => true,
					'show_name'   => true,
					'show_icon'   => false,
					'order'       => array( 'symbol', 'code', 'name' ),
				),
				'menu'         => array(
					'show_code'   => true,
					'show_symbol' => true,
					'show_name'   => true,
					'show_icon'   => false,
					'order'       => array( 'code', 'name', 'symbol' ),
				),
				'show_chevron' => true,
			),
			'design'     => array(
				'preset'    => SwitcherSettings::PRESET_BORDERLESS,
				'theme'     => SwitcherSettings::THEME_LIGHT,
				'size'      => SwitcherSettings::SIZE_LARGE,
				'shape'     => SwitcherSettings::SHAPE_ROUNDED,
				'overrides' => array( 'radius' => 12 ),
				'motion'    => SwitcherSettings::MOTION_SUBTLE,
			),
			'behavior'   => array(
				'remember_selection' => true,
				'active_first'       => true,
			),
			'visibility' => array(
				'desktop' => true,
				'mobile'  => true,
			),
			'responsive' => array(
				'hide_name_on_mobile' => true,
				'compact_on_mobile'   => false,
			),
			'custom_css' => '.umc-switcher { color: #111; }',
			'presentation' => array(
				'icon_overrides' => array(),
				'icon_size'      => SwitcherSettings::SIZE_STANDARD,
				'icon_shape'     => SwitcherSettings::ICON_SHAPE_NATURAL,
			),
		);

		return $fixture;
	}

	/**
	 * @param SwitcherSettings $settings Settings under test.
	 */
	private function view_model_for( SwitcherSettings $settings ): SwitcherViewModel {
		$active = new SwitcherOptionViewModel(
			'SEK',
			'SEK kr',
			'SEK kr',
			'#',
			true,
			'<span class="umc-switcher__code">SEK</span><span class="umc-switcher__symbol">kr</span>',
			'<span class="umc-switcher__code">SEK</span><span class="umc-switcher__symbol">kr</span>'
		);

		return new SwitcherViewModel(
			'1',
			$settings,
			array( $active ),
			$active,
			false
		);
	}
}
