<?php
/**
 * Integration tests for settings schema upgrade persistence.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Settings;
use UMC\SettingsUpgrader;
use WP_UnitTestCase;

/**
 * Exercises the real option repository path for upgrade writes and write avoidance.
 */
final class SettingsUpgradeIntegrationTest extends WP_UnitTestCase {

	/**
	 * Counts attempted writes to the settings option during a test.
	 *
	 * @var int
	 */
	private int $option_update_count = 0;

	public function set_up(): void {
		parent::set_up();

		$this->option_update_count = 0;

		add_filter(
			'pre_update_option_' . Settings::OPTION,
			function ( $value, $old_value ) {
				unset( $old_value );
				++$this->option_update_count;

				return $value;
			},
			10,
			2
		);
	}

	public function tear_down(): void {
		Settings::reset_upgrader();
		delete_option( Settings::OPTION );
		remove_all_filters( 'pre_update_option_' . Settings::OPTION );

		parent::tear_down();
	}

	public function test_absent_option_still_returns_defaults_without_writing(): void {
		delete_option( Settings::OPTION );

		$this->assertSame( Settings::defaults(), ( new Settings() )->get() );
		$this->assertSame( 0, $this->option_update_count );
		$this->assertFalse( get_option( Settings::OPTION, false ) );
	}

	public function test_v0_option_is_upgraded_and_persisted_on_first_load(): void {
		update_option(
			Settings::OPTION,
			array(
				'currencies' => array(
					'SEK' => array(
						'rate'     => '11.50',
						'decimals' => 2,
					),
				),
			)
		);

		$this->option_update_count = 0;

		$loaded = ( new Settings() )->get();

		$this->assertSame( Settings::SCHEMA_VERSION, $loaded['schema_version'] );
		$this->assertSame( '11.50', $loaded['currencies']['SEK']['rate'] );
		$this->assertSame( 1, $this->option_update_count );

		$stored = get_option( Settings::OPTION );
		$this->assertSame( Settings::SCHEMA_VERSION, $stored['schema_version'] );
	}

	public function test_canonical_v1_option_loads_without_extra_writes(): void {
		$settings = new Settings();
		$settings->save(
			array(
				'currencies' => array(
					'USD' => array(
						'rate' => '1.20',
					),
				),
			)
		);

		$this->option_update_count = 0;

		$reloaded = ( new Settings() )->get();

		$this->assertSame( '1.20', $reloaded['currencies']['USD']['rate'] );
		$this->assertSame( 0, $this->option_update_count );
	}

	public function test_second_load_after_v0_upgrade_is_idempotent(): void {
		update_option(
			Settings::OPTION,
			array(
				'currencies' => array(
					'JPY' => array(
						'rate'     => '161',
						'decimals' => 0,
					),
				),
			)
		);

		$this->option_update_count = 0;

		$first = ( new Settings() )->get();
		$this->assertSame( 1, $this->option_update_count );

		$this->option_update_count = 0;
		$second                    = ( new Settings() )->get();

		$this->assertSame( $first, $second );
		$this->assertSame( 0, $this->option_update_count );
	}

	public function test_unsupported_future_version_falls_back_without_persisting(): void {
		update_option(
			Settings::OPTION,
			array(
				'schema_version' => 99,
				'currencies'     => array(
					'USD' => array( 'rate' => '1' ),
				),
			)
		);

		$this->option_update_count = 0;

		$loaded = ( new Settings() )->get();

		$this->assertSame( Settings::defaults(), $loaded );
		$this->assertSame( 0, $this->option_update_count );
		$this->assertSame( 99, SettingsUpgrader::parse_stored_version( get_option( Settings::OPTION ) ) );
	}

	public function test_failed_migration_does_not_overwrite_stored_option(): void {
		update_option(
			Settings::OPTION,
			array(
				'schema_version' => 0,
				'currencies'     => array(
					'USD' => array( 'rate' => '1' ),
				),
			)
		);

		Settings::set_upgrader(
			new SettingsUpgrader(
				1,
				array(
					1 => static function (): array {
						throw new \RuntimeException( 'Migration failed deliberately.' );
					},
				)
			)
		);

		$this->option_update_count = 0;

		$this->assertSame( Settings::defaults(), ( new Settings() )->get() );
		$this->assertSame( 0, $this->option_update_count );
		$this->assertSame( 0, SettingsUpgrader::parse_stored_version( get_option( Settings::OPTION ) ) );
	}
}
