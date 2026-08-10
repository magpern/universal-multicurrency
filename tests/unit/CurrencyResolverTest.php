<?php
/**
 * Unit tests for the pure currency resolver.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\CurrencyResolver;

/**
 * Tests priority resolution and allow-list validation.
 */
final class CurrencyResolverTest extends TestCase {

	private const BASE       = 'EUR';
	private const SELECTABLE = array( 'SEK', 'JPY' );

	private function resolve( ?string $explicit, ?string $session, ?string $cookie ): string {
		return ( new CurrencyResolver() )->resolve( $explicit, $session, $cookie, self::BASE, self::SELECTABLE );
	}

	public function test_explicit_wins_over_session_and_cookie(): void {
		$this->assertSame( 'SEK', $this->resolve( 'SEK', 'JPY', 'JPY' ) );
	}

	public function test_session_wins_over_cookie_when_no_explicit(): void {
		$this->assertSame( 'SEK', $this->resolve( null, 'SEK', 'JPY' ) );
	}

	public function test_cookie_used_when_no_explicit_or_session(): void {
		$this->assertSame( 'JPY', $this->resolve( null, null, 'JPY' ) );
	}

	public function test_base_returned_when_nothing_selected(): void {
		$this->assertSame( 'EUR', $this->resolve( null, null, null ) );
	}

	public function test_non_selectable_candidate_is_skipped(): void {
		// GBP is not selectable; fall through to the cookie's SEK.
		$this->assertSame( 'SEK', $this->resolve( 'GBP', null, 'SEK' ) );
	}

	public function test_all_non_selectable_falls_back_to_base(): void {
		$this->assertSame( 'EUR', $this->resolve( 'GBP', 'USD', 'NOK' ) );
	}

	public function test_base_is_always_selectable(): void {
		$this->assertSame( 'EUR', $this->resolve( 'EUR', null, null ) );
	}

	public function test_candidates_are_normalized_and_trimmed(): void {
		$this->assertSame( 'SEK', $this->resolve( ' sek ', null, null ) );
	}

	public function test_empty_and_malformed_candidates_are_ignored(): void {
		$this->assertSame( 'JPY', $this->resolve( '', '   ', 'jpy' ) );
	}

	public function test_selectable_list_is_matched_case_insensitively(): void {
		$resolved = ( new CurrencyResolver() )->resolve( 'sek', null, null, 'eur', array( 'sek' ) );
		$this->assertSame( 'SEK', $resolved );
	}
}
