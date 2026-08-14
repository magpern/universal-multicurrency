<?php
/**
 * Unit tests for the pure Settings::sanitize()/::defaults() logic.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Settings;

/**
 * Tests the forgiving, WordPress-free sanitization of the settings structure.
 */
final class SettingsSanitizeTest extends TestCase {

	public function test_defaults_shape(): void {
		$this->assertSame(
			array(
				'schema_version'       => Settings::SCHEMA_VERSION,
				'rate_mode'            => Settings::RATE_MODE_MANUAL,
				'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
				'rate_update_interval' => Settings::DEFAULT_RATE_INTERVAL,
				'rate_max_age_hours'   => Settings::DEFAULT_RATE_MAX_AGE_HOURS,
				'currencies'           => array(),
				'display'              => \UMC\Display\SwitcherSettings::default_array(),
				'checkout'             => \UMC\Checkout\CheckoutSettings::default_array(),
				'geo'                  => \UMC\Geo\GeoDetectionSettings::default_array(),
			),
			Settings::defaults()
		);
		$this->assertSame( Settings::SCHEMA_VERSION, Settings::defaults()['schema_version'] );
	}

	public function test_well_formed_input_is_preserved(): void {
		$clean = Settings::sanitize(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'         => true,
						'symbol'          => 'kr',
						'position'        => 'right_space',
						'decimals'        => 2,
						'rate'            => '11.50',
						'rate_updated_at' => 1753440000,
					),
				),
			)
		);

		$this->assertSame(
			array(
				'enabled'             => true,
				'symbol'              => 'kr',
				'position'            => 'right_space',
				'decimals'            => 2,
				'manual_rate'         => '11.50',
				'provider_rate'       => '',
				'merchant_adjustment' => '0',
				'rate_mode'           => '',
				'rate_updated_at'     => 1753440000,
			),
			$clean['currencies']['SEK']
		);
		$this->assertSame( Settings::SCHEMA_VERSION, $clean['schema_version'] );
	}

	public function test_lowercase_code_is_uppercased(): void {
		$clean = Settings::sanitize( array( 'currencies' => array( 'sek' => array( 'rate' => '11.5' ) ) ) );
		$this->assertArrayHasKey( 'SEK', $clean['currencies'] );
		$this->assertArrayNotHasKey( 'sek', $clean['currencies'] );
	}

	/**
	 * @dataProvider malformed_codes
	 */
	public function test_malformed_code_rows_are_dropped( string $code ): void {
		$clean = Settings::sanitize( array( 'currencies' => array( $code => array( 'rate' => '2' ) ) ) );
		$this->assertSame( array(), $clean['currencies'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function malformed_codes(): array {
		return array(
			'short'  => array( 'EU' ),
			'long'   => array( 'EURO' ),
			'digits' => array( 'E1R' ),
			'empty'  => array( '' ),
		);
	}

	/**
	 * @dataProvider decimals_cases
	 */
	public function test_invalid_decimals_fall_back_to_default( mixed $input, int $expected ): void {
		$clean = Settings::sanitize(
			array(
				'currencies' => array(
					'USD' => array(
						'decimals' => $input,
						'rate'     => '1',
					),
				),
			)
		);
		$this->assertSame( $expected, $clean['currencies']['USD']['decimals'] );
	}

	/**
	 * @return array<string, array{0: mixed, 1: int}>
	 */
	public static function decimals_cases(): array {
		return array(
			'negative default to 2'  => array( -3, 2 ),
			'too large default to 2' => array( 9, 2 ),
			'valid zero preserved'   => array( 0, 0 ),
			'valid two preserved'    => array( 2, 2 ),
			'valid four preserved'   => array( 4, 4 ),
		);
	}

	public function test_missing_and_non_numeric_decimals_default_to_two(): void {
		$missing = Settings::sanitize( array( 'currencies' => array( 'USD' => array( 'rate' => '1' ) ) ) );
		$this->assertSame( 2, $missing['currencies']['USD']['decimals'] );

		$non_numeric = Settings::sanitize(
			array(
				'currencies' => array(
					'USD' => array(
						'decimals' => 'x',
						'rate'     => '1',
					),
				),
			)
		);
		$this->assertSame( 2, $non_numeric['currencies']['USD']['decimals'] );
	}

	public function test_bad_position_falls_back_to_left(): void {
		$clean = Settings::sanitize(
			array(
				'currencies' => array(
					'USD' => array(
						'position' => 'middle',
						'rate'     => '1',
					),
				),
			)
		);
		$this->assertSame( 'left', $clean['currencies']['USD']['position'] );
	}

	public function test_enabled_is_coerced_to_bool_and_defaults_true(): void {
		$explicit = Settings::sanitize(
			array(
				'currencies' => array(
					'USD' => array(
						'enabled' => 0,
						'rate'    => '1',
					),
				),
			)
		);
		$this->assertFalse( $explicit['currencies']['USD']['enabled'] );

		$missing = Settings::sanitize( array( 'currencies' => array( 'USD' => array( 'rate' => '1' ) ) ) );
		$this->assertTrue( $missing['currencies']['USD']['enabled'] );
	}

	/**
	 * @dataProvider rate_cases
	 */
	public function test_rate_normalization( mixed $input, string $expected ): void {
		$clean = Settings::sanitize( array( 'currencies' => array( 'USD' => array( 'rate' => $input ) ) ) );
		$this->assertSame( $expected, $clean['currencies']['USD']['manual_rate'] );
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function rate_cases(): array {
		return array(
			'clean decimal string' => array( '11.50', '11.50' ),
			'integer string'       => array( '12', '12' ),
			'float'                => array( 147.3, '147.3' ),
			'integer'              => array( 12, '12' ),
			'exponent normalized'  => array( '1e3', '1000' ),
			'zero blanked'         => array( '0', '' ),
			'negative blanked'     => array( '-5', '' ),
			'non-numeric blanked'  => array( 'abc', '' ),
			'thousands blanked'    => array( '1,150.50', '' ),
			'empty blanked'        => array( '', '' ),
		);
	}

	public function test_row_with_invalid_rate_is_kept_with_blank_rate(): void {
		$clean = Settings::sanitize(
			array(
				'currencies' => array(
					'USD' => array(
						'enabled' => true,
						'rate'    => '-1',
					),
				),
			)
		);
		$this->assertArrayHasKey( 'USD', $clean['currencies'] );
		$this->assertSame( '', $clean['currencies']['USD']['manual_rate'] );
	}

	public function test_non_array_input_returns_defaults(): void {
		$this->assertSame( Settings::defaults(), Settings::sanitize( 'nonsense' ) );
		$this->assertSame( Settings::defaults(), Settings::sanitize( null ) );
		$this->assertSame( Settings::defaults(), Settings::sanitize( array() ) );
	}

	public function test_unknown_keys_are_stripped(): void {
		$clean = Settings::sanitize(
			array(
				'currencies'    => array(
					'USD' => array(
						'rate' => '1',
						'evil' => 'x',
					),
				),
				'injected_root' => 'x',
			)
		);
		$this->assertArrayNotHasKey( 'injected_root', $clean );
		$this->assertArrayNotHasKey( 'evil', $clean['currencies']['USD'] );
		$this->assertSame(
			array( 'enabled', 'symbol', 'position', 'decimals', 'manual_rate', 'provider_rate', 'merchant_adjustment', 'rate_mode', 'rate_updated_at' ),
			array_keys( $clean['currencies']['USD'] )
		);
	}

	public function test_schema_version_is_forced(): void {
		$clean = Settings::sanitize(
			array(
				'schema_version' => 999,
				'currencies'     => array(),
			)
		);
		$this->assertSame( Settings::SCHEMA_VERSION, $clean['schema_version'] );
	}

	public function test_symbol_tags_are_stripped(): void {
		$clean = Settings::sanitize(
			array(
				'currencies' => array(
					'USD' => array(
						'symbol' => '<b>$</b>',
						'rate'   => '1',
					),
				),
			)
		);
		$this->assertSame( '$', $clean['currencies']['USD']['symbol'] );
	}

	public function test_in_memory_constructor_exposes_sanitized_getters(): void {
		$settings = new Settings(
			array(
				'currencies' => array(
					'sek' => array(
						'rate'     => '11.5',
						'decimals' => 2,
					),
				),
			)
		);
		$this->assertSame( '11.5', $settings->get_rate( 'SEK' ) );
		$this->assertSame( '11.5', $settings->get_rate( 'sek' ) );
		$this->assertNull( $settings->get_rate( 'JPY' ) );
		$this->assertNotNull( $settings->get_currency_config( 'SEK' ) );
	}

	public function test_blank_rate_reads_as_null(): void {
		$settings = new Settings( array( 'currencies' => array( 'USD' => array( 'rate' => 'bad' ) ) ) );
		$this->assertNull( $settings->get_rate( 'USD' ) );
	}

	public function test_checkout_settle_base_mode_migrates_to_store(): void {
		$clean = Settings::sanitize_checkout(
			array(
				'mode'        => 'settle_base',
				'show_notice' => '1',
			)
		);

		$this->assertSame( \UMC\Checkout\CheckoutSettings::MODE_STORE, $clean['mode'] );
		$this->assertTrue( $clean['show_notice'] );
	}
}
