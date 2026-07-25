<?php
/**
 * Unit tests for the currency registry.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Settings;

/**
 * Tests base-currency injection and assembly of configured currencies.
 */
final class CurrencyRegistryTest extends TestCase {

	private function registry( ?Currency $base = null ): CurrencyRegistry {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled' => true,
						'rate'    => '11.50',
					),
					'JPY' => array(
						'enabled'  => false,
						'decimals' => 0,
						'rate'     => '161',
					),
				),
			)
		);

		return new CurrencyRegistry( $settings, $base ?? new Currency( 'EUR', 2 ) );
	}

	public function test_base_is_present_even_when_absent_from_settings(): void {
		$registry = $this->registry();
		$this->assertTrue( $registry->has_currency( 'EUR' ) );
		$this->assertSame( 'EUR', $registry->get_base_code() );
		$this->assertTrue( $registry->is_base( 'eur' ) );
	}

	public function test_base_is_always_enabled_even_if_injected_disabled(): void {
		$registry = $this->registry( new Currency( 'EUR', 2, '', 'left', false ) );
		$this->assertTrue( $registry->get_base_currency()->is_enabled() );
		$codes = array_map( static fn ( Currency $c ): string => $c->code(), $registry->get_enabled_currencies() );
		$this->assertContains( 'EUR', $codes );
	}

	public function test_get_currency_returns_configured_currency(): void {
		$sek = $this->registry()->get_currency( 'SEK' );
		$this->assertNotNull( $sek );
		$this->assertSame( 'SEK', $sek->code() );
	}

	public function test_get_currency_is_null_for_unknown_code(): void {
		$this->assertNull( $this->registry()->get_currency( 'GBP' ) );
		$this->assertFalse( $this->registry()->has_currency( 'GBP' ) );
	}

	public function test_get_currencies_includes_base_first(): void {
		$codes = array_map( static fn ( Currency $c ): string => $c->code(), $this->registry()->get_currencies() );
		$this->assertSame( 'EUR', $codes[0] );
		$this->assertContains( 'SEK', $codes );
		$this->assertContains( 'JPY', $codes );
	}

	public function test_enabled_filtering_excludes_disabled_currencies(): void {
		$codes = array_map( static fn ( Currency $c ): string => $c->code(), $this->registry()->get_enabled_currencies() );
		$this->assertContains( 'EUR', $codes );
		$this->assertContains( 'SEK', $codes );
		$this->assertNotContains( 'JPY', $codes ); // Disabled.
	}

	public function test_same_code_settings_row_does_not_override_base_identity(): void {
		$settings = new Settings(
			array(
				'currencies' => array(
					'EUR' => array(
						'enabled'  => false,
						'decimals' => 4,
						'symbol'   => 'INJECTED',
						'rate'     => '2',
					),
				),
			)
		);
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2, '€', 'left', true ) );

		$base = $registry->get_base_currency();
		$this->assertSame( 2, $base->decimals() );
		$this->assertSame( '€', $base->symbol() );
		$this->assertTrue( $base->is_enabled() );

		// The base appears exactly once, from the injected identity.
		$eur_count = count(
			array_filter(
				$registry->get_currencies(),
				static fn ( Currency $c ): bool => 'EUR' === $c->code()
			)
		);
		$this->assertSame( 1, $eur_count );
	}

	public function test_registry_uses_injected_base_not_any_option(): void {
		// A base that would never be a store default proves nothing external is read.
		$registry = $this->registry( new Currency( 'GBP', 2 ) );
		$this->assertSame( 'GBP', $registry->get_base_code() );
		$this->assertTrue( $registry->is_base( 'GBP' ) );
		$this->assertFalse( $registry->is_base( 'EUR' ) );
	}
}
