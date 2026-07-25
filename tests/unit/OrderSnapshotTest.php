<?php
/**
 * Unit tests for the pure order-snapshot metadata builder.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Order\OrderSnapshot;

/**
 * Covers the WordPress-free snapshot_meta() builder: stable keys and values.
 */
final class OrderSnapshotTest extends TestCase {

	public function test_snapshot_meta_uses_stable_keys_and_values(): void {
		$meta = OrderSnapshot::snapshot_meta( 'EUR', 'SEK', '11.50', 1_700_000_000, 'manual', '0.3.0', 'SEK:11.50' );

		$this->assertSame(
			array(
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '11.50',
				'_umc_rate_timestamp'       => 1_700_000_000,
				'_umc_rate_source'          => 'manual',
				'_umc_plugin_version'       => '0.3.0',
				'_umc_rate_identity'        => 'SEK:11.50',
			),
			$meta
		);
	}

	public function test_snapshot_meta_keys_match_class_constants(): void {
		$meta = OrderSnapshot::snapshot_meta( 'EUR', 'EUR', '1', 0, 'manual', '0.3.0', 'EUR:1' );

		$this->assertArrayHasKey( OrderSnapshot::META_BASE_CURRENCY, $meta );
		$this->assertArrayHasKey( OrderSnapshot::META_TRANSACTION_CURRENCY, $meta );
		$this->assertArrayHasKey( OrderSnapshot::META_EXCHANGE_RATE, $meta );
		$this->assertArrayHasKey( OrderSnapshot::META_RATE_TIMESTAMP, $meta );
		$this->assertArrayHasKey( OrderSnapshot::META_RATE_SOURCE, $meta );
		$this->assertArrayHasKey( OrderSnapshot::META_PLUGIN_VERSION, $meta );
		$this->assertArrayHasKey( OrderSnapshot::META_RATE_IDENTITY, $meta );
	}
}
