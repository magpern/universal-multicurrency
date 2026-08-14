<?php
/**
 * Unit tests for the schema v5 → v6 switcher presentation migration.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Checkout\CheckoutSettings;
use UMC\Display\SwitcherSettings;
use UMC\Geo\GeoDetectionSettings;
use UMC\Settings;
use UMC\SettingsUpgrader;

/**
 * Proves migrate_5_to_6 restructures Display without changing appearance.
 */
final class SettingsMigrationV5ToV6Test extends TestCase {

	protected function tearDown(): void {
		Settings::reset_upgrader();
		parent::tearDown();
	}

	public function test_migrate_5_to_6_constant_points_to_callable(): void {
		$this->assertSame( SettingsUpgrader::MIGRATE_5_TO_6, SettingsUpgrader::production_migrations()[6] );
	}

	public function test_migrate_5_to_6_preserves_non_display_configuration(): void {
		$migrated = SettingsUpgrader::migrate_5_to_6( $this->v5_fixture() );

		$this->assertSame( 6, $migrated['schema_version'] );
		$this->assertSame( Settings::RATE_MODE_AUTOMATIC, $migrated['rate_mode'] );
		$this->assertSame( 'P3D', $migrated['rate_update_interval'] );
		$this->assertSame( '1.08', $migrated['currencies']['USD']['manual_rate'] );
		$this->assertSame( CheckoutSettings::default_array(), $migrated['checkout'] );
		$this->assertSame( GeoDetectionSettings::default_array(), $migrated['geo'] );
	}

	public function test_migrate_5_to_6_preserves_placement_behavior_and_visibility(): void {
		$display = SettingsUpgrader::migrate_5_to_6( $this->v5_fixture() )['display'];

		$this->assertTrue( $display['enabled'] );
		$this->assertSame( SwitcherSettings::PLACEMENT_FLOATING_SIDE, $display['placement'] );
		$this->assertSame( SwitcherSettings::STYLE_DROPDOWN, $display['style'] );
		$this->assertSame( SwitcherSettings::SIDE_LEFT, $display['position']['side'] );
		$this->assertSame( 24, $display['position']['edge_offset'] );
		$this->assertFalse( $display['behavior']['remember_selection'] );
		$this->assertTrue( $display['behavior']['active_first'] );
		$this->assertTrue( $display['visibility']['desktop'] );
		$this->assertFalse( $display['visibility']['mobile'] );
	}

	public function test_migrate_5_to_6_initializes_design_defaults_without_dropping_appearance(): void {
		$display = SettingsUpgrader::migrate_5_to_6( $this->v5_fixture() )['display'];

		$this->assertSame( SwitcherSettings::PRESET_DEFAULT, $display['design']['preset'] );
		$this->assertSame( SwitcherSettings::THEME_DARK, $display['design']['theme'] );
		$this->assertSame( SwitcherSettings::SIZE_LARGE, $display['design']['size'] );
		$this->assertSame( SwitcherSettings::SHAPE_PILL, $display['design']['shape'] );
		$this->assertSame( array(), $display['design']['overrides'] );
		$this->assertSame( SwitcherSettings::MOTION_SUBTLE, $display['design']['motion'] );
		$this->assertArrayNotHasKey( 'appearance', $display );
	}

	public function test_migrate_5_to_6_never_infers_a_named_preset_from_shape(): void {
		$v5 = $this->v5_fixture();

		$v5['display']['appearance']['shape'] = SwitcherSettings::SHAPE_PILL;

		$display = SettingsUpgrader::migrate_5_to_6( $v5 )['display'];

		$this->assertSame( SwitcherSettings::PRESET_DEFAULT, $display['design']['preset'] );
		$this->assertSame( SwitcherSettings::SHAPE_PILL, $display['design']['shape'] );
	}

	/**
	 * @dataProvider appearance_matrix_provider
	 *
	 * @param string $theme Theme token.
	 * @param string $size  Size token.
	 * @param string $shape Shape token.
	 */
	public function test_migration_preserves_every_appearance_combination( string $theme, string $size, string $shape ): void {
		$v5 = $this->v5_fixture();

		$v5['display']['appearance'] = array(
			'theme' => $theme,
			'size'  => $size,
			'shape' => $shape,
		);

		$design = SettingsUpgrader::migrate_5_to_6( $v5 )['display']['design'];

		$this->assertSame( $theme, $design['theme'] );
		$this->assertSame( $size, $design['size'] );
		$this->assertSame( $shape, $design['shape'] );
		$this->assertSame( SwitcherSettings::PRESET_DEFAULT, $design['preset'] );
	}

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

