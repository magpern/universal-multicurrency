<?php
/**
 * Unit tests for FrankfurterRateSource.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\Http\HttpResponse;
use UMC\Rates\ProviderMetadata;
use UMC\Rates\Providers\FrankfurterRateSource;
use UMC\Rates\RateFailureCode;
use UMC\Tests\Support\FakeHttpTransport;

/**
 * Fixture-driven Frankfurter provider tests without network I/O.
 */
final class FrankfurterRateSourceTest extends TestCase {

	private const URL = 'https://api.frankfurter.dev/v1/latest?base=EUR&symbols=SEK%2CNOK';

	public function test_successful_fetch_parses_quotes(): void {
		$transport = new FakeHttpTransport();
		$transport->register(
			self::URL,
			new HttpResponse(
				200,
				array( 'etag' => 'W/"abc"' ),
				'{"amount":1,"base":"EUR","date":"2026-07-24","rates":{"SEK":11.5,"NOK":11.8}}'
			)
		);

		$source = new FrankfurterRateSource( $transport );
		$result = $source->fetch( 'EUR', array( 'SEK', 'NOK' ) );

		$this->assertFalse( $result->is_not_modified() );
		$this->assertCount( 2, $result->quotes() );
		$this->assertSame( '11.5', $result->quotes()[0]->rate() );
		$this->assertSame( 'W/"abc"', $result->metadata()?->etag() );
	}

	public function test_partial_failure_when_symbol_missing(): void {
		$transport = new FakeHttpTransport();
		$transport->register(
			self::URL,
			new HttpResponse( 200, array(), '{"base":"EUR","date":"2026-07-24","rates":{"SEK":11.5}}' )
		);

		$result = ( new FrankfurterRateSource( $transport ) )->fetch( 'EUR', array( 'SEK', 'NOK' ) );

		$this->assertTrue( $result->is_partial_failure() );
		$this->assertArrayHasKey( 'NOK', $result->failures() );
	}

	public function test_not_modified_on_304(): void {
		$transport = new FakeHttpTransport();
		$transport->register( self::URL, new HttpResponse( 304, array(), '' ) );

		$previous = new ProviderMetadata( 1, 'frankfurter', null, null, 'W/"abc"', null );
		$result   = ( new FrankfurterRateSource( $transport ) )->fetch( 'EUR', array( 'SEK', 'NOK' ), $previous );

		$this->assertTrue( $result->is_not_modified() );
		$this->assertSame( 1, $transport->request_count() );
		$this->assertSame( 'W/"abc"', $transport->requests()[0]['headers']['If-None-Match'] ?? '' );
	}

	public function test_capability_methods(): void {
		$source = new FrankfurterRateSource( new FakeHttpTransport() );

		$this->assertTrue( $source->supports_conditional_requests() );
		$this->assertTrue( $source->supports_historical_rates() );
		$this->assertTrue( $source->supports_currencies( array( 'SEK', 'USD' ) ) );
		$this->assertFalse( $source->supports_currencies( array( 'XXX' ) ) );
	}

	public function test_transport_error_maps_to_network_error_code(): void {
		$transport = new FakeHttpTransport();
		$transport->register( self::URL, new HttpResponse( 0, array(), '', true ) );

		$result = ( new FrankfurterRateSource( $transport ) )->fetch( 'EUR', array( 'SEK', 'NOK' ) );

		$this->assertTrue( $result->is_total_failure() );
		$this->assertSame( RateFailureCode::NETWORK_ERROR, $result->failures()['SEK'] ?? null );
	}

	public function test_rate_limited_status_maps_to_failure_code(): void {
		$transport = new FakeHttpTransport();
		$transport->register( self::URL, new HttpResponse( 429, array(), 'rate limited' ) );

		$result = ( new FrankfurterRateSource( $transport ) )->fetch( 'EUR', array( 'SEK', 'NOK' ) );

		$this->assertTrue( $result->is_total_failure() );
		$this->assertSame( RateFailureCode::RATE_LIMITED, $result->failures()['SEK'] ?? null );
	}
}
