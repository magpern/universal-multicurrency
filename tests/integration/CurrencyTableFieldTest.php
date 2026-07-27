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
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies POST parsing round-trips through the settings sanitizer.
 */
final class CurrencyTableFieldTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	private function field(): CurrencyTableField {
		$settings = new Settings();

		return new CurrencyTableField(
			$settings,
			new Currency( 'EUR', 2 ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
		);
	}

	public function test_parse_builds_settings_shape_and_persists(): void {
		$parsed = $this->field()->parse(
			array(
				array(
					'code'                => 'sek',
					'enabled'             => '1',
					'symbol'              => 'kr',
					'position'            => 'right_space',
					'decimals'            => '2',
					'manual_rate'         => '11.50',
					'merchant_adjustment' => '0',
					'rate_mode'           => '',
				),
			)
		);

		( new Settings() )->save( array_merge( Settings::defaults(), array( 'currencies' => $parsed ) ) );

		$stored = ( new Settings() )->get_currency_config( 'SEK' );
		$this->assertNotNull( $stored );
		$this->assertSame( '11.50', $stored['manual_rate'] );
		$this->assertSame( 'kr', $stored['symbol'] );
		$this->assertSame( 'right_space', $stored['position'] );
		$this->assertTrue( $stored['enabled'] );
	}

	public function test_parse_drops_blank_and_base_rows(): void {
		$parsed = $this->field()->parse(
			array(
				array(
					'code'        => '',
					'manual_rate' => '5',
				),
				array(
					'code'        => 'EUR',
					'manual_rate' => '2',
				),
				array(
					'code'        => 'SEK',
					'manual_rate' => '11.5',
				),
			)
		);

		$this->assertArrayHasKey( 'SEK', $parsed );
		$this->assertArrayNotHasKey( 'EUR', $parsed );
		$this->assertCount( 1, $parsed );
	}

	public function test_parse_delegates_validation_to_sanitizer(): void {
		$parsed = $this->field()->parse(
			array(
				array(
					'code'        => 'SEK',
					'manual_rate' => '-5',
				),
			)
		);
		( new Settings() )->save( array_merge( Settings::defaults(), array( 'currencies' => $parsed ) ) );

		$this->assertNull( ( new Settings() )->get_rate( 'SEK' ) );
	}
}
