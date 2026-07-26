# Deployment & change record

A running, continuously-maintained record of what each milestone changes so it
can be safely deployed and, if needed, rolled back. Generic by design — no
site-, host- or deployment-specific detail belongs here.

## Milestone 3 — classic cart, checkout & order currency (v0.3.0)

### Summary

Makes the classic cart and checkout authoritative in the selected currency
(coupons, core shipping, taxes) and writes an immutable order-time rate snapshot.
Reuses the M2 conversion seam as the single product-price converter; adds
conversion only for fixed coupon amounts, coupon spend thresholds and core
shipping costs. Taxes are computed natively. Fees are not converted. Cart &
Checkout Blocks (Store API) and order display / emails / refunds are later
milestones.

### Files created

| Path | Purpose |
|---|---|
| `src/Cart/CartRecalculation.php` | Recalculate cart totals when the rate identity changes. |
| `src/Integration/CouponConversion.php` | Convert fixed coupon amounts + min/max thresholds. |
| `src/Integration/ShippingConversion.php` | Convert core-method shipping costs; isolate the shipping cache per currency. |
| `src/Integration/GatewayCompatibility.php` | Hide gateways incompatible with the active currency. |
| `src/Order/OrderSnapshot.php` | Write the immutable `_umc_*` order snapshot at creation. |
| `docs/architecture/transaction-flow.md` | Transaction flow + sequence diagram + double-conversion proof. |
| `docs/adr/0004-transaction-currency-and-order-snapshot.md` | The transaction-currency / snapshot decision. |
| `docs/DEPLOYMENT.md` | This record. |
| `tests/unit/OrderSnapshotTest.php` | Pure snapshot-meta builder tests. |
| `tests/integration/CartConversionTest.php` | Cart totals conversion, base no-op, sale price, no-double-conversion, recalc on signature change. |
| `tests/integration/CouponConversionTest.php` | Fixed cart/product amounts, percentage, and min-spend thresholds. |
| `tests/integration/ShippingConversionTest.php` | Core-rate cost/tax conversion, non-core pass-through, base no-op, per-currency cache isolation. |
| `tests/integration/TaxReconciliationTest.php` | Native tax computation, sum-of-parts reconciliation, no-drift on repeated recalculation. |
| `tests/integration/GatewayCompatibilityTest.php` | Hiding an incompatible gateway; default no-op. |
| `tests/integration/TransactionOrderTest.php` | Order snapshot audit meta, write-once, rate-change immutability, HPOS round-trip. |

### Files modified

| Path | Change |
|---|---|
| `src/CurrencyContext.php` | Add `get_currency_signature()` (rate identity, `umc_currency_signature` filter). |
| `src/Integration/PriceConversionService.php` | Add `convert_amount()` — the seam entry for coupon/shipping conversion. |
| `src/Plugin.php` | Wire the new services on `woocommerce_init`; pass `UMC_VERSION` to the snapshot. |
| `universal-multicurrency.php` | Version → 0.3.0 (`Version:` header + `UMC_VERSION`). |
| `phpcs.xml.dist` | Relax two comment sniffs for `tests/*`. |
| `tests/integration/bootstrap.php` | Enable HPOS at install so the order suite runs against it. |
| `tests/integration/SmokeTest.php` | Update the out-of-scope hook denylist for M3 scope. |
| `tests/integration/StorefrontGuardTest.php` | Update the denylist; add the no-direct-Converter / no-SQL / no-broad-catch / uninstall-retention structural guards. |
| `tests/integration/CurrencyRegistryTest.php` | Drop `woocommerce_checkout_create_order` from the domain-layer denylist (now legitimately hooked). |
| `tests/unit/PriceConversionServiceTest.php` | Cover `convert_amount()`. |
| `docs/ARCHITECTURE.md` | Add the M3 transaction layer. |
| `docs/HOOKS.md` | Catalogue the M3 hooks, filters and actions; update the deliberately-not-hooked list. |
| `docs/ROADMAP.md` | Record the M3/M4 split and the deferred Blocks milestone. |
| `docs/TEST_STRATEGY.md` | Add the M3 invariants under test. |

### New hooks registered

`woocommerce_cart_loaded_from_session` (20), `woocommerce_coupon_get_amount` /
`_minimum_amount` / `_maximum_amount` (10), `woocommerce_package_rates` (90),
`woocommerce_cart_shipping_packages` (10), `woocommerce_available_payment_gateways`
(10), `woocommerce_checkout_create_order` (10). Full catalogue: `docs/HOOKS.md`.

