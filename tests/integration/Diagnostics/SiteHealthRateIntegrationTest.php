<?php
/**
 * Integration tests for the Milestone 8 Site Health rate diagnostics.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Diagnostics\Diagnostics;
use UMC\Diagnostics\SiteHealthReport;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Rates\Scheduler;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Exercises the registered `umc_rate_health` direct test through
 * `site_status_tests`, across every state the implementation distinguishes.
 */
final class SiteHealthRateIntegrationTest extends WP_UnitTestCase {

	private const HOUR = 3600;

	/**
	 * Provider rate value that must never reach a diagnostic surface.
	 */
	private const SECRET_RATE = '11.987654';

	/**
	 * Operational error string that must never reach a diagnostic surface.
	 */
	private const SECRET_ERROR = 'provider_unavailable_token_abc123';

	protected function tearDown(): void {
		remove_all_filters( 'site_status_tests' );
		remove_all_filters( 'debug_information' );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Scheduler::HOOK );
		}

		delete_option( Settings::OPTION );
		delete_option( RateUpdateState::OPTION );

		parent::tearDown();
	}

	public function test_rate_health_test_is_registered_for_activate_plugins_user(): void {
		$this->as_activate_plugins_user();
		$this->boot_diagnostics( $this->settings_for( array() ), new RateUpdateState() );

		$tests = apply_filters( 'site_status_tests', array() );

		$this->assertArrayHasKey( 'direct', $tests );
		$this->assertArrayHasKey( SiteHealthReport::TEST_RATE_HEALTH, $tests['direct'] );
		$this->assertIsCallable( $tests['direct'][ SiteHealthReport::TEST_RATE_HEALTH ]['test'] );
	}

	public function test_rate_health_test_is_not_registered_without_activate_plugins(): void {
		wp_set_current_user(
			self::factory()->user->create( array( 'role' => 'shop_manager' ) )
		);
		$this->boot_diagnostics( $this->settings_for( array() ), new RateUpdateState() );

		$tests = apply_filters( 'site_status_tests', array() );

		$this->assertArrayNotHasKey( SiteHealthReport::TEST_RATE_HEALTH, $tests['direct'] ?? array() );
	}

	public function test_healthy_automatic_rates_report_good(): void {
		$this->schedule_recurring_update();

		$result = $this->run_rate_health_test(
			$this->settings_for(
				array(
					'SEK' => $this->currency_row( self::SECRET_RATE, time() - self::HOUR ),
				)
			),
			$this->state_for( array( 'SEK' => RateUpdateState::STATUS_SUCCESS ) )
		);

		$this->assertSame( SiteHealthReport::TEST_RATE_HEALTH, $result['test'] );
		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'green', $result['badge']['color'] );
	}

	public function test_no_successful_fetch_yet_reports_good_without_stale_or_failure_claim(): void {
		$this->schedule_recurring_update();

		$result = $this->run_rate_health_test(
			$this->settings_for(
				array(
					'SEK' => $this->currency_row( '', 0 ),
				)
			),
			new RateUpdateState()
		);

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'green', $result['badge']['color'] );
		$this->assertStringNotContainsString( 'exceed the configured maximum age', (string) $result['description'] );
	}

	public function test_automatic_mode_without_a_scheduled_update_is_critical(): void {
		$result = $this->run_rate_health_test(
			$this->settings_for(
				array(
					'SEK' => $this->currency_row( self::SECRET_RATE, time() - self::HOUR ),
				)
			),
			$this->state_for( array( 'SEK' => RateUpdateState::STATUS_SUCCESS ) )
		);

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'red', $result['badge']['color'] );
		$this->assertStringContainsString( 'scheduled', strtolower( (string) $result['description'] ) );
	}

	public function test_one_stale_currency_is_recommended(): void {
		$this->schedule_recurring_update();

		$result = $this->run_rate_health_test(
			$this->settings_for(
				array(
					'SEK' => $this->currency_row( self::SECRET_RATE, time() - ( 72 * self::HOUR ) ),
				)
			),
			$this->state_for( array( 'SEK' => RateUpdateState::STATUS_SUCCESS ) )
		);

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'orange', $result['badge']['color'] );
	}

	public function test_three_stale_currencies_escalate_to_critical(): void {
		$this->schedule_recurring_update();
		$stale = time() - ( 72 * self::HOUR );

		$result = $this->run_rate_health_test(
			$this->settings_for(
				array(
					'SEK' => $this->currency_row( self::SECRET_RATE, $stale ),
					'NOK' => $this->currency_row( '11.1', $stale ),
					'DKK' => $this->currency_row( '7.4', $stale ),
				)
			),
			$this->state_for(
				array(
					'SEK' => RateUpdateState::STATUS_SUCCESS,
					'NOK' => RateUpdateState::STATUS_SUCCESS,
					'DKK' => RateUpdateState::STATUS_SUCCESS,
				)
			)
		);

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( '3', (string) $result['description'] );
	}

	public function test_recorded_fetch_failure_is_critical_and_outranks_staleness(): void {
		$this->schedule_recurring_update();

		$result = $this->run_rate_health_test(
			$this->settings_for(
				array(
					'SEK' => $this->currency_row( self::SECRET_RATE, time() - ( 72 * self::HOUR ) ),
				)
			),
			$this->state_for( array( 'SEK' => RateUpdateState::STATUS_FAILED ), self::SECRET_ERROR )
		);

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'fetch', strtolower( (string) $result['description'] ) );
		$this->assertStringNotContainsString( 'stale', strtolower( (string) $result['description'] ) );
	}

	public function test_manual_mode_without_automatic_currencies_is_good_and_requires_no_schedule(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_MANUAL,
				'currencies' => array(
					'SEK' => array(
						'enabled'         => true,
						'manual_rate'     => self::SECRET_RATE,
						'rate_updated_at' => time() - ( 500 * self::HOUR ),
					),
				),
			)
		);

		$result = $this->run_rate_health_test( $settings, new RateUpdateState() );

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringNotContainsString( 'scheduled', strtolower( (string) $result['description'] ) );
	}

	public function test_uninitialized_rate_dependencies_degrade_without_error(): void {
		$this->as_activate_plugins_user();
		( new Diagnostics() )->register();

		$tests    = apply_filters( 'site_status_tests', array() );
		$result   = (array) call_user_func( $tests['direct'][ SiteHealthReport::TEST_RATE_HEALTH ]['test'] );
		$expected = SiteHealthReport::TEST_RATE_HEALTH;

		$this->assertSame( $expected, $result['test'] );
		$this->assertSame( 'good', $result['status'] );
	}

	public function test_rate_diagnostics_never_expose_rate_values_or_error_details(): void {
		$this->schedule_recurring_update();

		$settings = $this->settings_for(
			array(
				'SEK' => $this->currency_row( self::SECRET_RATE, time() - ( 72 * self::HOUR ) ),
			)
		);
		$state    = $this->state_for( array( 'SEK' => RateUpdateState::STATUS_FAILED ), self::SECRET_ERROR );

		$result = $this->run_rate_health_test( $settings, $state );

		$encoded = (string) wp_json_encode( $result );
		$this->assertStringNotContainsString( self::SECRET_RATE, $encoded );
		$this->assertStringNotContainsString( self::SECRET_ERROR, $encoded );
		$this->assertStringNotContainsString( 'api.frankfurter.dev', $encoded );
		$this->assertStringNotContainsString( 'etag', strtolower( $encoded ) );
	}

	public function test_debug_section_reports_rate_counters_without_rate_values(): void {
		$this->as_activate_plugins_user();

		$settings = $this->settings_for(
			array(
				'SEK' => $this->currency_row( self::SECRET_RATE, time() - ( 72 * self::HOUR ) ),
			)
		);
		$settings->save( $settings->get() );

		$this->boot_diagnostics( $settings, $this->state_for( array( 'SEK' => RateUpdateState::STATUS_SUCCESS ) ) );

		$fields = apply_filters( 'debug_information', array() )[ SiteHealthReport::SECTION ]['fields'];

		$this->assertArrayHasKey( 'stale_automatic_rates', $fields );
		$this->assertArrayHasKey( 'oldest_automatic_rate_age', $fields );
		$this->assertSame( '1', $fields['stale_automatic_rates']['value'] );
		$this->assertGreaterThanOrEqual( 72, (int) $fields['oldest_automatic_rate_age']['value'] );
		$this->assertStringNotContainsString( self::SECRET_RATE, (string) wp_json_encode( $fields ) );
	}

	/**
	 * Runs the registered rate-health direct test for the supplied stores.
	 *
	 * @param Settings        $settings Merchant settings store.
	 * @param RateUpdateState $state    Operational rate state store.
	 *
	 * @return array<string, mixed>
	 */
	private function run_rate_health_test( Settings $settings, RateUpdateState $state ): array {
		$this->as_activate_plugins_user();
		$this->boot_diagnostics( $settings, $state );

		$tests = apply_filters( 'site_status_tests', array() );
		$this->assertArrayHasKey( SiteHealthReport::TEST_RATE_HEALTH, $tests['direct'] );

		return (array) call_user_func( $tests['direct'][ SiteHealthReport::TEST_RATE_HEALTH ]['test'] );
	}

	/**
	 * Registers the production diagnostics surfaces with rate dependencies.
	 *
	 * @param Settings        $settings Merchant settings store.
	 * @param RateUpdateState $state    Operational rate state store.
	 */
	private function boot_diagnostics( Settings $settings, RateUpdateState $state ): void {
		$store = new ExchangeRateStore( $settings, $state, 'EUR', 'test-lock' );

		( new Diagnostics( null, $settings, $store ) )->register();
	}

	/**
	 * Builds a globally automatic settings store from currency rows.
	 *
	 * @param array<string, array<string, mixed>> $currencies Currency rows keyed by code.
	 */
	private function settings_for( array $currencies ): Settings {
		return new Settings(
			array(
				'rate_mode'          => Settings::RATE_MODE_AUTOMATIC,
				'rate_max_age_hours' => Settings::DEFAULT_RATE_MAX_AGE_HOURS,
				'currencies'         => $currencies,
			)
		);
	}

	/**
	 * Builds one automatic currency row.
	 *
	 * @param string $provider_rate Provider rate string.
	 * @param int    $updated_at    Provider-rate timestamp.
	 *
	 * @return array<string, mixed>
	 */
	private function currency_row( string $provider_rate, int $updated_at ): array {
		return array(
			'enabled'         => true,
			'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
			'provider_rate'   => $provider_rate,
			'rate_updated_at' => $updated_at,
		);
	}

	/**
	 * Builds an operational state store from per-currency statuses.
	 *
	 * @param array<string, string> $statuses Currency code => operational status.
	 * @param string                $error    Last error recorded for failed rows.
	 */
	private function state_for( array $statuses, string $error = '' ): RateUpdateState {
		$currencies = array();

		foreach ( $statuses as $code => $status ) {
			$currencies[ $code ] = array(
				'last_fetch_at'        => time() - self::HOUR,
				'last_status'          => $status,
				'last_error'           => RateUpdateState::STATUS_FAILED === $status ? $error : '',
				'consecutive_failures' => RateUpdateState::STATUS_FAILED === $status ? 1 : 0,
			);
		}

		return new RateUpdateState( array( 'currencies' => $currencies ) );
	}

	private function schedule_recurring_update(): void {
		$this->assertTrue( function_exists( 'as_schedule_recurring_action' ) );

		as_schedule_recurring_action( time() + self::HOUR, self::HOUR, Scheduler::HOOK );
	}

	private function as_activate_plugins_user(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}
}
