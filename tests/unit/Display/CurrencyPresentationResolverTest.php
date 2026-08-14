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
				'EUR' => CurrencyPresentationAssetRegistry::REGION_SE,
			)
		);

		$this->assertSame( CurrencyPresentationAssetRegistry::REGION_SE, $resolver->region_for_currency( 'EUR' ) );
	}

	public function test_unmapped_currency_returns_null(): void {
		$resolver = new CurrencyPresentationResolver();

		$this->assertNull( $resolver->region_for_currency( 'JPY' ) );
		$this->assertNull( $resolver->asset_url_for_currency( 'JPY' ) );
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
}
