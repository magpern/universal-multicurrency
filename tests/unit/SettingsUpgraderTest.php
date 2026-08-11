<?php
/**
 * Unit tests for the settings schema upgrade runner.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Settings;
use UMC\SettingsUpgrader;

/**
 * Exercises production migrations, failure modes, idempotency, and test-only chaining.
 */
final class SettingsUpgraderTest extends TestCase {

	protected function tearDown(): void {
		Settings::reset_upgrader();
		parent::tearDown();
	}

	public function test_parse_stored_version_treats_missing_as_zero(): void {
		$this->assertSame( 0, SettingsUpgrader::parse_stored_version( array( 'currencies' => array() ) ) );
		$this->assertSame( 0, SettingsUpgrader::parse_stored_version( array() ) );
		$this->assertSame( 0, SettingsUpgrader::parse_stored_version( null ) );
	}

	public function test_parse_stored_version_reads_integer_and_numeric_string(): void {
		$this->assertSame( 1, SettingsUpgrader::parse_stored_version( array( 'schema_version' => 1 ) ) );
		$this->assertSame( 1, SettingsUpgrader::parse_stored_version( array( 'schema_version' => '1' ) ) );
		$this->assertSame( 0, SettingsUpgrader::parse_stored_version( array( 'schema_version' => 0 ) ) );
	}

	public function test_parse_stored_version_treats_malformed_as_zero(): void {
		$this->assertSame( 0, SettingsUpgrader::parse_stored_version( array( 'schema_version' => 'abc' ) ) );
		$this->assertSame( 0, SettingsUpgrader::parse_stored_version( array( 'schema_version' => 1.5 ) ) );
	}

