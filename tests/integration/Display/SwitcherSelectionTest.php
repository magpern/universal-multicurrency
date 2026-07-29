<?php
/**
 * Integration tests for storefront currency selection persistence.
 *
 * Cookie write/delete side effects are covered in unit tests because PHPUnit
 * bootstrap sends headers before wc_setcookie() can run.
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
 * Covers remember-selection session persistence and invalid switch rejection.
 */
final class SwitcherSelectionTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( CurrencyContext::SESSION_KEY, null );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
	}

	public function tear_down(): void {
		unset( $_GET[ CurrencyContext::QUERY_VAR ], $_COOKIE[ CurrencyContext::COOKIE_NAME ] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( CurrencyContext::SESSION_KEY, null );
		}

		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_valid_currency_is_persisted_to_wc_session_when_remember_enabled(): void {
		$this->save_settings( true );

		$this->persist_without_cookie_notice( $this->switcher( true ), 'SEK' );

		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	public function test_invalid_currency_is_not_persisted_via_maybe_switch(): void {
		$this->save_settings( true );
		$_GET[ CurrencyContext::QUERY_VAR ] = 'XXX';

		$this->switcher( true )->maybe_switch();

		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertArrayNotHasKey( CurrencyContext::COOKIE_NAME, $_COOKIE );
	}

	public function test_remember_disabled_still_persists_session_on_switch(): void {
		$this->save_settings( false );
		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = 'SEK';

		$this->persist_without_cookie_notice( $this->switcher( false ), 'SEK' );

		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	private function persist_without_cookie_notice( CurrencySwitcher $switcher, string $code ): void {
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
			$switcher->persist( $code );
		} finally {
			if ( false !== $previous ) {
				restore_error_handler();
			}
		}
	}

	private function switcher( bool $remember ): CurrencySwitcher {
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context  = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );

		return new CurrencySwitcher( $context, new SwitcherSettingsRepository( $settings ) );
	}

	private function save_settings( bool $remember ): void {
		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'SEK' => array(
							'manual_rate' => '11.50',
						),
					),
					'display'    => array_merge(
						SwitcherSettings::default_array(),
						array(
							'behavior' => array(
								'remember_selection' => $remember,
								'active_first'       => true,
							),
						)
					),
				)
			)
		);
	}
}
