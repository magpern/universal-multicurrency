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
		$this->assertTrue( $defaults['content']['show_code'] );
		$this->assertTrue( $defaults['content']['show_symbol'] );
		$this->assertFalse( $defaults['content']['show_name'] );
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
				'placement'  => 'invalid',
				'style'      => 'invalid',
				'appearance' => array(
					'theme' => 'neon',
					'size'  => 'huge',
					'shape' => 'square',
				),
			)
		);

		$this->assertSame( SwitcherSettings::PLACEMENT_MANUAL, $settings->placement() );
		$this->assertSame( SwitcherSettings::STYLE_DROPDOWN, $settings->style() );
		$this->assertSame( SwitcherSettings::THEME_AUTOMATIC, $settings->appearance()['theme'] );
		$this->assertSame( SwitcherSettings::SIZE_STANDARD, $settings->appearance()['size'] );
		$this->assertSame( SwitcherSettings::SHAPE_ROUNDED, $settings->appearance()['shape'] );
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

		$this->assertTrue( $settings->content()['show_code'] );
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
