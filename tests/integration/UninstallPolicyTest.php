<?php
/**
 * Integration tests for the ADR-0009 uninstall retention policy.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\CacheState\CacheStateStore;
use UMC\Diagnostics\NoticeDismissal;
use UMC\Order\OrderSnapshot;
use UMC\Order\OrderSnapshotReader;
use UMC\Order\RefundSnapshot;
use UMC\PersistedKeys;
use UMC\Settings;
use WC_Order;
use WP_UnitTestCase;

/**
 * Executes uninstall.php against seeded persistence and asserts the approved
 * delete/preserve contract.
 */
final class UninstallPolicyTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		remove_all_filters( 'woocommerce_create_refund' );
		( new RefundSnapshot( new OrderSnapshotReader() ) )->register();
	}

	public function tear_down(): void {
		remove_all_filters( 'woocommerce_create_refund' );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	public function test_uninstall_removes_plugin_configuration(): void {
		( new Settings() )->save(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled' => true,
						'rate'    => '11.50',
					),
				),
			)
		);

		$this->assertNotFalse( get_option( Settings::OPTION, false ) );

		$this->run_plugin_uninstall();

		$this->assertFalse( get_option( Settings::OPTION, false ) );
	}

	public function test_uninstall_removes_cache_state_acknowledgement(): void {
		update_option(
			CacheStateStore::OPTION,
			array(
				'schema_version'    => 1,
				'acknowledged_hash' => 'a1b2c3d4e5f60718',
				'acknowledged_at'   => time(),
			),
			false
		);

		$this->assertNotFalse( get_option( CacheStateStore::OPTION, false ) );

		( new Settings() )->save( array( 'currencies' => array() ) );
		$this->run_plugin_uninstall();

		$this->assertFalse( get_option( CacheStateStore::OPTION, false ) );
	}

	public function test_uninstall_preserves_order_snapshot_metadata(): void {
		$order = $this->seed_order_with_meta( PersistedKeys::order_meta_keys() );

		( new Settings() )->save( array( 'currencies' => array() ) );
		$this->run_plugin_uninstall();

		$reloaded = wc_get_order( $order->get_id() );
		$this->assertInstanceOf( WC_Order::class, $reloaded );

		foreach ( PersistedKeys::order_meta_keys() as $key ) {
			$this->assertSame( 'persist-probe', $reloaded->get_meta( $key ) );
		}
	}

	public function test_uninstall_preserves_refund_snapshot_metadata(): void {
		$order = $this->seed_order_with_meta(
			array(
				OrderSnapshot::META_BASE_CURRENCY        => 'EUR',
				OrderSnapshot::META_TRANSACTION_CURRENCY => 'SEK',
				OrderSnapshot::META_EXCHANGE_RATE        => '11.50',
				OrderSnapshot::META_RATE_TIMESTAMP       => 1_700_000_000,
				OrderSnapshot::META_RATE_SOURCE          => OrderSnapshot::SOURCE_MANUAL,
				OrderSnapshot::META_PLUGIN_VERSION       => '0.7.0',
				OrderSnapshot::META_RATE_IDENTITY        => 'SEK:11.50',
				OrderSnapshot::META_SNAPSHOT_VERSION     => 2,
				OrderSnapshot::META_TRANSACTION_DECIMALS => 2,
			)
		);

		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => '5.00',
			)
		);

		$this->assertInstanceOf( \WC_Order_Refund::class, $refund );

		$expected = array();
		foreach ( PersistedKeys::refund_meta_keys() as $key ) {
			$expected[ $key ] = $refund->get_meta( $key );
		}

		$refund_id = $refund->get_id();

		( new Settings() )->save( array( 'currencies' => array() ) );
		$this->run_plugin_uninstall();

		$reloaded = wc_get_order( $refund_id );
		$this->assertInstanceOf( \WC_Order_Refund::class, $reloaded );

		foreach ( PersistedKeys::refund_meta_keys() as $key ) {
			$this->assertSame( $expected[ $key ], $reloaded->get_meta( $key ) );
		}
	}

	public function test_uninstall_preserves_dismissal_user_metadata(): void {
		$user_id = self::factory()->user->create();
		$stored  = array(
			'fixture-dismissal' => time(),
		);

		update_user_meta( $user_id, NoticeDismissal::META_KEY, $stored );
		( new Settings() )->save( array( 'currencies' => array() ) );

		$this->run_plugin_uninstall();

		$this->assertSame( $stored, get_user_meta( $user_id, NoticeDismissal::META_KEY, true ) );
	}

	/**
	 * @param array<int, string> $keys Meta keys to stamp with a probe value.
	 */
	private function seed_order_with_meta( array $keys ): WC_Order {
		$order = new WC_Order();
		$order->set_currency( 'SEK' );
		$order->set_total( '100.00' );

		foreach ( $keys as $key ) {
			$order->update_meta_data( $key, 'persist-probe' );
		}

		$order->save();

		return $order;
	}

	private function run_plugin_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}
}
