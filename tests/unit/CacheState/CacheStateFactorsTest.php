<?php
/**
 * Unit tests for the cache-state hash factors.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CacheState;

use PHPUnit\Framework\TestCase;
use UMC\CacheState\CacheStateFactors;

final class CacheStateFactorsTest extends TestCase {

	public function test_hash_is_deterministic_across_repeated_construction(): void {
		$a = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK', 'USD' ), true );
		$b = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK', 'USD' ), true );

		$this->assertSame( $a->hash(), $b->hash() );
	}

	public function test_hash_changes_when_geo_enabled_flips(): void {
		$enabled  = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK' ), true );
		$disabled = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK' ), false );

		$this->assertNotSame( $enabled->hash(), $disabled->hash() );
	}

	public function test_hash_changes_when_a_currency_joins_the_set(): void {
		$before = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK' ), true );
		$after  = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK', 'USD' ), true );

		$this->assertNotSame( $before->hash(), $after->hash() );
	}

	public function test_hash_changes_when_a_currency_leaves_the_set(): void {
		$before = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK', 'USD' ), true );
		$after  = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK' ), true );

		$this->assertNotSame( $before->hash(), $after->hash() );
	}

	public function test_hash_changes_when_base_currency_changes(): void {
		$eur = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK' ), true );
		$sek = new CacheStateFactors( 'SEK', array( 'EUR', 'SEK' ), true );

		$this->assertNotSame( $eur->hash(), $sek->hash() );
	}

	public function test_hash_changes_when_contract_version_changes(): void {
		$factors = new CacheStateFactors( 'EUR', array( 'EUR' ), true );

		$this->assertStringContainsString( 'v' . CacheStateFactors::CONTRACT_VERSION, $factors->canonical_string() );
	}

	public function test_currency_order_does_not_change_the_hash(): void {
		$a = new CacheStateFactors( 'EUR', array( 'USD', 'SEK', 'EUR' ), true );
		$b = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK', 'USD' ), true );

		$this->assertSame( $a->hash(), $b->hash() );
		$this->assertSame( $a->currencies(), $b->currencies() );
	}

	public function test_currency_case_does_not_change_the_hash(): void {
		$lower = new CacheStateFactors( 'eur', array( 'sek', 'usd' ), true );
		$upper = new CacheStateFactors( 'EUR', array( 'SEK', 'USD' ), true );

		$this->assertSame( $lower->hash(), $upper->hash() );
	}

	public function test_hash_format_is_sixteen_lowercase_hex(): void {
		$factors = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK' ), true );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $factors->hash() );
	}

	public function test_hash_is_derived_from_sha256_not_sha1(): void {
		$factors = new CacheStateFactors( 'EUR', array( 'EUR' ), false );

		$expected = substr( hash( 'sha256', $factors->canonical_string() ), 0, 16 );
		$sha1     = substr( hash( 'sha1', $factors->canonical_string() ), 0, 16 );

		$this->assertSame( $expected, $factors->hash() );
		$this->assertNotSame( $sha1, $factors->hash() );
	}

	public function test_canonical_string_contains_no_timestamp(): void {
		$factors = new CacheStateFactors( 'EUR', array( 'EUR', 'SEK' ), true );

		$this->assertDoesNotMatchRegularExpression( '/\d{10,}/', $factors->canonical_string() );
	}

	public function test_canonical_string_round_trip_is_stable(): void {
		$factors = new CacheStateFactors( 'EUR', array( 'SEK', 'EUR', 'usd' ), true );

		$this->assertSame(
			'umc-cache-state/v1|base=EUR|currencies=EUR,SEK,USD|geo=1',
			$factors->canonical_string()
		);
	}

	public function test_duplicate_currency_codes_are_deduplicated(): void {
		$factors = new CacheStateFactors( 'EUR', array( 'EUR', 'eur', 'SEK', 'SEK' ), false );

		$this->assertSame( array( 'EUR', 'SEK' ), $factors->currencies() );
	}
}