	public function test_v0_to_v1_upgrade_produces_canonical_settings(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'currencies' => array(
					'SEK' => array(
						'rate'     => '11.50',
						'decimals' => 2,
					),
				),
			)
		);

		$this->assertFalse( $result->is_failed() );
		$this->assertFalse( $result->is_unsupported_future() );
		$this->assertTrue( $result->should_persist() );
		$this->assertSame( Settings::SCHEMA_VERSION, $result->settings()['schema_version'] );
		$this->assertSame( '11.50', $result->settings()['currencies']['SEK']['manual_rate'] );
	}

	public function test_v2_to_v2_performs_no_migration_and_avoids_persist_when_canonical(): void {
		$canonical = Settings::sanitize(
			array(
				'schema_version' => Settings::SCHEMA_VERSION,
				'currencies'     => array(
					'USD' => array(
						'manual_rate' => '1.20',
					),
				),
			)
		);

		$result = ( new SettingsUpgrader() )->upgrade( $canonical );

		$this->assertSame( $canonical, $result->settings() );
		$this->assertFalse( $result->should_persist() );
	}

	public function test_v1_to_v2_migration_renames_rate_to_manual_rate(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'schema_version' => 1,
				'currencies'     => array(
					'SEK' => array(
						'rate'            => '11.50',
						'rate_updated_at' => 100,
					),
				),
			)
		);

		$this->assertSame( Settings::SCHEMA_VERSION, $result->settings()['schema_version'] );
		$this->assertSame( '11.50', $result->settings()['currencies']['SEK']['manual_rate'] );
		$this->assertArrayNotHasKey( 'rate', $result->settings()['currencies']['SEK'] );
		$this->assertSame( Settings::RATE_MODE_MANUAL, $result->settings()['rate_mode'] );
	}

	public function test_v0_upgrade_is_idempotent(): void {
		$upgrader = new SettingsUpgrader();
		$raw_v0   = array(
			'currencies' => array(
				'JPY' => array(
					'decimals' => 0,
					'rate'     => '161',
				),
			),
		);

		$first  = $upgrader->upgrade( $raw_v0 );
		$second = $upgrader->upgrade( $first->settings() );

		$this->assertSame( $first->settings(), $second->settings() );
		$this->assertFalse( $second->should_persist() );
	}

	public function test_partial_v0_settings_keep_valid_currencies(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'currencies'   => array(
					'sek'  => array( 'rate' => '11.5' ),
					'BAD1' => array( 'rate' => '2' ),
				),
				'legacy_field' => 'drop-me',
			)
		);

		$this->assertArrayHasKey( 'SEK', $result->settings()['currencies'] );
		$this->assertArrayNotHasKey( 'legacy_field', $result->settings() );
	}

	public function test_unknown_currency_keys_and_invalid_values_are_normalized(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'schema_version' => 0,
				'currencies'     => array(
					'USD' => array(
						'rate'     => '-1',
						'evil_key' => 'x',
					),
				),
			)
		);

		$this->assertSame( '', $result->settings()['currencies']['USD']['manual_rate'] );
		$this->assertArrayNotHasKey( 'evil_key', $result->settings()['currencies']['USD'] );
	}

	public function test_empty_v0_option_upgrades_to_defaults(): void {
		$result = ( new SettingsUpgrader() )->upgrade( array() );

		$this->assertSame( Settings::defaults(), $result->settings() );
		$this->assertTrue( $result->should_persist() );
	}

	public function test_unsupported_future_version_is_rejected_without_partial_state(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'schema_version' => 99,
				'currencies'     => array(
					'USD' => array( 'rate' => '1' ),
				),
			)
		);

		$this->assertTrue( $result->is_unsupported_future() );
		$this->assertSame( 99, $result->unsupported_future_version() );
		$this->assertFalse( $result->should_persist() );
		$this->assertSame( Settings::defaults(), $result->settings() );
	}

	public function test_failed_migration_does_not_persist_partial_result(): void {
		$upgrader = new SettingsUpgrader(
			1,
			array(
				1 => static function (): array {
					throw new \RuntimeException( 'Migration failed deliberately.' );
				},
			)
		);

		$result = $upgrader->upgrade(
			array(
				'schema_version' => 0,
				'currencies'     => array(),
			)
		);

		$this->assertTrue( $result->is_failed() );
		$this->assertFalse( $result->should_persist() );
		$this->assertSame( Settings::defaults(), $result->settings() );
	}

	public function test_migration_chaining_fixture_runs_in_order_without_skips(): void {
		$executed = array();

		$upgrader = new SettingsUpgrader(
			3,
			array(
				1 => static function ( array $data ) use ( &$executed ): array {
					$executed[]             = 1;
					$data['schema_version'] = 1;
					$data['chain']          = (string) ( $data['chain'] ?? '' ) . '1';
					$data['step_1_marker']  = true;

					return $data;
				},
				2 => static function ( array $data ) use ( &$executed ): array {
					$executed[]             = 2;
					$data['schema_version'] = 2;
					$data['chain']          = (string) ( $data['chain'] ?? '' ) . '2';
					$data['step_2_from']    = $data['schema_version'] - 1;

					return $data;
				},
				3 => static function ( array $data ) use ( &$executed ): array {
					$executed[]             = 3;
					$data['schema_version'] = 3;
					$data['chain']          = (string) ( $data['chain'] ?? '' ) . '3';

					return $data;
				},
			)
		);

		$migrated = $upgrader->migrate_only(
			array(
				'schema_version' => 0,
				'chain'          => '',
			)
		);

		$this->assertSame( array( 1, 2, 3 ), $executed );
		$this->assertSame( 3, $migrated['schema_version'] );
		$this->assertSame( '123', $migrated['chain'] );
		$this->assertTrue( $migrated['step_1_marker'] );
		$this->assertSame( 1, $migrated['step_2_from'] );

		$again = $upgrader->migrate_only( $migrated );
		$this->assertSame( $migrated, $again );
		$this->assertSame( array( 1, 2, 3 ), $executed, 'Re-running at target version must not repeat migrations.' );
	}

	public function test_production_runner_registers_v0_to_v1_and_v1_to_v2_migrations(): void {
		$this->assertSame(
			array( 1, 2, 3, 4, 5, 6 ),
			array_keys( SettingsUpgrader::production_migrations() )
		);
		$this->assertSame( SettingsUpgrader::MIGRATE_0_TO_1, SettingsUpgrader::production_migrations()[1] );
		$this->assertSame( SettingsUpgrader::MIGRATE_1_TO_2, SettingsUpgrader::production_migrations()[2] );
		$this->assertSame( SettingsUpgrader::MIGRATE_2_TO_3, SettingsUpgrader::production_migrations()[3] );
		$this->assertSame( SettingsUpgrader::MIGRATE_3_TO_4, SettingsUpgrader::production_migrations()[4] );
		$this->assertSame( SettingsUpgrader::MIGRATE_4_TO_5, SettingsUpgrader::production_migrations()[5] );
		$this->assertSame( SettingsUpgrader::MIGRATE_5_TO_6, SettingsUpgrader::production_migrations()[6] );
	}

	public function test_v2_to_v3_migration_preserves_existing_settings_and_adds_display_defaults(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'schema_version'       => 2,
				'rate_mode'            => Settings::RATE_MODE_AUTOMATIC,
				'rate_update_interval' => 'P3D',
				'currencies'           => array(
					'EUR' => array(
						'manual_rate' => '0.92',
						'enabled'     => true,
					),
				),
			)
		);

		$this->assertSame( Settings::SCHEMA_VERSION, $result->settings()['schema_version'] );
		$this->assertSame( Settings::RATE_MODE_AUTOMATIC, $result->settings()['rate_mode'] );
		$this->assertSame( 'P3D', $result->settings()['rate_update_interval'] );
		$this->assertSame( '0.92', $result->settings()['currencies']['EUR']['manual_rate'] );
		$this->assertFalse( $result->settings()['display']['enabled'] );
		$this->assertSame( \UMC\Display\SwitcherSettings::default_array()['placement'], $result->settings()['display']['placement'] );
	}

	public function test_v3_to_v4_migration_preserves_settings_and_adds_checkout_defaults(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'schema_version'       => 3,
				'rate_mode'            => Settings::RATE_MODE_AUTOMATIC,
				'rate_update_interval' => 'P3D',
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
			)
		);

		$this->assertSame( Settings::SCHEMA_VERSION, $result->settings()['schema_version'] );
		$this->assertSame( Settings::RATE_MODE_AUTOMATIC, $result->settings()['rate_mode'] );
		$this->assertSame( 'P3D', $result->settings()['rate_update_interval'] );
		$this->assertSame( '1.08', $result->settings()['currencies']['USD']['manual_rate'] );
		$this->assertTrue( $result->settings()['display']['enabled'] );
		$this->assertSame(
			\UMC\Checkout\CheckoutSettings::default_array(),
			$result->settings()['checkout']
		);
	}

	public function test_migrate_0_to_1_strips_unknown_root_keys(): void {
		$migrated = SettingsUpgrader::migrate_0_to_1(
			array(
				'currencies'     => array( 'USD' => array( 'rate' => '1' ) ),
				'legacy_root'    => 'remove',
				'schema_version' => 0,
			)
		);

		$this->assertSame(
			array(
				'schema_version' => 1,
				'currencies'     => array( 'USD' => array( 'rate' => '1' ) ),
			),
			$migrated
		);
	}

	public function test_upgrade_marks_non_canonical_v1_for_persistence_via_v2_migration(): void {
		$result = ( new SettingsUpgrader() )->upgrade(
			array(
				'schema_version' => 1,
				'currencies'     => array(
					'sek' => array( 'rate' => '11.5' ),
				),
			)
		);

		$this->assertTrue( $result->should_persist() );
		$this->assertArrayHasKey( 'SEK', $result->settings()['currencies'] );
		$this->assertSame( '11.5', $result->settings()['currencies']['SEK']['manual_rate'] );
	}
}
