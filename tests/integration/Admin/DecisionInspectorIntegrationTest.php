<?php
/**
 * Integration tests for Decision Inspector admin-post → redirect → GET flow.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\DecisionInspectorController;
use UMC\Admin\DecisionInspectorService;
use UMC\Admin\DecisionInspectorSettingsField;
use UMC\Admin\SettingsPage;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencySwitcher;
use UMC\PersistedKeys;
use UMC\Settings;
use UMC\Tests\Support\RedirectCapturedException;
use WP_UnitTestCase;
use WPDieException;

/**
 * Covers capability/nonce gates, query-arg revalidation, and side-effect freedom.
 */
final class DecisionInspectorIntegrationTest extends WP_UnitTestCase {

	private const ACTION = 'admin_post_' . DecisionInspectorController::ACTION;

	/**
	 * @var int
	 */
	private int $admin_user_id = 0;

	protected function setUp(): void {
		parent::setUp();

		update_option( 'woocommerce_currency', 'EUR' );

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		WC()->session->set( CurrencyContext::SESSION_KEY, null );
		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, null );
		WC()->session->set( CurrencySwitcher::SESSION_MANUAL_SELECTION, null );

		add_filter(
			'wp_redirect',
			static function ( $location ): string {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test control flow; the message is never rendered.
				throw new RedirectCapturedException( (string) $location );
			}
		);

		$this->seed_settings();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wp_redirect' );
		remove_all_actions( self::ACTION );

		unset( $_REQUEST['_wpnonce'], $_GET['_wpnonce'], $_POST['_wpnonce'], $_POST['umc_decision_inspector'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test teardown clears inspector query fixtures.
		foreach ( array_keys( $_GET ) as $key ) {
			if ( is_string( $key ) && ( str_starts_with( $key, 'umc_di_' ) || 'umc_inspected' === $key || 'section' === $key ) ) {
				unset( $_GET[ $key ] );
			}
		}
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( CurrencyContext::SESSION_KEY, null );
			WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, null );
			WC()->session->set( CurrencySwitcher::SESSION_MANUAL_SELECTION, null );
		}

		delete_option( Settings::OPTION );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public function test_unauthorized_user_is_rejected(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->boot_controller();
		$this->sign_request(
			array(
				'country_code'     => 'SE',
				'session_currency' => 'SEK',
				'include_checkout' => '1',
			)
		);

		$this->expectException( WPDieException::class );
		do_action( self::ACTION );
	}

	public function test_missing_nonce_is_rejected(): void {
		$this->authorize();
		$this->boot_controller();

		$_POST['umc_decision_inspector'] = array(
			'country_code'     => 'SE',
			'session_currency' => 'SEK',
		);

		$this->expectException( WPDieException::class );
		do_action( self::ACTION );
	}

	public function test_authorized_post_redirects_with_sanitized_query_args(): void {
		$this->authorize();
		$this->boot_controller();
		$this->sign_request(
			array(
				'country_code'             => 'se',
				'explicit_currency'        => 'sek',
				'session_currency'         => 'USD',
				'cookie_currency'          => '',
				'manual_selection'         => '1',
				'currency_origin'          => CurrencySwitcher::ORIGIN_CUSTOMER,
				'geo_enabled'              => '1',
				'checkout_locked'          => '0',
				'include_checkout'         => '1',
				'checkout_mode'            => 'selected',
				'show_notice'              => '1',
				'payment_required'         => '1',
				'gateway_supports_display' => '1',
				'order_context_active'     => '0',
			)
		);

		$redirect = $this->dispatch();

		$this->assertSame( '1', $redirect->query_arg( 'umc_inspected' ) );
		$this->assertSame( SettingsPage::SECTION_DECISION_INSPECTOR, $redirect->query_arg( 'section' ) );
		$this->assertSame( 'SE', $redirect->query_arg( 'umc_di_country' ) );
		$this->assertSame( 'SEK', $redirect->query_arg( 'umc_di_explicit' ) );
		$this->assertSame( 'USD', $redirect->query_arg( 'umc_di_session' ) );
		$this->assertSame( '1', $redirect->query_arg( 'umc_di_manual' ) );
		$this->assertSame( CurrencySwitcher::ORIGIN_CUSTOMER, $redirect->query_arg( 'umc_di_origin' ) );
		$this->assertSame( '1', $redirect->query_arg( 'umc_di_include_checkout' ) );
		$this->assertSame( 'selected', $redirect->query_arg( 'umc_di_checkout_mode' ) );
	}

