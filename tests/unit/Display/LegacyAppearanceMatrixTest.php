<?php
/**
 * Characterization tests for the legacy theme × size × shape appearance matrix.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherSettings;

/**
 * Pins v0.15 switcher appearance across all 27 theme × size × shape combinations.
 *
 * Milestone 17 restructures Display settings (schema 5 → 6) and layers a preset
 * class under theme/size/shape. Merchant appearance must not drift because of
 * that restructuring, so this test documents both halves of the contract:
 *
 * 1. the modifier classes each combination emits, and
 * 2. the effective control height and border radius produced by the stylesheet
 *    cascade, where `shape` wins the radius over `size` for slight and pill.
 */
final class LegacyAppearanceMatrixTest extends TestCase {

	/**
	 * Design tokens whose effective value is characterized here.
	 *
	 * @var array<int, string>
	 */
	private const TOKENS = array( 'radius', 'control-height' );

	/**
	 * Effective control height per size, independent of theme and shape.
	 *
	 * @var array<string, string>
	 */
	private const EXPECTED_HEIGHT = array(
		SwitcherSettings::SIZE_COMPACT  => '32px',
		SwitcherSettings::SIZE_STANDARD => '40px',
		SwitcherSettings::SIZE_LARGE    => '48px',
	);

	/**
	 * Effective radius when `shape` leaves the size default in place.
	 *
	 * @var array<string, string>
	 */
	private const EXPECTED_SIZE_RADIUS = array(
		SwitcherSettings::SIZE_COMPACT  => '6px',
		SwitcherSettings::SIZE_STANDARD => '8px',
		SwitcherSettings::SIZE_LARGE    => '10px',
	);

	/**
	 * Effective radius forced by shape, overriding the size default.
	 *
	 * @var array<string, string>
	 */
	private const EXPECTED_SHAPE_RADIUS = array(
		SwitcherSettings::SHAPE_SLIGHT => '6px',
		SwitcherSettings::SHAPE_PILL   => '999px',
	);

	/**
	 * All 27 theme × size × shape combinations.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function appearance_matrix_provider(): array {
		$cases = array();

		foreach ( SwitcherSettings::THEMES as $theme ) {
			foreach ( SwitcherSettings::SIZES as $size ) {
				foreach ( SwitcherSettings::SHAPES as $shape ) {
					$cases[ $theme . '/' . $size . '/' . $shape ] = array( $theme, $size, $shape );
				}
			}
		}

		return $cases;
	}

	public function test_matrix_covers_twenty_seven_combinations(): void {
		$this->assertCount( 27, self::appearance_matrix_provider() );
	}

	/**
	 * @dataProvider appearance_matrix_provider
	 *
	 * @param string $theme Theme token.
	 * @param string $size  Size token.
	 * @param string $shape Shape token.
	 */
	public function test_legacy_appearance_maps_to_independent_design_enums( string $theme, string $size, string $shape ): void {
		$settings = SwitcherSettings::from_array(
			array(
				'appearance' => array(
					'theme' => $theme,
					'size'  => $size,
					'shape' => $shape,
				),
			)
		);

		$this->assertSame( $theme, $settings->design()['theme'] );
		$this->assertSame( $size, $settings->design()['size'] );
		$this->assertSame( $shape, $settings->design()['shape'] );
		$this->assertSame( SwitcherSettings::PRESET_DEFAULT, $settings->preset() );
		$this->assertArrayNotHasKey( 'appearance', $settings->to_array() );
	}

	/**
	 * @dataProvider appearance_matrix_provider
	 *
	 * @param string $theme Theme token.
	 * @param string $size  Size token.
	 * @param string $shape Shape token.
	 */
	public function test_modifier_classes_are_stable_for_each_combination( string $theme, string $size, string $shape ): void {
		$classes = SwitcherSettings::from_array(
			array(
				'appearance' => array(
					'theme' => $theme,
					'size'  => $size,
					'shape' => $shape,
				),
			)
		)->modifier_classes();

		$this->assertContains( 'umc-switcher--theme-' . $theme, $classes );
		$this->assertContains( 'umc-switcher--size-' . $size, $classes );
		$this->assertContains( 'umc-switcher--shape-' . $shape, $classes );
		$this->assertContains( 'umc-switcher--preset-' . SwitcherSettings::PRESET_DEFAULT, $classes );
	}

