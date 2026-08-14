<?php
/**
 * Unit tests for schema 6 → 7 Display presentation migration.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherElementComposer;
use UMC\Display\SwitcherSettings;
use UMC\Settings;
use UMC\SettingsUpgrader;

/**
 * Proves migrate_6_to_7 adds presentation defaults without visible drift.
 */
final class SettingsMigrationV6ToV7Test extends TestCase {

	public function test_migrate_6_to_7_constant_points_to_callable(): void {
		$this->assertSame( SettingsUpgrader::MIGRATE_6_TO_7, SettingsUpgrader::production_migrations()[7] );
	}

	public function test_migrate_6_to_7_defaults_icons_off_and_preserves_order(): void {
		$display = SettingsUpgrader::migrate_6_to_7( $this->v6_fixture() )['display'];

		$this->assertFalse( $display['content']['trigger']['show_icon'] );
		$this->assertFalse( $display['content']['menu']['show_icon'] );
		$this->assertSame(
			array( 'name', 'code' ),
			$display['content']['menu']['order']
		);
		$this->assertNotContains( SwitcherElementComposer::ELEMENT_ICON, $display['content']['menu']['order'] );
	}

	public function test_migrate_6_to_7_initializes_presentation_defaults(): void {
		$presentation = SettingsUpgrader::migrate_6_to_7( $this->v6_fixture() )['display']['presentation'];

		$this->assertSame( array(), $presentation['icon_overrides'] );
		$this->assertSame( SwitcherSettings::SIZE_STANDARD, $presentation['icon_size'] );
		$this->assertSame( SwitcherSettings::ICON_SHAPE_NATURAL, $presentation['icon_shape'] );
	}

	public function test_migrate_6_to_7_preserves_existing_content_visibility(): void {
		$display = SettingsUpgrader::migrate_6_to_7( $this->v6_fixture() )['display'];

		$this->assertTrue( $display['content']['menu']['show_code'] );
		$this->assertFalse( $display['content']['menu']['show_symbol'] );
		$this->assertTrue( $display['content']['menu']['show_name'] );
	}

	public function test_migrate_6_to_7_is_idempotent(): void {
		$once  = SettingsUpgrader::migrate_6_to_7( $this->v6_fixture() );
		$twice = SettingsUpgrader::migrate_6_to_7( $once );

		$this->assertSame( $once, $twice );
	}

	public function test_upgrade_from_schema_six_reaches_schema_seven(): void {
		$result = ( new SettingsUpgrader() )->upgrade( $this->v6_fixture() );

		$this->assertFalse( $result->is_failed() );
		$this->assertSame( 7, $result->settings()['schema_version'] );
	}

	/**
	 * Representative schema-6 store with non-default menu composition.
	 *
	 * @return array<string, mixed>
	 */
	private function v6_fixture(): array {
		return array(
			'schema_version'       => 6,
			'rate_mode'            => Settings::RATE_MODE_MANUAL,
			'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => array(
				'EUR' => array(
					'enabled'     => true,
					'manual_rate' => '1',
				),
			),
			'display'              => array(
				'enabled'    => true,
				'placement'  => SwitcherSettings::PLACEMENT_MANUAL,
				'style'      => SwitcherSettings::STYLE_DROPDOWN,
				'content'    => array(
					'trigger'      => array(
						'show_code'   => true,
						'show_symbol' => true,
						'show_name'   => false,
						'order'       => array( 'code', 'symbol' ),
					),
					'menu'         => array(
						'show_code'   => true,
						'show_symbol' => false,
						'show_name'   => true,
						'order'       => array( 'name', 'code' ),
					),
					'show_chevron' => true,
				),
				'design'     => SwitcherSettings::default_array()['design'],
				'behavior'   => SwitcherSettings::default_array()['behavior'],
				'visibility' => SwitcherSettings::default_array()['visibility'],
				'responsive' => SwitcherSettings::default_array()['responsive'],
				'custom_css' => '.umc-switcher { opacity: 1; }',
			),
			'checkout'             => \UMC\Checkout\CheckoutSettings::default_array(),
			'geo'                  => \UMC\Geo\GeoDetectionSettings::default_array(),
		);
	}
}
