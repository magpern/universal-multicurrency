<?php
/**
 * Immutable order-time currency snapshot with schema versioning.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Order;

/**
 * Immutable readonly value object representing a persisted snapshot of order
 * currency metadata at creation, or the legacy fallback (order currency only).
 *
 * Schema version 1: M3 snapshot with 7 keys (_umc_base_currency, _umc_transaction_currency,
 * _umc_exchange_rate, _umc_rate_timestamp, _umc_rate_source, _umc_plugin_version,
 * _umc_rate_identity).
 *
 * Schema version 2: M4 snapshot adds _umc_snapshot_version, _umc_transaction_decimals.
 *
 * Legacy (no snapshot): pre-M3 order with native WC order currency only.
 */
final class OrderCurrencySnapshot {

	/**
	 * Snapshot schema version (M4+).
	 *
	 * @var int|null
	 */
	private ?int $schema_version;

	/**
	 * Base currency code at order creation.
	 *
	 * @var string|null
	 */
	private ?string $base_currency;

	/**
	 * Transaction currency code (order currency).
	 *
	 * @var string|null
	 */
	private ?string $transaction_currency;

	/**
	 * Base→transaction exchange rate as a decimal string.
	 *
	 * @var string|null
	 */
	private ?string $exchange_rate;

	/**
	 * Unix timestamp the rate was last set.
	 *
	 * @var int|null
	 */
	private ?int $rate_timestamp;

	/**
	 * Rate source identifier (e.g., 'manual').
	 *
	 * @var string|null
	 */
	private ?string $rate_source;

	/**
	 * Plugin version at order creation.
	 *
	 * @var string|null
	 */
	private ?string $plugin_version;

	/**
	 * Rate identity (e.g., 'SEK:11.50').
	 *
	 * @var string|null
	 */
	private ?string $rate_identity;

	/**
	 * Stored transaction decimals (M4+). Null for M3 and legacy orders.
	 *
	 * @var int|null
	 */
	private ?int $stored_decimals;

	/**
	 * Classification: is there any M3+ snapshot?
	 *
	 * @var bool
	 */
	private bool $has_snapshot;

	/**
	 * Classification: is this a pre-M3 legacy order?
	 *
	 * @var bool
	 */
	private bool $is_legacy;

	/**
	 * Classification: some snapshot keys missing within a valid schema version?
	 *
	 * @var bool
	 */
	private bool $is_partial;

	/**
	 * Classification: malformed (e.g., non-integer version, invalid types)?
	 *
	 * @var bool
	 */
	private bool $is_malformed;

	/**
	 * Classification: unknown future schema version?
	 *
	 * @var bool
	 */
	private bool $is_future;

	/**
	 * Builds an immutable snapshot.
	 *
	 * @param int|null    $schema_version     Snapshot schema version (1, 2, or unknown).
	 * @param string|null $base_currency      Base currency code.
	 * @param string|null $transaction_currency Order currency code.
	 * @param string|null $exchange_rate      Exchange rate string.
	 * @param int|null    $rate_timestamp     Timestamp.
	 * @param string|null $rate_source        Rate source identifier.
	 * @param string|null $plugin_version     Plugin version.
	 * @param string|null $rate_identity      Rate identity string.
	 * @param int|null    $stored_decimals    Stored transaction decimals (M4+).
	 * @param bool        $has_snapshot       Has any snapshot?
	 * @param bool        $is_legacy          Is legacy (no snapshot)?
	 * @param bool        $is_partial         Some keys missing?
	 * @param bool        $is_malformed       Malformed metadata?
	 * @param bool        $is_future          Unknown future version?
	 */
	public function __construct(
		?int $schema_version,
		?string $base_currency,
		?string $transaction_currency,
		?string $exchange_rate,
		?int $rate_timestamp,
		?string $rate_source,
		?string $plugin_version,
		?string $rate_identity,
		?int $stored_decimals,
		bool $has_snapshot,
		bool $is_legacy,
		bool $is_partial,
		bool $is_malformed,
		bool $is_future
	) {
		$this->schema_version       = $schema_version;
		$this->base_currency        = $base_currency;
		$this->transaction_currency = $transaction_currency;
		$this->exchange_rate        = $exchange_rate;
		$this->rate_timestamp       = $rate_timestamp;
		$this->rate_source          = $rate_source;
		$this->plugin_version       = $plugin_version;
		$this->rate_identity        = $rate_identity;
		$this->stored_decimals      = $stored_decimals;
		$this->has_snapshot         = $has_snapshot;
		$this->is_legacy            = $is_legacy;
		$this->is_partial           = $is_partial;
		$this->is_malformed         = $is_malformed;
		$this->is_future            = $is_future;
	}

	/**
	 * Snapshot schema version (1 = M3, 2 = M4+, null = legacy).
	 */
	public function schema_version(): ?int {
		return $this->schema_version;
	}

	/**
	 * Base currency code at order creation, or null if unknown.
	 */
	public function base_currency(): ?string {
		return $this->base_currency;
	}

	/**
	 * Transaction currency code (order currency), or null if unknown.
	 */
	public function transaction_currency(): ?string {
		return $this->transaction_currency;
	}

	/**
	 * Base→transaction exchange rate string, or null if unknown.
	 */
	public function exchange_rate(): ?string {
		return $this->exchange_rate;
	}

	/**
	 * Unix timestamp the rate was last set, or null if unknown.
	 */
	public function rate_timestamp(): ?int {
		return $this->rate_timestamp;
	}

	/**
	 * Rate source identifier ('manual', etc.), or null if unknown.
	 */
	public function rate_source(): ?string {
		return $this->rate_source;
	}

	/**
	 * Plugin version at order creation, or null if unknown.
	 */
	public function plugin_version(): ?string {
		return $this->plugin_version;
	}

	/**
	 * Rate identity (code:rate), or null if unknown.
	 */
	public function rate_identity(): ?string {
		return $this->rate_identity;
	}

	/**
	 * Stored transaction decimals for M4+ orders, or null for M3/legacy.
	 *
	 * When null, the caller must resolve decimals via HistoricalFormattingResolver.
	 */
	public function stored_decimals(): ?int {
		return $this->stored_decimals;
	}

	/**
	 * Whether this snapshot has any M3+ data (not legacy).
	 */
	public function has_snapshot(): bool {
		return $this->has_snapshot;
	}

	/**
	 * Whether this is a pre-M3 legacy order (order currency only, no snapshot).
	 */
	public function is_legacy(): bool {
		return $this->is_legacy;
	}

	/**
	 * Whether some snapshot keys are missing within a valid schema version.
	 */
	public function is_partial(): bool {
		return $this->is_partial;
	}

	/**
	 * Whether the snapshot metadata is malformed (non-integer version, invalid types, etc.).
	 */
	public function is_malformed(): bool {
		return $this->is_malformed;
	}

	/**
	 * Whether the snapshot schema version is unknown/future.
	 *
	 * The order remains readable and refundable; known fields are used, unknown ignored.
	 */
	public function is_future(): bool {
		return $this->is_future;
	}
}
