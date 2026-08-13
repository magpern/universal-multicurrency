<?php
/**
 * Subscriptions adapter integration tests (E2 simulated hooks).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Compatibility;

use UMC\Compatibility\Extension\Adapters\SubscriptionsAdapter;
use UMC\Compatibility\Extension\ExtensionCompatibilityContext;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Integration tests for Subscriptions renewal context isolation (E2).
 */
final class SubscriptionsAdapterTest extends WP_UnitTestCase {

	public function tear_down(): void {
		ExtensionCompatibilityContext::reset();
		remove_all_filters( 'umc_should_convert_product_price' );
		remove_all_actions( 'umc_test_extension_subscriptions_renewal_start' );
		remove_all_actions( 'umc_test_extension_subscriptions_renewal_end' );
		unset( $_COOKIE[ \UMC\CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_renewal_context_suppresses_product_price_conversion(): void {
		$this->boot_graph();

		$adapter = new SubscriptionsAdapter(
			$this->service(),
			$this->context(),
			null
		);
		$adapter->register();

		$this->assertTrue( apply_filters( 'umc_should_convert_product_price', true ) );

		do_action( 'umc_test_extension_subscriptions_renewal_start', 'USD' );

		$this->assertFalse( apply_filters( 'umc_should_convert_product_price', true ) );

		do_action( 'umc_test_extension_subscriptions_renewal_end' );

		$this->assertTrue( apply_filters( 'umc_should_convert_product_price', true ) );
	}

	public function test_browsing_currency_does_not_drive_renewal_context_currency(): void {
		$this->boot_graph();
		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = 'SEK';

		$adapter = new SubscriptionsAdapter(
			$this->service(),
			$this->context(),
			null
		);
		$adapter->register();

		do_action( 'umc_test_extension_subscriptions_renewal_start', 'USD' );

		$this->assertSame( 'USD', ExtensionCompatibilityContext::renewal_currency() );
		$this->assertNotSame(
			'SEK',
			ExtensionCompatibilityContext::renewal_currency(),
			'Renewal currency must come from subscription context, not browsing cookie.'
		);
	}

	private function boot_graph(): void {
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
