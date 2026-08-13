# Persisted data inventory

Authoritative contract for every key Universal Multicurrency writes to durable
storage or named runtime persistence surfaces (options, metadata, WooCommerce
session entries, cookies).

**Implementation source:** [`src/PersistedKeys.php`](../src/PersistedKeys.php)  
**Drift guard:** `tests/unit/PersistedKeysInventoryTest.php` binds the PHP
inventory, the implementation constants, and the machine-readable block below.

**Uninstall policy:** [`docs/adr/0009-uninstall-retention-policy.md`](adr/0009-uninstall-retention-policy.md)
(ADR-0009). Guards: `UninstallPolicyGuardTest`, `UninstallPolicyTest`,
`StorefrontGuardTest::test_uninstall_policy_invariants`.

**Merchant migration:** [`docs/MIGRATION.md`](MIGRATION.md) — manual cut-over only;
no foreign import (ADR-0003, ADR-0007).

---

## WordPress options

| Key | Owner | Contents | Uninstall |
|---|---|---|---|
| `umc_settings` | `Settings` | Plugin configuration: `schema_version`, global rate mode/provider/interval, per-currency formatting, `manual_rate`, co-located `provider_rate`, `merchant_adjustment`, `rate_mode` | **Deleted** (ADR-0009) |
| `umc_rate_state` | `RateUpdateState` (via `ExchangeRateStore`) | Operational fetch bookkeeping: versioned `provider_metadata`, per-currency fetch status, failure history, scheduler mirror, update lock | **Deleted** (ADR-0009) |

The store **base currency** lives in WooCommerce's `woocommerce_currency` option
only. It is never duplicated into `umc_settings` (see ADR-0003).

`provider_rate` is operational data (an observation from a fetch) but is stored
inside `umc_settings` so the storefront hot path avoids a second option read
(ADR-0012). Future export/import must use a field-level allowlist — never dump
either option wholesale (see ADR-0012).

Merchant-authored switcher CSS is persisted as the nested string
`umc_settings.display.custom_css` (schema 6, M17). It is a configuration field
inside the existing option, so it adds no inventory key and is deleted with
`umc_settings` on uninstall. Writing it requires the `edit_css` capability in
addition to Display-save authority, and the stored value is re-validated on
every read before it is emitted on the storefront, so a payload injected
directly into the option cannot reach a page (ADR-0022).

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
| `_umc_snapshot_version` | `OrderSnapshot::META_SNAPSHOT_VERSION` | M4 (`2` for orders through v0.9.x); M11 (`3` for checkout policy metadata) |
| `_umc_transaction_decimals` | `OrderSnapshot::META_TRANSACTION_DECIMALS` | M4 |
| `_umc_checkout_mode` | `OrderSnapshot::META_CHECKOUT_MODE` | M11 |
| `_umc_shopper_currency` | `OrderSnapshot::META_SHOPPER_CURRENCY` | M11 |
| `_umc_fallback_occurred` | `OrderSnapshot::META_FALLBACK_OCCURRED` | M11 |
| `_umc_rate_provider` | `OrderSnapshot::META_RATE_PROVIDER` | M16 (schema 4; empty when manual) |
| `_umc_rate_adjustment` | `OrderSnapshot::META_RATE_ADJUSTMENT` | M16 (schema 4; merchant adjustment %) |
| `_umc_currency_origin` | `OrderSnapshot::META_CURRENCY_ORIGIN` | M21 (schema 5; `customer` \| `visitor_location` only; absent when unknown) |

Writer: `Order\OrderSnapshot` (classic checkout and Store API checkout adapter).

---

## Product metadata (WooCommerce CRUD)

Optional merchant-authored fixed prices per **non-base** foreign currency (M20).
Stored on simple products and variations. **Deleted with the product** when the
product post is removed; not touched by plugin uninstall (same as other product
meta).

| Key | Constant | Since |
|---|---|---|
| `_umc_fixed_prices` | `FixedPriceDocument::META_KEY` | M20 |

Writer: `Pricing\FixedPriceRepository` via `Admin\ProductFixedPricesPanel`.

