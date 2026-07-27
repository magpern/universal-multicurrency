<?php
/**
 * Integration tests for rate_updated_at semantics on admin saves.
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
 * Verifies merchant rate-input edits bump rate_updated_at.
 */
final class CurrencyTableFieldRateTimestampTest extends WP_UnitTestCase {

	private const STORED_AT = 1_700_000_000;

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_manual_rate_change_bumps_timestamp(): void {
		$field  = $this->field_with_sek();
		$parsed = $field->parse(
			array(
				array(
					'code'                => 'SEK',
					'manual_rate'         => '12.00',
					'merchant_adjustment' => '0',
					'rate_mode'           => '',
				),
			)
		);

		$this->assertGreaterThan( self::STORED_AT, $parsed['SEK']['rate_updated_at'] );
		$this->assertLessThanOrEqual( time(), $parsed['SEK']['rate_updated_at'] );
	}

	public function test_merchant_adjustment_change_bumps_timestamp(): void {
		$field  = $this->field_with_sek();
		$parsed = $field->parse(
			array(
				array(
					'code'                => 'SEK',
					'manual_rate'         => '11.50',
					'merchant_adjustment' => '2.5',
					'rate_mode'           => '',
				),
			)
		);

		$this->assertGreaterThan( self::STORED_AT, $parsed['SEK']['rate_updated_at'] );
	}

	public function test_rate_mode_change_bumps_timestamp(): void {
		$field  = $this->field_with_sek();
		$parsed = $field->parse(
			array(
				array(
					'code'                => 'SEK',
					'manual_rate'         => '11.50',
					'merchant_adjustment' => '0',
					'rate_mode'           => Settings::RATE_MODE_AUTOMATIC,
				),
			)
		);

		$this->assertGreaterThan( self::STORED_AT, $parsed['SEK']['rate_updated_at'] );
	}

	public function test_unrelated_save_preserves_timestamp(): void {
		$field  = $this->field_with_sek();
		$parsed = $field->parse(
			array(
				array(
					'code'                => 'SEK',
					'symbol'              => 'updated',
					'manual_rate'         => '11.50',
					'merchant_adjustment' => '0',
					'rate_mode'           => '',
				),
			)
		);

		$this->assertSame( self::STORED_AT, $parsed['SEK']['rate_updated_at'] );
	}

	private function field_with_sek(): CurrencyTableField {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'             => true,
						'symbol'              => 'kr',
						'manual_rate'         => '11.50',
						'merchant_adjustment' => '0',
						'rate_mode'           => '',
						'rate_updated_at'     => self::STORED_AT,
					),
				),
			)
		);

		return new CurrencyTableField(
			$settings,
			new Currency( 'EUR', 2 ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
		);
	}
}
