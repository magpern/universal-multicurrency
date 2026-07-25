<?php
/**
 * Integration tests for the admin currencies-table parsing.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Admin\CurrencyTableField;
use UMC\Currency;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies POST parsing round-trips through the M1 sanitizer.
 */
final class CurrencyTableFieldTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	private function field(): CurrencyTableField {
		return new CurrencyTableField( new Settings(), new Currency( 'EUR', 2 ) );
	}

	public function test_parse_builds_settings_shape_and_persists(): void {
		$parsed = $this->field()->parse(
			array(
				array(
					'code'     => 'sek',
					'enabled'  => '1',
					'symbol'   => 'kr',
					'position' => 'right_space',
					'decimals' => '2',
					'rate'     => '11.50',
				),
			)
		);

		( new Settings() )->save( array( 'currencies' => $parsed ) );

		$stored = ( new Settings() )->get_currency_config( 'SEK' );
		$this->assertNotNull( $stored );
		$this->assertSame( '11.50', $stored['rate'] );
		$this->assertSame( 'kr', $stored['symbol'] );
		$this->assertSame( 'right_space', $stored['position'] );
		$this->assertTrue( $stored['enabled'] );
	}

	public function test_parse_drops_blank_and_base_rows(): void {
		$parsed = $this->field()->parse(
			array(
				array(
					'code' => '',
					'rate' => '5',
				),
				array(
					'code' => 'EUR',
					'rate' => '2',
				),
				array(
					'code' => 'SEK',
					'rate' => '11.5',
				),
			)
		);

		$this->assertArrayHasKey( 'SEK', $parsed );
		$this->assertArrayNotHasKey( 'EUR', $parsed );
		$this->assertCount( 1, $parsed );
	}

	public function test_parse_delegates_validation_to_sanitizer(): void {
		// A negative rate survives parsing but is blanked by Settings::sanitize.
		$parsed = $this->field()->parse(
			array(
				array(
					'code' => 'SEK',
					'rate' => '-5',
				),
			)
		);
		( new Settings() )->save( array( 'currencies' => $parsed ) );

		$this->assertNull( ( new Settings() )->get_rate( 'SEK' ) );
	}
}
