<?php
/**
 * Integration tests for the Milestone 3 order currency / rate snapshot.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Order\OrderSnapshot;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Order;
use WP_UnitTestCase;

/**
 * Verifies the immutable order snapshot is written via CRUD at order creation
 * and stays authoritative when store rates later change, under both the CPT and
 * HPOS order stores.
 */
final class TransactionOrderTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	/**
	 * Builds a currency graph + snapshot writer for the given active currency.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code.
	 * @return OrderSnapshot
	 */
	private function snapshot_writer( array $currencies, string $active ): OrderSnapshot {
		update_option( 'woocommerce_currency', 'EUR' );
		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;

		return new OrderSnapshot( $context, $settings, '0.3.0', new CheckoutTransitionStateRepository() );
	}

	public function test_snapshot_written_with_full_audit_meta(): void {
		$writer = $this->snapshot_writer(
			array(
				'SEK' => array(
					'rate'            => '11.50',
					'rate_updated_at' => 1_700_000_000,
				),
			),
			'SEK'
		);

		$order = new WC_Order();
		$writer->write_snapshot( $order );
		$order->save();

		$reloaded = wc_get_order( $order->get_id() );
		$this->assertSame( 'EUR', $reloaded->get_meta( OrderSnapshot::META_BASE_CURRENCY ) );
		$this->assertSame( 'SEK', $reloaded->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
		$this->assertSame( '11.50', $reloaded->get_meta( OrderSnapshot::META_EXCHANGE_RATE ) );
		$this->assertSame( '1700000000', (string) $reloaded->get_meta( OrderSnapshot::META_RATE_TIMESTAMP ) );
		$this->assertSame( 'manual', $reloaded->get_meta( OrderSnapshot::META_RATE_SOURCE ) );
		$this->assertSame( '0.3.0', $reloaded->get_meta( OrderSnapshot::META_PLUGIN_VERSION ) );
		$this->assertSame( 'SEK:11.50', $reloaded->get_meta( OrderSnapshot::META_RATE_IDENTITY ) );
	}

	public function test_snapshot_is_write_once(): void {
		$writer = $this->snapshot_writer( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$order  = new WC_Order();
		$writer->write_snapshot( $order );

		// A later attempt (e.g. a re-fired hook, or a changed rate) must not overwrite.
		$rewriter = $this->snapshot_writer( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$rewriter->write_snapshot( $order );

		$this->assertSame( '11.50', $order->get_meta( OrderSnapshot::META_EXCHANGE_RATE ) );
	}

	public function test_current_rate_change_does_not_alter_placed_order(): void {
		$writer = $this->snapshot_writer( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$order  = new WC_Order();
		$order->set_currency( 'SEK' );
		$writer->write_snapshot( $order );
		$order->save();
		$order_id = $order->get_id();

		// Change the store rate afterwards.
		( new Settings() )->save( array( 'currencies' => array( 'SEK' => array( 'rate' => '20' ) ) ) );

		$reloaded = wc_get_order( $order_id );
		$this->assertSame( 'SEK', $reloaded->get_currency() );
		$this->assertSame( '11.50', $reloaded->get_meta( OrderSnapshot::META_EXCHANGE_RATE ) );
	}

	public function test_snapshot_round_trips_under_hpos(): void {
		if ( ! $this->hpos_active() ) {
			$this->markTestSkipped( 'HPOS is not active in this environment.' );
		}

		$writer = $this->snapshot_writer( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$order  = new WC_Order();
		$order->set_currency( 'SEK' );
		$writer->write_snapshot( $order );
		$order->save();

		$reloaded = wc_get_order( $order->get_id() );
		$this->assertSame( 'SEK', $reloaded->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
		$this->assertSame( '11.50', $reloaded->get_meta( OrderSnapshot::META_EXCHANGE_RATE ) );
	}

	/**
	 * Whether the WooCommerce custom order tables are the active order store.
	 *
	 * Enabled once at bootstrap so the whole order suite runs against HPOS.
	 */
	private function hpos_active(): bool {
		return class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
