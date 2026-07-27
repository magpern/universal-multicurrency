<?php
/**
 * Action Scheduler integration for recurring rate updates.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Schedules and runs background rate updates.
 */
final class Scheduler {

	public const HOOK = 'umc_run_rate_update';

	/**
	 * Persistence boundary for rate configuration.
	 *
	 * @var ExchangeRateStore
	 */
	private ExchangeRateStore $store;

	/**
	 * Rate update orchestration service.
	 *
	 * @var RateUpdateService
	 */
	private RateUpdateService $service;

	/**
	 * Whether hooks have already been registered.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Binds the scheduler to the store and update service.
	 *
	 * @param ExchangeRateStore $store   Persistence boundary.
	 * @param RateUpdateService $service Rate update orchestration service.
	 */
	public function __construct( ExchangeRateStore $store, RateUpdateService $service ) {
		$this->store   = $store;
		$this->service = $service;
	}

	/**
	 * Registers Action Scheduler hooks.
	 */
	public function register(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled_on_init' ), 20 );
		add_action( 'umc_settings_saved', array( $this, 'ensure_scheduled' ) );
	}

	/**
	 * Ensures a recurring action exists on init when settings were not just saved.
	 */
	public function ensure_scheduled_on_init(): void {
		if ( did_action( 'umc_settings_saved' ) ) {
			return;
		}

		$this->ensure_scheduled();
	}

	/**
	 * Ensures or clears the recurring update action for the current configuration.
	 */
	public function ensure_scheduled(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		$config = $this->store->get_configuration();

		if ( ! $config->is_automatic_enabled() ) {
			$this->unschedule_all();
			return;
		}

		$interval_seconds = $config->rate_update_interval()->seconds();

		if ( $this->schedule_matches_configuration( $interval_seconds ) ) {
			return;
		}

		$this->unschedule_all();
		as_schedule_recurring_action( time() + $interval_seconds, $interval_seconds, self::HOOK );
	}

	/**
	 * Removes every pending recurring action for this hook.
	 */
	private function unschedule_all(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK );
			return;
		}

		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action( self::HOOK );
		}
	}

	/**
	 * Whether the pending recurring schedule matches the configured interval.
	 *
	 * @param int $configured_interval Configured interval in seconds.
	 */
	private function schedule_matches_configuration( int $configured_interval ): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( self::HOOK );
		}

		$scheduled_interval = $this->scheduled_interval_seconds();

		return null !== $scheduled_interval && $scheduled_interval === $configured_interval;
	}

	/**
	 * Returns the interval of the sole pending recurring action, if unambiguous.
	 */
	private function scheduled_interval_seconds(): ?int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return null;
		}

		$actions = as_get_scheduled_actions(
			array(
				'hook'     => self::HOOK,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 100,
			)
		);

		if ( array() === $actions ) {
			return null;
		}

		$intervals = array();

		foreach ( $actions as $action ) {
			if ( ! is_object( $action ) || ! method_exists( $action, 'get_schedule' ) ) {
				return null;
			}

			$schedule = $action->get_schedule();

			if ( ! is_object( $schedule ) || ! method_exists( $schedule, 'is_recurring' ) || ! $schedule->is_recurring() ) {
				return null;
			}

			if ( ! method_exists( $schedule, 'get_recurrence' ) ) {
				return null;
			}

			$intervals[] = (int) $schedule->get_recurrence();
		}

		$intervals = array_values( array_unique( $intervals ) );

		if ( 1 !== count( $actions ) || 1 !== count( $intervals ) ) {
			return null;
		}

		return $intervals[0];
	}

	/**
	 * Runs one scheduled rate update.
	 */
	public function run(): void {
		try {
			$this->service->update( null );
		} catch ( UpdateInProgressException $exception ) {
			unset( $exception );
		}
	}
}
