<?php
/**
 * Unit tests for schema v4 → v5 Geo Detection migration.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Geo\GeoDetectionSettings;
use UMC\Settings;
use UMC\SettingsUpgrader;

/**
 * Proves migrate_4_to_5 adds disabled geo defaults without altering existing config.
 */
final class SettingsMigrationV4ToV5Test extends TestCase {

	protected function tearDown(): void {
		Settings::reset_upgrader();
		parent::tearDown();
	}

	public function test_migrate_4_to_5_adds_geo_disabled_with_empty_rules(): void {
		$v4 = array(
			'schema_version'       => 4,
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
				'enabled'   => true,
				'placement' => \UMC\Display\SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			),
			'checkout'             => \UMC\Checkout\CheckoutSettings::default_array(),
		);

		$migrated = SettingsUpgrader::migrate_4_to_5( $v4 );

		$this->assertSame( 5, $migrated['schema_version'] );
		$this->assertSame( Settings::RATE_MODE_AUTOMATIC, $migrated['rate_mode'] );
		$this->assertSame( 'P3D', $migrated['rate_update_interval'] );
		$this->assertSame( '1.08', $migrated['currencies']['USD']['manual_rate'] );
		$this->assertTrue( $migrated['display']['enabled'] );
		$this->assertSame( GeoDetectionSettings::default_array(), $migrated['geo'] );
		$this->assertFalse( $migrated['geo']['enabled'] );
		$this->assertSame( array(), $migrated['geo']['rules'] );
	}

	public function test_v4_to_v5_upgrade_produces_canonical_settings(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'schema_version' => 4,
				'currencies'     => array(
					'EUR' => array(
						'manual_rate' => '1',
					),
				),
			)
		);

		$this->assertFalse( $result->is_failed() );
		$this->assertSame( Settings::SCHEMA_VERSION, $result->settings()['schema_version'] );
		$this->assertSame( GeoDetectionSettings::default_array(), $result->settings()['geo'] );
	}

	public function test_migrate_4_to_5_constant_points_to_callable(): void {
		$this->assertSame( SettingsUpgrader::MIGRATE_4_TO_5, SettingsUpgrader::production_migrations()[5] );
	}
}
