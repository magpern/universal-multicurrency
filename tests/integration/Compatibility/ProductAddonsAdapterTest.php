<?php
/**
 * Product Add-Ons adapter integration tests (E2 simulated hooks).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Compatibility;

use UMC\Compatibility\Extension\Adapters\ProductAddonsAdapter;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\PriceConversionService;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Integration tests for Product Add-Ons price seam (E2).
 */
final class ProductAddonsAdapterTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'umc_test_extension_product_addons_price_raw' );
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_simulated_addon_price_converts_once(): void {
		$this->activate();

		( new ProductAddonsAdapter( $this->service(), $this->context() ) )->register();

		$converted = apply_filters( 'umc_test_extension_product_addons_price_raw', '10' );

		$this->assertEqualsWithDelta( 115.0, (float) $converted, 0.001 );
	}

	public function test_opt_out_filter_preserves_raw_addon_price(): void {
		$this->activate();

		( new ProductAddonsAdapter( $this->service(), $this->context() ) )->register();

		add_filter(
			'umc_convert_product_addon_price',
			static function (): bool {
				return false;
			}
		);

		$converted = apply_filters( 'umc_test_extension_product_addons_price_raw', '10' );

		$this->assertEqualsWithDelta( 10.0, (float) $converted, 0.001 );
	}

	private function activate(): void {
		update_option( 'woocommerce_currency', 'EUR' );
		( new Settings() )->save( array( 'currencies' => array( 'SEK' => array( 'rate' => '11.50' ) ) ) );
		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = 'SEK';
	}

	private function context(): CurrencyContext {
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		return new CurrencyContext( $registry, $rates, new CurrencyResolver() );
	}

	private function service(): PriceConversionService {
		return new PriceConversionService( $this->context() );
	}
}
