<?php
/**
 * Integration tests for the order-pay currency lock.
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
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderCurrencyContext;
use UMC\Order\OrderPayCurrencyLock;
use UMC\Order\OrderSnapshotReader;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Order;
use WP_UnitTestCase;

/**
 * Verifies the order-pay endpoint resolves the order from the request, validates
 * the customer, and locks the currency context to the order currency — driving
 * gateway filtering with the explicit order currency and never rewriting totals.
 *
 * These tests exercise the real request path (`maybe_lock_order_pay()` reading the
 * request superglobals), not just the private parser, so the endpoint contract is
 * proven end to end.
 */
final class OrderPayCurrencyLockTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_available_payment_gateways',
		'umc_gateway_supported_currencies',
		'umc_gateway_hidden',
		'umc_order_pay_locked_currency',
		'umc_is_request_convertible',
	);

	public function tear_down(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset(
			$_GET['order-pay'],
			$_GET['pay_for_order'],
			$_GET['key'],
			$_COOKIE[ CurrencyContext::COOKIE_NAME ]
		);

		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		wp_set_current_user( 0 );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	// -- Request resolution --------------------------------------------------

	public function test_standard_order_pay_endpoint_locks_order_currency(): void {
		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );

		$this->request_order_pay( $order );

		$locked = $this->capture_locked_currency();
		$lock->maybe_lock_order_pay();

		$this->assertTrue( $this->context->is_active() );
		$this->assertSame( 'SEK', $this->context->current_code() );
		$this->assertSame( 'SEK', $locked() );
	}

	public function test_legacy_pay_for_order_parameter_locks_order_currency(): void {
		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'JPY' );

		// Legacy form: the ID travels in `pay_for_order`, not `order-pay`.
		$_GET['pay_for_order'] = (string) $order->get_id();
		$_GET['key']           = $order->get_order_key();

		$lock->maybe_lock_order_pay();

		$this->assertTrue( $this->context->is_active() );
		$this->assertSame( 'JPY', $this->context->current_code() );
	}

	public function test_missing_order_id_bails_safely(): void {
		$lock = $this->build_lock();

		// No request parameters at all.
		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	public function test_zero_order_id_bails_safely(): void {
		$lock = $this->build_lock();

		$_GET['order-pay'] = '0';
		$_GET['key']       = 'anything';

		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	public function test_malformed_order_id_resolves_to_no_order(): void {
		$lock = $this->build_lock();

		$_GET['order-pay'] = 'not-a-number';
		$_GET['key']       = 'anything';

		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	public function test_boolean_pay_for_order_flag_does_not_resolve_an_order(): void {
		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );

		// Real WooCommerce passes `pay_for_order=true` as a flag; the ID lives in
		// `order-pay`. With only the boolean flag present, no order resolves.
		$_GET['pay_for_order'] = 'true';
		$_GET['key']           = $order->get_order_key();

		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	// -- Permission checks ---------------------------------------------------

	public function test_order_key_is_validated(): void {
		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );

		$_GET['order-pay'] = (string) $order->get_id();
		$_GET['key']       = 'wc_order_wrongkey';

		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	public function test_missing_order_key_is_rejected(): void {
		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );

		$_GET['order-pay'] = (string) $order->get_id();
		// No key at all.

		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	public function test_order_belonging_to_another_customer_is_rejected(): void {
		$owner    = self::factory()->user->create();
		$attacker = self::factory()->user->create();

		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );
		$order->set_customer_id( $owner );
		$order->save();

		// The correct key, but a different logged-in customer.
		wp_set_current_user( $attacker );
		$_GET['order-pay'] = (string) $order->get_id();
		$_GET['key']       = $order->get_order_key();

		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	public function test_owner_with_valid_key_is_accepted(): void {
		$owner = self::factory()->user->create();

		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );
		$order->set_customer_id( $owner );
		$order->save();

		wp_set_current_user( $owner );
		$this->request_order_pay( $order );

		$lock->maybe_lock_order_pay();

		$this->assertTrue( $this->context->is_active() );
		$this->assertSame( 'SEK', $this->context->current_code() );
	}

	public function test_paid_order_is_not_locked(): void {
		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );
		$order->set_status( 'completed' );
		$order->save();

		$this->request_order_pay( $order );
		$lock->maybe_lock_order_pay();

		$this->assertFalse( $this->context->is_active() );
	}

	public function test_totals_are_never_rewritten_by_the_lock(): void {
		$lock  = $this->build_lock();
		$order = $this->create_payable_order( 'SEK' );
		$order->set_total( '123.45' );
		$order->save();
		$order_id = $order->get_id();

		$this->request_order_pay( $order );
		$lock->maybe_lock_order_pay();

		$reloaded = wc_get_order( $order_id );
		$this->assertSame( 'SEK', $reloaded->get_currency() );
		$this->assertSame( '123.45', $reloaded->get_total() );
	}

	// -- Gateway behaviour ---------------------------------------------------

	public function test_gateway_filtering_uses_the_explicit_order_currency(): void {
		$received = null;
		add_action(
			'umc_gateway_hidden',
			static function ( $id, $currency ) use ( &$received ) {
				$received = $currency;
			},
			10,
			2
		);

		$lock  = $this->wire_for_gateways( 'SEK' );
		$order = $this->create_payable_order( 'EUR' );
		$this->restrict_gateway( 'jpy_only', array( 'JPY' ) );

		$this->request_order_pay( $order );
		$lock->maybe_lock_order_pay();

		apply_filters(
			'woocommerce_available_payment_gateways',
			array(
				'jpy_only' => (object) array( 'id' => 'jpy_only' ),
				'any'      => (object) array( 'id' => 'any' ),
			)
		);

		// The gateway was judged against the order currency (EUR), not session SEK.
		$this->assertSame( 'EUR', $received );
	}

	public function test_compatible_gateway_remains_and_incompatible_is_removed(): void {
		$lock  = $this->wire_for_gateways( 'SEK' );
		$order = $this->create_payable_order( 'EUR' );
		$this->restrict_gateway( 'jpy_only', array( 'JPY' ) );

		$this->request_order_pay( $order );
		$lock->maybe_lock_order_pay();

		$gateways = apply_filters(
			'woocommerce_available_payment_gateways',
			array(
				'jpy_only' => (object) array( 'id' => 'jpy_only' ),
				'any'      => (object) array( 'id' => 'any' ),
			)
		);

		$this->assertArrayHasKey( 'any', $gateways );
		$this->assertArrayNotHasKey( 'jpy_only', $gateways );
	}

	public function test_gateway_supported_by_order_currency_survives_session_filtering(): void {
		// The core determinism guarantee: a gateway that the storefront session
		// filter WOULD remove (supports EUR only, session is SEK) must survive on
		// order-pay because the order currency is EUR and the order-pay filter
		// evaluates the ORIGINAL gateway set, not a session-pre-filtered one.
		$lock  = $this->wire_for_gateways( 'SEK' );
		$order = $this->create_payable_order( 'EUR' );
		$this->restrict_gateway( 'eur_only', array( 'EUR' ) );

		$original = array(
			'eur_only' => (object) array( 'id' => 'eur_only' ),
			'any'      => (object) array( 'id' => 'any' ),
		);

		// Control: with only the storefront (session=SEK) filter active, eur_only
		// would be removed — proving the scenario is not vacuous.
		$this->assertArrayNotHasKey(
			'eur_only',
			apply_filters( 'woocommerce_available_payment_gateways', $original ),
			'The storefront session filter should remove the EUR-only gateway.'
		);

		// Now lock to the order currency; the storefront callback is deregistered.
		$this->request_order_pay( $order );
		$lock->maybe_lock_order_pay();

		$this->assertFalse(
			has_filter( 'woocommerce_available_payment_gateways', array( $this->gateway_compat, 'filter_gateways' ) ),
			'The storefront session gateway filter must be removed on order-pay.'
		);

		$order_pay_filtered = apply_filters( 'woocommerce_available_payment_gateways', $original );
		$this->assertArrayHasKey( 'eur_only', $order_pay_filtered );
		$this->assertArrayHasKey( 'any', $order_pay_filtered );
	}

	public function test_disabled_historical_currency_stays_payable_when_a_gateway_supports_it(): void {
		// The order currency is no longer configured/selectable, but a gateway
		// explicitly supports it — the order must remain payable.
		$lock  = $this->wire_for_gateways( 'SEK' );
		$order = $this->create_payable_order( 'PLN' ); // Not configured/selectable.
		$this->restrict_gateway( 'nordic', array( 'PLN', 'SEK' ) );

		$this->request_order_pay( $order );
		$lock->maybe_lock_order_pay();

		$gateways = apply_filters(
			'woocommerce_available_payment_gateways',
			array( 'nordic' => (object) array( 'id' => 'nordic' ) )
		);

		$this->assertArrayHasKey( 'nordic', $gateways );
	}

	public function test_no_compatible_gateway_leaves_an_empty_set_and_a_notice(): void {
		$lock  = $this->wire_for_gateways( 'SEK' );
		$order = $this->create_payable_order( 'EUR' );
		$this->restrict_gateway( 'jpy_only', array( 'JPY' ) );

		$this->request_order_pay( $order );
		$lock->maybe_lock_order_pay();

		$gateways = apply_filters(
			'woocommerce_available_payment_gateways',
			array( 'jpy_only' => (object) array( 'id' => 'jpy_only' ) )
		);

		// The blocking behaviour: no payment method survives for the order currency.
		$this->assertSame( array(), $gateways );

		// And the explanatory checkout notice is raised (keyed to the order currency).
		$this->assertGreaterThan( 0, wc_notice_count( 'error' ) );
	}

	// -- Helpers -------------------------------------------------------------

	/**
	 * The order currency context under test (fresh per test, no cross-test leak).
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $context;

	/**
	 * The shared gateway-compatibility instance (storefront + order-pay).
	 *
	 * @var GatewayCompatibility
	 */
	private GatewayCompatibility $gateway_compat;

	/**
	 * Builds a lock wired to a fresh context, gateway engine and registry.
	 *
	 * @param string $session Session currency code the storefront resolves to.
	 */
	private function build_lock( string $session = 'EUR' ): OrderPayCurrencyLock {
		update_option( 'woocommerce_currency', 'EUR' );

		// Make SEK/NOK selectable so a non-base session currency can resolve.
		( new Settings() )->save(
			array(
				'currencies' => array(
					'SEK' => array( 'rate' => '11.50' ),
					'NOK' => array( 'rate' => '11.90' ),
				),
			)
		);

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$cc       = new CurrencyContext( $registry, $rates, new CurrencyResolver() );

		if ( 'EUR' !== $session ) {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $session;
		}

		$reader        = new OrderSnapshotReader();
		$resolver      = new HistoricalFormattingResolver( $registry );
		$this->context = new OrderCurrencyContext( $reader, $resolver );

		$this->gateway_compat = new GatewayCompatibility( $cc );

		return new OrderPayCurrencyLock( $this->context, $this->gateway_compat, $registry );
	}

	/**
	 * Builds a lock and registers the storefront gateway filter for a session
	 * currency, mirroring the production wiring so order-pay can deregister it.
	 *
	 * @param string $session Session currency code.
	 */
	private function wire_for_gateways( string $session ): OrderPayCurrencyLock {
		$lock = $this->build_lock( $session );

		// Isolate from the globally-booted plugin instance: clear any callback it
		// registered on the gateway hook so only this test's filters participate.
		remove_all_filters( 'woocommerce_available_payment_gateways' );

		// Force the storefront filter to treat this as a convertible request so it
		// actually filters (CLI requests are non-convertible by default).
		add_filter( 'umc_is_request_convertible', '__return_true' );

		$this->gateway_compat->register();

		return $lock;
	}

	/**
	 * Restricts a gateway id to a set of supported currency codes.
	 *
	 * @param string             $id    Gateway id.
	 * @param array<int, string> $codes Supported currency codes.
	 */
	private function restrict_gateway( string $id, array $codes ): void {
		add_filter(
			'umc_gateway_supported_currencies',
			static function ( $current, $gateway ) use ( $id, $codes ) {
				return isset( $gateway->id ) && $id === $gateway->id ? $codes : $current;
			},
			10,
			2
		);
	}

	/**
	 * Creates a saved order that needs payment (pending, total > 0) in a currency.
	 *
	 * @param string $currency Order currency code.
	 */
	private function create_payable_order( string $currency ): WC_Order {
		$order = new WC_Order();
		$order->set_currency( $currency );
		$order->set_status( 'pending' );
		$order->set_total( '50.00' );
		$order->set_order_key( wc_generate_order_key() );
		$order->save();

		return $order;
	}

	/**
	 * Populates the request superglobals for a valid order-pay request.
	 *
	 * @param WC_Order $order Order to pay.
	 */
	private function request_order_pay( WC_Order $order ): void {
		$_GET['order-pay'] = (string) $order->get_id();
		$_GET['key']       = $order->get_order_key();
	}

	/**
	 * Captures the currency reported by the umc_order_pay_locked_currency action.
	 *
	 * @return callable(): ?string Reader returning the captured currency.
	 */
	private function capture_locked_currency(): callable {
		$captured = null;

		add_action(
			'umc_order_pay_locked_currency',
			static function ( $currency ) use ( &$captured ) {
				$captured = $currency;
			},
			10,
			1
		);

		return static function () use ( &$captured ) {
			return $captured;
		};
	}
}
