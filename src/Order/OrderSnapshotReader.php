<?php
/**
 * Immutable order-snapshot metadata reader.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

use UMC\CurrencySwitcher;
use WC_Order;

/**
 * Reads and classifies persisted order currency metadata via WC_Order CRUD.
 */
final class OrderSnapshotReader {

	private const SCHEMA_VERSION_1       = 1;
	private const SCHEMA_VERSION_2       = 2;
	private const SCHEMA_VERSION_3       = 3;
	private const SCHEMA_VERSION_4       = 4;
	private const SCHEMA_VERSION_5       = 5;
	private const SCHEMA_VERSION_CURRENT = self::SCHEMA_VERSION_5;

	/**
	 * Reads and classifies persisted currency metadata for one order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function read( WC_Order $order ): OrderCurrencySnapshot {
		$base_currency        = (string) $order->get_meta( OrderSnapshot::META_BASE_CURRENCY );
		$transaction_currency = (string) $order->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY );
		$exchange_rate        = (string) $order->get_meta( OrderSnapshot::META_EXCHANGE_RATE );
		$rate_timestamp_raw   = $order->get_meta( OrderSnapshot::META_RATE_TIMESTAMP );
		$rate_source          = (string) $order->get_meta( OrderSnapshot::META_RATE_SOURCE );
		$plugin_version       = (string) $order->get_meta( OrderSnapshot::META_PLUGIN_VERSION );
		$rate_identity        = (string) $order->get_meta( OrderSnapshot::META_RATE_IDENTITY );
		$snapshot_version     = $order->get_meta( OrderSnapshot::META_SNAPSHOT_VERSION );
		$stored_decimals_raw  = $order->get_meta( OrderSnapshot::META_TRANSACTION_DECIMALS );
		$rate_provider_raw    = (string) $order->get_meta( OrderSnapshot::META_RATE_PROVIDER );
		$rate_adjustment_raw  = (string) $order->get_meta( OrderSnapshot::META_RATE_ADJUSTMENT );
		$checkout_mode_raw    = (string) $order->get_meta( OrderSnapshot::META_CHECKOUT_MODE );
		$shopper_currency_raw = (string) $order->get_meta( OrderSnapshot::META_SHOPPER_CURRENCY );
		$fallback_raw         = (string) $order->get_meta( OrderSnapshot::META_FALLBACK_OCCURRED );
		$origin_raw           = (string) $order->get_meta( OrderSnapshot::META_CURRENCY_ORIGIN );

		$rate_timestamp  = '' !== (string) $rate_timestamp_raw ? (int) $rate_timestamp_raw : null;
		$stored_decimals = '' !== (string) $stored_decimals_raw ? (int) $stored_decimals_raw : null;

		$has_any_m3_keys = '' !== $transaction_currency;
		$schema_version  = null;
		$is_legacy       = false;
		$is_partial      = false;
		$is_malformed    = false;
		$is_future       = false;

		if ( '' !== (string) $snapshot_version ) {
			$version_int = (int) $snapshot_version;

			if ( (string) $version_int !== (string) $snapshot_version || $version_int < 1 ) {
				$is_malformed = true;
			} elseif ( $version_int > self::SCHEMA_VERSION_CURRENT ) {
				$is_future      = true;
				$schema_version = $version_int;
			} else {
				$schema_version = $version_int;
			}
		} elseif ( $has_any_m3_keys ) {
			$schema_version = self::SCHEMA_VERSION_1;
		} else {
			$is_legacy = true;
		}

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

		$base_currency_out        = '' !== $base_currency ? $base_currency : null;
		$transaction_currency_out = '' !== $transaction_currency ? $transaction_currency : null;
		$exchange_rate_out        = '' !== $exchange_rate ? $exchange_rate : null;
		$rate_timestamp_out       = null !== $rate_timestamp && $rate_timestamp > 0 ? $rate_timestamp : null;
		$rate_source_out          = '' !== $rate_source ? $rate_source : null;
		$plugin_version_out       = '' !== $plugin_version ? $plugin_version : null;
		$rate_identity_out        = '' !== $rate_identity ? $rate_identity : null;
		$stored_decimals_out      = null !== $stored_decimals && $stored_decimals >= 0 ? $stored_decimals : null;
		$rate_provider_out        = null;
		$rate_adjustment_out      = null;
		$checkout_mode_out        = null;
		$shopper_currency_out     = null;
		$fallback_out             = null;
		$origin_out               = null;

		if ( null !== $schema_version && $schema_version >= self::SCHEMA_VERSION_4 ) {
			$rate_provider_out   = $rate_provider_raw;
			$rate_adjustment_out = $rate_adjustment_raw;
		}

		if ( null !== $schema_version && $schema_version >= self::SCHEMA_VERSION_3 ) {
			$checkout_mode_out    = '' !== $checkout_mode_raw ? $checkout_mode_raw : null;
			$shopper_currency_out = '' !== $shopper_currency_raw ? $shopper_currency_raw : null;
			if ( '' !== $fallback_raw ) {
				$fallback_out = 'yes' === $fallback_raw;
			}
		}

		if ( '' !== $origin_raw ) {
			if ( CurrencySwitcher::ORIGIN_CUSTOMER === $origin_raw || CurrencySwitcher::ORIGIN_VISITOR_LOCATION === $origin_raw ) {
				$origin_out = $origin_raw;
			}
		}

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
			$is_future,
			$rate_provider_out,
			$rate_adjustment_out,
			$checkout_mode_out,
			$shopper_currency_out,
			$fallback_out,
			$origin_out
		);
	}

	/**
	 * Whether a schema-1+ snapshot is missing required audit keys.
	 *
	 * @param string|null $base_currency Base store currency.
	 * @param string|null $exchange_rate Frozen exchange rate.
	 * @param int|null    $rate_timestamp Rate timestamp.
	 * @param string|null $rate_source Rate source identifier.
	 * @param string|null $plugin_version Plugin version at snapshot time.
	 * @param string|null $rate_identity Rate identity fingerprint.
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
