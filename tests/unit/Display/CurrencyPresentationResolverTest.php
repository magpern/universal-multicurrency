<?php
/**
 * Unit tests for currency presentation mapping resolution.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\CurrencyPresentationAssetRegistry;
use UMC\Display\CurrencyPresentationResolver;
use UMC\Display\SwitcherSettings;

/**
 * Covers override precedence and graceful omission.
 */
final class CurrencyPresentationResolverTest extends TestCase {

	protected function setUp(): void {
		if ( ! defined( 'UMC_PLUGIN_FILE' ) ) {
			define( 'UMC_PLUGIN_FILE', dirname( __DIR__, 3 ) . '/universal-multicurrency.php' );
		}

		if ( ! defined( 'UMC_VERSION' ) ) {
			define( 'UMC_VERSION', '0.21.0' );
		}
	}

	public function test_builtin_defaults_include_eur_to_eu(): void {
		$this->assertSame(
			CurrencyPresentationAssetRegistry::REGION_EU,
			CurrencyPresentationResolver::built_in_region_for_currency( 'EUR' )
		);
	}

	public function test_merchant_override_takes_precedence(): void {
		$resolver = new CurrencyPresentationResolver(
			array(
				'USD' => CurrencyPresentationAssetRegistry::REGION_SE,
			)
		);

		$this->assertSame( CurrencyPresentationAssetRegistry::REGION_SE, $resolver->region_for_currency( 'USD' ) );
	}

	public function test_eur_override_is_ignored_at_presentation_time(): void {
		$resolver = new CurrencyPresentationResolver(
			array(
				'EUR' => CurrencyPresentationAssetRegistry::REGION_SE,
			)
		);

		$this->assertSame(
			CurrencyPresentationAssetRegistry::REGION_EU,
			$resolver->region_for_currency( 'EUR' )
		);
	}

	public function test_unmapped_currency_returns_null(): void {
		$resolver = new CurrencyPresentationResolver();

		$this->assertNull( $resolver->region_for_currency( 'XOF' ) );
		$this->assertNull( $resolver->asset_url_for_currency( 'XOF' ) );
	}

	public function test_invalid_override_is_ignored_at_settings_layer(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'presentation' => array(
					'icon_overrides' => array(
						'EUR' => 'NOT_A_REGION',
					),
				),
			)
		);

		$resolver = CurrencyPresentationResolver::from_settings( $settings );

		$this->assertSame( CurrencyPresentationAssetRegistry::REGION_EU, $resolver->region_for_currency( 'EUR' ) );
	}

	public function test_disabled_currency_override_is_retained_in_settings(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'presentation' => array(
					'icon_overrides' => array(
						'SEK' => CurrencyPresentationAssetRegistry::REGION_NO,
					),
				),
			)
		);

		$this->assertSame(
			CurrencyPresentationAssetRegistry::REGION_NO,
			$settings->icon_overrides()['SEK']
		);
	}

	public function test_stored_eur_override_is_preserved_but_not_applied(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'presentation' => array(
					'icon_overrides' => array(
						'EUR' => CurrencyPresentationAssetRegistry::REGION_SE,
					),
				),
			)
		);

		$this->assertSame(
			CurrencyPresentationAssetRegistry::REGION_SE,
			$settings->icon_overrides()['EUR']
		);

		$resolver = CurrencyPresentationResolver::from_settings( $settings );
		$this->assertSame( CurrencyPresentationAssetRegistry::REGION_EU, $resolver->region_for_currency( 'EUR' ) );
	}

	public function test_posted_eur_override_does_not_change_presentation_region(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'presentation' => array(
					'icon_overrides' => array(
						'EUR' => CurrencyPresentationAssetRegistry::REGION_DK,
					),
				),
			)
		);

		$resolver = CurrencyPresentationResolver::from_settings( $settings );
		$this->assertSame( CurrencyPresentationAssetRegistry::REGION_EU, $resolver->region_for_currency( 'EUR' ) );
	}

	/**
	 * @dataProvider builtin_mapping_provider
	 */
	public function test_builtin_mappings( string $code, string $region ): void {
		$this->assertSame( $region, CurrencyPresentationResolver::built_in_region_for_currency( $code ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function builtin_mapping_provider(): array {
		return array(
			'SEK' => array( 'SEK', CurrencyPresentationAssetRegistry::REGION_SE ),
			'USD' => array( 'USD', CurrencyPresentationAssetRegistry::REGION_US ),
			'GBP' => array( 'GBP', CurrencyPresentationAssetRegistry::REGION_GB ),
			'NOK' => array( 'NOK', CurrencyPresentationAssetRegistry::REGION_NO ),
			'DKK' => array( 'DKK', CurrencyPresentationAssetRegistry::REGION_DK ),
			'CHF' => array( 'CHF', CurrencyPresentationAssetRegistry::REGION_CH ),
			'JPY' => array( 'JPY', CurrencyPresentationAssetRegistry::REGION_JP ),
			'CAD' => array( 'CAD', CurrencyPresentationAssetRegistry::REGION_CA ),
			'AUD' => array( 'AUD', CurrencyPresentationAssetRegistry::REGION_AU ),
			'NZD' => array( 'NZD', CurrencyPresentationAssetRegistry::REGION_NZ ),
			'KRW' => array( 'KRW', CurrencyPresentationAssetRegistry::REGION_KR ),
			'CNY' => array( 'CNY', CurrencyPresentationAssetRegistry::REGION_CN ),
			'INR' => array( 'INR', CurrencyPresentationAssetRegistry::REGION_IN ),
			'BRL' => array( 'BRL', CurrencyPresentationAssetRegistry::REGION_BR ),
			'MXN' => array( 'MXN', CurrencyPresentationAssetRegistry::REGION_MX ),
			'SGD' => array( 'SGD', CurrencyPresentationAssetRegistry::REGION_SG ),
			'HKD' => array( 'HKD', CurrencyPresentationAssetRegistry::REGION_HK ),
			'ZAR' => array( 'ZAR', CurrencyPresentationAssetRegistry::REGION_ZA ),
			'CZK' => array( 'CZK', CurrencyPresentationAssetRegistry::REGION_CZ ),
		);
	}

	public function test_supranational_codes_have_no_flag(): void {
		$resolver = new CurrencyPresentationResolver();

		foreach ( array( 'XOF', 'XAF', 'XCD', 'XPF', 'XDR', 'XAU', 'XAG', 'XPT', 'XPD' ) as $code ) {
			$this->assertNull( $resolver->region_for_currency( $code ), $code );
		}
	}

	public function test_unknown_currency_has_no_flag(): void {
		$resolver = new CurrencyPresentationResolver();

		$this->assertNull( $resolver->region_for_currency( 'ZZZ' ) );
		$this->assertNull( $resolver->asset_url_for_currency( 'ZZZ' ) );
	}

	public function test_geo_is_not_consulted_for_presentation_region(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Display/CurrencyPresentationResolver.php' );

		$this->assertStringNotContainsString( 'GeoDetection', $source );
		$this->assertStringNotContainsString( 'get_country', $source );
		$this->assertStringNotContainsString( 'billing_country', $source );
	}
}
