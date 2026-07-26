<?php
/**
 * Unit tests: the Store API adapters register the hooks they exist for.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Reads the adapter sources directly, without WordPress.
 *
 * A behavioural test proves a hook does the right thing when it fires; this
 * proves the registration itself has not been dropped, which is the failure
 * mode that would leave block orders silently unsnapshotted again.
 */
final class StoreApiHooksStructureTest extends TestCase {

	/**
	 * Hooks each adapter must register.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function required_hooks(): array {
		return array(
			'checkout order meta' => array( 'CheckoutSnapshotAdapter.php', 'woocommerce_store_api_checkout_update_order_meta' ),
			'cart draft re-sync'  => array( 'CheckoutSnapshotAdapter.php', 'woocommerce_store_api_cart_update_order_from_request' ),
			'order route entry'   => array( 'OrderCurrencyLock.php', 'rest_request_before_callbacks' ),
			'order route exit'    => array( 'OrderCurrencyLock.php', 'rest_request_after_callbacks' ),
			'cart endpoint data'  => array( 'CartExtensionData.php', 'woocommerce_store_api_register_endpoint_data' ),
		);
	}

	/**
	 * @dataProvider required_hooks
	 *
	 * @param string $file Adapter file basename.
	 * @param string $hook Hook that file must reference.
	 */
	public function test_adapter_registers_hook( string $file, string $hook ): void {
		$this->assertStringContainsString( $hook, $this->source( $file ) );
	}

	public function test_order_lock_restores_the_storefront_gateway_filter(): void {
		$source = $this->source( 'OrderCurrencyLock.php' );

		// Removing the session filter without putting it back would leave later
		// work in the same request filtering against a stale order currency.
		$this->assertSame(
			2,
			preg_match_all( '/remove_filter\s*\(/', $source ),
			'Each filter the lock removes must be removed exactly once.'
		);
		$this->assertSame(
			2,
			preg_match_all( '/add_filter\s*\(\s*\'woocommerce_available_payment_gateways\'/', $source ),
			'The lock installs its own gateway filter and restores the storefront one.'
		);
	}

	public function test_snapshot_adapter_confines_refreshing_to_unpaid_orders(): void {
		$source = $this->source( 'CheckoutSnapshotAdapter.php' );

		$this->assertStringContainsString( 'is_paid()', $source );
		$this->assertStringContainsString( 'get_date_paid()', $source );
		$this->assertStringContainsString( "'store-api'", $source );
	}

	public function test_adapters_do_not_swallow_exceptions(): void {
		foreach ( array( 'CheckoutSnapshotAdapter.php', 'OrderCurrencyLock.php', 'CartExtensionData.php' ) as $file ) {
			$this->assertStringNotContainsString( 'catch', $this->source( $file ), "{$file} must not swallow exceptions." );
		}
	}

	/**
	 * Reads a Store API adapter source file.
	 *
	 * @param string $file Basename below src/StoreApi.
	 */
	private function source( string $file ): string {
		$path = dirname( __DIR__, 2 ) . '/src/StoreApi/' . $file;

		$this->assertFileExists( $path );

		return (string) file_get_contents( $path );
	}
}
