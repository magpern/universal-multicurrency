<?php
/**
 * Unit tests for UgcIntegrationStatus.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\UgcIntegrationStatus;
use UMC\Tests\Support\UniversalGeoContextStub;

/**
 * @covers \UMC\Geo\UgcIntegrationStatus
 *
 * install() is called per-test (never in setUp()) so the two
 * @runInSeparateProcess tests below genuinely never define the stubbed
 * globals in their isolated process — the only way to observe
 * function_exists() === false, since PHP cannot undefine a function once
 * declared in a process.
 */
final class UgcIntegrationStatusTest extends TestCase {

	protected function tearDown(): void {
		UniversalGeoContextStub::reset();

		parent::tearDown();
	}

	public function test_state_is_available_when_functions_exist_and_api_version_meets_minimum(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );

		$status = new UgcIntegrationStatus();

		$this->assertSame( UgcIntegrationStatus::STATE_AVAILABLE, $status->state() );
		$this->assertTrue( $status->is_available() );
	}

	public function test_state_is_misconfigured_when_api_version_is_below_minimum(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 0 );

		$status = new UgcIntegrationStatus();

		$this->assertSame( UgcIntegrationStatus::STATE_MISCONFIGURED, $status->state() );
		$this->assertFalse( $status->is_available() );
	}

	public function test_state_is_available_when_api_version_exceeds_minimum(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 2 );

		$status = new UgcIntegrationStatus();

		$this->assertSame( UgcIntegrationStatus::STATE_AVAILABLE, $status->state() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_state_is_missing_when_functions_are_not_defined(): void {
		$status = new UgcIntegrationStatus();

		$this->assertSame( UgcIntegrationStatus::STATE_MISSING, $status->state() );
		$this->assertFalse( $status->is_available() );
	}

	public function test_api_version_reports_the_stubbed_value(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 3 );

		$status = new UgcIntegrationStatus();

		$this->assertSame( 3, $status->api_version() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_api_version_is_null_when_function_is_not_defined(): void {
		$status = new UgcIntegrationStatus();

		$this->assertNull( $status->api_version() );
	}

	public function test_version_reads_the_ugc_version_constant_for_display_only(): void {
		if ( ! defined( 'UNIVERSAL_GEO_VERSION' ) ) {
			define( 'UNIVERSAL_GEO_VERSION', '1.6.0' );
		}

		$status = new UgcIntegrationStatus();

		$this->assertSame( '1.6.0', $status->version() );
	}

	public function test_version_never_gates_availability(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );

		$status = new UgcIntegrationStatus();

		$this->assertTrue( $status->is_available() );
	}

	public function test_is_simulating_is_false_when_source_is_not_simulation(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );
		UniversalGeoContextStub::set_source( 'cloudflare' );

		$status = new UgcIntegrationStatus();

		$this->assertFalse( $status->is_simulating() );
	}

	public function test_is_simulating_is_true_when_source_is_simulation(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );
		UniversalGeoContextStub::set_source( 'simulation' );

		$status = new UgcIntegrationStatus();

		$this->assertTrue( $status->is_simulating() );
	}

	public function test_is_simulating_is_false_when_ugc_unavailable(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 0 );
		UniversalGeoContextStub::set_source( 'simulation' );

		$status = new UgcIntegrationStatus();

		$this->assertFalse( $status->is_simulating() );
	}

	public function test_deep_links_build_stable_admin_urls(): void {
		$status = new UgcIntegrationStatus();

		$this->assertStringContainsString( 'page=universal-geo-context', $status->overview_url() );
		$this->assertStringContainsString( 'page=universal-geo-context-detection', $status->detection_url() );
		$this->assertStringContainsString( 'page=universal-geo-context-providers', $status->providers_url() );
		$this->assertStringContainsString( 'page=universal-geo-context-trusted-proxies', $status->trusted_proxies_url() );
		$this->assertStringContainsString( 'page=universal-geo-context-diagnostics', $status->diagnostics_url() );
		$this->assertStringContainsString( 'page=universal-geo-context-settings', $status->settings_url() );
	}

	public function test_simulation_url_targets_the_detection_page_simulation_tab(): void {
		$status = new UgcIntegrationStatus();

		$url = $status->simulation_url();

		$this->assertStringContainsString( 'page=universal-geo-context-detection', $url );
		$this->assertStringContainsString( 'tab=simulation', $url );
	}
}
