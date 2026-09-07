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
use UMC\User\PreferredCurrency;
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
		wp_set_current_user( 0 );
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

	public function test_session_wins_over_logged_in_user_preference(): void {
		$this->save_settings( true );
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, PreferredCurrency::META_KEY, 'SEK' );
		WC()->session->set( CurrencyContext::SESSION_KEY, 'EUR' );

		$this->assertSame( 'EUR', $this->preference_context()->get_active_code() );
	}

	public function test_cookie_wins_over_logged_in_user_preference_without_session(): void {
		$this->save_settings( true );
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, PreferredCurrency::META_KEY, 'EUR' );
		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = 'SEK';

		$this->assertSame( 'SEK', $this->preference_context()->get_active_code() );
	}

	public function test_valid_user_preference_counts_as_existing_geo_source(): void {
		$this->save_settings( true );
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, PreferredCurrency::META_KEY, 'SEK' );

		$this->assertTrue( $this->preference_context()->has_valid_shopper_currency_source() );
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

	private function preference_context(): CurrencyContext {
		$settings   = new Settings();
		$registry   = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates      = new ManualRateProvider( $settings, 'EUR' );
		$preference = new PreferredCurrency( $registry, $rates );

		return new CurrencyContext( $registry, $rates, new CurrencyResolver(), $preference );
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
