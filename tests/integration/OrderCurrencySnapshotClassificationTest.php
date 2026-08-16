<?php
/**
 * Unit tests for the order currency snapshot reader and classification.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Order\OrderCurrencySnapshot;
use UMC\Order\OrderSnapshotReader;
use WC_Order;

/**
 * Verifies snapshot reading and classification (legacy, v1, v2, partial, malformed, future).
 */
final class OrderCurrencySnapshotClassificationTest extends TestCase {

	/**
	 * A snapshot with all M3 keys (no explicit version) classifies as version 1.
	 */
	public function test_m3_snapshot_without_explicit_version_classifies_as_version_1(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '11.50',
				'_umc_rate_timestamp'       => '1700000000',
				'_umc_rate_source'          => 'manual',
				'_umc_plugin_version'       => '0.3.0',
				'_umc_rate_identity'        => 'SEK:11.50',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertTrue( $snapshot->has_snapshot() );
		$this->assertFalse( $snapshot->is_legacy() );
		$this->assertSame( 1, $snapshot->schema_version() );
		$this->assertFalse( $snapshot->is_partial() );
		$this->assertFalse( $snapshot->is_malformed() );
		$this->assertFalse( $snapshot->is_future() );
	}

	/**
	 * An M3 snapshot with explicit version 1 is classified correctly.
	 */
	public function test_m3_snapshot_with_explicit_version_1(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_snapshot_version'     => '1',
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '11.50',
				'_umc_rate_timestamp'       => '1700000000',
				'_umc_rate_source'          => 'manual',
				'_umc_plugin_version'       => '0.3.0',
				'_umc_rate_identity'        => 'SEK:11.50',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertSame( 1, $snapshot->schema_version() );
		$this->assertFalse( $snapshot->is_partial() );
	}

	/**
	 * An M4 snapshot with version 2 and stored decimals is classified correctly.
	 */
	public function test_m4_snapshot_with_version_2_and_decimals(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_snapshot_version'     => '2',
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'JPY',
				'_umc_exchange_rate'        => '155.50',
				'_umc_rate_timestamp'       => '1700000000',
				'_umc_rate_source'          => 'manual',
				'_umc_plugin_version'       => '0.4.0',
				'_umc_rate_identity'        => 'JPY:155.50',
				'_umc_transaction_decimals' => '0',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertSame( 2, $snapshot->schema_version() );
		$this->assertSame( 0, $snapshot->stored_decimals() );
		$this->assertFalse( $snapshot->is_partial() );
	}

	/**
	 * An M11 snapshot with version 3 exposes checkout-policy fields (schema-3
	 * origin fixture; M26 WP2 -- the >= SCHEMA_VERSION_3 read branch had only
	 * incidental coverage via real current-schema orders before this).
	 */
	public function test_snapshot_with_version_3_exposes_checkout_policy_fields(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_snapshot_version'     => '3',
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '11.50',
				'_umc_rate_timestamp'       => '1700000000',
				'_umc_rate_source'          => 'manual',
				'_umc_plugin_version'       => '0.10.0',
				'_umc_rate_identity'        => 'SEK:11.50',
				'_umc_checkout_mode'        => 'selected_currency',
				'_umc_shopper_currency'     => 'SEK',
				'_umc_fallback_occurred'    => 'no',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertSame( 3, $snapshot->schema_version() );
		$this->assertSame( 'selected_currency', $snapshot->checkout_mode() );
		$this->assertSame( 'SEK', $snapshot->shopper_currency() );
		$this->assertFalse( $snapshot->fallback_occurred() );
		$this->assertNull( $snapshot->rate_provider() );
		$this->assertNull( $snapshot->rate_adjustment() );
	}

