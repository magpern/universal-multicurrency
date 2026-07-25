<?php
/**
 * Unit tests for the WordPress-free parts of the currency context.
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
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Covers the selectable allow-list logic (enabled and rated, plus base).
 */
final class CurrencyContextTest extends TestCase {

	private function context(): CurrencyContext {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled' => true,
						'rate'    => '11.50',
					),
					'JPY' => array(
						'enabled'  => true,
						'decimals' => 0,
						'rate'     => '',
					),   // Enabled but no rate.
					'NOK' => array(
						'enabled' => false,
						'rate'    => '11.00',
					),               // Rated but disabled.
				),
			)
		);
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		return new CurrencyContext( $registry, $rates, new CurrencyResolver() );
	}

	public function test_selectable_includes_base_and_enabled_rated_only(): void {
		$codes = $this->context()->get_selectable_codes();
		sort( $codes );

		$this->assertSame( array( 'EUR', 'SEK' ), $codes );
	}

	public function test_selectable_excludes_enabled_but_rateless(): void {
		$this->assertNotContains( 'JPY', $this->context()->get_selectable_codes() );
	}

	public function test_selectable_excludes_disabled_even_if_rated(): void {
		$this->assertNotContains( 'NOK', $this->context()->get_selectable_codes() );
	}

	public function test_base_currency_is_exposed(): void {
		$this->assertSame( 'EUR', $this->context()->get_base_currency()->code() );
	}
}