---

## Order line-item metadata (HPOS CRUD)

Write-once pricing provenance for product line items at checkout (M20). Permanent
audit data — **never deleted on uninstall** (same policy as order snapshot meta).

| Key | Constant | Since |
|---|---|---|
| `_umc_line_price_source` | `LineItemPriceProvenance::META_SOURCE` | M20 (`fixed` \| `converted`) |
| `_umc_line_price_currency` | `LineItemPriceProvenance::META_CURRENCY` | M20 |

Writer: `Order\LineItemPriceProvenance`.

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

| Key | Owner | Contents | Uninstall |
|---|---|---|---|
| `umc_dismissed_notices` | `Diagnostics\NoticeDismissal` | Per-user map of dismissed conflict-notice fingerprints (cap 20, 180-day expiry) | **Preserved** (ADR-0009) |
| `umc_geo_sandbox_last_result` | `Admin\GeoSandboxController` | Last Currency Simulation result for this admin (encoded `GeoContext` document, schema-versioned; stale-version entries are discarded on read) | **Preserved** (ADR-0009) |
| `umc_geo_sandbox_recent` | `Admin\Geo\GeoSandboxRecentStore` | Per-user recently-used Currency Simulation country codes (cap 8) | **Preserved** (ADR-0009) |

This is the first non-order data persisted outside `umc_settings` (ADR-0007).

The two sandbox keys were added to this inventory in M14 (previously an
undocumented gap) and classified as preserved, matching `uninstall.php`'s
existing "configuration options only" contract — they are admin-only display
cache, harmless if orphaned, and were never deleted by `uninstall.php` in the
first place.

---

## WooCommerce session keys

Stored in the WooCommerce customer session (database or session handler). Cleared
when the session expires or the customer clears their session — **not touched by
`uninstall.php`**.

| Key | Owner | Purpose |
|---|---|---|
| `umc_currency` | `CurrencyContext` | Active shopper currency code |
| `umc_cart_signature` | `Cart\CartRecalculation` | Last cart currency/rate signature used to detect recalculation need |
| `umc_checkout_transition` | `Checkout\CheckoutTransitionStateRepository` | Current checkout transition state for policy/notices |
| `umc_checkout_notice_signature` | `Checkout\CheckoutTransitionStateRepository` | Last classic checkout notice signature rendered |
| `umc_manual_currency` | `CurrencySwitcher` | Manual switcher selection flag (`until_manual` geo suppression) |
| `umc_currency_origin` | `CurrencySwitcher` | Explanatory origin of the persisted shopper currency (`customer` or `visitor_location`). **Never** used by `CurrencyResolver` for precedence. Updated on every `persist()` write. |
| `umc_geo_applied` | `Geo\GeoCurrencyDecisionService` | Geo applied for `first_visit` / `until_manual` modes |
| `umc_geo_session_done` | `Geo\GeoCurrencyDecisionService` | Geo applied once for `session` mode |
| `umc_geo_prev_billing_country` | `Geo\GeoDetectionApplicator` | Previous checkout billing country for re-evaluation |
| `umc_geo_prev_shipping_country` | `Geo\GeoDetectionApplicator` | Previous checkout shipping country for re-evaluation |

---

## HTTP cookies

| Name | Owner | Purpose | Uninstall |
|---|---|---|---|
| `umc_currency` | `CurrencyContext` | Guest currency persistence (30-day lifetime via `CurrencySwitcher`) | Browser lifecycle only |

---

## Store API extension data

| Namespace | Owner | Persisted? |
|---|---|---|
| `umc` | `StoreApi\CartExtensionData` | **No** — read-only cart/checkout response extension (`active_currency`, `base_currency`, `selectable_currencies`, checkout policy fields, `checkout_notice`). No database write. No update callback. |

---

## Transients

M21 reporting may cache immutable aggregate report payloads:

| Pattern | Owner | TTL | Uninstall |
|---|---|---|---|
| `umc_report_*` | `Reporting\ReportingCache` | 15 minutes | Expires naturally |

