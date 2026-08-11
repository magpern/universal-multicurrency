<?php
/**
 * Integration tests for shopper currency provenance in a real WC session.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Display;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\CurrencySwitcher;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies umc_currency_origin write/overwrite/clear against a live WC session.
 */
final class CurrencyProvenanceIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}
	}

	public function tear_down(): void {
		unset( $_GET[ CurrencyContext::QUERY_VAR ], $_COOKIE[ CurrencyContext::COOKIE_NAME ] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( CurrencyContext::SESSION_KEY, null );
			WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, null );
			WC()->session->set( CurrencySwitcher::SESSION_MANUAL_SELECTION, null );
		}

		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_customer_persist_records_customer_provenance(): void {
		$this->save_settings();

		$this->persist_without_cookie_notice( $this->switcher(), 'SEK', true );

		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_CUSTOMER,
			WC()->session->get( CurrencySwitcher::SESSION_CURRENCY_ORIGIN )
		);
		$this->assertSame( '1', WC()->session->get( CurrencySwitcher::SESSION_MANUAL_SELECTION ) );
	}

	public function test_geo_persist_records_visitor_location_provenance(): void {
		$this->save_settings();

		$this->persist_without_cookie_notice( $this->switcher(), 'SEK', false );

		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_VISITOR_LOCATION,
			WC()->session->get( CurrencySwitcher::SESSION_CURRENCY_ORIGIN )
		);
		$this->assertEmpty( WC()->session->get( CurrencySwitcher::SESSION_MANUAL_SELECTION ) );
	}

	public function test_provenance_does_not_affect_currency_resolution(): void {
		$this->save_settings();
		$resolver = new CurrencyResolver();

		$this->persist_without_cookie_notice( $this->switcher(), 'SEK', false );
		$session = WC()->session->get( CurrencyContext::SESSION_KEY );

		$resolved = $resolver->resolve( null, $session, null, 'EUR', array( 'SEK' ) );
		$this->assertSame( 'SEK', $resolved );

		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, CurrencySwitcher::ORIGIN_CUSTOMER );
		$resolved_after_origin_flip = $resolver->resolve( null, $session, null, 'EUR', array( 'SEK' ) );
		$this->assertSame( 'SEK', $resolved_after_origin_flip );

		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, 'tampered' );
		$resolved_after_tamper = $resolver->resolve( null, $session, null, 'EUR', array( 'SEK' ) );
		$this->assertSame( 'SEK', $resolved_after_tamper );
	}

	public function test_overwrite_and_clear_provenance_leave_currency_intact_when_cleared(): void {
		$this->save_settings();
		$switcher = $this->switcher();

		$this->persist_without_cookie_notice( $switcher, 'SEK', false );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_VISITOR_LOCATION,
			CurrencySwitcher::read_currency_origin()
		);

		$this->persist_without_cookie_notice( $switcher, 'USD', true );
		$this->assertSame( 'USD', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_CUSTOMER,
			CurrencySwitcher::read_currency_origin()
		);

		$switcher->clear_currency_origin();
		$this->assertSame( 'USD', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertNull( CurrencySwitcher::read_currency_origin() );
	}

	/**
	 * @param CurrencySwitcher $switcher Switcher under test.
	 * @param string           $code     Currency code.
	 * @param bool             $manual   Manual selection flag.
	 */
	private function persist_without_cookie_notice( CurrencySwitcher $switcher, string $code, bool $manual ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Suppresses wc_setcookie notices after PHPUnit bootstrap sends headers.
		$previous = set_error_handler(
			static function ( int $errno, string $errstr ): bool {
				if ( E_USER_NOTICE === $errno && str_contains( $errstr, 'cookie cannot be set' ) ) {
					return true;
				}

				return false;
			}
		);

		try {
			$switcher->persist( $code, $manual );
		} finally {
			if ( false !== $previous ) {
				restore_error_handler();
			}
		}
	}

	private function switcher(): CurrencySwitcher {
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context  = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );

		return new CurrencySwitcher( $context, new SwitcherSettingsRepository( $settings ) );
	}

	private function save_settings(): void {
		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'SEK' => array(
							'manual_rate' => '11.50',
						),
						'USD' => array(
							'manual_rate' => '1.10',
						),
					),
					'display'    => array_merge(
						SwitcherSettings::default_array(),
						array(
							'behavior' => array(
								'remember_selection' => true,
								'active_first'       => true,
							),
						)
					),
				)
			)
		);
	}
}
