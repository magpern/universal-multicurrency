<?php
/**
 * Unit tests for RateHealthService.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateFailureCode;
use UMC\Rates\RateHealthService;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateState;
use UMC\Settings;

/**
 * Read-only health aggregation coverage.
 *
 * @group rates
 */
final class RateHealthServiceTest extends TestCase {

	public function test_report_classifies_targets_and_age_bands(): void {
		$max_age  = 48;
		$now      = time();
		$fresh_at = $now - (int) ( 0.25 * $max_age * 3600 );
		$aging_at = $now - (int) ( 0.75 * $max_age * 3600 );
		$stale_at = $now - ( ( $max_age + 1 ) * 3600 );

		$settings = new Settings(
			array(
				'rate_mode'          => Settings::RATE_MODE_AUTOMATIC,
				'rate_provider'      => Settings::DEFAULT_RATE_PROVIDER,
				'rate_max_age_hours' => $max_age,
				'currencies'         => array(
					'SEK' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '11.50',
						'rate_updated_at' => $fresh_at,
					),
					'USD' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '1.10',
						'rate_updated_at' => $aging_at,
					),
					'GBP' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '0.85',
						'rate_updated_at' => $stale_at,
					),
					'NOK' => array(
						'enabled'     => true,
						'rate_mode'   => Settings::RATE_MODE_MANUAL,
						'manual_rate' => '11.00',
					),
					'DKK' => array(
						'enabled'   => false,
						'rate_mode' => Settings::RATE_MODE_AUTOMATIC,
					),
					'PLN' => array(
						'enabled'       => true,
						'rate_mode'     => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate' => '',
					),
				),
			)
		);

		$state = new RateUpdateState(
			array(
				'last_run_at'     => $now - 100,
				'currencies'      => array(
					'SEK' => array(
						'last_fetch_at'        => $fresh_at,
						'last_status'          => RateUpdateState::STATUS_SUCCESS,
						'last_error'           => '',
						'consecutive_failures' => 0,
					),
					'USD' => array(
						'last_fetch_at'        => $aging_at,
						'last_status'          => RateUpdateState::STATUS_SUCCESS,
						'last_error'           => '',
						'consecutive_failures' => 0,
					),
					'GBP' => array(
						'last_fetch_at'        => $stale_at,
						'last_status'          => RateUpdateState::STATUS_SUCCESS,
						'last_error'           => '',
						'consecutive_failures' => 0,
					),
					'PLN' => array(
						'last_fetch_at'        => $now - 50,
						'last_status'          => RateUpdateState::STATUS_FAILED,
						'last_error'           => RateFailureCode::TIMEOUT,
						'consecutive_failures' => 3,
					),
				),
				'failure_history' => array(
					array(
						'at'    => $now - 50,
						'scope' => 'PLN',
						'error' => RateFailureCode::TIMEOUT,
					),
				),
			)
		);

		$store     = new ExchangeRateStore( $settings, $state, 'EUR', 'health-lock' );
		$evaluator = new RateStatusEvaluator( $settings, $store );
		$report    = ( new RateHealthService( $settings, $store, $evaluator ) )->report();

		$this->assertSame( Settings::DEFAULT_RATE_PROVIDER, $report->provider_id() );
		$this->assertSame( Settings::RATE_MODE_AUTOMATIC, $report->global_mode() );
		$this->assertSame( 4, $report->automatic_target_count() );
		$this->assertSame( 1, $report->manual_target_count() );
		$this->assertSame( 1, $report->disabled_count() );
		$this->assertSame( 1, $report->fresh_count() );
		$this->assertSame( 1, $report->aging_count() );
		$this->assertSame( 1, $report->stale_count() );
		$this->assertSame( 1, $report->unavailable_count() );
		$this->assertSame( $now - 50, $report->last_attempt_at() );
		$this->assertSame( RateFailureCode::TIMEOUT, $report->last_failure_code() );
		$this->assertSame( 3, $report->consecutive_failures_max() );
		$this->assertTrue( $report->has_automatic_targets() );
		$this->assertFalse( $report->lock_held() );
		$this->assertNull( $report->next_scheduled_at() );
		$this->assertFalse( $report->scheduler_missing() );
		$this->assertSame( $report->to_array()['fresh_count'], $report->fresh_count() );
	}

	public function test_disabled_automatic_currency_is_not_an_automatic_target(): void {
		$settings  = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_MANUAL,
				'currencies' => array(
					'SEK' => array(
						'enabled'   => false,
						'rate_mode' => Settings::RATE_MODE_AUTOMATIC,
					),
				),
			)
		);
		$store     = new ExchangeRateStore( $settings, new RateUpdateState( array() ), 'EUR', 'health-lock' );
		$evaluator = new RateStatusEvaluator( $settings, $store );
		$report    = ( new RateHealthService( $settings, $store, $evaluator ) )->report();

		$this->assertSame( array(), $store->get_automatic_currency_codes() );
		$this->assertFalse( $store->has_automatic_targets() );
		$this->assertFalse( $report->has_automatic_targets() );
		$this->assertSame( 0, $report->automatic_target_count() );
		$this->assertSame( 1, $report->disabled_count() );
	}

	public function test_lock_held_is_reported(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'   => true,
						'rate_mode' => Settings::RATE_MODE_AUTOMATIC,
					),
				),
			)
		);
		$state    = new RateUpdateState( array() );
		$store    = new ExchangeRateStore( $settings, $state, 'EUR', 'health-lock' );
		$this->assertTrue( $store->try_acquire_lock() );

		$evaluator = new RateStatusEvaluator( $settings, $store );
		$report    = ( new RateHealthService( $settings, $store, $evaluator ) )->report();

		$this->assertTrue( $report->lock_held() );
		$this->assertTrue( $store->is_lock_held() );
	}

	public function test_unknown_failure_code_sanitizes_to_provider_unavailable(): void {
		$this->assertTrue( RateFailureCode::is_known( RateFailureCode::TIMEOUT ) );
		$this->assertFalse( RateFailureCode::is_known( 'http_teapot' ) );
		$this->assertSame( RateFailureCode::PROVIDER_UNAVAILABLE, RateFailureCode::sanitize( 'http_teapot' ) );
		$this->assertSame( RateFailureCode::TIMEOUT, RateFailureCode::sanitize( 'timeout' ) );
	}
}
