<?php
/**
 * Unit tests for RateStatusEvaluator aging bands.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Converter;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\ManualRateProvider;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateState;
use UMC\Settings;

/**
 * Fresh / aging / stale classification and conversion invariants.
 *
 * @group rates
 */
final class RateStatusEvaluatorAgingTest extends TestCase {

	public function test_fresh_aging_and_stale_bands(): void {
		$max_age = 40;
		$now     = time();

		$settings = new Settings(
			array(
				'rate_mode'          => Settings::RATE_MODE_AUTOMATIC,
				'rate_max_age_hours' => $max_age,
				'currencies'         => array(
					'SEK' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '11.50',
						'rate_updated_at' => $now - (int) ( 0.4 * $max_age * 3600 ),
					),
					'USD' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '1.10',
						'rate_updated_at' => $now - (int) ( 0.6 * $max_age * 3600 ),
					),
					'GBP' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '0.85',
						'rate_updated_at' => $now - ( ( $max_age + 2 ) * 3600 ),
					),
				),
			)
		);

		$store     = new ExchangeRateStore( $settings, new RateUpdateState( array() ), 'EUR', 'aging-lock' );
		$evaluator = new RateStatusEvaluator( $settings, $store );

		$this->assertSame( RateStatusEvaluator::LABEL_OK, $evaluator->label_for_currency( 'SEK' ) );
		$this->assertSame( RateStatusEvaluator::LABEL_AGING, $evaluator->label_for_currency( 'USD' ) );
		$this->assertSame( RateStatusEvaluator::LABEL_STALE, $evaluator->label_for_currency( 'GBP' ) );
		$this->assertSame( 'Aging', $evaluator->display_label( RateStatusEvaluator::LABEL_AGING ) );
	}

	public function test_boundary_at_half_max_is_fresh_and_at_max_is_aging(): void {
		$max_age = 40;
		$now     = time();

		$settings = new Settings(
			array(
				'rate_mode'          => Settings::RATE_MODE_AUTOMATIC,
				'rate_max_age_hours' => $max_age,
				'currencies'         => array(
					'SEK' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '11.50',
						'rate_updated_at' => $now - (int) ( 0.5 * $max_age * 3600 ),
					),
					'USD' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '1.10',
						'rate_updated_at' => $now - ( $max_age * 3600 ),
					),
				),
			)
		);

		$store     = new ExchangeRateStore( $settings, new RateUpdateState( array() ), 'EUR', 'aging-lock' );
		$evaluator = new RateStatusEvaluator( $settings, $store );

		$this->assertSame( RateStatusEvaluator::LABEL_OK, $evaluator->label_for_currency( 'SEK' ) );
		$this->assertSame( RateStatusEvaluator::LABEL_AGING, $evaluator->label_for_currency( 'USD' ) );
	}

	public function test_failed_status_stays_failed_even_with_usable_provider_rate(): void {
		$settings  = new Settings(
			array(
				'rate_mode'          => Settings::RATE_MODE_AUTOMATIC,
				'rate_max_age_hours' => 48,
				'currencies'         => array(
					'SEK' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '11.50',
						'rate_updated_at' => time() - 3600,
					),
				),
			)
		);
		$state     = new RateUpdateState(
			array(
				'currencies' => array(
					'SEK' => array(
						'last_fetch_at'        => time(),
						'last_status'          => RateUpdateState::STATUS_FAILED,
						'last_error'           => 'timeout',
						'consecutive_failures' => 1,
					),
				),
			)
		);
		$store     = new ExchangeRateStore( $settings, $state, 'EUR', 'aging-lock' );
		$evaluator = new RateStatusEvaluator( $settings, $store );

		$this->assertSame( RateStatusEvaluator::LABEL_FAILED, $evaluator->label_for_currency( 'SEK' ) );
		$this->assertSame( '11.5', $settings->get_rate( 'SEK' ) );
	}

	public function test_stale_and_aging_rates_still_convert(): void {
		$max_age  = 48;
		$aging_at = time() - (int) ( 0.75 * $max_age * 3600 );
		$stale_at = time() - ( ( $max_age + 24 ) * 3600 );

		$settings = new Settings(
			array(
				'rate_mode'          => Settings::RATE_MODE_AUTOMATIC,
				'rate_max_age_hours' => $max_age,
				'currencies'         => array(
					'SEK' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '11.50',
						'rate_updated_at' => $stale_at,
					),
					'USD' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '2.00',
						'rate_updated_at' => $aging_at,
					),
				),
			)
		);

		$store     = new ExchangeRateStore( $settings, new RateUpdateState( array() ), 'EUR', 'aging-lock' );
		$evaluator = new RateStatusEvaluator( $settings, $store );

		$this->assertSame( RateStatusEvaluator::LABEL_STALE, $evaluator->label_for_currency( 'SEK' ) );
		$this->assertSame( RateStatusEvaluator::LABEL_AGING, $evaluator->label_for_currency( 'USD' ) );
		$this->assertSame( '11.5', $settings->get_rate( 'SEK' ) );
		$this->assertSame( '2', $settings->get_rate( 'USD' ) );

		$registry  = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$converter = new Converter( new ManualRateProvider( $settings, 'EUR' ), $registry );

		$this->assertSame( '1150.00', $converter->convert( '100', 'SEK' ) );
		$this->assertSame( '200.00', $converter->convert( '100', 'USD' ) );
	}

	public function test_manual_mode_remains_ok_label(): void {
		$settings  = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_MANUAL,
				'currencies' => array(
					'SEK' => array(
						'enabled'     => true,
						'rate_mode'   => Settings::RATE_MODE_MANUAL,
						'manual_rate' => '11.50',
					),
				),
			)
		);
		$store     = new ExchangeRateStore( $settings, new RateUpdateState( array() ), 'EUR', 'aging-lock' );
		$evaluator = new RateStatusEvaluator( $settings, $store );

		$this->assertSame( RateStatusEvaluator::LABEL_OK, $evaluator->label_for_currency( 'SEK' ) );
	}
}
