<?php
/**
 * Unit tests for the monetary converter — the precision core.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UMC\Converter;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Exceptions\InvalidRateException;
use UMC\Exceptions\MissingRateException;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Tests conversion, rounding boundaries, no-op base behaviour and determinism.
 */
final class ConverterTest extends TestCase {

	private function converter(): Converter {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'decimals' => 2,
						'rate'     => '11.50',
					),
					'JPY' => array(
						'decimals' => 0,
						'rate'     => '161',
					),
					'USD' => array(
						'decimals' => 2,
						'rate'     => '', // Configured but no usable rate.
					),
				),
			)
		);
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		return new Converter( $rates, $registry );
	}

	/**
	 * @dataProvider apply_rate_cases
	 */
	public function test_apply_rate( string $amount, string $rate, int $decimals, string $expected ): void {
		$this->assertSame( $expected, Converter::apply_rate( $amount, $rate, $decimals ) );
	}

	/**
	 * Expected values verified against PHP's deterministic round(HALF_UP),
	 * which is the same arithmetic WooCommerce uses.
	 *
	 * @return array<string, array{0: string, 1: string, 2: int, 3: string}>
	 */
	public static function apply_rate_cases(): array {
		return array(
			'standard 2dp'       => array( '100', '11.5', 2, '1150.00' ),
			'jpy 0dp'            => array( '100', '147.3', 0, '14730' ),
			'zero 2dp'           => array( '0', '11.5', 2, '0.00' ),
			'zero 0dp'           => array( '0', '161', 0, '0' ),
			'negative refund'    => array( '-100', '11.5', 2, '-1150.00' ),
			'large value'        => array( '1000000', '11.5', 2, '11500000.00' ),
			'small value'        => array( '0.01', '11.5', 2, '0.12' ),
			'tiny to zero jpy'   => array( '0.001', '161', 0, '0' ),
			'boundary 2.675'     => array( '2.675', '1', 2, '2.68' ),
			'boundary 10.005'    => array( '10.005', '1', 2, '10.01' ),
			'boundary 0.125'     => array( '0.125', '1', 2, '0.13' ),
			'boundary 1.005'     => array( '1.005', '1', 2, '1.01' ),
			'half up 2.5 to 3'   => array( '2.5', '1', 0, '3' ),
			'half up 1.5 to 2'   => array( '1.5', '1', 0, '2' ),
			'half up -2.5 to -3' => array( '-2.5', '1', 0, '-3' ),
		);
	}

	public function test_round_to_string_formats_fixed_decimals(): void {
		$this->assertSame( '100.00', Converter::round_to_string( '100', 2 ) );
		$this->assertSame( '100', Converter::round_to_string( '100', 0 ) );
		$this->assertSame( '-11.50', Converter::round_to_string( '-11.5', 2 ) );
	}

	public function test_apply_rate_rejects_non_positive_or_non_numeric_rate(): void {
		$this->expectException( InvalidRateException::class );
		Converter::apply_rate( '100', '0', 2 );
	}

	public function test_apply_rate_rejects_negative_rate(): void {
		$this->expectException( InvalidRateException::class );
		Converter::apply_rate( '100', '-5', 2 );
	}

	public function test_apply_rate_rejects_non_numeric_amount(): void {
		$this->expectException( InvalidArgumentException::class );
		Converter::apply_rate( 'abc', '11.5', 2 );
	}

	public function test_convert_standard(): void {
		$this->assertSame( '1150.00', $this->converter()->convert( '100', 'SEK' ) );
	}

	public function test_convert_zero_decimal_currency(): void {
		$this->assertSame( '16100', $this->converter()->convert( '100', 'JPY' ) );
	}

	public function test_convert_to_base_is_no_op(): void {
		$this->assertSame( '100.00', $this->converter()->convert( '100', 'EUR' ) );
		$this->assertSame( '100.00', $this->converter()->convert( '100.00', 'EUR' ) );
		$this->assertSame( '0.00', $this->converter()->convert( '0', 'EUR' ) );
		$this->assertSame( '-42.50', $this->converter()->convert( '-42.5', 'EUR' ) );
	}

	public function test_convert_to_base_introduces_no_drift_when_repeated(): void {
		$converter = $this->converter();
		$value     = '199.99';
		for ( $i = 0; $i < 5; $i++ ) {
			$value = $converter->convert( $value, 'EUR' );
		}
		$this->assertSame( '199.99', $value );
	}

	public function test_convert_is_deterministic(): void {
		$converter = $this->converter();
		$this->assertSame(
			$converter->convert( '100', 'SEK' ),
			$converter->convert( '100', 'SEK' )
		);
	}

	public function test_convert_missing_rate_fails_explicitly(): void {
		$this->expectException( MissingRateException::class );
		$this->converter()->convert( '100', 'USD' ); // Configured but blank rate.
	}

	public function test_convert_unknown_currency_fails_explicitly(): void {
		$this->expectException( MissingRateException::class );
		$this->converter()->convert( '100', 'GBP' ); // Not configured at all.
	}

	public function test_convert_rejects_non_numeric_amount(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->converter()->convert( 'abc', 'SEK' );
	}
}
