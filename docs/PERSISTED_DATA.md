# Persisted data inventory

Authoritative contract for every key Universal Multicurrency writes to durable
storage or named runtime persistence surfaces (options, metadata, WooCommerce
session entries, cookies).

**Implementation source:** [`src/PersistedKeys.php`](../src/PersistedKeys.php)  
**Drift guard:** `tests/unit/PersistedKeysInventoryTest.php` binds the PHP
inventory, the implementation constants, and the machine-readable block below.

Uninstall policy for each surface is documented here for reference; the
**approved uninstall behaviour** is finalized in Milestone 7 Commit 2.

---

## WordPress options

| Key | Owner | Contents | Uninstall (current) |
|---|---|---|---|
| `umc_settings` | `Settings` | Plugin configuration: `schema_version`, enabled currencies, manual rates, formatting per currency | **Deleted** by [`uninstall.php`](../uninstall.php) |

The store **base currency** lives in WooCommerce's `woocommerce_currency` option
only. It is never duplicated into `umc_settings` (see ADR-0003).

---

## Order metadata (HPOS CRUD)

Written once at order creation (and refreshed only for unpaid Store API drafts per
ADR-0006). Permanent audit data — **never deleted on uninstall**.

| Key | Constant | Since |
|---|---|---|
| `_umc_base_currency` | `OrderSnapshot::META_BASE_CURRENCY` | M3 |
| `_umc_transaction_currency` | `OrderSnapshot::META_TRANSACTION_CURRENCY` | M3 |
| `_umc_exchange_rate` | `OrderSnapshot::META_EXCHANGE_RATE` | M3 |
| `_umc_rate_timestamp` | `OrderSnapshot::META_RATE_TIMESTAMP` | M3 |
| `_umc_rate_source` | `OrderSnapshot::META_RATE_SOURCE` | M3 |
| `_umc_plugin_version` | `OrderSnapshot::META_PLUGIN_VERSION` | M3 |
| `_umc_rate_identity` | `OrderSnapshot::META_RATE_IDENTITY` | M3 |
| `_umc_snapshot_version` | `OrderSnapshot::META_SNAPSHOT_VERSION` | M4 (`2` for new orders) |
| `_umc_transaction_decimals` | `OrderSnapshot::META_TRANSACTION_DECIMALS` | M4 |

Writer: `Order\OrderSnapshot` (classic checkout and Store API checkout adapter).

---

## Refund metadata (HPOS CRUD)

Write-once audit linkage to the parent order snapshot. **Never deleted on
uninstall.**

| Key | Constant | Since |
|---|---|---|
| `_umc_parent_transaction_currency` | `RefundSnapshot::META_PARENT_TRANSACTION_CURRENCY` | M4 |
| `_umc_parent_rate_identity` | `RefundSnapshot::META_PARENT_RATE_IDENTITY` | M4 |

Writer: `Order\RefundSnapshot`.

---

## User metadata

| Key | Owner | Contents | Uninstall (current) |
|---|---|---|---|
| `umc_dismissed_notices` | `Diagnostics\NoticeDismissal` | Per-user map of dismissed conflict-notice fingerprints (cap 20, 180-day expiry) | **Not deleted** (M6 D4 trade-off; M7 Commit 2 will document the approved policy) |

This is the first non-order data persisted outside `umc_settings` (ADR-0007).

---

## WooCommerce session keys

Stored in the WooCommerce customer session (database or session handler). Cleared
when the session expires or the customer clears their session — **not touched by
`uninstall.php`**.

| Key | Owner | Purpose |
|---|---|---|
| `umc_currency` | `CurrencyContext` | Active shopper currency code |
| `umc_cart_signature` | `Cart\CartRecalculation` | Last cart currency/rate signature used to detect recalculation need |

---

## HTTP cookies

| Name | Owner | Purpose | Uninstall |
|---|---|---|---|
| `umc_currency` | `CurrencyContext` | Guest currency persistence (30-day lifetime via `CurrencySwitcher`) | Browser lifecycle only |

---

## Store API extension data

| Namespace | Owner | Persisted? |
|---|---|---|
| `umc` | `StoreApi\CartExtensionData` | **No** — read-only cart response extension (`active_currency`, `base_currency`, `selectable_currencies`). No database write. No update callback. |

---

## Transients

**None.** Runtime code under `src/` does not call `set_transient()` or
`get_transient()`.

---

## Object cache

**None.** Runtime code under `src/` does not call `wp_cache_set()`,
`wp_cache_get()`, or related WordPress object-cache APIs.

WooCommerce may cache shipping rates internally; the plugin injects
`umc_currency_signature` into in-memory shipping **package** arrays to isolate
that cache per currency. That value is not a standalone persisted key.

---

## Machine-readable inventory

The block below is parsed by `PersistedKeysInventoryTest`. It must stay in sync
with `PersistedKeys::inventory()` — never edit one without the other.

```umc:persisted-inventory
{
  "inventory_version": 1,
  "options": [
    "umc_settings"
  ],
  "order_meta": [
    "_umc_base_currency",
    "_umc_transaction_currency",
    "_umc_exchange_rate",
    "_umc_rate_timestamp",
    "_umc_rate_source",
    "_umc_plugin_version",
    "_umc_rate_identity",
    "_umc_snapshot_version",
    "_umc_transaction_decimals"
  ],
  "refund_meta": [
    "_umc_parent_transaction_currency",
    "_umc_parent_rate_identity"
  ],
  "user_meta": [
    "umc_dismissed_notices"
  ],
  "session_keys": [
    "umc_currency",
    "umc_cart_signature"
  ],
  "cookies": [
    "umc_currency"
  ],
  "store_api_extension_namespaces": [
    "umc"
  ],
  "transients": [],
  "object_cache": []
}
```
