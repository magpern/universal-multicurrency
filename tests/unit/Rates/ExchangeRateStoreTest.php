<?php
/**
 * Unit tests for ExchangeRateStore write ordering.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Tests\Unit\Doubles\ThrowingRateUpdateState;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\ProviderMetadata;
use UMC\Rates\RateFetchResult;
use UMC\Rates\RateQuote;
use UMC\Rates\RateUpdateState;
use UMC\Settings;

/**
 * Verifies money-bearing settings writes survive operational-state failures.
 */
final class ExchangeRateStoreTest extends TestCase {

	public function test_settings_provider_rate_survives_state_save_failure(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'     => true,
						'manual_rate' => '',
						'rate_mode'   => Settings::RATE_MODE_AUTOMATIC,
					),
				),
			)
		);

		$throwing_state = new ThrowingRateUpdateState();

		$store = new ExchangeRateStore( $settings, $throwing_state, 'EUR', 'lock' );

		$meta   = new ProviderMetadata( ProviderMetadata::SCHEMA_VERSION, 'frankfurter', '2026-07-24' );
		$result = RateFetchResult::success(
			array( new RateQuote( 'EUR', 'SEK', '11.5' ) ),
			array(),
			$meta,
			1_700_000_000
		);

		try {
			$store->apply_fetch_result( $result );
			$this->fail( 'Expected state save failure.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'Simulated state write failure.', $exception->getMessage() );
		}

		$this->assertSame( '11.5', $settings->get_currency_config( 'SEK' )['provider_rate'] ?? null );
	}

	public function test_not_modified_updates_state_only(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'       => true,
						'provider_rate' => '10',
						'rate_mode'     => Settings::RATE_MODE_AUTOMATIC,
					),
				),
			)
		);

		$state = new RateUpdateState(
			array(
				'currencies' => array(
					'SEK' => array(
						'last_fetch_at'        => 1,
						'last_status'          => RateUpdateState::STATUS_FAILED,
						'last_error'           => 'timeout',
						'consecutive_failures' => 2,
					),
				),
			)
		);

		$store = new ExchangeRateStore( $settings, $state, 'EUR', 'lock' );
		$store->apply_fetch_result( RateFetchResult::not_modified( 'frankfurter', 1_700_000_100 ) );

		$this->assertSame( '10', $settings->get_currency_config( 'SEK' )['provider_rate'] ?? '' );
		$status = $store->get_operational_status( 'SEK' );
		$this->assertSame( RateUpdateState::STATUS_SUCCESS, $status->last_status() );
		$this->assertSame( 0, $status->consecutive_failures() );
	}

	public function test_successful_fetch_updates_rate_updated_at(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'         => true,
						'manual_rate'     => '',
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'rate_updated_at' => 100,
					),
				),
			)
		);

		$store = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'lock' );
		$meta  = new ProviderMetadata( ProviderMetadata::SCHEMA_VERSION, 'frankfurter', '2026-07-24' );
		$store->apply_fetch_result(
			RateFetchResult::success(
				array( new RateQuote( 'EUR', 'SEK', '11.5' ) ),
				array(),
				$meta,
				1_700_000_500
			)
		);

		$this->assertSame( 1_700_000_500, $settings->get_currency_config( 'SEK' )['rate_updated_at'] ?? null );
	}
}
