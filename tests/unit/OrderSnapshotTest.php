<?php
/**
 * Unit tests for the pure order-snapshot metadata builder.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Checkout\CheckoutSettings;
use UMC\Order\OrderSnapshot;

/**
 * Covers the WordPress-free snapshot_meta() builder: stable keys and values.
 */
final class OrderSnapshotTest extends TestCase {

	public function test_snapshot_meta_uses_stable_keys_and_values(): void {
		// M11 snapshots default to v3 with checkout policy metadata.
		$meta = OrderSnapshot::snapshot_meta(
			'EUR',
			'SEK',
			'11.50',
			1_700_000_000,
			'manual',
			'0.3.0',
			'SEK:11.50',
			3,
			2,
			CheckoutSettings::MODE_SELECTED,
			'SEK',
			false
		);

		$this->assertSame(
			array(
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'SEK',
				'_umc_exchange_rate'        => '11.50',
				'_umc_rate_timestamp'       => 1_700_000_000,
				'_umc_rate_source'          => 'manual',
				'_umc_plugin_version'       => '0.3.0',
				'_umc_rate_identity'        => 'SEK:11.50',
				'_umc_snapshot_version'     => 3,
				'_umc_transaction_decimals' => 2,
				'_umc_checkout_mode'        => CheckoutSettings::MODE_SELECTED,
				'_umc_shopper_currency'     => 'SEK',
				'_umc_fallback_occurred'    => 'no',
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

	public function test_snapshot_meta_m4_includes_version_and_decimals(): void {
		$meta = OrderSnapshot::snapshot_meta( 'EUR', 'JPY', '155.50', 1_700_000_000, 'manual', '0.4.0', 'JPY:155.50', 2, 0 );

		$this->assertSame(
			array(
				'_umc_base_currency'        => 'EUR',
				'_umc_transaction_currency' => 'JPY',
				'_umc_exchange_rate'        => '155.50',
				'_umc_rate_timestamp'       => 1_700_000_000,
				'_umc_rate_source'          => 'manual',
				'_umc_plugin_version'       => '0.4.0',
				'_umc_rate_identity'        => 'JPY:155.50',
				'_umc_snapshot_version'     => 2,
				'_umc_transaction_decimals' => 0,
			),
			$meta
		);
	}

	public function test_snapshot_meta_v2_backward_compat_omits_checkout_fields(): void {
		$meta = OrderSnapshot::snapshot_meta( 'EUR', 'SEK', '11.50', 1_700_000_000, 'manual', '0.3.0', 'SEK:11.50', 2, 2 );

		$this->assertSame( 2, $meta[ OrderSnapshot::META_SNAPSHOT_VERSION ] );
		$this->assertArrayNotHasKey( OrderSnapshot::META_CHECKOUT_MODE, $meta );
		$this->assertArrayNotHasKey( OrderSnapshot::META_SHOPPER_CURRENCY, $meta );
		$this->assertArrayNotHasKey( OrderSnapshot::META_FALLBACK_OCCURRED, $meta );
	}

	public function test_snapshot_meta_v3_includes_checkout_policy_fields(): void {
		$meta = OrderSnapshot::snapshot_meta(
			'EUR',
			'EUR',
			'1',
			0,
			'manual',
			'0.10.0',
			'EUR:1',
			3,
			2,
			CheckoutSettings::MODE_STORE,
			'SEK',
			true
		);

		$this->assertSame( 3, $meta[ OrderSnapshot::META_SNAPSHOT_VERSION ] );
		$this->assertSame( CheckoutSettings::MODE_STORE, $meta[ OrderSnapshot::META_CHECKOUT_MODE ] );
		$this->assertSame( 'SEK', $meta[ OrderSnapshot::META_SHOPPER_CURRENCY ] );
		$this->assertSame( 'yes', $meta[ OrderSnapshot::META_FALLBACK_OCCURRED ] );
	}

	public function test_snapshot_meta_m4_keys_match_constants(): void {
		$meta = OrderSnapshot::snapshot_meta( 'EUR', 'JPY', '155.50', 1_700_000_000, 'manual', '0.4.0', 'JPY:155.50', 2, 0 );

		$this->assertArrayHasKey( OrderSnapshot::META_SNAPSHOT_VERSION, $meta );
		$this->assertArrayHasKey( OrderSnapshot::META_TRANSACTION_DECIMALS, $meta );
		$this->assertSame( 2, $meta[ OrderSnapshot::META_SNAPSHOT_VERSION ] );
		$this->assertSame( 0, $meta[ OrderSnapshot::META_TRANSACTION_DECIMALS ] );
	}
}
