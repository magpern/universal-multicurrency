<?php
/**
 * Characterization tests locking CountryContextResolver provider-chain
 * behaviour before the M14 admin-IA refactor.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\CountryContext;
use UMC\Geo\CountryContextResolver;
use UMC\Tests\Unit\Doubles\FakeCountryContextProvider;

/**
 * @covers \UMC\Geo\CountryContextResolver
 */
final class CountryContextResolverTest extends TestCase {

	public function test_returns_unknown_when_no_providers_are_configured(): void {
		$resolver = new CountryContextResolver( array() );

		$this->assertFalse( $resolver->resolve()->is_known() );
	}

	public function test_returns_unknown_when_all_providers_are_unavailable(): void {
		$resolver = new CountryContextResolver(
			array(
				new FakeCountryContextProvider( 'a', false, new CountryContext( 'SE', 'a', 1.0 ) ),
				new FakeCountryContextProvider( 'b', false, new CountryContext( 'DE', 'b', 1.0 ) ),
			)
		);

		$this->assertFalse( $resolver->resolve()->is_known() );
	}

	public function test_first_available_provider_with_a_known_context_wins(): void {
		$first  = new FakeCountryContextProvider( 'first', true, new CountryContext( 'SE', 'first', 1.0 ) );
		$second = new FakeCountryContextProvider( 'second', true, new CountryContext( 'DE', 'second', 1.0 ) );

		$resolver = new CountryContextResolver( array( $first, $second ) );
		$context  = $resolver->resolve();

		$this->assertTrue( $context->is_known() );
		$this->assertSame( 'SE', $context->country_code() );
		$this->assertSame( 'first', $context->source() );
		$this->assertSame( 0, $second->resolve_call_count(), 'The second provider must never be consulted once the first one wins.' );
	}

	public function test_falls_through_to_next_provider_when_first_is_unavailable(): void {
		$unavailable = new FakeCountryContextProvider( 'unavailable', false, new CountryContext( 'SE', 'unavailable', 1.0 ) );
		$fallback    = new FakeCountryContextProvider( 'fallback', true, new CountryContext( 'DE', 'fallback', 0.75 ) );

		$context = ( new CountryContextResolver( array( $unavailable, $fallback ) ) )->resolve();

		$this->assertSame( 'DE', $context->country_code() );
		$this->assertSame( 'fallback', $context->source() );
		$this->assertSame( 0, $unavailable->resolve_call_count(), 'An unavailable provider must never be asked to resolve().' );
	}

	public function test_falls_through_to_next_provider_when_first_resolves_unknown(): void {
		$unknown  = new FakeCountryContextProvider( 'unknown', true, CountryContext::unknown() );
		$fallback = new FakeCountryContextProvider( 'fallback', true, new CountryContext( 'GB', 'fallback', 0.75 ) );

		$context = ( new CountryContextResolver( array( $unknown, $fallback ) ) )->resolve();

		$this->assertSame( 'GB', $context->country_code() );
	}

	public function test_falls_through_to_next_provider_when_first_resolves_null(): void {
		$null     = new FakeCountryContextProvider( 'null', true, null );
		$fallback = new FakeCountryContextProvider( 'fallback', true, new CountryContext( 'FR', 'fallback', 0.75 ) );

		$context = ( new CountryContextResolver( array( $null, $fallback ) ) )->resolve();

		$this->assertSame( 'FR', $context->country_code() );
	}

	public function test_returns_unknown_when_every_available_provider_resolves_unknown(): void {
		$resolver = new CountryContextResolver(
			array(
				new FakeCountryContextProvider( 'a', true, CountryContext::unknown() ),
				new FakeCountryContextProvider( 'b', true, null ),
			)
		);

		$this->assertFalse( $resolver->resolve()->is_known() );
	}
}
