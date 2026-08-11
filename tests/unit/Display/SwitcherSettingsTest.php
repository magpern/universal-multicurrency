<?php
/**
 * Unit tests for Display switcher settings.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherSettings;
use UMC\Settings;

/**
 * Exercises normalization, validation, and modifier output for Display settings.
 */
final class SwitcherSettingsTest extends TestCase {

	public function test_default_array_matches_disabled_manual_dropdown(): void {
		$defaults = SwitcherSettings::default_array();

		$this->assertFalse( $defaults['enabled'] );
		$this->assertSame( SwitcherSettings::PLACEMENT_MANUAL, $defaults['placement'] );
		$this->assertSame( SwitcherSettings::STYLE_DROPDOWN, $defaults['style'] );
		$this->assertTrue( $defaults['content']['menu']['show_code'] );
		$this->assertTrue( $defaults['content']['menu']['show_symbol'] );
		$this->assertFalse( $defaults['content']['menu']['show_name'] );
		$this->assertTrue( $defaults['content']['trigger']['show_code'] );
		$this->assertTrue( $defaults['content']['trigger']['show_symbol'] );
		$this->assertFalse( $defaults['content']['trigger']['show_name'] );
		$this->assertFalse( $defaults['content']['show_chevron'] );
	}

	public function test_default_array_matches_schema_six_top_level_keys(): void {
		$this->assertSame(
			array( 'enabled', 'placement', 'style', 'position', 'content', 'design', 'behavior', 'visibility', 'responsive', 'custom_css' ),
			array_keys( SwitcherSettings::default_array() )
		);
	}

	public function test_default_design_is_default_preset_with_subtle_motion(): void {
		$design = SwitcherSettings::default_array()['design'];

		$this->assertSame( SwitcherSettings::PRESET_DEFAULT, $design['preset'] );
		$this->assertSame( SwitcherSettings::THEME_AUTOMATIC, $design['theme'] );
		$this->assertSame( SwitcherSettings::SIZE_STANDARD, $design['size'] );
		$this->assertSame( SwitcherSettings::SHAPE_ROUNDED, $design['shape'] );
		$this->assertSame( array(), $design['overrides'] );
		$this->assertSame( SwitcherSettings::MOTION_SUBTLE, $design['motion'] );
	}

	public function test_default_responsive_bag_is_off_and_custom_css_is_empty(): void {
		$defaults = SwitcherSettings::default_array();

		$this->assertFalse( $defaults['responsive']['hide_name_on_mobile'] );
		$this->assertFalse( $defaults['responsive']['compact_on_mobile'] );
		$this->assertSame( '', $defaults['custom_css'] );
	}

