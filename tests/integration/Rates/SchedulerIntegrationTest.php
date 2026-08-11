<?php
/**
 * Integration tests for recurring rate-update scheduling.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Rates;

use ActionScheduler_Store;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\Providers\FrankfurterRateSource;
use UMC\Rates\RateUpdateInterval;
use UMC\Rates\RateUpdateService;
use UMC\Rates\RateUpdateState;
use UMC\Rates\Scheduler;
use UMC\Settings;
use UMC\Tests\Support\FakeHttpTransport;
use WP_UnitTestCase;

/**
 * Behavioural coverage for Action Scheduler interval reconciliation.
 */
final class SchedulerIntegrationTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Scheduler::HOOK );
		}

		delete_option( Settings::OPTION );
		delete_option( RateUpdateState::OPTION );

		parent::tearDown();
	}

	public function test_schedules_one_recurring_action_when_none_exists(): void {
		$scheduler = $this->make_scheduler(
			array(
				'rate_update_interval' => RateUpdateInterval::default()->iso8601(),
			)
		);

		$scheduler->ensure_scheduled();

		$actions = $this->pending_hook_actions();
		$this->assertCount( 1, $actions );
		$this->assertSame( RateUpdateInterval::default()->seconds(), $this->recurring_interval( reset( $actions ) ) );
	}

	public function test_matching_interval_does_not_mutate_schedule(): void {
		$scheduler = $this->make_scheduler(
			array(
				'rate_update_interval' => 'P1D',
			)
		);

		$scheduler->ensure_scheduled();
		$before = array_keys( $this->pending_hook_actions() );

		$scheduler->ensure_scheduled();
		$after = array_keys( $this->pending_hook_actions() );

		$this->assertSame( $before, $after );
		$this->assertCount( 1, $after );
	}

	public function test_changed_interval_replaces_existing_recurring_action(): void {
		$settings  = new Settings( $this->automatic_settings( 'P1D' ) );
		$store     = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );
		$scheduler = new Scheduler( $store, $this->make_service( $store ) );

		$scheduler->ensure_scheduled();
		$this->assertSame( RateUpdateInterval::from_iso8601( 'P1D' )->seconds(), $this->single_pending_interval() );

		$settings->save(
			array_merge(
				$settings->get(),
				array( 'rate_update_interval' => 'PT6H' )
			)
		);

		$scheduler->ensure_scheduled();

		$this->assertCount( 1, $this->pending_hook_actions() );
		$this->assertSame( RateUpdateInterval::from_iso8601( 'PT6H' )->seconds(), $this->single_pending_interval() );
	}

	public function test_repeated_calls_remain_idempotent(): void {
		$scheduler = $this->make_scheduler(
			array(
				'rate_update_interval' => 'PT12H',
			)
		);

		$scheduler->ensure_scheduled();
		$first_ids = array_keys( $this->pending_hook_actions() );

		$scheduler->ensure_scheduled();
		$scheduler->ensure_scheduled();

		$this->assertSame( $first_ids, array_keys( $this->pending_hook_actions() ) );
		$this->assertCount( 1, $first_ids );
	}

	/**
	 * Characterization (pre-M16): Scheduler gates on global rate_mode only.
	 *
	 * Effective per-currency automatic under global manual still yields automatic
	 * targets via ExchangeRateStore::get_automatic_currency_codes(), but
	 * ensure_scheduled() unschedules because RateConfiguration::is_automatic_enabled()
	 * is false. M16 must flip this contract to schedule whenever automatic targets exist.
	 */
	public function test_characterize_global_manual_with_per_currency_automatic_currently_unschedules(): void {
		$settings  = new Settings(
			array(
				'rate_mode'            => Settings::RATE_MODE_MANUAL,
				'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
				'rate_update_interval' => 'P1D',
				'rate_max_age_hours'   => Settings::DEFAULT_RATE_MAX_AGE_HOURS,
				'currencies'           => array(
					'SEK' => array(
						'enabled'   => true,
						'rate_mode' => Settings::RATE_MODE_AUTOMATIC,
					),
				),
			)
		);
		$store     = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );
		$scheduler = new Scheduler( $store, $this->make_service( $store ) );

		$this->assertSame( array( 'SEK' ), $store->get_automatic_currency_codes() );
		$this->assertFalse( $store->get_configuration()->is_automatic_enabled() );

		as_schedule_recurring_action( time() + 3600, 86400, Scheduler::HOOK );
		$scheduler->ensure_scheduled();

		$this->assertCount(
			0,
			$this->pending_hook_actions(),
			'Pre-M16 characterization: global manual unschedules even when automatic targets exist.'
		);
	}

	public function test_global_manual_with_all_manual_currencies_unschedules(): void {
		$settings  = new Settings(
			array(
				'rate_mode'            => Settings::RATE_MODE_MANUAL,
				'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
				'rate_update_interval' => 'P1D',
				'currencies'           => array(
					'SEK' => array(
						'enabled'     => true,
						'rate_mode'   => Settings::RATE_MODE_MANUAL,
						'manual_rate' => '11.50',
					),
				),
			)
		);
		$store     = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );
		$scheduler = new Scheduler( $store, $this->make_service( $store ) );

		as_schedule_recurring_action( time() + 3600, 86400, Scheduler::HOOK );
		$scheduler->ensure_scheduled();

		$this->assertCount( 0, $this->pending_hook_actions() );
	}

	public function test_duplicate_recurring_actions_are_collapsed_to_one(): void {
		as_schedule_recurring_action( time() + 3600, 3600, Scheduler::HOOK );
		as_schedule_recurring_action( time() + 7200, 7200, Scheduler::HOOK );

		$this->assertGreaterThan( 1, count( $this->pending_hook_actions() ) );

		$scheduler = $this->make_scheduler(
			array(
				'rate_update_interval' => 'P1D',
			)
		);

		$scheduler->ensure_scheduled();

		$actions = $this->pending_hook_actions();
		$this->assertCount( 1, $actions );
		$this->assertSame( RateUpdateInterval::from_iso8601( 'P1D' )->seconds(), $this->recurring_interval( reset( $actions ) ) );
	}

	/**
	 * @param array<string, mixed> $overrides Settings overrides.
	 */
	private function make_scheduler( array $overrides = array() ): Scheduler {
		$settings = new Settings( $this->automatic_settings( 'P1D', $overrides ) );
		$store    = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );

		return new Scheduler( $store, $this->make_service( $store ) );
	}

	/**
	 * @param string               $interval  Default interval ISO-8601 string.
	 * @param array<string, mixed> $overrides Additional settings overrides.
	 * @return array<string, mixed>
	 */
	private function automatic_settings( string $interval = 'P1D', array $overrides = array() ): array {
		return array_merge(
			array(
				'rate_mode'            => Settings::RATE_MODE_AUTOMATIC,
				'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
				'rate_update_interval' => $interval,
				'rate_max_age_hours'   => Settings::DEFAULT_RATE_MAX_AGE_HOURS,
				'currencies'           => array(
					'SEK' => array(
						'enabled'   => true,
						'rate_mode' => Settings::RATE_MODE_AUTOMATIC,
					),
				),
			),
			$overrides
		);
	}

	private function make_service( ExchangeRateStore $store ): RateUpdateService {
		return new RateUpdateService(
			new FrankfurterRateSource( new FakeHttpTransport() ),
			$store,
			'EUR'
		);
	}

	/**
	 * @return array<int|string, mixed>
	 */
	private function pending_hook_actions(): array {
		$this->assertTrue( function_exists( 'as_get_scheduled_actions' ) );

		return as_get_scheduled_actions(
			array(
				'hook'     => Scheduler::HOOK,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 100,
			)
		);
	}

	private function single_pending_interval(): int {
		$actions = $this->pending_hook_actions();
		$this->assertCount( 1, $actions );

		return $this->recurring_interval( reset( $actions ) );
	}

	/**
	 * @param object $action Scheduled action object.
	 */
	private function recurring_interval( object $action ): int {
		$this->assertTrue( method_exists( $action, 'get_schedule' ) );

		$schedule = $action->get_schedule();

		$this->assertTrue( method_exists( $schedule, 'is_recurring' ) );
		$this->assertTrue( $schedule->is_recurring() );
		$this->assertTrue( method_exists( $schedule, 'get_recurrence' ) );

		return (int) $schedule->get_recurrence();
	}
}