	public function test_malformed_post_inputs_are_normalized_before_redirect(): void {
		$this->authorize();
		$this->boot_controller();
		$this->sign_request(
			array(
				'country_code'     => '<script>SE</script>',
				'currency_origin'  => 'attacker',
				'checkout_mode'    => 'drop-table',
				'session_currency' => 'SEK',
			)
		);

		$redirect = $this->dispatch();

		$this->assertSame( '', $redirect->query_arg( 'umc_di_country' ) );
		$this->assertSame( '', $redirect->query_arg( 'umc_di_origin' ) );
		$this->assertSame( 'selected', $redirect->query_arg( 'umc_di_checkout_mode' ) );
		$this->assertSame( 'SEK', $redirect->query_arg( 'umc_di_session' ) );
	}

	public function test_query_arg_round_trip_revalidates_before_recomputation_without_side_effects(): void {
		$this->authorize();
		$this->boot_controller();
		$this->sign_request(
			array(
				'country_code'     => 'SE',
				'session_currency' => 'SEK',
				'currency_origin'  => CurrencySwitcher::ORIGIN_VISITOR_LOCATION,
				'geo_enabled'      => '1',
				'include_checkout' => '0',
			)
		);

		$redirect = $this->dispatch();

		WC()->session->set( CurrencyContext::SESSION_KEY, 'USD' );
		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, CurrencySwitcher::ORIGIN_CUSTOMER );
		WC()->session->set( CurrencySwitcher::SESSION_MANUAL_SELECTION, '1' );
		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = 'USD';

		$user_id = get_current_user_id();
		$before  = $this->user_meta_snapshot( $user_id );

		$_GET = array(
			'page'                    => 'wc-settings',
			'tab'                     => 'umc',
			'section'                 => SettingsPage::SECTION_DECISION_INSPECTOR,
			'umc_inspected'           => '1',
			'umc_di_country'          => (string) $redirect->query_arg( 'umc_di_country' ),
			'umc_di_explicit'         => (string) $redirect->query_arg( 'umc_di_explicit' ),
			'umc_di_session'          => (string) $redirect->query_arg( 'umc_di_session' ),
			'umc_di_cookie'           => (string) $redirect->query_arg( 'umc_di_cookie' ),
			'umc_di_manual'           => (string) $redirect->query_arg( 'umc_di_manual' ),
			'umc_di_origin'           => (string) $redirect->query_arg( 'umc_di_origin' ),
			'umc_di_geo_enabled'      => (string) $redirect->query_arg( 'umc_di_geo_enabled' ),
			'umc_di_checkout_locked'  => (string) $redirect->query_arg( 'umc_di_checkout_locked' ),
			'umc_di_include_checkout' => (string) $redirect->query_arg( 'umc_di_include_checkout' ),
			'umc_di_checkout_mode'    => (string) $redirect->query_arg( 'umc_di_checkout_mode' ),
			'umc_di_show_notice'      => (string) $redirect->query_arg( 'umc_di_show_notice' ),
			'umc_di_payment_required' => (string) $redirect->query_arg( 'umc_di_payment_required' ),
			'umc_di_gateway_supports' => (string) $redirect->query_arg( 'umc_di_gateway_supports' ),
			'umc_di_order_context'    => (string) $redirect->query_arg( 'umc_di_order_context' ),
		);