	public function test_from_array_clamps_edge_offset(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'position' => array(
					'edge_offset' => 999,
				),
			)
		);

		$this->assertSame( 200, $settings->position()['edge_offset'] );
	}

	public function test_from_array_clamps_middle_vertical_offset_signed(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'position' => array(
					'vertical_alignment' => SwitcherSettings::ALIGN_MIDDLE,
					'vertical_offset'    => -500,
				),
			)
		);

		$this->assertSame( -300, $settings->position()['vertical_offset'] );
	}

	public function test_from_array_clamps_top_vertical_offset_positive_only(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'position' => array(
					'vertical_alignment' => SwitcherSettings::ALIGN_TOP,
					'vertical_offset'    => -10,
				),
			)
		);

		$this->assertSame( 0, $settings->position()['vertical_offset'] );
	}

	public function test_invalid_enums_fall_back_to_defaults(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'placement' => 'invalid',
				'style'     => 'invalid',
				'design'    => array(
					'preset' => 'brutalist',
					'theme'  => 'neon',
					'size'   => 'huge',
					'shape'  => 'square',
					'motion' => 'bouncy',
				),
			)
		);

		$this->assertSame( SwitcherSettings::PLACEMENT_MANUAL, $settings->placement() );
		$this->assertSame( SwitcherSettings::STYLE_DROPDOWN, $settings->style() );
		$this->assertSame( SwitcherSettings::PRESET_DEFAULT, $settings->preset() );
		$this->assertSame( SwitcherSettings::THEME_AUTOMATIC, $settings->design()['theme'] );
		$this->assertSame( SwitcherSettings::SIZE_STANDARD, $settings->design()['size'] );
		$this->assertSame( SwitcherSettings::SHAPE_ROUNDED, $settings->design()['shape'] );
		$this->assertSame( SwitcherSettings::MOTION_SUBTLE, $settings->motion() );
	}

	public function test_legacy_appearance_is_read_into_design(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'appearance' => array(
					'theme' => SwitcherSettings::THEME_DARK,
					'size'  => SwitcherSettings::SIZE_COMPACT,
					'shape' => SwitcherSettings::SHAPE_PILL,
				),
			)
		);

		$this->assertSame( SwitcherSettings::THEME_DARK, $settings->design()['theme'] );
		$this->assertSame( SwitcherSettings::SIZE_COMPACT, $settings->design()['size'] );
		$this->assertSame( SwitcherSettings::SHAPE_PILL, $settings->design()['shape'] );
		$this->assertSame( $settings->design()['theme'], $settings->appearance()['theme'] );
		$this->assertArrayNotHasKey( 'appearance', $settings->to_array() );
	}

	public function test_design_wins_over_legacy_appearance_alias(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'design'     => array( 'theme' => SwitcherSettings::THEME_LIGHT ),
				'appearance' => array( 'theme' => SwitcherSettings::THEME_DARK ),
			)
		);

		$this->assertSame( SwitcherSettings::THEME_LIGHT, $settings->design()['theme'] );
	}

	public function test_legacy_flat_content_splits_into_trigger_and_menu(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'content' => array(
					'show_code'   => true,
					'show_symbol' => false,
					'show_name'   => true,
				),
			)
		);

		$this->assertFalse( $settings->trigger_content()['show_name'] );
		$this->assertTrue( $settings->menu_content()['show_name'] );
		$this->assertSame( array( 'code' ), $settings->trigger_content()['order'] );
		$this->assertSame( array( 'code', 'name' ), $settings->menu_content()['order'] );
	}

	public function test_structured_content_is_read_per_context(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'content' => array(
					'trigger'      => array(
						'show_code'   => false,
						'show_symbol' => true,
						'show_name'   => false,
					),
					'menu'         => array(
						'show_code'   => true,
						'show_symbol' => true,
						'show_name'   => true,
						'order'       => array( 'name', 'code' ),
					),
					'show_chevron' => true,
				),
			)
		);

		$this->assertFalse( $settings->trigger_content()['show_code'] );
		$this->assertSame( array( 'symbol' ), $settings->trigger_content()['order'] );
		$this->assertSame( array( 'name', 'code', 'symbol' ), $settings->menu_content()['order'] );
		$this->assertTrue( $settings->show_chevron() );
	}

	public function test_order_drops_unknown_and_hidden_elements(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'content' => array(
					'menu' => array(
						'show_code'   => true,
						'show_symbol' => false,
						'show_name'   => false,
						'order'       => array( 'flag', 'symbol', 'code', 'code' ),
					),
				),
			)
		);

		$this->assertSame( array( 'code' ), $settings->menu_content()['order'] );
	}

	public function test_content_requires_at_least_one_label_component(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'content' => array(
					'show_code'   => false,
					'show_symbol' => false,
					'show_name'   => false,
				),
			)
		);

		$this->assertTrue( $settings->menu_content()['show_code'] );
		$this->assertTrue( $settings->trigger_content()['show_code'] );
	}

	public function test_overrides_keep_allowlisted_values_only(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'design' => array(
					'overrides' => array(
						'surface'        => '#FFEEDD',
						'text'           => 'rgb(10, 20, 30)',
						'border'         => 'red',
						'radius'         => '12',
						'control_height' => 900,
						'font_weight'    => 600,
						'z_index'        => 5,
					),
				),
			)
		);

		$this->assertSame(
			array(
				'surface'     => '#ffeedd',
				'text'        => 'rgb(10, 20, 30)',
				'radius'      => 12,
				'font_weight' => 600,
			),
			$settings->overrides()
		);
	}

	public function test_css_variables_expose_overrides_and_motion(): void {
		$variables = SwitcherSettings::from_array(
			array(
				'design' => array(
					'motion'    => SwitcherSettings::MOTION_NONE,
					'overrides' => array(
						'surface' => '#101010',
						'radius'  => 4,
					),
				),
			)
		)->css_variables();

		$this->assertSame( '#101010', $variables['--umc-switcher-surface'] );
		$this->assertSame( '4px', $variables['--umc-switcher-radius'] );
		$this->assertSame( '0ms', $variables['--umc-switcher-transition-duration'] );
		$this->assertSame( '9990', $variables['--umc-switcher-z-index'] );
	}

	public function test_responsive_bag_is_normalized(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'responsive' => array(
					'hide_name_on_mobile' => '1',
					'compact_on_mobile'   => 'no',
				),
			)
		);

		$this->assertTrue( $settings->responsive()['hide_name_on_mobile'] );
		$this->assertFalse( $settings->responsive()['compact_on_mobile'] );
	}

	public function test_custom_css_is_validated_on_normalization(): void {
		$accepted = SwitcherSettings::from_array( array( 'custom_css' => '.umc-switcher { color: #111111; }' ) );
		$rejected = SwitcherSettings::from_array( array( 'custom_css' => '@import "evil.css";' ) );

		$this->assertSame( '.umc-switcher { color: #111111; }', $accepted->custom_css() );
		$this->assertSame( '', $rejected->custom_css() );
	}

	public function test_normalization_is_idempotent(): void {
		$normalized = SwitcherSettings::sanitize_raw(
			array(
				'enabled'    => true,
				'appearance' => array( 'shape' => SwitcherSettings::SHAPE_PILL ),
				'content'    => array(
					'show_code'   => true,
					'show_symbol' => true,
					'show_name'   => true,
				),
			)
		);

		$this->assertSame( $normalized, SwitcherSettings::sanitize_raw( $normalized ) );
	}

	public function test_horizontal_list_coerced_for_automatic_placement(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
				'style'     => SwitcherSettings::STYLE_HORIZONTAL_LIST,
			)
		);

		$this->assertSame( SwitcherSettings::STYLE_DROPDOWN, $settings->style() );
		$this->assertTrue( $settings->was_style_coerced() );
	}

	public function test_visibility_valid_for_save_when_disabled(): void {
		$this->assertTrue(
			SwitcherSettings::visibility_valid_for_save(
				array(
					'enabled'    => false,
					'visibility' => array(
						'desktop' => false,
						'mobile'  => false,
					),
				)
			)
		);
	}

	public function test_visibility_invalid_for_save_when_enabled_and_both_off(): void {
		$this->assertFalse(
			SwitcherSettings::visibility_valid_for_save(
				array(
					'enabled'    => true,
					'visibility' => array(
						'desktop' => false,
						'mobile'  => false,
					),
				)
			)
		);

		$this->assertFalse(
			SwitcherSettings::visibility_valid_for_save(
				array(
					'enabled'    => '1',
					'visibility' => array(
						'desktop' => '0',
						'mobile'  => '0',
					),
				)
			)
		);
	}

	public function test_should_render_automatic_only_for_fixed_placements(): void {
		$manual   = SwitcherSettings::from_array(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_MANUAL,
			)
		);
		$floating = SwitcherSettings::from_array(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);

		$this->assertFalse( $manual->should_render_automatic() );
		$this->assertTrue( $floating->should_render_automatic() );
	}

	public function test_modifier_classes_include_visibility_and_preview(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled'    => true,
				'placement'  => SwitcherSettings::PLACEMENT_STICKY_FOOTER,
				'style'      => SwitcherSettings::STYLE_DROPDOWN,
				'visibility' => array(
					'desktop' => false,
					'mobile'  => true,
				),
			)
		);

		$classes = $settings->modifier_classes( true );

		$this->assertContains( 'umc-switcher--floating-bottom', $classes );
		$this->assertContains( 'umc-switcher--hide-desktop', $classes );
		$this->assertContains( 'umc-switcher--preview', $classes );
	}

	public function test_modifier_classes_include_the_selected_preset(): void {
		$classes = SwitcherSettings::from_array(
			array( 'design' => array( 'preset' => SwitcherSettings::PRESET_MINIMAL ) )
		)->modifier_classes();

		$this->assertContains( 'umc-switcher--preset-minimal', $classes );
		$this->assertContains( 'umc-switcher--theme-automatic', $classes );
	}

	public function test_modifier_classes_include_responsive_adjustments(): void {
		$classes = SwitcherSettings::from_array(
			array(
				'responsive' => array(
					'hide_name_on_mobile' => true,
					'compact_on_mobile'   => true,
				),
			)
		)->modifier_classes();

		$this->assertContains( 'umc-switcher--hide-name-on-mobile', $classes );
		$this->assertContains( 'umc-switcher--compact-on-mobile', $classes );
	}

	public function test_modifier_classes_omit_responsive_adjustments_when_disabled(): void {
		$classes = SwitcherSettings::from_array( array() )->modifier_classes();

		$this->assertNotContains( 'umc-switcher--hide-name-on-mobile', $classes );
		$this->assertNotContains( 'umc-switcher--compact-on-mobile', $classes );
	}

	public function test_manual_placement_modifier_class_is_not_duplicated(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_MANUAL,
				'style'     => SwitcherSettings::STYLE_DROPDOWN,
			)
		);

		$classes = $settings->modifier_classes();

		$this->assertSame( 1, count( array_filter( $classes, static fn( string $modifier ): bool => 'umc-switcher--manual' === $modifier ) ) );
	}

	public function test_settings_sanitize_includes_display_defaults(): void {
		$clean = Settings::sanitize( array( 'currencies' => array() ) );

		$this->assertSame( Settings::SCHEMA_VERSION, $clean['schema_version'] );
		$this->assertSame( SwitcherSettings::default_array(), $clean['display'] );
	}
}