	/**
	 * @dataProvider appearance_matrix_provider
	 *
	 * @param string $theme Theme token.
	 * @param string $size  Size token.
	 * @param string $shape Shape token.
	 */
	public function test_effective_cascade_values_match_v015_baseline( string $theme, string $size, string $shape ): void {
		$effective = $this->effective_tokens( $theme, $size, $shape );

		$expected_radius = self::EXPECTED_SHAPE_RADIUS[ $shape ] ?? self::EXPECTED_SIZE_RADIUS[ $size ];

		$this->assertSame(
			$expected_radius,
			$effective['radius'],
			sprintf( 'Radius drifted for %s/%s/%s.', $theme, $size, $shape )
		);
		$this->assertSame(
			self::EXPECTED_HEIGHT[ $size ],
			$effective['control-height'],
			sprintf( 'Control height drifted for %s/%s/%s.', $theme, $size, $shape )
		);
	}

	public function test_default_preset_declares_no_tokens(): void {
		$rules = $this->stylesheet_rules();

		$this->assertArrayHasKey( 'preset-default', $rules, 'The default preset rule must exist.' );
		$this->assertSame(
			array(),
			$this->declarations( $rules['preset-default'] ),
			'--preset-default must be a token no-op relative to base and theme/size/shape.'
		);
	}

	public function test_presets_are_declared_before_theme_size_and_shape(): void {
		$source = $this->stylesheet();

		$last_preset = 0;

		foreach ( SwitcherSettings::PRESETS as $preset ) {
			$position = strpos( $source, '.umc-switcher--preset-' . $preset . ' {' );

			$this->assertIsInt( $position, 'Missing preset rule: ' . $preset );
			$last_preset = max( $last_preset, $position );
		}

		foreach ( array( 'size-compact', 'size-large', 'shape-slight', 'shape-pill' ) as $modifier ) {
			$position = strpos( $source, '.umc-switcher--' . $modifier . ' {' );

			$this->assertIsInt( $position, 'Missing modifier rule: ' . $modifier );
			$this->assertGreaterThan(
				$last_preset,
				$position,
				'First-class theme/size/shape must be declared after presets so they win the cascade.'
			);
		}
	}

	/**
	 * Resolves characterized tokens through the stylesheet cascade.
	 *
	 * @param string $theme Theme token.
	 * @param string $size  Size token.
	 * @param string $shape Shape token.
	 * @return array<string, string>
	 */
	private function effective_tokens( string $theme, string $size, string $shape ): array {
		$active = array(
			'',
			'preset-' . SwitcherSettings::PRESET_DEFAULT,
			'theme-' . $theme,
			'size-' . $size,
			'shape-' . $shape,
		);

		$resolved = array();

		foreach ( $this->stylesheet_rules() as $modifier => $body ) {
			if ( ! in_array( $modifier, $active, true ) ) {
				continue;
			}

			foreach ( $this->declarations( $body ) as $property => $value ) {
				foreach ( self::TOKENS as $token ) {
					if ( '--umc-switcher-' . $token === $property ) {
						$resolved[ $token ] = $value;
					}
				}
			}
		}

		foreach ( self::TOKENS as $token ) {
			$this->assertArrayHasKey( $token, $resolved, 'Stylesheet never declares --umc-switcher-' . $token . '.' );
		}

		return $resolved;
	}

	/**
	 * Top-level single-class switcher rules, in stylesheet source order.
	 *
	 * @return array<string, string>
	 */
	private function stylesheet_rules(): array {
		$rules = array();

		if ( preg_match_all( '/^\.umc-switcher(--[a-z0-9-]+)?\s*\{([^}]*)\}/m', $this->stylesheet(), $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$rules[ ltrim( $match[1], '-' ) ] = $match[2];
			}
		}

		return $rules;
	}

	/**
	 * Extracts custom-property declarations from one rule body.
	 *
	 * @param string $body Rule body.
	 * @return array<string, string>
	 */
	private function declarations( string $body ): array {
		$declarations = array();

		if ( preg_match_all( '/(--[a-z0-9-]+)\s*:\s*([^;]+);/', $body, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$declarations[ $match[1] ] = trim( $match[2] );
			}
		}

		return $declarations;
	}

	/**
	 * Reads the shipped switcher stylesheet.
	 */
	private function stylesheet(): string {
		return (string) file_get_contents( dirname( __DIR__, 3 ) . '/assets/css/switcher.css' );
	}
}
