<?php
/**
 * Performance guard for HTTP 304 / not-modified rate updates.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\Http\HttpResponse;
use UMC\Rates\Providers\FrankfurterRateSource;
use UMC\Rates\RateFetchResult;
use UMC\Rates\RateUpdateService;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use UMC\Tests\Support\FakeHttpTransport;
use UMC\Tests\Support\OptionWriteMetrics;

/**
 * Ensures not-modified fetches never rewrite merchant settings.
 *
 * @group performance
 */
final class RateUpdateNotModifiedWriteCeilingTest extends TestCase {

	/**
	 * Documented in docs/PERFORMANCE_BASELINES.md (M8 rate-update scenario).
	 */
	public const CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES = 0;

	private const FRANKFURTER_URL = 'https://api.frankfurter.dev/v1/latest?base=EUR&symbols=SEK';

	/**
	 * Store-only apply_fetch_result(not_modified) performs one state persistence.
	 */
	private const EXPECTED_STORE_NOT_MODIFIED_STATE_WRITES = 1;

	/**
	 * Service orchestration: lock acquire, not-modified state, lock release.
	 */
	private const EXPECTED_SERVICE_NOT_MODIFIED_STATE_WRITES = 3;

	protected function setUp(): void {
		OptionWriteMetrics::reset();
	}

	public function test_exchange_rate_store_not_modified_respects_settings_write_ceiling(): void {
		$settings    = $this->automatic_settings(
			array(
				'provider_rate'       => '10.00',
				'rate_updated_at'     => 1_700_000_000,
				'manual_rate'         => '',
				'merchant_adjustment' => '0',
			)
		);
		$before_rate = $settings->get_rate( 'SEK' );

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

		OptionWriteMetrics::reset();

		$store->apply_fetch_result( RateFetchResult::not_modified( 'frankfurter', 1_700_000_100 ) );

		$this->assertSame(
			self::CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES,
			OptionWriteMetrics::$umc_settings_writes,
			'HTTP 304 must not rewrite umc_settings.'
		);
		$this->assertSame(
			self::EXPECTED_STORE_NOT_MODIFIED_STATE_WRITES,
			OptionWriteMetrics::$umc_rate_state_writes,
			'Not-modified fetch must persist operational state only.'
		);
		$this->assertSame( '10.00', $settings->get_currency_config( 'SEK' )['provider_rate'] ?? '' );
		$this->assertSame( 1_700_000_000, $settings->get_currency_config( 'SEK' )['rate_updated_at'] ?? null );
		$this->assertSame( $before_rate, $settings->get_rate( 'SEK' ) );

		$status = $store->get_operational_status( 'SEK' );
		$this->assertSame( RateUpdateState::STATUS_SUCCESS, $status->last_status() );
		$this->assertSame( 1_700_000_100, $status->last_fetch_at() );
	}

	public function test_rate_update_service_not_modified_respects_settings_write_ceiling(): void {
		$settings    = $this->automatic_settings(
			array(
				'provider_rate'   => '11.25',
				'rate_updated_at' => 1_699_999_000,
			)
		);
		$before_rate = $settings->get_rate( 'SEK' );

		$transport = new FakeHttpTransport();
		$transport->register( self::FRANKFURTER_URL, new HttpResponse( 304, array(), '' ) );

		$store   = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'service-lock' );
		$service = new RateUpdateService(
			new FrankfurterRateSource( $transport ),
			$store,
			'EUR'
		);

		OptionWriteMetrics::reset();

		$result = $service->update();

		$this->assertTrue( $result->is_not_modified() );
		$this->assertSame(
			self::CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES,
			OptionWriteMetrics::$umc_settings_writes,
			'Service-level HTTP 304 must not rewrite umc_settings.'
		);
		$this->assertSame(
			self::EXPECTED_SERVICE_NOT_MODIFIED_STATE_WRITES,
			OptionWriteMetrics::$umc_rate_state_writes,
			'Service orchestration must persist operational state without touching settings.'
		);
		$this->assertSame( '11.25', $settings->get_currency_config( 'SEK' )['provider_rate'] ?? '' );
		$this->assertSame( 1_699_999_000, $settings->get_currency_config( 'SEK' )['rate_updated_at'] ?? null );
		$this->assertSame( $before_rate, $settings->get_rate( 'SEK' ) );
	}

	/**
	 * @param array<string, mixed> $currency_overrides Per-currency overrides.
	 */
	private function automatic_settings( array $currency_overrides = array() ): Settings {
		return new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array_merge(
						array(
							'enabled'   => true,
							'rate_mode' => Settings::RATE_MODE_AUTOMATIC,
						),
						$currency_overrides
					),
				),
			)
		);
	}
}
