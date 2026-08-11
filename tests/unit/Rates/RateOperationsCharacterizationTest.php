<?php
/**
 * Characterization tests for pre-M16 exchange-rate operations behaviour.
 *
 * Locks current conversion, status, lock, and automatic-target semantics before
 * M16 operational hardening. Production code is not changed in this commit.
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
use UMC\Rates\Http\HttpResponse;
use UMC\Rates\ManualRateProvider;
use UMC\Rates\Providers\FrankfurterRateSource;
use UMC\Rates\RateResolver;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateService;
use UMC\Rates\RateUpdateState;
use UMC\Rates\UpdateInProgressException;
use UMC\Settings;
use UMC\Tests\Support\FakeHttpTransport;

/**
 * Pre-refactor characterization of rate operations contracts.
 *
 * @group rates
 * @group characterization
 */
final class RateOperationsCharacterizationTest extends TestCase {

	public function test_effective_mode_inherits_global_when_currency_override_empty(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'   => true,
						'rate_mode' => '',
					),
				),
			)
		);

		$this->assertSame( Settings::RATE_MODE_AUTOMATIC, $settings->get_effective_rate_mode( 'SEK' ) );
	}

	public function test_effective_mode_per_currency_override_beats_global_manual(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_MANUAL,
				'currencies' => array(
					'SEK' => array(
						'enabled'   => true,
						'rate_mode' => Settings::RATE_MODE_AUTOMATIC,
					),
				),
			)
		);

		$this->assertSame( Settings::RATE_MODE_AUTOMATIC, $settings->get_effective_rate_mode( 'SEK' ) );
	}

	public function test_automatic_targets_include_per_currency_override_under_global_manual(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_MANUAL,
				'currencies' => array(
					'SEK' => array(
						'enabled'       => true,
						'rate_mode'     => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate' => '11.50',
					),
					'USD' => array(
						'enabled'     => true,
						'rate_mode'   => Settings::RATE_MODE_MANUAL,
						'manual_rate' => '1.10',
					),
				),
			)
		);
		$store    = new ExchangeRateStore( $settings, new RateUpdateState( array() ), 'EUR', 'char-lock' );

		$this->assertSame( array( 'SEK' ), $store->get_automatic_currency_codes() );
		$this->assertFalse( $store->get_configuration()->is_automatic_enabled() );
	}

	public function test_manual_rate_resolution_ignores_provider_rate(): void {
		$this->assertSame(
			'11.50',
			RateResolver::effective_rate( Settings::RATE_MODE_MANUAL, '11.50', '99.00', '10' )
		);
	}

	public function test_automatic_rate_applies_adjustment_and_ignores_manual_rate(): void {
		$this->assertSame(
			'11',
			RateResolver::effective_rate( Settings::RATE_MODE_AUTOMATIC, '99.00', '10', '10' )
		);
	}

	public function test_stale_automatic_provider_rate_still_converts(): void {
		$max_age_hours = 48;
		$stale_at      = time() - ( ( $max_age_hours + 24 ) * 3600 );

		$settings = new Settings(
			array(
				'rate_mode'          => Settings::RATE_MODE_AUTOMATIC,
				'rate_max_age_hours' => $max_age_hours,
				'currencies'         => array(
					'SEK' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => '11.50',
						'rate_updated_at' => $stale_at,
					),
				),
			)
		);

		$evaluator = new RateStatusEvaluator( $settings, new ExchangeRateStore( $settings, new RateUpdateState( array() ), 'EUR', 'char-lock' ) );
		$this->assertSame( RateStatusEvaluator::LABEL_STALE, $evaluator->label_for_currency( 'SEK' ) );
		$this->assertSame( '11.5', $settings->get_rate( 'SEK' ) );

		$registry  = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$converter = new Converter( new ManualRateProvider( $settings, 'EUR' ), $registry );
		$this->assertSame( '1150.00', $converter->convert( '100', 'SEK' ) );
	}

	public function test_missing_automatic_rate_does_not_convert(): void {
		$settings = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'       => true,
						'rate_mode'     => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate' => '',
					),
				),
			)
		);

		$this->assertNull( $settings->get_rate( 'SEK' ) );
	}

	public function test_lock_first_acquire_succeeds_second_blocks_including_same_owner(): void {
		$state = new RateUpdateState( array() );

		$this->assertTrue( $state->try_acquire_lock( 'owner-a' ) );
		$this->assertFalse( $state->try_acquire_lock( 'owner-a' ) );
		$this->assertFalse( $state->try_acquire_lock( 'owner-b' ) );

		$lock = $state->get()['lock'];
		$this->assertSame( 'owner-a', $lock['owner'] );
		$this->assertGreaterThan( time(), (int) $lock['expires_at'] );
	}

	public function test_lock_release_clears_and_allows_reacquire(): void {
		$state = new RateUpdateState( array() );

		$this->assertTrue( $state->try_acquire_lock( 'owner-a' ) );
		$state->release_lock();

		$lock = $state->get()['lock'];
		$this->assertSame( '', $lock['owner'] );
		$this->assertSame( 0, (int) $lock['expires_at'] );
		$this->assertTrue( $state->try_acquire_lock( 'owner-b' ) );
	}

	public function test_expired_lock_is_recoverable(): void {
		$state = new RateUpdateState(
			array(
				'lock' => array(
					'owner'      => 'stale-owner',
					'expires_at' => time() - 1,
				),
			)
		);

		$this->assertTrue( $state->try_acquire_lock( 'recovery-owner' ) );
		$this->assertSame( 'recovery-owner', $state->get()['lock']['owner'] );
	}

	public function test_store_lock_delegates_non_reentrant_semantics(): void {
		$settings = new Settings( array( 'rate_mode' => Settings::RATE_MODE_AUTOMATIC ) );
		$state    = new RateUpdateState( array() );
		$store_a  = new ExchangeRateStore( $settings, $state, 'EUR', 'owner-a' );
		$store_b  = new ExchangeRateStore( $settings, $state, 'EUR', 'owner-b' );

		$this->assertTrue( $store_a->try_acquire_lock() );
		$this->assertFalse( $store_a->try_acquire_lock() );
		$this->assertFalse( $store_b->try_acquire_lock() );
		$store_a->release_lock();
		$this->assertTrue( $store_b->try_acquire_lock() );
	}

	public function test_rate_update_service_releases_lock_after_provider_failure(): void {
		$settings  = new Settings(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'       => true,
						'rate_mode'     => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate' => '10',
					),
				),
			)
		);
		$state     = new RateUpdateState( array() );
		$store     = new ExchangeRateStore( $settings, $state, 'EUR', 'svc-lock' );
		$transport = new FakeHttpTransport();
		$transport->register(
			'https://api.frankfurter.dev/v1/latest?base=EUR&symbols=SEK',
			new HttpResponse( 500, array(), 'boom' )
		);
		$service = new RateUpdateService(
			new FrankfurterRateSource( $transport ),
			$store,
			'EUR'
		);

		$result = $service->update( null );
		$this->assertTrue( $result->is_total_failure() );
		$this->assertSame( '10', $settings->get_currency_config( 'SEK' )['provider_rate'] ?? '' );
		$this->assertSame( '', $state->get()['lock']['owner'] );
		$this->assertSame( 0, (int) $state->get()['lock']['expires_at'] );
	}

	public function test_rate_update_service_blocks_when_lock_held(): void {
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
		$store    = new ExchangeRateStore( $settings, $state, 'EUR', 'svc-lock' );
		$this->assertTrue( $store->try_acquire_lock() );

		$service = new RateUpdateService(
			new FrankfurterRateSource( new FakeHttpTransport() ),
			$store,
			'EUR'
		);

		$this->expectException( UpdateInProgressException::class );
		$service->update( null );
	}
}
