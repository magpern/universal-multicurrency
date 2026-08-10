<?php
/**
 * Unit tests for GeoDetectionSettingsParser.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use UMC\Admin\GeoDetectionSettingsParser;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Settings;

/**
 * @covers \UMC\Admin\GeoDetectionSettingsParser
 */
final class GeoDetectionSettingsParserTest extends TestCase {

	protected function tearDown(): void {
		unset( $_POST['umc_geo'], $_POST['umc_geo_rules_order'] );

		parent::tearDown();
	}

	public function test_missing_umc_geo_post_leaves_current_settings_untouched(): void {
		$current = array(
			'enabled'                       => true,
			'mode'                          => 'session',
			'fallback_currency'             => 'EUR',
			'allow_wc_geolocation_fallback' => false,
			'rules'                         => array(),
			'checkout'                      => array(
				'lock_on_entry'                 => false,
				'reevaluate_on_billing_change'  => true,
				'reevaluate_on_shipping_change' => false,
				'country_precedence'            => 'shipping',
			),
		);

		$parser = $this->parser( $current );
		$result = $parser->parse_post();

		$this->assertNotNull( $result );
		$this->assertSame( $current, $result['geo'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_full_submission_round_trips_every_field_unchanged(): void {
		$_POST['umc_geo']             = array(
			'enabled'                       => '1',
			'mode'                          => 'until_manual',
			'fallback_currency'             => 'EUR',
			'allow_wc_geolocation_fallback' => '1',
			'rules'                         => array(
				'rule_00000001' => array(
					'id'       => 'rule_00000001',
					'type'     => 'country',
					'value'    => 'se',
					'currency' => 'eur',
				),
				'rule_00000002' => array(
					'id'       => 'rule_00000002',
					'type'     => 'other',
					'value'    => '',
					'currency' => 'eur',
				),
			),
			'checkout'                      => array(
				'lock_on_entry'                 => '1',
				'reevaluate_on_billing_change'  => '1',
				'reevaluate_on_shipping_change' => '',
				'country_precedence'            => 'shipping',
			),
		);
		$_POST['umc_geo_rules_order'] = array( 'rule_00000001', 'rule_00000002' );

		$parser = $this->parser( self::default_geo() );
		$result = $parser->parse_post();

		$this->assertNotNull( $result );
		$this->assertTrue( $result['geo']['enabled'] );
		$this->assertSame( 'until_manual', $result['geo']['mode'] );
		$this->assertSame( 'EUR', $result['geo']['fallback_currency'] );
		$this->assertTrue( $result['geo']['allow_wc_geolocation_fallback'] );
		$this->assertSame(
			array(
				array(
					'id'       => 'rule_00000001',
					'type'     => 'country',
					'value'    => 'SE',
					'currency' => 'EUR',
				),
				array(
					'id'       => 'rule_00000002',
					'type'     => 'other',
					'value'    => '',
					'currency' => 'EUR',
				),
			),
			$result['geo']['rules']
		);
		$this->assertTrue( $result['geo']['checkout']['lock_on_entry'] );
		$this->assertTrue( $result['geo']['checkout']['reevaluate_on_billing_change'] );
		$this->assertFalse( $result['geo']['checkout']['reevaluate_on_shipping_change'] );
		$this->assertSame( 'shipping', $result['geo']['checkout']['country_precedence'] );
	}

	public function test_rules_order_field_controls_final_rule_order(): void {
		$_POST['umc_geo']             = array(
			'enabled' => '1',
			'mode'    => 'first_visit',
			'rules'   => array(
				'rule_00000002' => array(
					'id'       => 'rule_00000002',
					'type'     => 'other',
					'value'    => '',
					'currency' => 'eur',
				),
				'rule_00000001' => array(
					'id'       => 'rule_00000001',
					'type'     => 'country',
					'value'    => 'de',
					'currency' => 'eur',
				),
			),
		);
		$_POST['umc_geo_rules_order'] = array( 'rule_00000001', 'rule_00000002' );

		$parser = $this->parser( self::default_geo() );
		$result = $parser->parse_post();

		$this->assertNotNull( $result );
		$this->assertSame( 'rule_00000001', $result['geo']['rules'][0]['id'] );
		$this->assertSame( 'rule_00000002', $result['geo']['rules'][1]['id'] );
	}

	public function test_missing_boolean_fields_default_the_hidden_zero_companion_to_false(): void {
		$_POST['umc_geo'] = array(
			'mode' => 'first_visit',
		);

		$parser = $this->parser( self::default_geo() );
		$result = $parser->parse_post();

		$this->assertNotNull( $result );
		$this->assertFalse( $result['geo']['enabled'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_geo(): array {
		return array(
			'enabled'                       => false,
			'mode'                          => 'first_visit',
			'fallback_currency'             => '',
			'allow_wc_geolocation_fallback' => true,
			'rules'                         => array(),
			'checkout'                      => array(
				'lock_on_entry'                 => true,
				'reevaluate_on_billing_change'  => false,
				'reevaluate_on_shipping_change' => false,
				'country_precedence'            => 'billing',
			),
		);
	}

	/**
	 * @param array<string, mixed> $geo Current geo subtree.
	 */
	private function parser( array $geo ): GeoDetectionSettingsParser {
		$settings = new Settings(
			array(
				'currencies' => array(),
				'geo'        => $geo,
			)
		);
		$base     = new Currency( 'EUR', 2 );

		return new GeoDetectionSettingsParser( $settings, new CurrencyRegistry( $settings, $base ) );
	}
}
