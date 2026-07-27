<?php
/**
 * Action Scheduler integration for recurring rate updates.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

use UMC\Settings;

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
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		$config = $this->store->get_configuration();

		if ( ! $config->is_automatic_enabled() ) {
			if ( function_exists( 'as_unschedule_action' ) ) {
				as_unschedule_action( self::HOOK );
			}
			return;
		}

		$interval = $config->rate_update_interval()->seconds();
		$next     = as_next_scheduled_action( self::HOOK );

		if ( false !== $next ) {
			return;
		}

		as_schedule_recurring_action( time() + $interval, $interval, self::HOOK );
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