### New public filters / actions

Filters: `umc_currency_signature`, `umc_coupon_amount_is_base`,
`umc_convert_shipping_rate`, `umc_convert_fee` (documented, not wired),
`umc_gateway_supported_currencies`, `umc_order_snapshot_meta`. Actions:
`umc_cart_recalculated`, `umc_gateway_hidden`, `umc_order_snapshot_created`.

### New order metadata keys (permanent; never removed on uninstall)

`_umc_base_currency`, `_umc_transaction_currency`, `_umc_exchange_rate`,
`_umc_rate_timestamp`, `_umc_rate_source`, `_umc_plugin_version`,
`_umc_rate_identity`.

### New session keys

`umc_cart_signature` (WooCommerce cart session) — the rate identity the cart
totals were last computed for.

### New options / DB changes

None. No new plugin option; the settings schema is unchanged; no migrations.

### Tests / CI

`composer phpcs` clean; `composer test:unit` — 145 tests / 203 assertions green;
`composer test:integration` — 62 tests / 139 assertions green, **HPOS enabled**.
Integration runs against MySQL/MariaDB with `tests/bin/install-wp.sh`; see
`.github/workflows/ci.yml`.

### Deployment sequence

1. Enter a controlled-deployment / low-traffic state.
2. Back up plugin files and the database.
3. Build the plugin zip and deploy it; activate/upgrade.
4. Migrations: none.
5. Clear the persistent object cache (e.g. Redis).
6. Clear the page cache.
7. Purge the CDN only if storefront responses/assets changed; ensure the
   `umc_currency` cookie is part of any full-page cache key, or that
   cart/checkout are bypassed by the cache.
8. Clear WooCommerce transients (shipping/variation caches).
9. Verify cart and checkout recalculate per currency.
10. Place a low-value order in a non-base currency.
11. Verify the gateway charge currency equals the order currency.
12. Verify the order currency and the `_umc_*` snapshot metadata (HPOS).
13. Record the deployed commit and verification result here.

### Rollback

Orders created during the window are safe by design: each stores its own order
currency and `_umc_*` snapshot and renders natively even with the plugin
deactivated. **No rollback step may re-derive an order's currency from current
rates.** Steps: deactivate/downgrade to the prior tag → restore prior plugin
files → clear object + page cache → clear WooCommerce transients → verify the
storefront reverts to base-only display and existing orders still render in their
stored currency (snapshot meta is retained). Restore the DB backup only if no
legitimate orders were placed after it.

### Cache-clearing requirements

Object cache, page cache, WooCommerce transients (variation + shipping). Monetary
caches are keyed by the rate identity (`code:rate`), so they self-invalidate on a
switch or rate edit; a manual clear is only needed on deploy.

### Known limitations (M3)

Classic checkout only — Blocks/Store API not supported (later milestone). Order
display, emails, admin/account rendering, refunds and the order-pay currency lock
are Milestone 4. Fees are not converted. Shipping conversion is core methods only.
Base-currency reference totals are not stored (reporting milestone).

## Milestone 4 — historical order behaviour & refunds (v0.4.0)

### Summary

Ensures once an order exists, its stored WooCommerce order currency and immutable
`_umc_*` snapshot are authoritative for every later operation — the order never
changes appearance, totals, gateway currency, or formatting due to session
currency changes, rate edits, disabled currencies, or base-currency changes.
Introduces an order-scoped currency context that reads the snapshot once,
resolves formatting via a fallback chain (stored decimals → config → ISO-4217 → 2),
and never reconverts a persisted total. Covers order display (thank-you, My-Account,
admin, emails), order-pay retry, refund audit metadata, and legacy order viewing.

### Files created

