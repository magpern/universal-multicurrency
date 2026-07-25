<?php
/**
 * Unit tests for ISO-4217 currency decimals fallback.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Support\IsoCurrencyDecimals;

/**
 * Verifies ISO-4217 decimal lookup for currencies no longer in configuration.
 */
final class IsoCurrencyDecimalsTest extends TestCase {

	/**
	 * Zero-decimal currencies return 0.
	 *
	 * @dataProvider zero_decimal_codes
	 */
	public function test_zero_decimal_currencies( string $code ): void {
		$this->assertSame( 0, IsoCurrencyDecimals::decimals( $code ) );
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	public static function zero_decimal_codes(): array {
		return array(
			array( 'JPY' ),
			array( 'KRW' ),
			array( 'VND' ),
			array( 'XAF' ),
			array( 'CLP' ),
			array( 'jpy' ), // Case-insensitive.
		);
	}

	/**
	 * Three-decimal currencies return 3.
	 *
	 * @dataProvider three_decimal_codes
	 */
	public function test_three_decimal_currencies( string $code ): void {
		$this->assertSame( 3, IsoCurrencyDecimals::decimals( $code ) );
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	public static function three_decimal_codes(): array {
		return array(
			array( 'BHD' ),
			array( 'KWD' ),
			array( 'OMR' ),
			array( 'bhd' ), // Case-insensitive.
		);
	}

	/**
	 * Two-decimal currencies (default) return 2.
	 *
	 * @dataProvider two_decimal_codes
	 */
	public function test_two_decimal_currencies( string $code ): void {
		$this->assertSame( 2, IsoCurrencyDecimals::decimals( $code ) );
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	public static function two_decimal_codes(): array {
		return array(
			array( 'EUR' ),
			array( 'USD' ),
			array( 'GBP' ),
			array( 'SEK' ),
			array( 'UNKNOWN' ), // Unknown codes default to 2.
		);
	}

	/**
	 * Whitespace is trimmed.
	 */
	public function test_whitespace_is_trimmed(): void {
		$this->assertSame( 0, IsoCurrencyDecimals::decimals( '  JPY  ' ) );
	}
}
