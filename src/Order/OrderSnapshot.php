<?php
/**
 * Immutable order-time currency / rate snapshot.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\CurrencyContext;
use UMC\CurrencySwitcher;
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
	public const META_SNAPSHOT_VERSION     = '_umc_snapshot_version';
	public const META_TRANSACTION_DECIMALS = '_umc_transaction_decimals';
	public const META_CHECKOUT_MODE        = '_umc_checkout_mode';
	public const META_SHOPPER_CURRENCY     = '_umc_shopper_currency';
	public const META_FALLBACK_OCCURRED    = '_umc_fallback_occurred';
	public const META_RATE_PROVIDER        = '_umc_rate_provider';
	public const META_RATE_ADJUSTMENT      = '_umc_rate_adjustment';
	public const META_CURRENCY_ORIGIN      = '_umc_currency_origin';

	/**
	 * Current order snapshot schema written for new orders.
	 */
	public const SCHEMA_VERSION = 5;

	/**
	 * Rate source identifier for the manual (admin-entered) provider.
	 */
	public const SOURCE_MANUAL = 'manual';

	/**
	 * Rate source identifier for automatic (provider-derived) rates.
	 */
	public const SOURCE_AUTOMATIC = 'automatic';

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
	 * Checkout transition state repository.
	 *
	 * @var CheckoutTransitionStateRepository
	 */
	private CheckoutTransitionStateRepository $transition_repository;

	/**
	 * Binds the snapshot writer to its collaborators.
	 *
	 * @param CurrencyContext                   $context               Request-scoped currency facade.
	 * @param Settings                          $settings              Settings store.
	 * @param string                            $version               Plugin version.
	 * @param CheckoutTransitionStateRepository $transition_repository Checkout transition repository.
	 */
	public function __construct(
		CurrencyContext $context,
		Settings $settings,
		string $version,
		CheckoutTransitionStateRepository $transition_repository
	) {
		$this->context               = $context;
		$this->settings              = $settings;
		$this->version               = $version;
		$this->transition_repository = $transition_repository;
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

		$this->write_snapshot_for( $order );
	}

	/**
	 * Stages the snapshot on an order, optionally refreshing an existing one.
	 *
	 * Classic checkout never refreshes: the order is created once, from a cart
	 * whose currency cannot change afterwards. The Store API reuses a draft
	 * order across payment retries and re-stamps its currency and totals from
	 * the cart on the way, so a snapshot written before a mid-retry currency
	 * change would end up describing a currency the order no longer has.
	 * Refreshing is therefore permitted, but only while the order is unpaid;
	 * see {@see \UMC\StoreApi\CheckoutSnapshotAdapter} for that policy.
	 *
	 * Meta is staged, never saved. Callers running on a hook that fires after
	 * WooCommerce has already saved the order must save it themselves, which is
	 * what the returned flag is for.
	 *
	 * @param WC_Order $order         Order to stage the snapshot on.
	 * @param bool     $allow_refresh Whether an existing snapshot may be rewritten.
	 * @return bool Whether any metadata changed.
	 */
	public function write_snapshot_for( WC_Order $order, bool $allow_refresh = false ): bool {
		$existing = (string) $order->get_meta( self::META_TRANSACTION_CURRENCY );

		if ( '' !== $existing && ! $allow_refresh ) {
			return false;
		}

		$active_code = $this->context->get_active_code();
		$rate_source = Settings::RATE_MODE_AUTOMATIC === $this->settings->get_effective_rate_mode( $active_code )
			? self::SOURCE_AUTOMATIC
			: self::SOURCE_MANUAL;
		$transition  = $this->transition_repository->get();
		$config      = $this->settings->get_currency_config( $active_code );
		$adjustment  = Settings::enforce_adjustment_range(
			Settings::normalize_adjustment( is_array( $config ) ? ( $config['merchant_adjustment'] ?? '0' ) : '0' )
		);
		$provider    = self::SOURCE_AUTOMATIC === $rate_source
			? (string) ( $this->settings->get()['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER )
			: '';

		$meta = self::snapshot_meta(
			$this->context->get_base_currency()->code(),
			$active_code,
			$this->context->get_rate(),
			$this->rate_timestamp( $active_code ),
			$rate_source,
			$this->version,
			$this->context->get_currency_signature(),
			self::SCHEMA_VERSION,
			$this->context->get_active_currency()->decimals(),
			null !== $transition ? $transition->mode() : '',
			null !== $transition ? $transition->shopper_currency() : $this->context->get_shopper_code(),
			null !== $transition && $transition->fallback_occurred(),
			$provider,
			$adjustment
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

		if ( '' !== $existing ) {
			return $this->refresh_snapshot( $order, $meta );
		}

		$origin = CurrencySwitcher::read_currency_origin();

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( (string) $key, $value );
		}

		if ( null !== $origin ) {
			$order->update_meta_data( self::META_CURRENCY_ORIGIN, $origin );
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

		return true;
	}

	/**
	 * Rewrites an existing snapshot, but only when it has actually gone stale.
	 *
	 * Comparing first keeps the common case free: a draft order is re-synced
	 * from the cart on every mutating cart request, and almost none of those
	 * involve a currency change.
	 *
	 * @param WC_Order              $order Order carrying the existing snapshot.
	 * @param array<string, scalar> $meta  Freshly built snapshot metadata.
	 * @return bool Whether any metadata changed.
	 */
	private function refresh_snapshot( WC_Order $order, array $meta ): bool {
		$stored_identity = (string) $order->get_meta( self::META_RATE_IDENTITY );
		$stored_currency = (string) $order->get_meta( self::META_TRANSACTION_CURRENCY );

		$fresh_identity   = (string) ( $meta[ self::META_RATE_IDENTITY ] ?? '' );
		$identity_matches = $fresh_identity === $stored_identity;
		$currency_matches = $order->get_currency() === $stored_currency;

		if ( $identity_matches && $currency_matches ) {
			return false;
		}

		$previous = array();

		foreach ( $meta as $key => $value ) {
			$previous[ $key ] = $order->get_meta( (string) $key );
			$order->update_meta_data( (string) $key, $value );
		}

		/**
		 * Fires when an unpaid order's snapshot is rewritten for a new currency
		 * or rate.
		 *
		 * @since 0.5.0
		 *
		 * @param WC_Order              $order    Order whose snapshot changed.
		 * @param array<string, mixed>  $previous Metadata replaced.
		 * @param array<string, scalar> $meta     Metadata written.
		 */
		do_action( 'umc_order_snapshot_refreshed', $order, $previous, $meta );

		return true;
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
	 * @param int    $schema_version       Snapshot schema version (3 for M11+, 4 for M16+).
	 * @param int    $transaction_decimals Transaction currency decimals.
	 * @param string $checkout_mode        Configured checkout mode.
	 * @param string $shopper_currency     Shopper-selected currency code.
	 * @param bool   $fallback_occurred    Whether checkout fell back to store currency.
	 * @param string $rate_provider        Provider id when automatic; empty when manual (schema 4+).
	 * @param string $rate_adjustment      Merchant adjustment percentage string (schema 4+).
	 * @return array<string, scalar>
	 */
	public static function snapshot_meta(
		string $base_currency,
		string $transaction_currency,
		string $exchange_rate,
		int $rate_timestamp,
		string $rate_source,
		string $plugin_version,
		string $rate_identity,
		int $schema_version = 3,
		int $transaction_decimals = 2,
		string $checkout_mode = '',
		string $shopper_currency = '',
		bool $fallback_occurred = false,
		string $rate_provider = '',
		string $rate_adjustment = ''
	): array {
		$meta = array(
			self::META_BASE_CURRENCY        => $base_currency,
			self::META_TRANSACTION_CURRENCY => $transaction_currency,
			self::META_EXCHANGE_RATE        => $exchange_rate,
			self::META_RATE_TIMESTAMP       => $rate_timestamp,
			self::META_RATE_SOURCE          => $rate_source,
			self::META_PLUGIN_VERSION       => $plugin_version,
			self::META_RATE_IDENTITY        => $rate_identity,
			self::META_SNAPSHOT_VERSION     => $schema_version,
			self::META_TRANSACTION_DECIMALS => $transaction_decimals,
		);

		if ( $schema_version >= 3 ) {
			$meta[ self::META_CHECKOUT_MODE ]     = $checkout_mode;
			$meta[ self::META_SHOPPER_CURRENCY ]  = $shopper_currency;
			$meta[ self::META_FALLBACK_OCCURRED ] = $fallback_occurred ? 'yes' : 'no';
		}

		if ( $schema_version >= 4 ) {
			$meta[ self::META_RATE_PROVIDER ]   = $rate_provider;
			$meta[ self::META_RATE_ADJUSTMENT ] = $rate_adjustment;
		}

		return $meta;
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
