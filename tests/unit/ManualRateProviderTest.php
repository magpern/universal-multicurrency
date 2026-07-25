<?php
/**
 * Unit tests for the manual exchange-rate provider.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Tests rate resolution against an in-memory settings store.
 */
final class ManualRateProviderTest extends TestCase {

	private function provider(): ManualRateProvider {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array( 'rate' => '11.50' ),
					'JPY' => array(
						'rate'     => '161',
						'decimals' => 0,
					),
					'USD' => array( 'rate' => 'bad' ), // Blanked by sanitize.
				),
			)
		);

		return new ManualRateProvider( $settings, 'EUR' );
	}

	public function test_base_to_base_is_one(): void {
		$this->assertSame( '1', $this->provider()->get_rate( 'EUR', 'EUR' ) );
	}

	public function test_same_currency_is_one_even_when_not_base(): void {
		$this->assertSame( '1', $this->provider()->get_rate( 'SEK', 'SEK' ) );
	}

	public function test_configured_base_to_target_rate_is_returned_verbatim(): void {
		$this->assertSame( '11.50', $this->provider()->get_rate( 'EUR', 'SEK' ) );
		$this->assertSame( '161', $this->provider()->get_rate( 'EUR', 'JPY' ) );
	}

	public function test_missing_target_returns_null(): void {
		$this->assertNull( $this->provider()->get_rate( 'EUR', 'GBP' ) );
	}

	public function test_blanked_invalid_rate_returns_null(): void {
		$this->assertNull( $this->provider()->get_rate( 'EUR', 'USD' ) );
	}

	public function test_non_base_source_returns_null(): void {
		$this->assertNull( $this->provider()->get_rate( 'SEK', 'JPY' ) );
	}

	public function test_codes_are_case_insensitive(): void {
		$this->assertSame( '11.50', $this->provider()->get_rate( 'eur', 'sek' ) );
	}

	public function test_has_rate_mirrors_get_rate(): void {
		$provider = $this->provider();
		$this->assertTrue( $provider->has_rate( 'EUR', 'SEK' ) );
		$this->assertTrue( $provider->has_rate( 'EUR', 'EUR' ) );
		$this->assertFalse( $provider->has_rate( 'EUR', 'GBP' ) );
		$this->assertFalse( $provider->has_rate( 'EUR', 'USD' ) );
	}
}
