<?php
/**
 * Integration tests for the Compatibility -> Cache external cache-state result.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Compatibility;

use UMC\CacheState\CacheStateService;
use UMC\CacheState\CacheStateStore;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityServices;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies the three enrollment/reconciliation states appear in the
 * Compatibility -> Cache category with the documented ids and severities.
 */
final class CompatibilityCacheStateCheckTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		delete_option( CacheStateStore::OPTION );

		parent::tear_down();
	}

	/**
	 * @return array{0: CacheStateService, 1: \UMC\Compatibility\CompatibilityScan}
	 */
	private function scan_with( ?CacheStateStore $store = null ): array {
		$settings = new Settings( array( 'geo' => array( 'enabled' => false ) ) );
		$base     = new Currency( 'EUR', 2 );
		$registry = new CurrencyRegistry( $settings, $base );
		$state    = new RateUpdateState();
		$rates    = new ExchangeRateStore( $settings, $state, $base->code() );
		$detector = new ConflictDetector( new DetectorRegistry(), new WordPressEnvironmentProbe(), new ConflictScorer() );

		$cache_state = new CacheStateService(
			$registry,
			new GeoDetectionSettingsRepository( $settings ),
			$settings,
			$store ?? new CacheStateStore()
		);

		$scanner = CompatibilityServices::scanner( $settings, $rates, $base, $detector, $cache_state );

		return array( $cache_state, $scanner->scan() );
	}

	private function cache_results( \UMC\Compatibility\CompatibilityScan $scan ): array {
		return array_values(
			array_filter(
				$scan->results(),
				static fn( $result ): bool => CompatibilityCategory::CACHE === $result->category()
					&& str_starts_with( $result->id(), 'cache.state_' )
			)
		);
	}

	public function test_not_enrolled_state_appears_as_info(): void {
		[ , $scan ] = $this->scan_with();
		$results    = $this->cache_results( $scan );

		$this->assertCount( 1, $results );
		$this->assertSame( 'cache.state_not_enrolled', $results[0]->id() );
		$this->assertSame( 'info', $results[0]->severity() );
	}

	public function test_reconciled_state_appears_as_info(): void {
		$store       = new CacheStateStore();
		[ $service ] = $this->scan_with( $store );
		$service->acknowledge( $service->report()->state_hash() );

		[ , $scan ] = $this->scan_with( $store );
		$results    = $this->cache_results( $scan );

		$this->assertCount( 1, $results );
		$this->assertSame( 'cache.state_reconciled', $results[0]->id() );
		$this->assertSame( 'info', $results[0]->severity() );
	}

	public function test_reconciliation_required_state_appears_as_warning(): void {
		$store       = new CacheStateStore();
		[ $service ] = $this->scan_with( $store );
		$service->acknowledge( $service->report()->state_hash() );

		// Enroll against geo=false, then scan again with geo=true using the same store.
		$settings = new Settings(
			array(
				'currencies' => array(),
				'geo'        => array( 'enabled' => true ),
			)
		);
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$state    = new RateUpdateState();
		$rates    = new ExchangeRateStore( $settings, $state, 'EUR' );
		$detector = new ConflictDetector( new DetectorRegistry(), new WordPressEnvironmentProbe(), new ConflictScorer() );
		$mutated  = new CacheStateService( $registry, new GeoDetectionSettingsRepository( $settings ), $settings, $store );

		$scanner = CompatibilityServices::scanner( $settings, $rates, new Currency( 'EUR', 2 ), $detector, $mutated );
		$results = $this->cache_results( $scanner->scan() );

		$this->assertCount( 1, $results );
		$this->assertSame( 'cache.state_reconciliation_required', $results[0]->id() );
		$this->assertSame( 'warning', $results[0]->severity() );
	}

	public function test_scanner_without_cache_state_service_omits_the_check(): void {
		$settings = new Settings();
		$base     = new Currency( 'EUR', 2 );
		$state    = new RateUpdateState();
		$rates    = new ExchangeRateStore( $settings, $state, $base->code() );
		$detector = new ConflictDetector( new DetectorRegistry(), new WordPressEnvironmentProbe(), new ConflictScorer() );

		$scanner = CompatibilityServices::scanner( $settings, $rates, $base, $detector );
		$results = $this->cache_results( $scanner->scan() );

		$this->assertSame( array(), $results );
	}
}
