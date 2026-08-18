<?php
/**
 * Integration tests for the Site Health external cache-state readiness test.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Diagnostics;

use UMC\CacheState\CacheStateService;
use UMC\CacheState\CacheStateStore;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\SiteHealthReport;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Exercises the registered `umc_cache_state` direct test through
 * `site_status_tests`, across every state the implementation distinguishes,
 * plus the five cache_state_* debug fields.
 */
final class SiteHealthCacheStateIntegrationTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		remove_all_filters( 'site_status_tests' );
		remove_all_filters( 'debug_information' );

		delete_option( Settings::OPTION );
		delete_option( CacheStateStore::OPTION );

		parent::tearDown();
	}

	private function service( ?CacheStateStore $store = null ): CacheStateService {
		$settings = new Settings( array( 'geo' => array( 'enabled' => false ) ) );
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );

		return new CacheStateService(
			$registry,
			new GeoDetectionSettingsRepository( $settings ),
			$settings,
			$store ?? new CacheStateStore()
		);
	}

	private function as_activate_plugins_user(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * @param CacheStateService|null $cache_state Cache-state service to wire, or null to simulate wiring failure.
	 *
	 * @return array<string, mixed>
	 */
	private function run_cache_state_test( ?CacheStateService $cache_state ): array {
		$this->as_activate_plugins_user();
		( new Diagnostics( null, null, null, null, $cache_state ) )->register();

		$tests = apply_filters( 'site_status_tests', array() );
		$this->assertArrayHasKey( SiteHealthReport::TEST_CACHE_STATE, $tests['direct'] );

		return (array) call_user_func( $tests['direct'][ SiteHealthReport::TEST_CACHE_STATE ]['test'] );
	}

	public function test_cache_state_test_is_registered_for_activate_plugins_user(): void {
		$this->as_activate_plugins_user();
		( new Diagnostics( null, null, null, null, $this->service() ) )->register();

		$tests = apply_filters( 'site_status_tests', array() );

		$this->assertArrayHasKey( SiteHealthReport::TEST_CACHE_STATE, $tests['direct'] );
		$this->assertIsCallable( $tests['direct'][ SiteHealthReport::TEST_CACHE_STATE ]['test'] );
	}

	public function test_cache_state_test_is_not_registered_without_activate_plugins(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		( new Diagnostics( null, null, null, null, $this->service() ) )->register();

		$tests = apply_filters( 'site_status_tests', array() );

		$this->assertArrayNotHasKey( SiteHealthReport::TEST_CACHE_STATE, $tests['direct'] ?? array() );
	}

	public function test_never_enrolled_reports_good_not_enrolled_while_raw_field_stays_true(): void {
		$service = $this->service();

		$result = $this->run_cache_state_test( $service );

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'not enrolled', strtolower( (string) $result['description'] ) );

		// Raw field, read independently, proves display and machine state were not conflated.
		$this->assertTrue( $service->report()->reconciliation_required() );
	}

	public function test_enrolled_and_reconciled_reports_good(): void {
		$store   = new CacheStateStore();
		$service = $this->service( $store );
		$service->acknowledge( $service->report()->state_hash() );

		$result = $this->run_cache_state_test( $this->service( $store ) );

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'reconciled', strtolower( (string) $result['label'] ) );
	}

	public function test_enrolled_and_mismatched_reports_recommended(): void {
		$store   = new CacheStateStore();
		$service = $this->service( $store );
		$service->acknowledge( $service->report()->state_hash() );

		// Mutate geo after acknowledgement.
		$settings = new Settings(
			array(
				'currencies' => array(),
				'geo'        => array( 'enabled' => true ),
			)
		);
		$mutated  = new CacheStateService(
			new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) ),
			new GeoDetectionSettingsRepository( $settings ),
			$settings,
			$store
		);

		$result = $this->run_cache_state_test( $mutated );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'reconciliation required', strtolower( (string) $result['label'] ) );
	}

	public function test_unavailable_service_reports_critical_never_good(): void {
		$result = $this->run_cache_state_test( null );

		$this->assertSame( 'critical', $result['status'] );
	}

	public function test_debug_fields_report_raw_reconciliation_required_even_when_not_enrolled(): void {
		$this->as_activate_plugins_user();
		( new Diagnostics( null, null, null, null, $this->service() ) )->register();

		$fields = apply_filters( 'debug_information', array() )[ SiteHealthReport::SECTION ]['fields'];

		$this->assertArrayHasKey( 'cache_state_hash', $fields );
		$this->assertArrayHasKey( 'cache_state_acknowledged_hash', $fields );
		$this->assertArrayHasKey( 'cache_state_monitoring_enrolled', $fields );
		$this->assertArrayHasKey( 'cache_state_reconciliation_required', $fields );
		$this->assertArrayHasKey( 'cache_state_contract_version', $fields );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $fields['cache_state_hash']['value'] );
		$this->assertSame( '', $fields['cache_state_acknowledged_hash']['value'] );
		$this->assertSame( 'No', $fields['cache_state_monitoring_enrolled']['value'] );
		$this->assertSame( 'Yes', $fields['cache_state_reconciliation_required']['value'] );
		$this->assertSame( '1', $fields['cache_state_contract_version']['value'] );
	}
}
