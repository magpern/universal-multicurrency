<?php
/**
 * Integration tests: payment-gateway currency compatibility filtering.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\GatewayCompatibility;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Gateways declaring an unsupported currency are hidden; gateways are untouched
 * by default.
 */
final class GatewayCompatibilityTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_available_payment_gateways',
		'umc_gateway_supported_currencies',
	);

	public function tear_down(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	/**
	 * Registers gateway filtering and forces the active currency.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code.
	 */
	private function activate( array $currencies, string $active ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		update_option( 'woocommerce_currency', 'EUR' );

		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );

		( new GatewayCompatibility( $context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	public function test_incompatible_gateway_is_hidden(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		add_filter(
			'umc_gateway_supported_currencies',
			static fn ( $codes, $gateway ) => 'only_eur' === $gateway->id ? array( 'EUR' ) : $codes,
			10,
			2
		);

		$gateways = apply_filters(
			'woocommerce_available_payment_gateways',
			array(
				'only_eur' => (object) array( 'id' => 'only_eur' ),
				'any'      => (object) array( 'id' => 'any' ),
			)
		);

		$this->assertArrayNotHasKey( 'only_eur', $gateways );
		$this->assertArrayHasKey( 'any', $gateways );
	}

	public function test_gateways_untouched_by_default(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$gateways = apply_filters( 'woocommerce_available_payment_gateways', array( 'a' => (object) array( 'id' => 'a' ) ) );

		$this->assertArrayHasKey( 'a', $gateways );
	}
}
