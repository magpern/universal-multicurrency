<?php
/**
 * Integration tests for Settings option persistence.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Settings;
use WP_UnitTestCase;

/**
 * Exercises Settings against the real umc_settings option under WordPress.
 */
final class SettingsOptionTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_absent_option_returns_defaults(): void {
		delete_option( Settings::OPTION );
		$this->assertSame( Settings::defaults(), ( new Settings() )->get() );
	}

	public function test_save_then_get_round_trips_through_the_option(): void {
		$settings = new Settings();
		$settings->save(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'  => true,
						'symbol'   => 'kr',
						'position' => 'right_space',
						'decimals' => 2,
						'rate'     => '11.50',
					),
				),
			)
		);

		// A fresh instance reads what was persisted.
		$reloaded = ( new Settings() )->get();
		$this->assertSame( '11.50', $reloaded['currencies']['SEK']['rate'] );
		$this->assertSame( 'kr', $reloaded['currencies']['SEK']['symbol'] );
		$this->assertSame( Settings::SCHEMA_VERSION, $reloaded['schema_version'] );
	}

	public function test_sanitization_is_applied_on_save(): void {
		$settings = new Settings();
		$settings->save(
			array(
				'currencies' => array(
					'sek'  => array( // Lowercase → uppercased.
						'decimals' => 99,   // Invalid → default 2.
						'position' => 'x',  // Invalid → left.
						'rate'     => '-1',  // Invalid → blanked.
					),
					'BAD1' => array( 'rate' => '2' ), // Malformed code → dropped.
				),
			)
		);

		$stored = get_option( Settings::OPTION );
		$this->assertArrayHasKey( 'SEK', $stored['currencies'] );
		$this->assertArrayNotHasKey( 'sek', $stored['currencies'] );
		$this->assertArrayNotHasKey( 'BAD1', $stored['currencies'] );
		$this->assertSame( 2, $stored['currencies']['SEK']['decimals'] );
		$this->assertSame( 'left', $stored['currencies']['SEK']['position'] );
		$this->assertSame( '', $stored['currencies']['SEK']['rate'] );
	}

	public function test_resaving_is_stable(): void {
		$settings = new Settings();
		$input    = array(
			'currencies' => array(
				'JPY' => array(
					'decimals' => 0,
					'rate'     => '161',
				),
			),
		);
		$settings->save( $input );
		$first = get_option( Settings::OPTION );

		( new Settings() )->save( $first );
		$second = get_option( Settings::OPTION );

		$this->assertSame( $first, $second );
	}
}
