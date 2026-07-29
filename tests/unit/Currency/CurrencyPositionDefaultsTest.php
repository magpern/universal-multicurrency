<?php
/**
 * Unit tests for currency symbol position defaults.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Currency;

use PHPUnit\Framework\TestCase;
use UMC\Currency\CurrencyPositionDefaults;

/**
 * Covers prefix/suffix heuristics for common storefront conventions.
 */
final class CurrencyPositionDefaultsTest extends TestCase {

	public function test_nordic_codes_default_to_right_with_space(): void {
		foreach ( array( 'SEK', 'NOK', 'DKK', 'ISK' ) as $code ) {
			$this->assertSame( 'right_space', CurrencyPositionDefaults::for_currency( $code, 'kr' ) );
		}
	}

	public function test_single_character_symbols_default_to_left_with_space(): void {
		$this->assertSame( 'left_space', CurrencyPositionDefaults::for_currency( 'EUR', '€' ) );
		$this->assertSame( 'left_space', CurrencyPositionDefaults::for_currency( 'USD', '$' ) );
		$this->assertSame( 'left_space', CurrencyPositionDefaults::for_currency( 'GBP', '£' ) );
	}

	public function test_multi_character_symbols_default_to_right_with_space(): void {
		$this->assertSame( 'right_space', CurrencyPositionDefaults::for_currency( 'CHF', 'CHF' ) );
		$this->assertSame( 'right_space', CurrencyPositionDefaults::for_currency( 'AED', 'د.إ' ) );
	}

	public function test_unknown_single_letter_symbol_defaults_to_right_with_space(): void {
		$this->assertSame( 'right_space', CurrencyPositionDefaults::for_currency( 'XYZ', 'X' ) );
	}
}
