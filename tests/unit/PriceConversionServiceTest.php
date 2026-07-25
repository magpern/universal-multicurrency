<?php
/**
 * Unit tests for the display-price conversion seam.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\PriceConversionService;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Covers the passthrough/base rules via the pure convert_to() path.
 */
final class PriceConversionServiceTest extends TestCase {

	private function service(): PriceConversionService {
		$settings = new Settings( array( 'currencies' => array( 'SEK' => array( 'rate' => '11.50' ) ) ) );
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context  = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );

		return new PriceConversionService( $context );
	}

	public function test_empty_string_passes_through_unchanged(): void {
		$this->assertSame( '', $this->service()->convert_to( '', new Currency( 'SEK', 2 ), '11.5' ) );
	}

	public function test_null_passes_through_unchanged(): void {
		$this->assertNull( $this->service()->convert_to( null, new Currency( 'SEK', 2 ), '11.5' ) );
	}

	public function test_non_numeric_passes_through_unchanged(): void {
		$this->assertSame( 'abc', $this->service()->convert_to( 'abc', new Currency( 'SEK', 2 ), '11.5' ) );
	}

	public function test_base_target_is_a_no_op(): void {
		$this->assertSame( '100', $this->service()->convert_to( '100', new Currency( 'EUR', 2 ), '1' ) );
	}

	public function test_non_base_target_is_converted(): void {
		$this->assertSame( '1150.00', $this->service()->convert_to( '100', new Currency( 'SEK', 2 ), '11.5' ) );
	}

	public function test_zero_decimal_target_is_converted(): void {
		$this->assertSame( '16100', $this->service()->convert_to( '100', new Currency( 'JPY', 0 ), '161' ) );
	}

	public function test_zero_amount_is_converted_not_passed_through(): void {
		$this->assertSame( '0.00', $this->service()->convert_to( '0', new Currency( 'SEK', 2 ), '11.5' ) );
	}

	public function test_convert_amount_is_a_string_no_op_when_base_is_active(): void {
		// No request state in a unit context, so the active currency is the base:
		// convert_amount() must return the amount unchanged, as a string.
		$this->assertSame( '100', $this->service()->convert_amount( '100' ) );
		$this->assertSame( '100', $this->service()->convert_amount( 100 ) );
	}

	public function test_convert_amount_passes_non_numeric_through_as_string(): void {
		$this->assertSame( 'abc', $this->service()->convert_amount( 'abc' ) );
	}
}
