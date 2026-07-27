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

	private ExchangeRateStore $store;

	private RateUpdateService $service;

	private bool $booted = false;

	public function __construct( ExchangeRateStore $store, RateUpdateService $service ) {
		$this->store   = $store;
		$this->service = $service;
	}

	public function register(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled_on_init' ), 20 );
		add_action( 'umc_settings_saved', array( $this, 'ensure_scheduled' ) );
	}

	public function ensure_scheduled_on_init(): void {
		if ( did_action( 'umc_settings_saved' ) ) {
			return;
		}

		$this->ensure_scheduled();
	}

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

	public function run(): void {
		try {
			$this->service->update( null );
		} catch ( UpdateInProgressException $exception ) {
			unset( $exception );
		}
	}
}
