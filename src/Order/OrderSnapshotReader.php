<?php
/**
 * Immutable order-snapshot metadata reader.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Order;

use WC_Order;

/**
 * Reads and classifies persisted order currency metadata via WC_Order CRUD.
 *
 * Never depends on Settings, CurrencyRegistry, RateProvider, or session state.
 * Classifies snapshots as valid (v1/v2), legacy, partial, malformed, or future
 * without throwing exceptions — orders always remain readable and refundable.
 *
 * The snapshot version is explicit; when absent, the presence/absence of M3 keys
 * is used for backward-compatible classification (v1 if keys present, legacy if not).
 */
final class OrderSnapshotReader {

	/**
	 * Snapshot version for M3 orders (inferred from presence of M3 keys).
	 */
	private const SCHEMA_VERSION_1 = 1;

	/**
	 * Snapshot version for M4+ orders (explicit _umc_snapshot_version).
	 */
	private const SCHEMA_VERSION_2 = 2;

	/**
	 * Reads and classifies the snapshot from an order.
	 *
	 * @param WC_Order $order Order to read from.
	 * @return OrderCurrencySnapshot
	 */
	public function read( WC_Order $order ): OrderCurrencySnapshot {
		$base_currency        = (string) $order->get_meta( OrderSnapshot::META_BASE_CURRENCY );
		$transaction_currency = (string) $order->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY );
		$exchange_rate        = (string) $order->get_meta( OrderSnapshot::META_EXCHANGE_RATE );
		$rate_timestamp       = $order->get_meta( OrderSnapshot::META_RATE_TIMESTAMP );
		$rate_source          = (string) $order->get_meta( OrderSnapshot::META_RATE_SOURCE );
		$plugin_version       = (string) $order->get_meta( OrderSnapshot::META_PLUGIN_VERSION );
		$rate_identity        = (string) $order->get_meta( OrderSnapshot::META_RATE_IDENTITY );
		$snapshot_version     = $order->get_meta( '_umc_snapshot_version' );
		$stored_decimals      = $order->get_meta( '_umc_transaction_decimals' );

		// Classify the snapshot state.
		$has_any_m3_keys = '' !== $transaction_currency;
		$schema_version  = null;
		$is_legacy       = false;
		$is_partial      = false;
		$is_malformed    = false;
		$is_future       = false;

		// Explicit version key is present; validate it.
		if ( '' !== (string) $snapshot_version ) {
			$version_int = (int) $snapshot_version;

			// Validate that the version can be parsed as an integer.
			if ( (string) $version_int !== (string) $snapshot_version || $version_int < 1 ) {
				$is_malformed = true;
			} elseif ( $version_int > self::SCHEMA_VERSION_2 ) {
				// Unknown future version.
				$is_future      = true;
				$schema_version = $version_int;
			} else {
				// Valid version 1 or 2.
				$schema_version = $version_int;
			}
		} elseif ( $has_any_m3_keys ) {
			// No explicit version but M3 keys present — infer version 1.
			$schema_version = self::SCHEMA_VERSION_1;
		} else {
			// No version, no M3 keys — legacy.
			$is_legacy = true;
		}

		// Check for partial snapshot (some keys missing within a valid version).
		if ( ! $is_malformed && $schema_version && $has_any_m3_keys ) {
			$is_partial = $this->is_partial_snapshot(
				$base_currency,
				$exchange_rate,
				$rate_timestamp,
				$rate_source,
				$plugin_version,
				$rate_identity
			);
		}

		// Normalize empty strings to null for all fields.
		$base_currency_out        = '' !== $base_currency ? $base_currency : null;
		$transaction_currency_out = '' !== $transaction_currency ? $transaction_currency : null;
		$exchange_rate_out        = '' !== $exchange_rate ? $exchange_rate : null;
		$rate_timestamp_out       = ( '' !== (string) $rate_timestamp && (int) $rate_timestamp > 0 ) ? (int) $rate_timestamp : null;
		$rate_source_out          = '' !== $rate_source ? $rate_source : null;
		$plugin_version_out       = '' !== $plugin_version ? $plugin_version : null;
		$rate_identity_out        = '' !== $rate_identity ? $rate_identity : null;
		$stored_decimals_out      = ( '' !== (string) $stored_decimals && (int) $stored_decimals >= 0 ) ? (int) $stored_decimals : null;

		return new OrderCurrencySnapshot(
			$schema_version,
			$base_currency_out,
			$transaction_currency_out,
			$exchange_rate_out,
			$rate_timestamp_out,
			$rate_source_out,
			$plugin_version_out,
			$rate_identity_out,
			$stored_decimals_out,
			$has_any_m3_keys,
			$is_legacy,
			$is_partial,
			$is_malformed,
			$is_future
		);
	}

	/**
	 * Whether a snapshot is missing some M3 keys.
	 *
	 * All seven M3 keys should be present in a complete snapshot; if any is missing,
	 * the snapshot is partial (but still usable).
	 *
	 * @param string|null $base_currency  Base currency.
	 * @param string|null $exchange_rate  Exchange rate.
	 * @param int|null    $rate_timestamp Timestamp.
	 * @param string|null $rate_source    Rate source.
	 * @param string|null $plugin_version Plugin version.
	 * @param string|null $rate_identity  Rate identity.
	 */
	private function is_partial_snapshot(
		?string $base_currency,
		?string $exchange_rate,
		?int $rate_timestamp,
		?string $rate_source,
		?string $plugin_version,
		?string $rate_identity
	): bool {
		return null === $base_currency
			|| null === $exchange_rate
			|| null === $rate_timestamp
			|| null === $rate_source
			|| null === $plugin_version
			|| null === $rate_identity;
	}
}