| Path | Purpose |
|---|---|
| `src/Order/OrderSnapshotReader.php` | Read `_umc_*` metadata; validate/normalize; classify snapshot version. |
| `src/Order/OrderCurrencySnapshot.php` | Immutable VO with typed accessors and classification flags. |
| `src/Order/HistoricalFormattingResolver.php` | Decimals fallback chain; symbol/position from live config. |
| `src/Order/ResolvedOrderCurrencyFormatting.php` | Immutable VO: code, decimals, symbol, position. |
| `src/Order/OrderCurrencyContext.php` | Request-scoped LIFO stack; enter/exit/run lifecycle. |
| `src/Order/OrderCurrencyFormatting.php` | Override globals (currency, decimals, symbol) when context active. |
| `src/Order/HistoricalOrderDisplay.php` | Enter/exit brackets (prio 1/999 FILO) around render zones. |
| `src/Order/OrderPayCurrencyLock.php` | Detect order-pay endpoint; lock currency; filter gateways explicitly. |
| `src/Order/RefundSnapshot.php` | Write-once audit metadata on refund creation. |
| `src/Support/IsoCurrencyDecimals.php` | Pure ISO-4217 decimals fallback map. |
| `src/Admin/OrderCurrencyMetaBox.php` | Read-only audit box (HPOS + legacy); shows snapshot + resolved formatting. |
| `docs/adr/0005-historical-order-currency-context.md` | Decision record: decimals storage, context lifecycle, gateway filtering. |
| `tests/unit/IsoCurrencyDecimalsTest.php` | ISO map: 0/2/3-decimal codes, unknowns → 2. |
| `tests/integration/OrderCurrencySnapshotClassificationTest.php` | Classification: legacy, v1 (M3), v2 (M4), partial, malformed, future. |
| `tests/integration/HistoricalFormattingResolverTest.php` | Fallback chain; disabled-currency path; symbol/position from config. |
| `tests/integration/OrderCurrencyContextTest.php` | LIFO stack; `run()` restores on return and error; nested renders. |
| `tests/integration/HistoricalOrderDisplayTest.php` | EUR/JPY orders in a different session through the real display brackets; symbol/decimals; rate-edit & currency-removal immutability; stored totals unchanged; nested/repeated-render leak checks. |
| `tests/integration/OrderPayCurrencyLockTest.php` | Standard & legacy endpoints; key/ownership/paid-order checks; explicit-currency gateway filtering; compatible kept / incompatible removed; original-set determinism; disabled currency payable; empty-set blocking notice; no total/currency rewrite. |
| `tests/integration/RefundConversionTest.php` | Full/partial/line-level refunds; parent-currency inheritance; amount stored unchanged; `_umc_parent_*` audit; reconciliation without drift (0-dp JPY, 2-dp EUR); rate-edit & session-switch immutability; legacy-parent fallback. |
| `tests/integration/LegacyOrderTest.php` | Legacy / v1 / partial / malformed / future snapshots — each readable and refundable; order-currency fallback; decimal fallback chain. |

### Files modified

| Path | Change |
|---|---|
| `src/Order/OrderSnapshot.php` | Write `_umc_snapshot_version = 2` and `_umc_transaction_decimals` at creation (backward compat: old calls default v2 + 2 decimals). |
| `src/Integration/GatewayCompatibility.php` | Extract public `filter_gateways_for_currency(array $gateways, string $currency): array` engine driven by an explicit code; storefront callback resolves the session currency; no order-context inspection. |
| `src/Order/RefundSnapshot.php` | Parent-currency audit falls back to the parent order currency for legacy parents. |
| `src/Plugin.php` | Build shared `OrderSnapshotReader`, `HistoricalFormattingResolver`, `OrderCurrencyContext` and a single shared `GatewayCompatibility`; wire all M4 services on `woocommerce_init`. |
| `universal-multicurrency.php` | Version → 0.4.0. |
| `tests/unit/OrderSnapshotTest.php` | Extend with M4 snapshot-version + decimals keys. |
| `tests/integration/StorefrontGuardTest.php` | Release `woocommerce_create_refund` from forbidden hooks; add guards for no conversion / no session / no live-rate / no CurrencyContext / no post-meta API in historical services, gateway not inspecting the order context, no runtime `_umc_*` deletion, no Store API/Blocks registration, and paired display brackets. |
| `docs/HOOKS.md` | Add M4 section (order-scoped formatting, order-pay, refunds, meta box); update deliberately-not-hooked list; add M4 filters/actions. |
| `docs/ARCHITECTURE.md` | Add M4 section covering invariants, context stack, collaborators, ADR-0005 link. |

### New hooks registered

`woocommerce_order_details_before/after_order_table` (1/999 FILO),
`woocommerce_email_before/after_order_table` (1/999 FILO),
`woocommerce_before_resend_order_emails` / `woocommerce_after_resend_order_email` (1/999 FILO),
`woocommerce_my_account_my_orders_column_order-total` (10),
`template_redirect` (10),
`woocommerce_available_payment_gateways` (10, order-pay — after deregistering the
storefront callback for the request),
`woocommerce_create_refund` (10),
`add_meta_boxes_{wc_get_page_screen_id('shop-order')}` (10),
`add_meta_boxes_shop_order` (10).