	public function test_migrate_5_to_6_splits_flat_content_into_trigger_and_menu(): void {
		$v5 = $this->v5_fixture();

		$v5['display']['content'] = array(
			'show_code'   => false,
			'show_symbol' => true,
			'show_name'   => true,
		);

		$content = SettingsUpgrader::migrate_5_to_6( $v5 )['display']['content'];

		$this->assertFalse( $content['trigger']['show_code'] );
		$this->assertTrue( $content['trigger']['show_symbol'] );
		$this->assertFalse( $content['trigger']['show_name'] );

		$this->assertFalse( $content['menu']['show_code'] );
		$this->assertTrue( $content['menu']['show_symbol'] );
		$this->assertTrue( $content['menu']['show_name'] );
	}

	public function test_migrate_5_to_6_orders_visible_elements_code_symbol_name(): void {
		$v5 = $this->v5_fixture();

		$v5['display']['content'] = array(
			'show_code'   => true,
			'show_symbol' => true,
			'show_name'   => true,
		);

		$content = SettingsUpgrader::migrate_5_to_6( $v5 )['display']['content'];

		$this->assertSame( array( 'code', 'symbol' ), $content['trigger']['order'] );
		$this->assertSame( array( 'code', 'symbol', 'name' ), $content['menu']['order'] );
	}

	public function test_migrate_5_to_6_orders_only_visible_elements(): void {
		$v5 = $this->v5_fixture();

		$v5['display']['content'] = array(
			'show_code'   => false,
			'show_symbol' => true,
			'show_name'   => true,
		);

		$content = SettingsUpgrader::migrate_5_to_6( $v5 )['display']['content'];

		$this->assertSame( array( 'symbol' ), $content['trigger']['order'] );
		$this->assertSame( array( 'symbol', 'name' ), $content['menu']['order'] );
	}

	public function test_migrate_5_to_6_defaults_chevron_off(): void {
		$content = SettingsUpgrader::migrate_5_to_6( $this->v5_fixture() )['display']['content'];

		$this->assertFalse( $content['show_chevron'] );
	}

	public function test_fresh_schema_six_installs_also_default_chevron_off(): void {
		$this->assertFalse( SwitcherSettings::default_array()['content']['show_chevron'] );
	}

	public function test_migrate_5_to_6_initializes_responsive_and_custom_css(): void {
		$display = SettingsUpgrader::migrate_5_to_6( $this->v5_fixture() )['display'];

		$this->assertSame(
			array(
				'hide_name_on_mobile' => false,
				'compact_on_mobile'   => false,
			),
			$display['responsive']
		);
		$this->assertSame( '', $display['custom_css'] );
	}

	public function test_migrate_5_to_6_is_idempotent(): void {
		$once  = SettingsUpgrader::migrate_5_to_6( $this->v5_fixture() );
		$twice = SettingsUpgrader::migrate_5_to_6( $once );

		$this->assertSame( $once, $twice );
	}

	public function test_migrated_display_round_trips_through_sanitization(): void {
		$display = SettingsUpgrader::migrate_5_to_6( $this->v5_fixture() )['display'];

		$this->assertSame( $display, SwitcherSettings::sanitize_raw( $display ) );
	}

	public function test_v5_to_v6_upgrade_produces_canonical_settings(): void {
		$result = ( new SettingsUpgrader() )->upgrade( $this->v5_fixture() );

		$this->assertFalse( $result->is_failed() );
		$this->assertSame( Settings::SCHEMA_VERSION, $result->settings()['schema_version'] );
		$this->assertSame(
			SwitcherSettings::THEME_DARK,
			$result->settings()['display']['design']['theme']
		);
		$this->assertArrayNotHasKey( 'appearance', $result->settings()['display'] );
	}

	public function test_upgrade_from_schema_zero_reaches_current_schema(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'currencies' => array(
					'SEK' => array( 'rate' => '11.5' ),
				),
			)
		);

		$this->assertFalse( $result->is_failed() );
		$this->assertSame( Settings::SCHEMA_VERSION, $result->settings()['schema_version'] );
		$this->assertSame( SwitcherSettings::default_array(), $result->settings()['display'] );
	}

	/**
	 * Representative schema-5 store with non-default Display configuration.
	 *
	 * @return array<string, mixed>
	 */
	private function v5_fixture(): array {
		return array(
			'schema_version'       => 5,
			'rate_mode'            => Settings::RATE_MODE_AUTOMATIC,
			'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => 'P3D',
			'rate_max_age_hours'   => Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => array(
				'USD' => array(
					'manual_rate' => '1.08',
					'enabled'     => true,
				),
			),
			'display'              => array(
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
					'show_code'   => true,
					'show_symbol' => false,
					'show_name'   => true,
				),
				'appearance' => array(
					'theme' => SwitcherSettings::THEME_DARK,
					'size'  => SwitcherSettings::SIZE_LARGE,
					'shape' => SwitcherSettings::SHAPE_PILL,
				),
				'behavior'   => array(
					'remember_selection' => false,
					'active_first'       => true,
				),
				'visibility' => array(
					'desktop' => true,
					'mobile'  => false,
				),
			),
			'checkout'             => CheckoutSettings::default_array(),
			'geo'                  => GeoDetectionSettings::default_array(),
		);
	}
}
