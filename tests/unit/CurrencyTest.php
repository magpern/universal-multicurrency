<?php
/**
 * Unit tests for the Currency value object.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Currency;
use UMC\Exceptions\Exception as DomainException;
use UMC\Exceptions\InvalidCurrencyCodeException;
use UMC\Exceptions\InvalidCurrencyException;

/**
 * Tests currency construction, validation, immutability and array mapping.
 */
final class CurrencyTest extends TestCase {

	/**
	 * @dataProvider valid_currencies
	 */
	public function test_accepts_valid_currencies( string $code, int $decimals ): void {
		$currency = new Currency( $code, $decimals );
		$this->assertSame( $code, $currency->code() );
		$this->assertSame( $decimals, $currency->decimals() );
	}

	/**
	 * @return array<string, array{0: string, 1: int}>
	 */
	public static function valid_currencies(): array {
		return array(
			'EUR' => array( 'EUR', 2 ),
			'SEK' => array( 'SEK', 2 ),
			'USD' => array( 'USD', 2 ),
			'GBP' => array( 'GBP', 2 ),
			'JPY' => array( 'JPY', 0 ),
		);
	}

	public function test_lowercase_code_is_rejected(): void {
		$this->expectException( InvalidCurrencyCodeException::class );
		new Currency( 'eur' );
	}

	public function test_from_array_rejects_lowercase_code(): void {
		$this->expectException( InvalidCurrencyCodeException::class );
		Currency::from_array( 'eur', array() );
	}

	public function test_uppercase_code_is_preserved_unchanged(): void {
		$this->assertSame( 'EUR', ( new Currency( 'EUR' ) )->code() );
	}

	/**
	 * @dataProvider malformed_codes
	 */
	public function test_rejects_malformed_codes( string $code ): void {
		$this->expectException( InvalidCurrencyCodeException::class );
		new Currency( $code );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function malformed_codes(): array {
		return array(
			'lowercase'   => array( 'eur' ),
			'mixed case'  => array( 'Eur' ),
			'too short'   => array( 'EU' ),
			'too long'    => array( 'EURO' ),
			'has digit'   => array( 'E1R' ),
			'all digits'  => array( '123' ),
			'empty'       => array( '' ),
			'punctuation' => array( 'E-R' ),
			'whitespace'  => array( ' EUR' ),
		);
	}

	/**
	 * @dataProvider valid_decimals
	 */
	public function test_accepts_decimals_across_allowed_range( int $decimals ): void {
		$this->assertSame( $decimals, ( new Currency( 'USD', $decimals ) )->decimals() );
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function valid_decimals(): array {
		return array(
			'zero'  => array( 0 ),
			'one'   => array( 1 ),
			'two'   => array( 2 ),
			'three' => array( 3 ),
			'four'  => array( 4 ),
		);
	}

	/**
	 * @dataProvider invalid_decimals
	 */
	public function test_rejects_out_of_range_decimals( int $decimals ): void {
		$this->expectException( InvalidCurrencyException::class );
		new Currency( 'USD', $decimals );
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function invalid_decimals(): array {
		return array(
			'negative'  => array( -1 ),
			'too large' => array( 5 ),
		);
	}

	public function test_accepts_every_valid_position(): void {
		foreach ( Currency::POSITIONS as $position ) {
			$this->assertSame( $position, ( new Currency( 'USD', 2, '$', $position ) )->position() );
		}
	}

	public function test_rejects_unknown_position(): void {
		$this->expectException( InvalidCurrencyException::class );
		new Currency( 'USD', 2, '$', 'middle' );
	}

	public function test_empty_symbol_is_allowed(): void {
		$this->assertSame( '', ( new Currency( 'USD' ) )->symbol() );
	}

	public function test_domain_exceptions_share_marker_interface(): void {
		try {
			new Currency( 'eur1' );
			$this->fail( 'Expected an exception.' );
		} catch ( DomainException $e ) {
			$this->assertInstanceOf( InvalidCurrencyCodeException::class, $e );
		}
	}

	public function test_exposes_all_attributes(): void {
		$currency = new Currency( 'SEK', 2, 'kr', 'right_space', false );
		$this->assertSame( 'SEK', $currency->code() );
		$this->assertSame( 2, $currency->decimals() );
		$this->assertSame( 'kr', $currency->symbol() );
		$this->assertSame( 'right_space', $currency->position() );
		$this->assertFalse( $currency->is_enabled() );
	}

	public function test_equals_compares_by_value(): void {
		$a = new Currency( 'SEK', 2, 'kr', 'right_space', true );
		$b = new Currency( 'SEK', 2, 'kr', 'right_space', true );
		$c = new Currency( 'SEK', 2, 'kr', 'right_space', false );
		$this->assertTrue( $a->equals( $b ) );
		$this->assertFalse( $a->equals( $c ) );
	}

	public function test_from_array_round_trips_to_array(): void {
		$currency = new Currency( 'SEK', 3, 'kr', 'right_space', false );
		$rebuilt  = Currency::from_array( 'SEK', $currency->to_array() );
		$this->assertTrue( $currency->equals( $rebuilt ) );
	}

	public function test_from_array_applies_defaults_for_missing_keys(): void {
		$currency = Currency::from_array( 'USD', array() );
		$this->assertSame( Currency::DEFAULT_DECIMALS, $currency->decimals() );
		$this->assertSame( '', $currency->symbol() );
		$this->assertSame( Currency::DEFAULT_POSITION, $currency->position() );
		$this->assertTrue( $currency->is_enabled() );
	}
}