Generation invalidation uses option `umc_reporting_cache_gen`. No other runtime
code under `src/` calls `set_transient()` or `get_transient()`.

---

## Object cache

**None.** Runtime code under `src/` does not call `wp_cache_set()`,
`wp_cache_get()`, or related WordPress object-cache APIs.

WooCommerce may cache shipping rates internally; the plugin injects
`umc_currency_signature` into in-memory shipping **package** arrays to isolate
that cache per currency. That value is not a standalone persisted key.

---

## Uninstall policy (ADR-0009)

[`uninstall.php`](../uninstall.php) implements a narrow delete contract:

| Surface | On uninstall |
|---|---|
| `umc_settings` option | **Deleted** |
| `umc_rate_state` option | **Deleted** |
| `_umc_*` order meta | **Preserved forever** |
| `_umc_parent_*` refund meta | **Preserved forever** |
| `umc_dismissed_notices` user meta | **Preserved** |
| Currency Simulation user meta (`umc_geo_sandbox_*`) | **Preserved** |
| WC session keys | Not targeted (WC session lifecycle) |
| Cookies | Not targeted (browser lifecycle) |
| Store API `umc` extension | Not persisted |

Rationale: commerce audit data is permanent; plugin configuration is ephemeral;
dismissal rows are harmless orphans. See
[ADR-0009](adr/0009-uninstall-retention-policy.md).

---

## Machine-readable inventory

The block below is parsed by `PersistedKeysInventoryTest`. It must stay in sync
with `PersistedKeys::inventory()` — never edit one without the other.

```umc:persisted-inventory
{
  "inventory_version": 10,
  "options": [
    "umc_settings",
    "umc_rate_state"
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
    "_umc_transaction_decimals",
    "_umc_checkout_mode",
    "_umc_shopper_currency",
    "_umc_fallback_occurred",
    "_umc_rate_provider",
    "_umc_rate_adjustment",
    "_umc_currency_origin"
  ],
  "refund_meta": [
    "_umc_parent_transaction_currency",
    "_umc_parent_rate_identity"
  ],
  "product_meta": [
    "_umc_fixed_prices"
  ],
  "order_line_item_meta": [
    "_umc_line_price_source",
    "_umc_line_price_currency"
  ],
  "user_meta": [
    "umc_dismissed_notices",
    "umc_geo_sandbox_last_result",
    "umc_geo_sandbox_recent"
  ],
  "session_keys": [
    "umc_currency",
    "umc_cart_signature",
    "umc_checkout_transition",
    "umc_checkout_notice_signature",
    "umc_manual_currency",
    "umc_currency_origin",
    "umc_geo_applied",
    "umc_geo_session_done",
    "umc_geo_prev_billing_country",
    "umc_geo_prev_shipping_country"
  ],
  "cookies": [
    "umc_currency"
  ],
  "store_api_extension_namespaces": [
    "umc"
  ],
  "transients": [
    "umc_report_*"
  ],
  "object_cache": [],
  "uninstall_policy": {
    "delete_options": [
      "umc_settings",
      "umc_rate_state"
    ],
    "preserve_order_meta": [
      "_umc_base_currency",
      "_umc_transaction_currency",
      "_umc_exchange_rate",
      "_umc_rate_timestamp",
      "_umc_rate_source",
      "_umc_plugin_version",
      "_umc_rate_identity",
      "_umc_snapshot_version",
      "_umc_transaction_decimals",
      "_umc_checkout_mode",
      "_umc_shopper_currency",
      "_umc_fallback_occurred",
      "_umc_rate_provider",
      "_umc_rate_adjustment",
      "_umc_currency_origin"
    ],
    "preserve_refund_meta": [
      "_umc_parent_transaction_currency",
      "_umc_parent_rate_identity"
    ],
    "preserve_product_meta": [],
    "preserve_order_line_item_meta": [
      "_umc_line_price_source",
      "_umc_line_price_currency"
    ],
    "preserve_user_meta": [
      "umc_dismissed_notices",
      "umc_geo_sandbox_last_result",
      "umc_geo_sandbox_recent"
    ]
  }
}
```
