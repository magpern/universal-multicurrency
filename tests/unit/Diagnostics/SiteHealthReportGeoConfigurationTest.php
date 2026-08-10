<?php
/**
 * Unit tests for SiteHealthReport::run_geo_configuration_test().
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\SiteHealthReport;
use UMC\Settings;
use UMC\Tests\Support\UniversalGeoContextStub;
use UMC\Tests\Unit\Doubles\ArrayEnvironmentProbe;
use UMC\Tests\Unit\Doubles\StaticDetectorRegistry;

/**
 * @covers \UMC\Diagnostics\SiteHealthReport
 */
final class SiteHealthReportGeoConfigurationTest extends TestCase {

	protected function tearDown(): void {
		UniversalGeoContextStub::reset();

		parent::tearDown();
	}

	public function test_reports_good_when_settings_not_initialized(): void {
		$report = $this->report( null );

		$result = $report->run_geo_configuration_test();

		$this->assertSame( 'good', $result['status'] );
	}

	public function test_reports_good_when_disabled(): void {
		$report = $this->report( $this->settings( array( 'enabled' => false ) ) );

		$this->assertSame( 'good', $report->run_geo_configuration_test()['status'] );
	}

	public function test_reports_recommended_when_enabled_without_rules(): void {
		$report = $this->report(
			$this->settings(
				array(
					'enabled' => true,
					'rules'   => array(),
				)
			)
		);

		$this->assertSame( 'recommended', $report->run_geo_configuration_test()['status'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_reports_critical_when_no_provider_is_available_at_all(): void {
		$settings = $this->settings(
			array(
				'enabled'                       => true,
				'rules'                         => $this->one_rule(),
				'allow_wc_geolocation_fallback' => false,
			)
		);

		$this->assertSame( 'critical', $this->report( $settings )->run_geo_configuration_test()['status'] );
	}

	public function test_reports_recommended_when_only_the_woocommerce_fallback_is_available(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 0 );

		$settings = $this->settings(
			array(
				'enabled'                       => true,
				'rules'                         => $this->one_rule(),
				'allow_wc_geolocation_fallback' => true,
			)
		);

		$this->assertSame( 'recommended', $this->report( $settings )->run_geo_configuration_test()['status'] );
	}

	public function test_reports_good_when_ugc_is_available(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );

		$settings = $this->settings(
			array(
				'enabled'                       => true,
				'rules'                         => $this->one_rule(),
				'allow_wc_geolocation_fallback' => false,
			)
		);

		$this->assertSame( 'good', $this->report( $settings )->run_geo_configuration_test()['status'] );
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function one_rule(): array {
		return array(
			array(
				'id'       => 'rule_00000001',
				'type'     => 'country',
				'value'    => 'SE',
				'currency' => 'EUR',
			),
		);
	}

	private function settings( array $geo ): Settings {
		// Settings::sanitize() only processes 'display'/'checkout'/'geo' once a
		// 'currencies' array key is present — without it, sanitize() bails out
		// to pure defaults before ever reaching the geo subtree.
		return new Settings(
			array(
				'currencies' => array(),
				'geo'        => $geo,
			)
		);
	}

	private function report( ?Settings $settings ): SiteHealthReport {
		$detector = new ConflictDetector(
			new StaticDetectorRegistry( array() ),
			new ArrayEnvironmentProbe( array() ),
			new ConflictScorer()
		);

		return new SiteHealthReport( $detector, null, $settings, null );
	}
}