Full catalogue: `docs/HOOKS.md`.

### New public filters / actions

Filters: `umc_order_audit_view_model`. Actions: `umc_order_currency_context_entered`,
`umc_order_currency_context_exited`, `umc_order_pay_locked_currency`,
`umc_refund_snapshot_created`.

### New order metadata keys (permanent; never removed on uninstall)

`_umc_snapshot_version` (2 for M4 orders; M3 = v1 when absent but `_umc_transaction_currency` present),
`_umc_transaction_decimals` (stored decimal precision from the active currency at creation).

### New refund metadata keys (permanent; never removed on uninstall)

`_umc_parent_transaction_currency`, `_umc_parent_rate_identity` (audit metadata written once at creation).

### New session keys

None. The M4 context is request-scoped and uses no session state.

### New options / DB changes

None. No new plugin option; the settings schema is unchanged; no migrations.

### Tests / CI

`composer phpcs` clean; `composer test:unit` — 163 tests / 224 assertions green;
`composer test:integration` — 133 tests / 337 assertions green, **HPOS enabled**.
Structural guards green: no conversion, no session, no live-rate, no CurrencyContext,
no post-meta API in historical services; gateway never inspects the order context;
no runtime `_umc_*` deletion; no Store API/Blocks registration; paired display brackets.

### Deployment sequence

1. Enter a controlled-deployment / low-traffic state.
2. Back up plugin files and the database.
3. Build the plugin zip and deploy it; activate/upgrade.
4. Migrations: none.
5. Clear the persistent object cache (e.g. Redis).
6. Clear the page cache.
7. Purge the CDN only if storefront responses/assets changed; ensure the
   `umc_currency` cookie is part of any full-page cache key, or that
   cart/checkout are bypassed by the cache.
8. Clear WooCommerce transients (shipping/variation/order caches).
9. Verify an existing non-base order on:
   - Thank-you page (fresh order) — correct decimals/symbol, unchanged totals.
   - My-Account list + detail — same.
   - Admin order (HPOS + legacy) — meta box shows snapshot + resolved formatting.
   - Transactional email (resend from admin) — correct decimals, unchanged totals.
10. Verify order-pay: open an unpaid non-base order's pay page while another
    currency is selected; gateways lock to the order currency; totals unchanged.
11. Verify refund: a partial refund on a non-base order; `_umc_parent_*` written;
    amounts unchanged; reconciliation correct (parent − refunds = remaining).
12. Record the deployed commit and verification result here.

### Rollback

Orders created during the window are safe by design: each stores its own currency,
totals, and `_umc_*` snapshot and renders in its stored currency via the snapshot
even with the plugin deactivated. **No rollback step may re-derive an order's
currency from current rates.** Steps: deactivate/downgrade to v0.3.0 → restore
prior plugin files → clear object + page cache → clear WooCommerce transients →
verify storefront reverts to base-only display and existing orders (including
new ones) still render in their stored currency. Restore the DB backup only if
no legitimate orders were placed after it.

### Cache-clearing requirements

Object cache, page cache, WooCommerce transients (variation + shipping + order).
Monetary caches are keyed by the rate identity (`code:rate`), so they
self-invalidate on a switch or rate edit; a manual clear is only needed on deploy.

### Known limitations (M4)

Presentation (symbol, position, separators) always resolves **live** from current
config — orders reflect later merchant/localization changes, while decimals and
totals stay fixed. Legacy/v1 orders in a disabled currency display ISO decimals
(or 2), not a per-order stored value — cosmetic only; totals exact. v2 orders are
exact via `_umc_transaction_decimals`. Third-party templates/emails that format
amounts without `wc_price()` are out of scope.


## Milestone 5 — Cart & Checkout Blocks (v0.5.0)

### Files created

- `src/StoreApi/CheckoutSnapshotAdapter.php` — snapshot timing and the
  unpaid-draft refresh policy for Store API checkout.
- `src/StoreApi/OrderCurrencyLock.php` — order-scope currency for the Store API's
  order and pay-for-order routes.
- `src/StoreApi/CartExtensionData.php` — currency state on the cart endpoint.
- `docs/adr/0006-store-api-and-blocks-parity.md`,
  `docs/architecture/store-api-request-lifecycle.md`.
