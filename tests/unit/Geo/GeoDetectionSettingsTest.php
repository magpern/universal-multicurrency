<?php
/**
 * Unit tests for Geo Detection settings normalization.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\GeoDetectionSettings;

/**
 * Exercises defaults and mode sanitization for the geo settings subtree.
 */
final class GeoDetectionSettingsTest extends TestCase {

	public function test_default_array_matches_disabled_empty_configuration(): void {
		$defaults = GeoDetectionSettings::default_array();

		$this->assertFalse( $defaults['enabled'] );
		$this->assertSame( GeoDetectionSettings::MODE_FIRST_VISIT, $defaults['mode'] );
		$this->assertSame( '', $defaults['fallback_currency'] );
		$this->assertTrue( $defaults['allow_wc_geolocation_fallback'] );
		$this->assertSame( array(), $defaults['rules'] );
		$this->assertTrue( $defaults['checkout']['lock_on_entry'] );
		$this->assertFalse( $defaults['checkout']['reevaluate_on_billing_change'] );
		$this->assertFalse( $defaults['checkout']['reevaluate_on_shipping_change'] );
		$this->assertSame( GeoDetectionSettings::PRECEDENCE_BILLING, $defaults['checkout']['country_precedence'] );
	}

	public function test_from_array_round_trips_defaults(): void {
		$settings = GeoDetectionSettings::from_array( GeoDetectionSettings::default_array() );

		$this->assertFalse( $settings->is_enabled() );
		$this->assertSame( GeoDetectionSettings::MODE_FIRST_VISIT, $settings->mode() );
		$this->assertSame( array(), $settings->rules() );
		$this->assertSame( GeoDetectionSettings::default_array(), $settings->to_array() );
	}

	/**
	 * @dataProvider mode_sanitization_cases
	 */
	public function test_sanitize_mode_clamps_unknown_values( mixed $input, string $expected ): void {
		$this->assertSame( $expected, GeoDetectionSettings::sanitize_mode( $input ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function mode_sanitization_cases(): array {
		return array(
			'first_visit preserved'  => array( 'first_visit', GeoDetectionSettings::MODE_FIRST_VISIT ),
			'session preserved'      => array( 'SESSION', GeoDetectionSettings::MODE_SESSION ),
			'until_manual preserved' => array( ' until_manual ', GeoDetectionSettings::MODE_UNTIL_MANUAL ),
			'unknown defaults'       => array( 'always', GeoDetectionSettings::MODE_FIRST_VISIT ),
			'non_string defaults'    => array( 42, GeoDetectionSettings::MODE_FIRST_VISIT ),
		);
	}

	public function test_sanitize_raw_returns_defaults_for_non_array_input(): void {
		$this->assertSame( GeoDetectionSettings::default_array(), GeoDetectionSettings::sanitize_raw( null ) );
		$this->assertSame( GeoDetectionSettings::default_array(), GeoDetectionSettings::sanitize_raw( 'invalid' ) );
	}

	public function test_from_array_sanitizes_invalid_mode_and_currency(): void {
		$settings = GeoDetectionSettings::from_array(
			array(
				'enabled'           => true,
				'mode'              => 'bogus',
				'fallback_currency' => '12',
			)
		);

		$this->assertTrue( $settings->is_enabled() );
		$this->assertSame( GeoDetectionSettings::MODE_FIRST_VISIT, $settings->mode() );
		$this->assertSame( '', $settings->fallback_currency() );
	}
}
