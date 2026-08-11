<?php
/**
 * Authoritative inventory of every key the plugin writes to persistent storage.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

use UMC\Admin\Geo\GeoSandboxRecentStore;
use UMC\Admin\GeoSandboxController;
use UMC\Cart\CartRecalculation;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\CurrencySwitcher;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Order\OrderSnapshot;
use UMC\Order\RefundSnapshot;
use UMC\StoreApi\CartExtensionData;

/**
 * Single source of truth for persisted-key contracts.
 *
 * Human-readable documentation lives in `docs/PERSISTED_DATA.md`. The unit test
 * `PersistedKeysInventoryTest` binds this class, the implementation constants,
 * and that document together so the inventory cannot drift silently.
 */
final class PersistedKeys {

	/**
	 * Bump when the inventory shape or membership changes.
	 */
	public const INVENTORY_VERSION = 8;

	/**
	 * WordPress options written by the plugin.
	 *
	 * @return list<string>
	 */
	public static function option_keys(): array {
		return array(
			Settings::OPTION,
			\UMC\Rates\RateUpdateState::OPTION,
		);
	}

	/**
	 * Order metadata keys written at checkout (HPOS CRUD).
	 *
	 * @return list<string>
	 */
	public static function order_meta_keys(): array {
		return array(
			OrderSnapshot::META_BASE_CURRENCY,
			OrderSnapshot::META_TRANSACTION_CURRENCY,
			OrderSnapshot::META_EXCHANGE_RATE,
			OrderSnapshot::META_RATE_TIMESTAMP,
			OrderSnapshot::META_RATE_SOURCE,
			OrderSnapshot::META_PLUGIN_VERSION,
			OrderSnapshot::META_RATE_IDENTITY,
			OrderSnapshot::META_SNAPSHOT_VERSION,
			OrderSnapshot::META_TRANSACTION_DECIMALS,
			OrderSnapshot::META_CHECKOUT_MODE,
			OrderSnapshot::META_SHOPPER_CURRENCY,
			OrderSnapshot::META_FALLBACK_OCCURRED,
			OrderSnapshot::META_RATE_PROVIDER,
			OrderSnapshot::META_RATE_ADJUSTMENT,
		);
	}

	/**
	 * Refund metadata keys linking a refund to its parent order snapshot.
	 *
	 * @return list<string>
	 */
	public static function refund_meta_keys(): array {
		return array(
			RefundSnapshot::META_PARENT_TRANSACTION_CURRENCY,
			RefundSnapshot::META_PARENT_RATE_IDENTITY,
		);
	}

	/**
	 * User metadata keys written by the plugin.
	 *
	 * @return list<string>
	 */
	public static function user_meta_keys(): array {
		return array(
			'umc_dismissed_notices',
			GeoSandboxController::RESULT_META,
			GeoSandboxRecentStore::META_KEY,
		);
	}

	/**
	 * WooCommerce session keys owned by the plugin.
	 *
	 * @return list<string>
	 */
	public static function session_keys(): array {
		return array(
			CurrencyContext::SESSION_KEY,
			CartRecalculation::SESSION_KEY,
			CheckoutTransitionStateRepository::SESSION_KEY,
			CheckoutTransitionStateRepository::SESSION_NOTICE_KEY,
			CurrencySwitcher::SESSION_MANUAL_SELECTION,
			CurrencySwitcher::SESSION_CURRENCY_ORIGIN,
			GeoCurrencyDecisionService::SESSION_GEO_APPLIED,
			GeoCurrencyDecisionService::SESSION_GEO_SESSION_DONE,
			'umc_geo_prev_billing_country',
			'umc_geo_prev_shipping_country',
		);
	}

	/**
	 * HTTP cookies set by the plugin.
	 *
	 * @return list<string>
	 */
	public static function cookie_names(): array {
		return array(
			CurrencyContext::COOKIE_NAME,
		);
	}

	/**
	 * Store API cart extension namespaces registered by the plugin.
	 *
	 * These are request-scoped response data only; nothing is written to the database.
	 *
	 * @return list<string>
	 */
	public static function store_api_extension_namespaces(): array {
		return array(
			CartExtensionData::NAMESPACE_KEY,
		);
	}

	/**
	 * WordPress transients written by runtime `src/` code.
	 *
	 * @return list<string>
	 */
	public static function transient_keys(): array {
		return array();
	}

	/**
	 * Object-cache keys or groups written by runtime `src/` code.
	 *
	 * @return list<string>
	 */
	public static function object_cache_keys(): array {
		return array();
	}

	/**
	 * WordPress options removed when the plugin is uninstalled.
	 *
	 * @return list<string>
	 */
	public static function uninstall_deleted_option_keys(): array {
		return self::option_keys();
	}

	/**
	 * User metadata keys intentionally preserved on uninstall.
	 *
	 * @return list<string>
	 */
	public static function uninstall_preserved_user_meta_keys(): array {
		return self::user_meta_keys();
	}

	/**
	 * Uninstall contract export for documentation drift tests.
	 *
	 * @return array<string, mixed>
	 */
	public static function uninstall_policy(): array {
		return array(
			'delete_options'       => self::uninstall_deleted_option_keys(),
			'preserve_order_meta'  => self::order_meta_keys(),
			'preserve_refund_meta' => self::refund_meta_keys(),
			'preserve_user_meta'   => self::uninstall_preserved_user_meta_keys(),
		);
	}

	/**
	 * Machine-readable inventory export for documentation drift tests.
	 *
	 * @return array<string, mixed>
	 */
	public static function inventory(): array {
		return array(
			'inventory_version'              => self::INVENTORY_VERSION,
			'options'                        => self::option_keys(),
			'order_meta'                     => self::order_meta_keys(),
			'refund_meta'                    => self::refund_meta_keys(),
			'user_meta'                      => self::user_meta_keys(),
			'session_keys'                   => self::session_keys(),
			'cookies'                        => self::cookie_names(),
			'store_api_extension_namespaces' => self::store_api_extension_namespaces(),
			'transients'                     => self::transient_keys(),
			'object_cache'                   => self::object_cache_keys(),
			'uninstall_policy'               => self::uninstall_policy(),
		);
	}
}
