<?php
/**
 * Integration tests for the GeoDetectionApplicator storefront gating.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Geo;

use UMC\Checkout\CheckoutTransitionState;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\CurrencySwitcher;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Geo\CheckoutGeoPolicy;
use UMC\Geo\CountryContext;
use UMC\Geo\CountryContextResolver;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoDetectionApplicator;
use UMC\Geo\GeoDetectionSettings;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderCurrencyContext;
use UMC\Order\OrderSnapshotReader;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Unit\Doubles\FakeCountryContextProvider;
use WP_UnitTestCase;

// Test doubles never match *Test.php, so PHPUnit's directory-based discovery
// never loads them for the integration suite; required explicitly, matching
// how tests/unit/bootstrap.php loads this same double for the unit suite.
require_once dirname( __DIR__, 2 ) . '/unit/Doubles/FakeCountryContextProvider.php';

/**
 * Verifies GeoDetectionApplicator's storefront guard chain — the class that
 * decides whether a geo-routed currency is ever persisted for a visitor.
 *
 * All collaborators are real (every collaborator class in this plugin is
 * `final`, so mocking is not viable — the repository's own test pattern,
 * seen in GatewayCompatibilityTest and SwitcherSelectionTest, is to
 * construct genuine service graphs backed by a real persisted Settings
 * option and a real WC session). The only test double is
 * FakeCountryContextProvider, an existing double already used by
 * CountryContextResolverTest for exactly this purpose.
 *
 * Session flag names asserted here are read directly from the collaborators
 * that own them (GeoCurrencyDecisionService::SESSION_GEO_APPLIED,
 * ::SESSION_GEO_SESSION_DONE, CurrencySwitcher::SESSION_MANUAL_SELECTION) —
 * not guessed — so a rename in either class breaks this test rather than
 * silently testing nothing.
 */
final class GeoDetectionApplicatorTest extends WP_UnitTestCase {

