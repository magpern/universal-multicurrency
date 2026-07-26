<?php
/**
 * Integration tests: legacy / malformed / future snapshots stay usable.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderSnapshot;
use UMC\Order\OrderSnapshotReader;
use UMC\Order\RefundSnapshot;
use UMC\Settings;
use WC_Order;
use WP_UnitTestCase;

/**
 * Every snapshot state — legacy (no snapshot), M3 (v1), partial, malformed and
 * unknown-future — must remain readable and refundable, the order currency must
 * fall back to $order->get_currency(), stored totals must not change, and the
 * decimal fallback chain (stored → live config → ISO-4217 → 2) must hold.
 */
final class LegacyOrderTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );

		// One refund writer so refundability is exercised through the real hook.
		remove_all_filters( 'woocommerce_create_refund' );
		( new RefundSnapshot( new OrderSnapshotReader() ) )->register();
	}

	public function tear_down(): void {
		remove_all_filters( 'woocommerce_create_refund' );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	// -- Classification, readability & refundability --------------------------

	public function test_legacy_order_is_readable_and_refundable(): void {
		$order = $this->create_order( 'SEK', '40.00', array() );

		$snapshot = ( new OrderSnapshotReader() )->read( $order );
		$this->assertTrue( $snapshot->is_legacy() );
		$this->assertFalse( $snapshot->has_snapshot() );
		$this->assertNull( $snapshot->transaction_currency() );

		// Order currency falls back to the WC order currency for formatting.
		$resolved = $this->resolver()->resolve( $snapshot, $order->get_currency() );
		$this->assertSame( 'SEK', $resolved->code() );

		$this->assert_refundable( $order, '15.00', 'SEK' );
		$this->assert_total_unchanged( $order, '40.00' );
	}

	public function test_v1_m3_snapshot_is_classified_and_refundable(): void {
		$order = $this->create_order( 'SEK', '100.00', $this->m3_keys( 'SEK', '11.50' ) );

		$snapshot = ( new OrderSnapshotReader() )->read( $order );
		$this->assertSame( 1, $snapshot->schema_version() );
		$this->assertFalse( $snapshot->is_legacy() );
		$this->assertNull( $snapshot->stored_decimals() );

		$this->assert_refundable( $order, '25.00', 'SEK' );
		$this->assert_total_unchanged( $order, '100.00' );
	}

	public function test_partial_snapshot_is_readable_and_refundable(): void {
		// Version 2 but missing most audit fields.
		$order = $this->create_order(
			'SEK',
			'100.00',
			array(
				OrderSnapshot::META_TRANSACTION_CURRENCY => 'SEK',
				OrderSnapshot::META_SNAPSHOT_VERSION     => 2,
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );
		$this->assertTrue( $snapshot->is_partial() );
		$this->assertSame( 2, $snapshot->schema_version() );

		$this->assert_refundable( $order, '10.00', 'SEK' );
		$this->assert_total_unchanged( $order, '100.00' );
	}

	public function test_malformed_snapshot_is_readable_and_refundable(): void {
		$order = $this->create_order(
			'SEK',
			'100.00',
			array(
				OrderSnapshot::META_TRANSACTION_CURRENCY => 'SEK',
				OrderSnapshot::META_SNAPSHOT_VERSION     => 'not-an-int',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );
		$this->assertTrue( $snapshot->is_malformed() );

		// Still renders and refunds using the stored order currency + totals.
		$resolved = $this->resolver()->resolve( $snapshot, $order->get_currency() );
		$this->assertSame( 'SEK', $resolved->code() );

		$this->assert_refundable( $order, '10.00', 'SEK' );
		$this->assert_total_unchanged( $order, '100.00' );
	}

	public function test_future_version_snapshot_is_readable_and_refundable(): void {
		$order = $this->create_order(
			'SEK',
			'100.00',
			array(
				OrderSnapshot::META_TRANSACTION_CURRENCY => 'SEK',
				OrderSnapshot::META_SNAPSHOT_VERSION     => 99,
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );
		$this->assertTrue( $snapshot->is_future() );
		$this->assertSame( 99, $snapshot->schema_version() );

		$this->assert_refundable( $order, '10.00', 'SEK' );
		$this->assert_total_unchanged( $order, '100.00' );
	}

	// -- Decimal fallback chain ----------------------------------------------

	public function test_decimals_prefer_stored_value(): void {
		// Stored decimals win even when live config would say otherwise.
		$order = $this->create_order(
			'JPY',
			'2500',
			array(
				OrderSnapshot::META_TRANSACTION_CURRENCY => 'JPY',
				OrderSnapshot::META_SNAPSHOT_VERSION     => 2,
				OrderSnapshot::META_TRANSACTION_DECIMALS => 0,
			)
		);

		$resolver = $this->resolver( array( 'JPY' => array( 'decimals' => 2 ) ) );
		$this->assertSame( 0, $resolver->resolve( ( new OrderSnapshotReader() )->read( $order ), 'JPY' )->decimals() );
	}

	public function test_decimals_use_live_config_when_no_stored_value(): void {
		// v1 snapshot (no stored decimals) → live currency configuration (3).
		$order    = $this->create_order( 'BHD', '10.000', $this->m3_keys( 'BHD', '0.40' ) );
		$resolver = $this->resolver( array( 'BHD' => array( 'decimals' => 3 ) ) );

		$this->assertSame( 3, $resolver->resolve( ( new OrderSnapshotReader() )->read( $order ), 'BHD' )->decimals() );
	}

	public function test_decimals_use_iso_map_when_currency_unconfigured(): void {
		// Legacy JPY order, JPY not configured → ISO-4217 map (0).
		$order = $this->create_order( 'JPY', '2500', array() );

		$this->assertSame( 0, $this->resolver()->resolve( ( new OrderSnapshotReader() )->read( $order ), 'JPY' )->decimals() );
	}

	public function test_decimals_default_to_two_for_unknown_code(): void {
		// Unknown, unconfigured, non-ISO-listed code → default 2. Resolved directly
		// from a legacy snapshot: WooCommerce would reject such a code on an order,
		// but the resolver must still degrade gracefully to two decimals.
		$legacy = new \UMC\Order\OrderCurrencySnapshot(
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			false,
			true,
			false,
			false,
			false
		);

		$this->assertSame( 2, $this->resolver()->resolve( $legacy, 'ABC' )->decimals() );
	}

	// -- Helpers -------------------------------------------------------------

	/**
	 * The seven M3 (version-1) snapshot keys.
	 *
	 * @param string $currency Transaction currency.
	 * @param string $rate     Exchange rate string.
	 * @return array<string, scalar>
	 */
	private function m3_keys( string $currency, string $rate ): array {
		return array(
			OrderSnapshot::META_BASE_CURRENCY        => 'EUR',
			OrderSnapshot::META_TRANSACTION_CURRENCY => $currency,
			OrderSnapshot::META_EXCHANGE_RATE        => $rate,
			OrderSnapshot::META_RATE_TIMESTAMP       => 1_700_000_000,
			OrderSnapshot::META_RATE_SOURCE          => OrderSnapshot::SOURCE_MANUAL,
			OrderSnapshot::META_PLUGIN_VERSION       => '0.3.0',
			OrderSnapshot::META_RATE_IDENTITY        => $currency . ':' . $rate,
		);
	}

	/**
	 * Builds a resolver over a registry with the given configured currencies.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 */
	private function resolver( array $currencies = array() ): HistoricalFormattingResolver {
		if ( array() !== $currencies ) {
			( new Settings() )->save( array( 'currencies' => $currencies ) );
		}

		return new HistoricalFormattingResolver(
			new CurrencyRegistry( new Settings(), new Currency( 'EUR', 2 ) )
		);
	}

	/**
	 * Creates a saved order in a currency with a total and raw snapshot meta.
	 *
	 * @param string                $currency Order currency code.
	 * @param string                $total    Order total as a decimal string.
	 * @param array<string, scalar> $meta     Raw _umc_* metadata to stamp.
	 */
	private function create_order( string $currency, string $total, array $meta ): WC_Order {
		$order = new WC_Order();
		$order->set_currency( $currency );
		$order->set_total( $total );

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( (string) $key, $value );
		}

		$order->save();

		return $order;
	}

	/**
	 * Asserts an order can be refunded in its own currency for the given amount.
	 *
	 * @param WC_Order $order    Order to refund.
	 * @param string   $amount   Refund amount string.
	 * @param string   $currency Expected refund currency.
	 */
	private function assert_refundable( WC_Order $order, string $amount, string $currency ): void {
		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => $amount,
			)
		);

		$this->assertInstanceOf( \WC_Order_Refund::class, $refund );
		$this->assertSame( $currency, $refund->get_currency() );
		$this->assertSame( $amount, $refund->get_amount() );
	}

	/**
	 * Asserts the stored order total is unchanged after reloading.
	 *
	 * @param WC_Order $order    Order to reload.
	 * @param string   $expected Expected total string.
	 */
	private function assert_total_unchanged( WC_Order $order, string $expected ): void {
		$reloaded = wc_get_order( $order->get_id() );
		$this->assertSame( $expected, $reloaded->get_total() );
	}
}