	/**
	 * An M16 snapshot with version 4 exposes rate provider/adjustment fields
	 * in addition to the version-3 checkout-policy fields (schema-4 origin
	 * fixture; M26 WP2 -- the >= SCHEMA_VERSION_4 read branch had only
	 * incidental coverage via real current-schema orders before this).
	 */
	public function test_snapshot_with_version_4_exposes_rate_provider_and_adjustment(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_snapshot_version'     => '4',
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '11.50',
				'_umc_rate_timestamp'       => '1700000000',
				'_umc_rate_source'          => 'automatic',
				'_umc_plugin_version'       => '0.15.0',
				'_umc_rate_identity'        => 'SEK:11.50',
				'_umc_checkout_mode'        => 'store_currency',
				'_umc_shopper_currency'     => 'SEK',
				'_umc_fallback_occurred'    => 'yes',
				'_umc_rate_provider'        => 'frankfurter',
				'_umc_rate_adjustment'      => '0.50',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertSame( 4, $snapshot->schema_version() );
		$this->assertSame( 'frankfurter', $snapshot->rate_provider() );
		$this->assertSame( '0.50', $snapshot->rate_adjustment() );
		$this->assertSame( 'store_currency', $snapshot->checkout_mode() );
		$this->assertTrue( $snapshot->fallback_occurred() );
		$this->assertNull( $snapshot->currency_origin() );
	}

	/**
	 * A legacy order (no snapshot) is classified correctly.
	 */
	public function test_legacy_order_without_snapshot(): void {
		$order = $this->create_order_with_meta( array() );

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertTrue( $snapshot->is_legacy() );
		$this->assertFalse( $snapshot->has_snapshot() );
		$this->assertNull( $snapshot->schema_version() );
	}

	/**
	 * A partial snapshot (missing some M3 keys) is classified as partial.
	 */
	public function test_partial_snapshot_missing_audit_fields(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '11.50',
				// Missing rate_timestamp, rate_source, plugin_version, rate_identity.
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertTrue( $snapshot->is_partial() );
		$this->assertSame( 1, $snapshot->schema_version() );
		$this->assertNull( $snapshot->rate_timestamp() );
	}

	/**
	 * A malformed version (non-integer) is classified as malformed.
	 */
	public function test_malformed_snapshot_non_integer_version(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_snapshot_version'     => 'notaninteger',
				'_umc_transaction_currency' => 'SEK',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertTrue( $snapshot->is_malformed() );
		$this->assertFalse( $snapshot->is_future() );
	}

	/**
	 * A future version (unknown) is classified as future.
	 */
	public function test_future_snapshot_unknown_version(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_snapshot_version'     => '99',
				'_umc_transaction_currency' => 'SEK',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertTrue( $snapshot->is_future() );
		$this->assertFalse( $snapshot->is_malformed() );
		$this->assertSame( 99, $snapshot->schema_version() );
	}

	/**
	 * Empty strings are normalized to null.
	 */
	public function test_empty_strings_normalized_to_null(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '',
				'_umc_rate_timestamp'       => '1700000000',
				'_umc_rate_source'          => '',
				'_umc_plugin_version'       => '0.3.0',
				'_umc_rate_identity'        => '',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertNull( $snapshot->exchange_rate() );
		$this->assertNull( $snapshot->rate_source() );
		$this->assertNull( $snapshot->rate_identity() );
	}

	/**
	 * Stored decimals zero is valid and preserved.
	 */
	public function test_stored_decimals_zero_is_valid(): void {
		$order = $this->create_order_with_meta(
			array(
				'_umc_snapshot_version'     => '2',
				'_umc_transaction_currency' => 'JPY',
				'_umc_transaction_decimals' => '0',
			)
		);

		$snapshot = ( new OrderSnapshotReader() )->read( $order );

		$this->assertSame( 0, $snapshot->stored_decimals() );
	}

	/**
	 * Creates a mock WC_Order with the given metadata.
	 *
	 * @param array<string, string> $meta Key-value pairs.
	 */
	private function create_order_with_meta( array $meta ): WC_Order {
		$order = $this->createMock( WC_Order::class );

		$order->expects( $this->any() )
			->method( 'get_meta' )
			->willReturnCallback(
				static function ( string $key ) use ( $meta ): string {
					return $meta[ $key ] ?? '';
				}
			);

		return $order;
	}
}
