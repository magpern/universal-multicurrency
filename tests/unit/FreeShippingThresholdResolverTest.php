<?php
/**
 * Unit tests for FreeShippingThresholdResolver.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\FreeShippingThresholdResolver;
use UMC\Integration\PriceConversionService;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Pure resolution rules without WooCommerce cart.
 */
final class FreeShippingThresholdResolverTest extends TestCase {

	/**
	 * @return array{0: FreeShippingThresholdResolver, 1: CurrencyContext}
	 */
	private function graph( string $active = 'EUR' ): array {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'rate'     => '11.0625',
						'enabled'  => true,
						'decimals' => 2,
					),
					'JPY' => array(
						'rate'     => '160',
						'enabled'  => true,
						'decimals' => 0,
					),
				),
			)
		);
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		if ( 'EUR' !== $active ) {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
		} else {
			unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		}

		return array( new FreeShippingThresholdResolver( $service, $context ), $context );
	}

	protected function tearDown(): void {
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		parent::tearDown();
	}

	public function test_rejects_empty_and_non_numeric_and_negative(): void {
		[ $resolver ] = $this->graph();

		$this->assertNull( $resolver->resolve( '' ) );
		$this->assertNull( $resolver->resolve( 'abc' ) );
		$this->assertNull( $resolver->resolve( '-1' ) );
	}

	public function test_base_active_returns_canonical_base_amount(): void {
		[ $resolver ] = $this->graph( 'EUR' );
		$result       = $resolver->resolve( '200.5' );

		$this->assertNotNull( $result );
		$this->assertSame( '200.50', $result->amount() );
		$this->assertSame( 'EUR', $result->currency_code() );
	}

	public function test_foreign_uses_price_conversion_service(): void {
		[ $resolver ] = $this->graph( 'SEK' );
		$result       = $resolver->resolve( '200.00' );

		$this->assertNotNull( $result );
		$this->assertSame( '2212.50', $result->amount() );
		$this->assertSame( 'SEK', $result->currency_code() );
	}

	public function test_exceeds_base_precision_detects_option_a_inputs(): void {
		[ $resolver ] = $this->graph( 'EUR' );

		$this->assertFalse( $resolver->exceeds_base_precision( '200.50' ) );
		$this->assertTrue( $resolver->exceeds_base_precision( '200.001' ) );
		$this->assertFalse( $resolver->exceeds_base_precision( '200' ) );
	}

	public function test_valid_base_fraction_ok_for_zero_decimal_target_path(): void {
		[ $resolver ] = $this->graph( 'JPY' );
		$result       = $resolver->resolve( '200.50' );

		$this->assertNotNull( $result );
		$this->assertSame( 'JPY', $result->currency_code() );
		$this->assertMatchesRegularExpression( '/^\d+$/', $result->amount() );
	}
}
