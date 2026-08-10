<?php
/**
 * Unit tests for GeoCurrencyDecisionService::simulate().
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoDetectionSettings;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Geo\GeoRuleEvaluationResult;
use UMC\Settings;

/**
 * @covers \UMC\Geo\GeoCurrencyDecisionService
 */
final class GeoCurrencyDecisionServiceTest extends TestCase {

	private function service( array $geo ): GeoCurrencyDecisionService {
		$settings = new Settings(
			array(
				'currencies' => array(),
				'geo'        => $geo,
			)
		);

		return new GeoCurrencyDecisionService( new GeoDetectionSettingsRepository( $settings ) );
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private function sweden_rule(): array {
		return array(
			array(
				'id'       => 'rule_00000001',
				'type'     => 'country',
				'value'    => 'SE',
				'currency' => 'SEK',
			),
		);
	}

	public function test_explicit_currency_takes_precedence_and_skips_geo(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'      => 'SE',
				'selectable'        => array( 'SEK', 'USD' ),
				'base_currency'     => 'EUR',
				'explicit_currency' => 'USD',
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'explicit_currency', $result['geo_skip_reason'] );
		$this->assertSame( 'USD', $result['final_currency'] );
	}

	public function test_session_currency_takes_precedence_when_no_explicit_currency(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'     => 'SE',
				'selectable'       => array( 'SEK', 'USD' ),
				'base_currency'    => 'EUR',
				'session_currency' => 'USD',
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'session_currency', $result['geo_skip_reason'] );
		$this->assertSame( 'USD', $result['final_currency'] );
	}

	public function test_cookie_currency_takes_precedence_when_no_explicit_or_session_currency(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'    => 'SE',
				'selectable'      => array( 'SEK', 'USD' ),
				'base_currency'   => 'EUR',
				'cookie_currency' => 'USD',
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'cookie_currency', $result['geo_skip_reason'] );
		$this->assertSame( 'USD', $result['final_currency'] );
	}

	public function test_unselectable_shopper_currencies_do_not_skip_geo(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'      => 'SE',
				'selectable'        => array( 'SEK' ),
				'base_currency'     => 'EUR',
				'explicit_currency' => 'XXX',
				'session_currency'  => 'YYY',
				'cookie_currency'   => 'ZZZ',
			)
		);

		$this->assertFalse( $result['geo_skipped'] );
		$this->assertSame( 'SEK', $result['final_currency'] );
	}

	public function test_checkout_locked_skips_geo_and_uses_base_currency(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'    => 'SE',
				'selectable'      => array( 'SEK' ),
				'base_currency'   => 'EUR',
				'checkout_locked' => true,
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'checkout_locked', $result['geo_skip_reason'] );
		$this->assertSame( 'EUR', $result['final_currency'] );
	}

	public function test_geo_disabled_setting_skips_and_uses_fallback_currency(): void {
		$result = $this->service(
			array(
				'enabled'           => true,
				'rules'             => $this->sweden_rule(),
				'fallback_currency' => 'SEK',
			)
		)->simulate(
			array(
				'country_code'  => 'SE',
				'selectable'    => array( 'SEK' ),
				'base_currency' => 'EUR',
				'geo_enabled'   => false,
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'geo_disabled', $result['geo_skip_reason'] );
		$this->assertSame( 'SEK', $result['final_currency'] );
	}

	public function test_geo_disabled_setting_falls_back_to_base_when_no_fallback_currency_configured(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'  => 'SE',
				'selectable'    => array( 'SEK' ),
				'base_currency' => 'EUR',
				'geo_enabled'   => false,
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'EUR', $result['final_currency'] );
	}

	public function test_matched_rule_produces_an_evaluation_and_final_currency(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'  => 'SE',
				'selectable'    => array( 'SEK' ),
				'base_currency' => 'EUR',
			)
		);

		$this->assertFalse( $result['geo_skipped'] );
		$this->assertInstanceOf( GeoRuleEvaluationResult::class, $result['evaluation'] );
		$this->assertSame( 'SEK', $result['final_currency'] );
		$this->assertFalse( $result['technical_fallback_used'] );
		$this->assertFalse( $result['catch_all_matched'] );
	}

	public function test_missing_country_uses_technical_fallback(): void {
		$result = $this->service(
			array(
				'enabled'           => true,
				'rules'             => $this->sweden_rule(),
				'fallback_currency' => 'SEK',
			)
		)->simulate(
			array(
				'country_code'  => '',
				'selectable'    => array( 'SEK' ),
				'base_currency' => 'EUR',
			)
		);

		$this->assertFalse( $result['geo_skipped'] );
		$this->assertTrue( $result['technical_fallback_used'] );
		$this->assertSame( 'SEK', $result['final_currency'] );
	}

	public function test_missing_country_without_a_fallback_currency_uses_base(): void {
		$result = $this->service(
			array(
				'enabled' => true,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'  => '',
				'selectable'    => array( 'SEK' ),
				'base_currency' => 'EUR',
			)
		);

		$this->assertFalse( $result['geo_skipped'] );
		$this->assertTrue( $result['technical_fallback_used'] );
		$this->assertSame( 'EUR', $result['final_currency'] );
	}

	public function test_geo_enabled_defaults_to_the_stored_setting_when_not_provided(): void {
		$result = $this->service(
			array(
				'enabled' => false,
				'rules'   => $this->sweden_rule(),
			)
		)->simulate(
			array(
				'country_code'  => 'SE',
				'selectable'    => array( 'SEK' ),
				'base_currency' => 'EUR',
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'geo_disabled', $result['geo_skip_reason'] );
	}

	public function test_should_apply_for_mode_is_true_without_a_woocommerce_session(): void {
		$service = $this->service( array( 'enabled' => true ) );

		foreach ( array( GeoDetectionSettings::MODE_FIRST_VISIT, GeoDetectionSettings::MODE_SESSION, GeoDetectionSettings::MODE_UNTIL_MANUAL ) as $mode ) {
			$settings = GeoDetectionSettings::from_array( array( 'mode' => $mode ) );

			$this->assertTrue( $service->should_apply_for_mode( $settings ) );
		}
	}

	public function test_mark_applied_does_not_throw_without_a_woocommerce_session(): void {
		$service  = $this->service( array( 'enabled' => true ) );
		$settings = GeoDetectionSettings::from_array( array( 'mode' => GeoDetectionSettings::MODE_SESSION ) );

		$service->mark_applied( $settings );

		$this->addToAssertionCount( 1 );
	}
}