- `tests/integration/StoreApi/` — shared harness plus ten suites;
  `tests/unit/StoreApiHooksStructureTest.php`.

### Files modified

- `src/CurrencyContext.php` — the conversion gate now admits Store API requests
  and keeps every other REST namespace out.
- `src/CurrencySwitcher.php` — returns early on REST requests.
- `src/Integration/CurrencyFormatting.php`, `src/Order/OrderCurrencyFormatting.php`
  — filter `option_woocommerce_currency_pos`.
- `src/Integration/GatewayCompatibility.php` — no session notices from REST.
- `src/Order/OrderSnapshot.php` — `write_snapshot_for()` seam; the classic path is
  unchanged.
- `src/Plugin.php` — wires the three adapters.
- `.github/workflows/ci.yml` — WooCommerce pinned to **10.9.4** at workflow
  level (`WC_VERSION`), consumed by `tests/bin/install-wp.sh`. An unpinned
  `latest` resolves to pre-release builds, whose response-shape changes would
  surface as CI failures unrelated to the plugin.

### New hooks

`option_woocommerce_currency_pos` (10 and 20),
`woocommerce_store_api_checkout_update_order_meta` (10),
`woocommerce_store_api_cart_update_order_from_request` (10),
`rest_request_before_callbacks` / `rest_request_after_callbacks` (10).

### New actions provided

`umc_order_snapshot_refreshed( $order, $previous, $meta )` — fires only when an
unpaid Store API draft's snapshot is realigned to a new currency or rate.

### New persisted data

None. Same `_umc_*` keys, same `_umc_snapshot_version = 2`. One new namespaced
identifier, the `umc` Store API extension namespace, which publishes the active
currency, the base currency and the selectable codes — no amounts, no exchange
rate.

### Validation

PHPCS clean (0 errors, 0 warnings). Unit **171 tests / 247 assertions**.
Integration **238 tests / 721 assertions**, HPOS enabled, 0 skipped, 0
incomplete, 0 risky. Verified against WordPress 7.0.2 and WooCommerce 10.9.4.

### Deployment sequence

1. Deploy the plugin files; no migration and no schema change.
2. Clear the object cache and any page or edge cache.
3. `docker compose ps` / logs clean; public listeners unchanged.
4. Smoke the blocks: put a Cart block and a Checkout block on a page, select a
   non-base currency, confirm the block cart totals, shipping rates and payment
   methods are all in that currency.
5. Place a test order through the Checkout block; confirm the admin order shows
   the currency audit box with `_umc_snapshot_version = 2` and the expected rate.
6. Place a test order through classic checkout; confirm no regression.
7. Open the block order confirmation while browsing in a different currency;
   confirm the order still reports its own.
8. Confirm HPOS is still enabled and orders are readable.
9. Record the deployed commit and verification result here.

### Rollback

Downgrade to v0.4.0. Nothing is destructive in either direction: no schema
changed and no metadata format changed.

Orders placed through the blocks while v0.5.0 was active keep their snapshots and
remain fully readable by v0.4.0, since the schema version is the same. Orders
placed through the blocks *after* a rollback carry no snapshot and are classified
legacy — the pre-M5 behaviour, which M4 already handles through the ISO decimal
fallback, and refunds still work in the parent currency. Store API responses
revert to base currency, which is what they did before this milestone.

### Cache-clearing requirements

As M4. Monetary caches are keyed by the rate identity and self-invalidate; a
manual clear is only needed on deploy.

Additionally, `/wp-json/wc/store/v1/products` responses now vary by the selected
currency but, unlike the cart routes, WooCommerce does not send them with
`Cache-Control: no-store`. Any cookie-blind page, edge or CDN cache must exclude
`/wp-json/wc/store/` or vary on the `umc_currency` cookie, or shoppers can be
served another currency's prices.

### Known limitations (M5)

Switching currency reloads the page; there is no in-place switch, which would
require shipping JavaScript. Product price-filter blocks query WooCommerce's
base-currency lookup table, so filtering by price range is evaluated in base
currency — the same limitation the classic price-filter widget has. Third-party
shipping methods are not converted unless a host opts in per rate. Block editor
previews of product prices follow the editing administrator's own selected
currency. A no-compatible-gateway error notice raised on a storefront request can
still surface as a Store API cart error on a later request, because WooCommerce
stores notices in the session and converts error notices into cart errors; the
plugin no longer creates such notices during REST requests.
