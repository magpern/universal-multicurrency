<?php
/**
 * Integration tests for historical order display formatting.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CurrencyFormatting;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\HistoricalOrderDisplay;
use UMC\Order\OrderCurrencyContext;
use UMC\Order\OrderCurrencyFormatting;
use UMC\Order\OrderSnapshotReader;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Order;
use WP_UnitTestCase;

/**
 * Renders orders through the real historical path — the registered display
 * brackets plus the M4 formatting filters layered over the M2 session formatter —
 * and proves the order currency wins, decimals resolve correctly, stored totals
 * never change, and the context never leaks.
 */
final class HistoricalOrderDisplayTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_currency',
		'woocommerce_currency_symbol',
		'wc_get_price_decimals',
		'wc_price_args',
		'woocommerce_order_details_before_order_table',
		'woocommerce_order_details_after_order_table',
		'umc_is_request_convertible',
		'umc_order_currency_context_entered',
		'umc_order_currency_context_exited',
	);

	/**
	 * The order currency context under test.
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $order_context;

	public function set_up(): void {
		parent::set_up();

		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}
	}

	public function tear_down(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	// -- Tests ---------------------------------------------------------------

	public function test_eur_order_reports_eur_identity_in_a_sek_session(): void {
		$this->wire_display( 'SEK' );
		$order = $this->create_order( 'EUR', '99.90' );

		// Baseline: outside any order context the session formatter reports SEK.
		$this->assertSame( 'SEK', get_woocommerce_currency() );

		do_action( 'woocommerce_order_details_before_order_table', $order );

		$this->assertSame( 'EUR', get_woocommerce_currency() );
		// The order currency drives the no-arg symbol: it resolves to the EUR
		// symbol, not the SEK session symbol.
		$this->assertSame( get_woocommerce_currency_symbol( 'EUR' ), get_woocommerce_currency_symbol() );
		$this->assertNotSame( get_woocommerce_currency_symbol( 'SEK' ), get_woocommerce_currency_symbol() );
		$this->assertSame( 2, wc_get_price_decimals() );

		do_action( 'woocommerce_order_details_after_order_table' );

		// Formatting reverts to the session currency and the stack is empty.
		$this->assertSame( 'SEK', get_woocommerce_currency() );
		$this->assertSame( 0, $this->order_context->depth() );
	}

	public function test_zero_decimal_order_reports_zero_decimals_in_a_two_decimal_session(): void {
		$this->wire_display( 'SEK' );
		$order = $this->create_order( 'JPY', '2500' );

		$this->assertSame( 2, wc_get_price_decimals() );

		do_action( 'woocommerce_order_details_before_order_table', $order );

		$this->assertSame( 'JPY', get_woocommerce_currency() );
		$this->assertSame( 0, wc_get_price_decimals() );

		// A price rendered under the context uses zero decimals for the order currency.
		$rendered = wp_strip_all_tags( wc_price( 2500, array( 'currency' => 'JPY' ) ) );
		$this->assertStringNotContainsString( '.', $rendered );

		do_action( 'woocommerce_order_details_after_order_table' );

		$this->assertSame( 2, wc_get_price_decimals() );
	}

	public function test_missing_currency_configuration_falls_back_to_iso_decimals(): void {
		// JPY is not configured and the order carries no stored decimals; the
		// resolver must fall back to the ISO-4217 map (JPY = 0).
		$this->wire_display( 'SEK' );
		$order = $this->create_order( 'JPY', '2500' ); // No snapshot written.

		do_action( 'woocommerce_order_details_before_order_table', $order );
		$this->assertSame( 0, wc_get_price_decimals() );
		do_action( 'woocommerce_order_details_after_order_table' );
	}

	public function test_stored_totals_are_never_changed_by_rendering(): void {
		$this->wire_display( 'SEK' );
		$order    = $this->create_order( 'EUR', '123.45' );
		$order_id = $order->get_id();

		do_action( 'woocommerce_order_details_before_order_table', $order );
		do_action( 'woocommerce_order_details_after_order_table' );

		$reloaded = wc_get_order( $order_id );
		$this->assertSame( 'EUR', $reloaded->get_currency() );
		$this->assertSame( '123.45', $reloaded->get_total() );
	}

	public function test_repeated_render_leaves_no_leaked_context(): void {
		$this->wire_display( 'SEK' );
		$order = $this->create_order( 'EUR', '10.00' );

		for ( $i = 0; $i < 3; $i++ ) {
			do_action( 'woocommerce_order_details_before_order_table', $order );
			$this->assertSame( 'EUR', get_woocommerce_currency() );
			do_action( 'woocommerce_order_details_after_order_table' );
			$this->assertSame( 0, $this->order_context->depth() );
			$this->assertSame( 'SEK', get_woocommerce_currency() );
		}
	}

	public function test_nested_render_is_lifo(): void {
		$this->wire_display( 'SEK' );
		$eur = $this->create_order( 'EUR', '10.00' );
		$jpy = $this->create_order( 'JPY', '2500' );

		do_action( 'woocommerce_order_details_before_order_table', $eur );
		$this->assertSame( 'EUR', get_woocommerce_currency() );

		do_action( 'woocommerce_order_details_before_order_table', $jpy );
		$this->assertSame( 'JPY', get_woocommerce_currency() );
		$this->assertSame( 2, $this->order_context->depth() );

		do_action( 'woocommerce_order_details_after_order_table' );
		$this->assertSame( 'EUR', get_woocommerce_currency() );

		do_action( 'woocommerce_order_details_after_order_table' );
		$this->assertSame( 'SEK', get_woocommerce_currency() );
		$this->assertSame( 0, $this->order_context->depth() );
	}

	public function test_rate_edit_and_currency_disable_do_not_change_a_rendered_order(): void {
		$this->wire_display( 'SEK' );
		$order    = $this->create_order( 'EUR', '50.00' );
		$order_id = $order->get_id();

		// Change the SEK rate and remove SEK from configuration entirely.
		( new Settings() )->save( array( 'currencies' => array() ) );

		do_action( 'woocommerce_order_details_before_order_table', $order );
		$this->assertSame( 'EUR', get_woocommerce_currency() );
		$this->assertSame( 2, wc_get_price_decimals() );
		do_action( 'woocommerce_order_details_after_order_table' );

		$reloaded = wc_get_order( $order_id );
		$this->assertSame( 'EUR', $reloaded->get_currency() );
		$this->assertSame( '50.00', $reloaded->get_total() );
	}

	// -- Helpers -------------------------------------------------------------

	/**
	 * Wires the M2 session formatter and the M4 display + formatter for a session.
	 *
	 * @param string $session Session currency code (must be selectable).
	 */
	private function wire_display( string $session ): void {
		update_option( 'woocommerce_currency', 'EUR' );

		( new Settings() )->save(
			array(
				'currencies' => array(
					'SEK' => array( 'rate' => '11.50' ),
				),
			)
		);

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$cc       = new CurrencyContext( $registry, $rates, new CurrencyResolver() );

		$reader              = new OrderSnapshotReader();
		$resolver            = new HistoricalFormattingResolver( $registry );
		$this->order_context = new OrderCurrencyContext( $reader, $resolver );

		if ( 'EUR' !== $session ) {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $session;
		}

		// Treat the request as convertible so the M2 session formatter is active.
		add_filter( 'umc_is_request_convertible', '__return_true' );

		// M2 session formatter (priority 10) + M4 order formatter (priority 20) +
		// the display brackets, mirroring the production wiring.
		( new CurrencyFormatting( $cc ) )->register();
		( new OrderCurrencyFormatting( $this->order_context, $resolver ) )->register();
		( new HistoricalOrderDisplay( $this->order_context ) )->register();
	}

	/**
	 * Creates a saved order in a currency with a fixed total (no snapshot meta).
	 *
	 * @param string $currency Order currency code.
	 * @param string $total    Order total as a decimal string.
	 */
	private function create_order( string $currency, string $total ): WC_Order {
		$order = new WC_Order();
		$order->set_currency( $currency );
		$order->set_total( $total );
		$order->save();

		return $order;
	}
}
