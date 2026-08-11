<?php
/**
 * Unit tests for structured currency resolution evaluation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\CurrencyResolutionCandidate;
use UMC\CurrencyResolutionResult;
use UMC\CurrencyResolver;

/**
 * @covers \UMC\CurrencyResolver
 * @covers \UMC\CurrencyResolutionResult
 * @covers \UMC\CurrencyResolutionCandidate
 */
final class CurrencyResolutionResultTest extends TestCase {

	private const BASE       = 'EUR';
	private const SELECTABLE = array( 'SEK', 'JPY' );

	public function test_resolve_matches_evaluate_currency_for_representative_inputs(): void {
		$resolver = new CurrencyResolver();
		$cases    = array(
			array( 'SEK', 'JPY', 'JPY' ),
			array( null, 'SEK', 'JPY' ),
			array( null, null, 'JPY' ),
			array( null, null, null ),
			array( 'GBP', null, 'SEK' ),
			array( 'GBP', 'USD', 'NOK' ),
			array( ' sek ', null, null ),
			array( '', '   ', 'jpy' ),
			array( 'EUR', 'SEK', null ),
		);

		foreach ( $cases as $case ) {
			$resolved  = $resolver->resolve( $case[0], $case[1], $case[2], self::BASE, self::SELECTABLE );
			$evaluated = $resolver->evaluate( $case[0], $case[1], $case[2], self::BASE, self::SELECTABLE );

			$this->assertSame( $resolved, $evaluated->currency() );
		}
	}

	public function test_evaluate_reports_explicit_winner_and_candidate_statuses(): void {
		$result = ( new CurrencyResolver() )->evaluate( 'SEK', 'JPY', 'GBP', self::BASE, self::SELECTABLE );

		$this->assertSame( 'SEK', $result->currency() );
		$this->assertSame( CurrencyResolutionResult::SOURCE_EXPLICIT, $result->winning_source() );
		$this->assertFalse( $result->was_fallback_to_base() );

		$candidates = $result->candidates();
		$this->assertCount( 3, $candidates );
		$this->assertSame( CurrencyResolutionCandidate::STATUS_ACCEPTED, $candidates[0]->status() );
		$this->assertSame( CurrencyResolutionCandidate::STATUS_ACCEPTED, $candidates[1]->status() );
		$this->assertSame( CurrencyResolutionCandidate::STATUS_REJECTED, $candidates[2]->status() );
		$this->assertSame( CurrencyResolutionCandidate::REJECT_NOT_SELECTABLE, $candidates[2]->reject_reason() );
	}

	public function test_evaluate_reports_base_fallback(): void {
		$result = ( new CurrencyResolver() )->evaluate( 'GBP', 'USD', 'NOK', self::BASE, self::SELECTABLE );

		$this->assertSame( 'EUR', $result->currency() );
		$this->assertSame( CurrencyResolutionResult::SOURCE_BASE, $result->winning_source() );
		$this->assertTrue( $result->was_fallback_to_base() );
	}

	public function test_winning_source_is_never_geo(): void {
		$result = ( new CurrencyResolver() )->evaluate( null, 'SEK', null, self::BASE, self::SELECTABLE );

		$this->assertSame( CurrencyResolutionResult::SOURCE_SESSION, $result->winning_source() );
		$this->assertNotSame( 'geo', $result->winning_source() );
		$this->assertNotSame( 'visitor_location', $result->winning_source() );
	}
}
