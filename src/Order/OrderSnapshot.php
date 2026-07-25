<?php
/**
 * Immutable order-time currency / rate snapshot.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Order;

use UMC\CurrencyContext;
use UMC\Settings;
use WC_Order;

/**
 * Writes a permanent audit snapshot of the currency and exchange rate used at
 * order creation.
 *
 * WooCommerce already stores the order currency and the active-currency line
 * totals natively, so this class does not touch any monetary total — it only
 * records the base currency, the transaction currency, the exact rate, its
 * timestamp and source, the plugin version and the rate identity, via `WC_Order`
 * CRUD (HPOS-safe; no post meta, no SQL). The snapshot is written once at
 * creation and never overwritten, so changing store rates later can never alter a
 * historical order.
 *
 * The order object is saved by WooCommerce's own checkout flow after
 * `woocommerce_checkout_create_order`, so this class must not call save().
 */
final class OrderSnapshot {

	public const META_BASE_CURRENCY        = '_umc_base_currency';
	public const META_TRANSACTION_CURRENCY = '_umc_transaction_currency';
	public const META_EXCHANGE_RATE        = '_umc_exchange_rate';
	public const META_RATE_TIMESTAMP       = '_umc_rate_timestamp';
	public const META_RATE_SOURCE          = '_umc_rate_source';
	public const META_PLUGIN_VERSION       = '_umc_plugin_version';
	public const META_RATE_IDENTITY        = '_umc_rate_identity';

	/**
	 * Rate source identifier for the manual (admin-entered) provider.
	 */
	public const SOURCE_MANUAL = 'manual';

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Settings store (for the per-currency rate timestamp).
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Plugin version stamped into the snapshot.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Binds the snapshot writer to its collaborators.
	 *
	 * @param CurrencyContext $context  Request-scoped currency facade.
	 * @param Settings        $settings Settings store.
	 * @param string          $version  Plugin version.
	 */
	public function __construct( CurrencyContext $context, Settings $settings, string $version ) {
		$this->context  = $context;
		$this->settings = $settings;
		$this->version  = $version;
	}

	/**
	 * Registers the order-creation hook (classic checkout).
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'write_snapshot' ), 10, 2 );
	}

	/**
	 * Writes the snapshot onto a new order, once.
	 *
	 * @param mixed $order Order being created.
	 * @param mixed $data  Posted checkout data (unused).
	 */
	public function write_snapshot( $order, $data = array() ): void {
		unset( $data );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Write-once: never overwrite an existing snapshot.
		if ( '' !== (string) $order->get_meta( self::META_TRANSACTION_CURRENCY ) ) {
			return;
		}

		$meta = self::snapshot_meta(
			$this->context->get_base_currency()->code(),
			$this->context->get_active_code(),
			$this->context->get_rate(),
			$this->rate_timestamp( $this->context->get_active_code() ),
			self::SOURCE_MANUAL,
			$this->version,
			$this->context->get_currency_signature()
		);

		/**
		 * Filters the order snapshot metadata before it is written.
		 *
		 * @since 0.3.0
		 *
		 * @param array<string, scalar> $meta    Snapshot metadata keyed by meta key.
		 * @param WC_Order              $order   Order being created.
		 * @param CurrencyContext       $context Request-scoped currency facade.
		 */
		$meta = (array) apply_filters( 'umc_order_snapshot_meta', $meta, $order, $this->context );

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( (string) $key, $value );
		}

		/**
		 * Fires after the order snapshot metadata has been staged on the order.
		 *
		 * @since 0.3.0
		 *
		 * @param WC_Order              $order Order being created.
		 * @param array<string, scalar> $meta  Snapshot metadata written.
		 */
		do_action( 'umc_order_snapshot_created', $order, $meta );
	}

	/**
	 * Builds the snapshot metadata array from primitive values.
	 *
	 * Pure and WordPress-free so it is unit-testable in isolation.
	 *
	 * @param string $base_currency        Store base currency code.
	 * @param string $transaction_currency Order (active) currency code.
	 * @param string $exchange_rate        Base→transaction rate string.
	 * @param int    $rate_timestamp       Unix timestamp the rate was last set.
	 * @param string $rate_source          Rate source identifier.
	 * @param string $plugin_version       Plugin version.
	 * @param string $rate_identity        Rate identity (code:rate).
	 * @return array<string, scalar>
	 */
	public static function snapshot_meta(
		string $base_currency,
		string $transaction_currency,
		string $exchange_rate,
		int $rate_timestamp,
		string $rate_source,
		string $plugin_version,
		string $rate_identity
	): array {
		return array(
			self::META_BASE_CURRENCY        => $base_currency,
			self::META_TRANSACTION_CURRENCY => $transaction_currency,
			self::META_EXCHANGE_RATE        => $exchange_rate,
			self::META_RATE_TIMESTAMP       => $rate_timestamp,
			self::META_RATE_SOURCE          => $rate_source,
			self::META_PLUGIN_VERSION       => $plugin_version,
			self::META_RATE_IDENTITY        => $rate_identity,
		);
	}

	/**
	 * The Unix timestamp the active currency's rate was last set, or now.
	 *
	 * @param string $code Active currency code.
	 */
	private function rate_timestamp( string $code ): int {
		$config = $this->settings->get_currency_config( $code );

		if ( null !== $config && isset( $config['rate_updated_at'] ) && (int) $config['rate_updated_at'] > 0 ) {
			return (int) $config['rate_updated_at'];
		}

		return time();
	}
}