		$field = new DecisionInspectorSettingsField( new Settings(), new Currency( 'EUR', 2 ) );
		ob_start();
		$field->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'umc-ui-decision-timeline', $html );
		$this->assertStringContainsString( 'SEK', $html );

		$this->assertSame( 'USD', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_CUSTOMER,
			WC()->session->get( CurrencySwitcher::SESSION_CURRENCY_ORIGIN )
		);
		$this->assertSame( '1', WC()->session->get( CurrencySwitcher::SESSION_MANUAL_SELECTION ) );
		$cookie_name = CurrencyContext::COOKIE_NAME;
		$cookie      = isset( $_COOKIE[ $cookie_name ] )
			? sanitize_text_field( wp_unslash( (string) $_COOKIE[ $cookie_name ] ) )
			: '';
		$this->assertSame( 'USD', $cookie );
		$this->assertSame( $before, $this->user_meta_snapshot( $user_id ) );

		// Tampered GET args are re-sanitized before recomputation.
		$tampered_country = 'N<script>O</script>EXTRA';
		$tampered_origin  = 'evil';
		$tampered_mode    = 'store;drop';

		$_GET['umc_di_country']       = $tampered_country;
		$_GET['umc_di_origin']        = $tampered_origin;
		$_GET['umc_di_checkout_mode'] = $tampered_mode;

		$service = new DecisionInspectorService( new Settings(), new Currency( 'EUR', 2 ) );
		$input   = $service->input_from_array(
			array(
				'country_code'     => sanitize_text_field( wp_unslash( $tampered_country ) ),
				'currency_origin'  => sanitize_text_field( wp_unslash( $tampered_origin ) ),
				'checkout_mode'    => sanitize_text_field( wp_unslash( $tampered_mode ) ),
				'session_currency' => 'SEK',
			)
		);

		$this->assertSame( '', $input->country_code() );
		$this->assertNull( $input->currency_origin() );
		$this->assertSame( 'selected', $input->checkout_mode() );

		$explanation = $service->explain_from_array(
			array(
				'country_code'     => $input->country_code(),
				'currency_origin'  => $input->currency_origin(),
				'checkout_mode'    => $input->checkout_mode(),
				'session_currency' => 'SEK',
			)
		);

		$this->assertSame( 'SEK', $explanation->display_currency() );
		$this->assertSame( 'USD', WC()->session->get( CurrencyContext::SESSION_KEY ) );
		$this->assertSame( $before, $this->user_meta_snapshot( $user_id ) );
	}

	public function test_settings_schema_and_currency_origin_inventory_remain_stable(): void {
		$this->assertSame( 7, Settings::SCHEMA_VERSION );
		$this->assertContains(
			CurrencySwitcher::SESSION_CURRENCY_ORIGIN,
			PersistedKeys::session_keys()
		);
		$this->assertSame( 10, PersistedKeys::INVENTORY_VERSION );
		$this->assertNotContains(
			'umc_decision_inspector',
			PersistedKeys::user_meta_keys()
		);
	}

	private function boot_controller(): void {
		( new DecisionInspectorController( new Settings(), new Currency( 'EUR', 2 ) ) )->register();
	}

	private function dispatch(): RedirectCapturedException {
		try {
			do_action( self::ACTION );
		} catch ( RedirectCapturedException $redirect ) {
			return $redirect;
		}

		$this->fail( 'Decision Inspector must redirect after a successful POST.' );
	}

	private function authorize(): void {
		$this->admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		$this->assertTrue(
			current_user_can( 'manage_woocommerce' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce shop-manager capability.
			'Administrators must hold manage_woocommerce for this fixture to be meaningful.'
		);
	}

	/**
	 * @param array<string, mixed> $payload Inspector POST payload.
	 */
	private function sign_request( array $payload ): void {
		$nonce = wp_create_nonce( DecisionInspectorController::ACTION );

		$_REQUEST['_wpnonce']            = $nonce;
		$_GET['_wpnonce']                = $nonce;
		$_POST['_wpnonce']               = $nonce;
		$_POST['umc_decision_inspector'] = $payload;
	}

	private function seed_settings(): void {
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
					'geo'        => array(
						'enabled'                       => true,
						'mode'                          => 'first_visit',
						'fallback_currency'             => '',
						'allow_wc_geolocation_fallback' => true,
						'rules'                         => array(
							array(
								'id'       => 'rule_00000001',
								'type'     => 'country',
								'value'    => 'SE',
								'currency' => 'SEK',
							),
						),
						'checkout'                      => array(
							'lock_on_entry'                => false,
							'reevaluate_on_billing_change' => false,
							'reevaluate_on_shipping_change' => false,
						),
					),
				)
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function user_meta_snapshot( int $user_id ): array {
		$meta = get_user_meta( $user_id );
		ksort( $meta );

		return $meta;
	}
}