	private const BASE = 'USD';

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', self::BASE );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}
	}

	public function tear_down(): void {
		unset( $_GET[ CurrencyContext::QUERY_VAR ], $_COOKIE[ CurrencyContext::COOKIE_NAME ], $_POST['billing_country'], $_POST['shipping_country'] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( CurrencyContext::SESSION_KEY, null );
			WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, null );
			WC()->session->set( CurrencySwitcher::SESSION_MANUAL_SELECTION, null );
		}

		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	/**
	 * An explicit `?currency=` request is a valid shopper currency source,
	 * so has_valid_shopper_currency_source() blocks geo before it ever runs.
	 * (The full precedence story also relies on CurrencySwitcher::maybe_switch()
	 * consuming the request and redirecting before the applicator runs at all —
	 * that hook ordering lives in Plugin.php, not in this class — but this
	 * class's own guard is what is asserted here.)
	 */
	public function test_explicit_currency_request_blocks_geo_application(): void {
		$this->save_settings( array( 'SEK' ) );
		$_GET[ CurrencyContext::QUERY_VAR ] = 'SEK';

		$applicator = $this->build_applicator( 'SE' );

		$applicator->maybe_apply();

		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	/**
	 * An existing valid session currency blocks geo from overwriting it.
	 */
	public function test_existing_valid_session_currency_blocks_geo_application(): void {
		$this->save_settings( array( 'SEK', 'JPY' ) );
		WC()->session->set( CurrencyContext::SESSION_KEY, 'JPY' );

		$applicator = $this->build_applicator( 'SE' );

		$applicator->maybe_apply();

		$this->assertSame( 'JPY', WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	/**
	 * The manual-selection marker blocks geo even when no currently valid
	 * shopper currency source exists — the guard is independent of
	 * has_valid_shopper_currency_source() and must not be short-circuited by it.
	 */
	public function test_manual_selection_marker_blocks_geo_even_without_a_valid_current_currency(): void {
		$this->save_settings( array( 'SEK' ) );
		// No session/cookie/explicit currency at all: has_valid_shopper_currency_source()
		// is false. Only the manual marker is set.
		WC()->session->set( CurrencySwitcher::SESSION_MANUAL_SELECTION, '1' );

		$applicator = $this->build_applicator( 'SE' );

		$applicator->maybe_apply();

		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	/**
	 * Checkout lock (lock_on_entry) blocks the initial geo application once
	 * checkout has been entered.
	 */
	public function test_checkout_lock_blocks_geo_application(): void {
		$this->save_settings( array( 'SEK' ), false, false, GeoDetectionSettings::MODE_FIRST_VISIT, true );

		$transition_repo = new CheckoutTransitionStateRepository();
		$transition_repo->save( new CheckoutTransitionState( 'selected', self::BASE, self::BASE ) );

		$applicator = $this->build_applicator( 'SE' );

		$applicator->maybe_apply();

		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ) );

		$transition_repo->clear();
	}

	/**
	 * Checkout lock also blocks maybe_reapply_on_checkout_update() when
	 * lock_on_entry is configured.
	 */
	public function test_checkout_lock_blocks_reapply_on_checkout_update(): void {
		$this->save_settings( array( 'SEK' ), true, true, GeoDetectionSettings::MODE_FIRST_VISIT, true );

		$transition_repo = new CheckoutTransitionStateRepository();
		$transition_repo->save( new CheckoutTransitionState( 'selected', self::BASE, self::BASE ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test simulates checkout AJAX payload.
		$_POST['billing_country'] = 'SE';

		$applicator = $this->build_applicator( 'SE' );

		$applicator->maybe_reapply_on_checkout_update();

		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ) );

		$transition_repo->clear();
	}

	/**
	 * First_visit mode: should_apply_for_mode() gates on
	 * GeoCurrencyDecisionService::SESSION_GEO_APPLIED. First call applies;
	 * mark_applied() sets the flag so a second call in the same session does not.
	 */
	public function test_first_visit_mode_applies_once_then_stops(): void {
		$this->save_settings( array( 'SEK', 'DKK' ), false, false, GeoDetectionSettings::MODE_FIRST_VISIT );

		$applicator = $this->build_applicator( 'SE' );

		$this->apply_without_cookie_notice( $applicator );
		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertNotEmpty( WC()->session->get( GeoCurrencyDecisionService::SESSION_GEO_APPLIED ) );

		// Simulate a second request: reset only the currency, keep session flags.
		WC()->session->set( CurrencyContext::SESSION_KEY, null );

		$applicator2 = $this->build_applicator( 'DK' );
		$applicator2->maybe_apply();

		// Second application did not run: SESSION_GEO_APPLIED already set.
		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	/**
	 * Session mode: should_apply_for_mode() gates on
	 * GeoCurrencyDecisionService::SESSION_GEO_SESSION_DONE specifically —
	 * a distinct flag from first_visit's, and mark_applied() only sets it
	 * for session mode.
	 */
	public function test_session_mode_applies_once_then_stops_via_its_own_flag(): void {
		$this->save_settings( array( 'SEK', 'DKK' ), false, false, GeoDetectionSettings::MODE_SESSION );

		$applicator = $this->build_applicator( 'SE' );

		$this->apply_without_cookie_notice( $applicator );
		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertNotEmpty( WC()->session->get( GeoCurrencyDecisionService::SESSION_GEO_SESSION_DONE ) );

		WC()->session->set( CurrencyContext::SESSION_KEY, null );

		$applicator2 = $this->build_applicator( 'DK' );
		$applicator2->maybe_apply();

		$this->assertNull( WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	/**
	 * Until_manual mode shares first_visit's SESSION_GEO_APPLIED gate for the
	 * main entry point (maybe_apply), but — unlike first_visit — its checkout
	 * re-evaluation path (maybe_reapply_on_checkout_update) bypasses that gate
	 * via force=true and instead keeps re-evaluating until a manual selection
	 * exists, at which point has_manual_currency_selection() blocks it.
	 */
	public function test_until_manual_mode_reapplies_on_checkout_update_until_manual_selection(): void {
		$this->save_settings( array( 'SEK', 'DKK' ), true, false, GeoDetectionSettings::MODE_UNTIL_MANUAL );

		// Initial application (first_visit-style gate consumes SESSION_GEO_APPLIED).
		$applicator = $this->build_applicator( 'SE' );
		$this->apply_without_cookie_notice( $applicator );
		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );

		// Country changes on checkout (billing) before manual selection: geo re-applies
		// because maybe_reapply_on_checkout_update() forces past the SESSION_GEO_APPLIED gate.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test simulates checkout AJAX payload.
		$_POST['billing_country'] = 'DK';

		$applicator2 = $this->build_applicator( 'DK' );
		$this->reapply_without_cookie_notice( $applicator2 );

		$this->assertSame( 'DKK', WC()->session->get( CurrencyContext::SESSION_KEY ) );

		// Now the shopper manually selects a currency.
		WC()->session->set( CurrencySwitcher::SESSION_MANUAL_SELECTION, '1' );

		$_POST['billing_country'] = 'SE';

		$applicator3 = $this->build_applicator( 'SE' );
		$applicator3->maybe_reapply_on_checkout_update();

		// Manual selection blocks further re-evaluation: currency unchanged.
		$this->assertSame( 'DKK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	/**
	 * A successful geo application persists via CurrencySwitcher::persist()
	 * with manual defaulting to false — the shopper must remain able to
	 * override it, so the manual-selection marker must NOT be set.
	 */
	public function test_successful_application_does_not_set_manual_selection_marker(): void {
		$this->save_settings( array( 'SEK' ) );

		$applicator = $this->build_applicator( 'SE' );

		$this->apply_without_cookie_notice( $applicator );

		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertEmpty( WC()->session->get( CurrencySwitcher::SESSION_MANUAL_SELECTION ) );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_VISITOR_LOCATION,
			WC()->session->get( CurrencySwitcher::SESSION_CURRENCY_ORIGIN )
		);
	}

	/**
	 * Calls maybe_apply() suppressing the "cookie cannot be set" notice that
	 * wc_setcookie() raises once PHPUnit's bootstrap has sent headers — the
	 * same constraint and workaround documented in SwitcherSelectionTest.
	 *
	 * @param GeoDetectionApplicator $applicator Applicator under test.
	 */
	private function apply_without_cookie_notice( GeoDetectionApplicator $applicator ): void {
		$this->suppressing_cookie_notice( static fn () => $applicator->maybe_apply() );
	}

	/**
	 * Calls maybe_reapply_on_checkout_update() with the same cookie-notice
	 * suppression as apply_without_cookie_notice().
	 *
	 * @param GeoDetectionApplicator $applicator Applicator under test.
	 */
	private function reapply_without_cookie_notice( GeoDetectionApplicator $applicator ): void {
		$this->suppressing_cookie_notice( static fn () => $applicator->maybe_reapply_on_checkout_update() );
	}

	/**
	 * Runs a callback with wc_setcookie()'s post-headers-sent notice suppressed.
	 *
	 * @param callable $callback Callback to invoke.
	 */
	private function suppressing_cookie_notice( callable $callback ): void {
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
			$callback();
		} finally {
			if ( false !== $previous ) {
				restore_error_handler();
			}
		}
	}

	/**
	 * Builds a GeoDetectionApplicator with real collaborators, reading the
	 * shared persisted Settings option written by save_settings().
	 *
	 * @param string $resolved_country Country code the fake provider resolves for this request.
	 */
	private function build_applicator( string $resolved_country ): GeoDetectionApplicator {
		$settings = new Settings();

		$settings_repo    = new GeoDetectionSettingsRepository( $settings );
		$decision_service = new GeoCurrencyDecisionService( $settings_repo );

		$provider         = new FakeCountryContextProvider(
			'fake',
			true,
			new CountryContext( $resolved_country, 'fake', 1.0 )
		);
		$country_resolver = new CountryContextResolver( array( $provider ) );

		$base_currency = new Currency( self::BASE, 2 );
		$registry      = new CurrencyRegistry( $settings, $base_currency );
		$rates         = new ManualRateProvider( $settings, self::BASE );
		$context       = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$switcher      = new CurrencySwitcher( $context, new SwitcherSettingsRepository( $settings ) );

		$order_context = new OrderCurrencyContext(
			new OrderSnapshotReader(),
			new HistoricalFormattingResolver( $registry )
		);

		$checkout_transition = new CheckoutTransitionStateRepository();
		$checkout_policy     = new CheckoutGeoPolicy();

		return new GeoDetectionApplicator(
			$settings_repo,
			$decision_service,
			$country_resolver,
			$context,
			$switcher,
			$registry,
			$order_context,
			$checkout_transition,
			$checkout_policy
		);
	}

	/**
	 * Persists merchant settings with SE→SEK / DK→DKK country rules (only for
	 * currencies present in $extra_currencies) and geo enabled.
	 *
	 * @param array<int, string> $extra_currencies              Configured (non-base) currencies with manual rates.
	 * @param bool               $reevaluate_on_billing_change  Checkout billing re-eval flag.
	 * @param bool               $reevaluate_on_shipping_change Checkout shipping re-eval flag.
	 * @param string             $mode                          Detection mode.
	 * @param bool               $lock_on_entry                 Whether checkout lock_on_entry is enabled.
	 */
	private function save_settings(
		array $extra_currencies,
		bool $reevaluate_on_billing_change = false,
		bool $reevaluate_on_shipping_change = false,
		string $mode = GeoDetectionSettings::MODE_FIRST_VISIT,
		bool $lock_on_entry = false
	): void {
		$currencies = array();
		foreach ( $extra_currencies as $code ) {
			$currencies[ $code ] = array( 'manual_rate' => '10.0' );
		}

		$rules = array();
		$idx   = 1;
		foreach ( array(
			'SE' => 'SEK',
			'DK' => 'DKK',
		) as $country => $currency ) {
			if ( ! in_array( $currency, $extra_currencies, true ) ) {
				continue;
			}

			$rules[] = array(
				'id'       => sprintf( 'rule_%08d', $idx ),
				'type'     => 'country',
				'value'    => $country,
				'currency' => $currency,
			);
			++$idx;
		}

		( new Settings() )->save(
			array(
				'currencies' => $currencies,
				'geo'        => array(
					'enabled'                       => true,
					'mode'                          => $mode,
					'fallback_currency'             => '',
					'allow_wc_geolocation_fallback' => true,
					'rules'                         => $rules,
					'checkout'                      => array(
						'lock_on_entry'                 => $lock_on_entry,
						'reevaluate_on_billing_change'  => $reevaluate_on_billing_change,
						'reevaluate_on_shipping_change' => $reevaluate_on_shipping_change,
					),
				),
			)
		);
	}
}
