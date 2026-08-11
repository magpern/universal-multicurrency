<?php
/**
 * Unit tests for rate refresh messaging contracts shared by admin / CLI.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use UMC\Admin\RateUpdateController;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\ProviderMetadata;
use UMC\Rates\Providers\FrankfurterRateSource;
use UMC\Rates\RateFetchResult;
use UMC\Rates\RateQuote;
use UMC\Rates\RateUpdateService;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use UMC\Tests\Support\FakeHttpTransport;

/**
 * Verifies refresh messaging / notice typing used by admin and CLI contracts.
 */
final class RatesCommandContractTest extends TestCase {

	public function test_controller_messages_cover_refresh_outcomes(): void {
		$settings   = new Settings( array( 'rate_mode' => Settings::RATE_MODE_MANUAL ) );
		$store      = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'cli-test' );
		$service    = new RateUpdateService( new FrankfurterRateSource( new FakeHttpTransport() ), $store, 'EUR' );
		$controller = new RateUpdateController( $service );

		$meta = new ProviderMetadata( ProviderMetadata::SCHEMA_VERSION, 'frankfurter', '2026-08-11' );

		$this->assertStringContainsString(
			'No automatic',
			$controller->message_for_result( RateFetchResult::no_automatic_targets( 'frankfurter', time() ) )
		);
		$this->assertSame(
			'warning',
			$controller->notice_type_for_result( RateFetchResult::no_automatic_targets( 'frankfurter', time() ) )
		);

		$this->assertStringContainsString(
			'up to date',
			$controller->message_for_result( RateFetchResult::not_modified( 'frankfurter', time() ) )
		);

		$total = RateFetchResult::success(
			array(),
			array( 'SEK' => 'provider_unavailable' ),
			$meta,
			time()
		);
		$this->assertStringContainsString( 'failed for all', $controller->message_for_result( $total ) );
		$this->assertSame( 'warning', $controller->notice_type_for_result( $total ) );

		$partial = RateFetchResult::success(
			array( new RateQuote( 'EUR', 'SEK', '11.5' ) ),
			array( 'NOK' => 'not_returned_by_provider' ),
			$meta,
			time()
		);
		$this->assertStringContainsString( 'partially', $controller->message_for_result( $partial ) );
		$this->assertSame( 'warning', $controller->notice_type_for_result( $partial ) );

		$ok = RateFetchResult::success(
			array( new RateQuote( 'EUR', 'SEK', '11.5' ) ),
			array(),
			$meta,
			time()
		);
		$this->assertStringContainsString( 'successfully', $controller->message_for_result( $ok ) );
		$this->assertSame( 'success', $controller->notice_type_for_result( $ok ) );
	}

	public function test_rates_command_class_is_autoloadable(): void {
		$this->assertTrue( class_exists( \UMC\CLI\RatesCommand::class ) );
	}
}
