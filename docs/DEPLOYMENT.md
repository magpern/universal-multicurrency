# Deployment & change record

A running, continuously-maintained record of what each milestone changes so it
can be safely deployed and, if needed, rolled back. Generic by design — no
site-, host- or deployment-specific detail belongs here.

## Contributor validation commands

Canonical commands (see `composer.json`):

| Command | Purpose |
|---|---|
| `composer phpcs` | PHPCS — 0 errors, 0 warnings required |
| `composer test:unit` | Pure PHP unit suite |
| `composer test:integration` | WordPress + WooCommerce integration (MySQL; `tests/bin/install-wp.sh`) |
| `composer test:mutation` | Infection over Diagnostics scorer (PCOV) |
| `composer make-pot` | Regenerate `languages/universal-multicurrency.pot` |
| `composer make-pot:check` | Fail when committed POT is stale |
| `composer audit` | Composer security audit (production deps) |
| `composer release-audit` | Release-blocking RC gate — see [`RELEASE_AUDIT.md`](RELEASE_AUDIT.md) |

Release zip build:

```bash
composer install --no-dev
bash bin/build-zip.sh
```

Produces `dist/universal-multicurrency-0.23.0.zip`. The archive includes `readme.txt`,
production `src/`, `vendor/`, bundled presentation assets, block metadata, and
`languages/universal-multicurrency.pot`.

Performance subset:

```bash
vendor/bin/phpunit -c phpunit.xml.dist --group performance
vendor/bin/phpunit -c phpunit-integration.xml.dist --group performance
```

**Current release on `main`:** **v0.22.0** (tagged and published).

---

## v0.22.0 — Native Switcher Block (released)

Ships Milestone 23 under version **0.22.0** (ADR-0028). Dynamic Gutenberg block
`universal-multicurrency/currency-switcher` on the existing M17/M22 switcher
engine. Settings schema **7** unchanged; PersistedKeys **10**; order snapshot **5**;
no DB migration. Block attributes live in WordPress post/template content only.

**Released as:** **v0.22.0** — tag `v0.22.0`, GitHub release published, artifact
`universal-multicurrency-0.22.0.zip` (SHA-256
`159e743ae267cb5c20cbc6d3521e0e9998423a9a4086bfd2802483ee61433130`).

### Deployment sequence (release verification completed)

1. Run `composer release-audit` on the **0.22.0** tree.
2. Build `dist/universal-multicurrency-0.22.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.21.0** in place. No settings schema change; block appears in the
   block inserter after upgrade. **Not performed as part of release closure.**

---

## v0.21.0 — Switcher Currency Presentation (released)

Ships Milestone 22 under version **0.21.0** (ADR-0027). Settings schema **6 → 7**
with optional bundled presentation icons (`display.presentation.*`,
`content.*.show_icon`, default off). PersistedKeys **10**; order snapshot **5**;
no DB migration. Upgrade preserves existing switcher appearance when icons remain
disabled.

**Released as:** **v0.21.0** — tag `v0.21.0`, GitHub release published, artifact `universal-multicurrency-0.21.0.zip` (SHA-256 `cc9fef85d9bc9c14a9ee984aa24680ba89b0673a9f8b42c95d7ca444a98905b7`).

### Deployment sequence (release verification completed)

1. Run `composer release-audit` on the **0.21.0** tree.
2. Build `dist/universal-multicurrency-0.21.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.20.0** in place. Settings upgrade runs on load (`migrate_6_to_7`);
   icons remain off until enabled. **Not performed as part of release closure.**

---

## v0.20.0 — Multicurrency Reporting & Analytics Foundation (released)

Ships Milestone 21 under version **0.20.0** (ADR-0026). Settings schema remains **6**; PersistedKeys inventory **10**; order snapshot schema **5** (`_umc_currency_origin`). Admin reporting in native transaction currency; aggregate CSV export; 15-minute report cache with lifecycle invalidation. No FX normalization, no WooCommerce Analytics integration, no DB migration.

**Released as:** **v0.20.0** — tag `v0.20.0`, GitHub release published, artifact `universal-multicurrency-0.20.0.zip` (SHA-256 `8fc04afa0dd00eb06e026d28872c96489bdf189910e2041b13a57a47c9da9e59`).

### Deployment sequence (release verification completed)

1. Run `composer release-audit` on the **0.20.0** tree.
2. Build `dist/universal-multicurrency-0.20.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.19.0** in place. No migration step — legacy orders without origin meta classify as `unknown` in reporting only. **Not performed as part of release closure.**

---

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

## Milestone 6 — compatibility & diagnostics (v0.6.0)

### Summary

Adds passive detection of conflicting currency switchers with confidence
grading, administrative notices (dashboard, plugins screen, Multicurrency
settings tab), per-user dismissal, Site Health direct tests, and a debug
section. Introduces the five-leg supported-version CI matrix and
`docs/COMPATIBILITY.md` as the authoritative version source. Detection never
deactivates, modifies, or calls into another plugin and never affects monetary
behaviour on any surface.

### Files created

| Path | Purpose |
|---|---|
| `src/Diagnostics/*` | Full diagnostics layer (14 classes) |
| `docs/COMPATIBILITY.md` | Version matrix, detector governance, Site Health behaviour |
| `docs/adr/0007-passive-conflict-detection.md` | Passive observation vs coupling (ADR-0003 reconciliation) |
| `docs/adr/0008-version-support-policy.md` | Four-tier policy and CI matrix rules |
| `tests/unit/Diagnostics/*` | Pure scoring, registry, version policy unit tests |
| `tests/integration/Diagnostics/*` | Notice, Site Health, detector integration tests |
| `tests/integration/DiagnosticsGuardTest.php` | I1–I7 structural guards |
| `tests/Support/SourceGuardTrait.php` | Shared guard helpers |
| `tests/unit/CompatibilityMatrixTest.php` | Drift test binding all version sources |
| `tests/unit/CiMatrixGuardTest.php` | No version_compare gating; matrix shape guards |
| `infection.json5` | Scoped mutation testing for Diagnostics scorer |

### Files modified

| Path | Change |
|---|---|
| `src/Plugin.php` | Register `Diagnostics` behind admin gate |
| `src/Admin/SettingsPage.php` | Prepend `umc_conflict` field type |
| `.github/workflows/ci.yml` | Five integration legs, unit PHP matrix, mutation job |
| `docs/ARCHITECTURE.md` | Diagnostics layer + I1–I7 |
| `docs/HOOKS.md` | M6 admin hooks + provided filters |
| `docs/ROADMAP.md` | Item 6 shipped; item 7 objectives expanded |
| `docs/TEST_STRATEGY.md` | M6 invariants, version matrix, mutation scope |
| `docs/PRODUCT_REQUIREMENTS.md` | Compatibility section + non-goals |
| `docs/adr/0003-*.md` | ADR-0007 amendment pointer |
| `README.md`, `CLAUDE.md` | Front page and workflow pointers |
| `universal-multicurrency.php` | Version → 0.6.0 |

### New hooks registered

Seven admin-only hooks at priority 10 — see `docs/HOOKS.md § Milestone 6`.
No storefront, Store API, REST, cron, or CLI hooks.

### New public filters / actions

Filters: `umc_conflict_detectors`, `umc_conflict_notice_view_model`,
`umc_conflict_settings_view_model`.

### New user meta — `umc_dismissed_notices`

The first non-order data the plugin persists outside `umc_settings`. Map of
conflict fingerprint → dismissed-at timestamp; capped at 20 entries with
180-day expiry. **Deliberately not cleaned up on uninstall** — orphaned rows
are harmless and keyed by fingerprint.

### New options / DB changes

None. No new plugin option; `umc_settings` schema unchanged; no migrations.

### Tests / CI

PHPCS clean. Unit, integration, and mutation suites green at commit time (see
Milestone 6 closure report for final counts). Integration HPOS-enabled;
`floor` leg excludes `@group wc-order-route-unavailable` (8 tests).

### Deployment sequence

1. Enter a controlled-deployment / low-traffic state.
2. Back up plugin files and the database.
3. Build the plugin zip and deploy it; activate/upgrade to **0.6.0**.
4. Migrations: none.
5. Confirm no other currency switcher is active (or plan to deactivate it).
6. Clear the persistent object cache and page cache if used.
7. Check **Tools → Site Health** — both Universal Multicurrency tests should
   report `good` when no conflict exists; environment test confirms HPOS.
8. Open **WooCommerce → Settings → Multicurrency** — no conflict panel when
   alone; with a conflicting plugin active, the long-form evidence list appears.
9. Verify storefront, cart, checkout (classic and blocks), and an existing order
   — behaviour unchanged from v0.5.0 when no conflict is present.
10. Record the deployed commit and verification result here.

### Rollback

Downgrade to v0.5.0. No schema change. Order snapshots and `umc_settings`
unchanged. Orphaned `umc_dismissed_notices` user meta may remain — cosmetic only.
Conflict detection and Site Health tests disappear; monetary behaviour identical
to v0.5.0.

### Known limitations (M6)

Built-in detectors cover four verified switchers (FOX/WOOCS, CURCY, WCML,
YayCurrency). Community-submitted built-in labels and automatic remediation are
explicit non-goals. Dismissal is per user and per fingerprint — other
administrators still see active conflicts. One residual notice may appear on the
plugin deactivation confirmation screen until the next request (PHP cannot
undeclare classes mid-request).

---

## Milestone 7 — merchant migration documentation (Release Candidate)

### Summary

Documents the **manual** merchant cut-over path from another currency switcher.
No runtime migration, foreign import, CSV parser, or admin import UI is added.
Internal `umc_settings` schema 0→1 upgrade (`SettingsUpgrader`) was shipped in
an earlier M7 commit; it never reads foreign plugin data.

### Files created

| Path | Purpose |
|---|---|
| `docs/MIGRATION.md` | Merchant migration playbook, checklist, FAQ, UMC CSV format spec (future only) |
| `tests/unit/MigrationDocumentationTest.php` | Structural guard binding migration docs to ADR policy |

### Files modified

| Path | Change |
|---|---|
| `docs/ARCHITECTURE.md` | Link to `MIGRATION.md` |
| `docs/COMPATIBILITY.md` | Supported/unsupported migration matrix |
| `docs/ROADMAP.md` | Item 7 migration playbook progress |
| `docs/DEPLOYMENT.md` | This record |
| `README.md` | Documentation table entry |

### New options / DB changes

None. No schema version bump. No migrations beyond existing `SettingsUpgrader` 0→1.

### Deployment sequence (stores arriving from another switcher)

Follow [`docs/MIGRATION.md`](MIGRATION.md) in full. Summary:

1. Backup database and plugin files; rehearse on staging.
2. Export old switcher currencies/rates to a spreadsheet from its admin UI.
3. Deactivate the old switcher — never run two runtime converters on live traffic.
4. Install/activate UMC; recreate currencies and rates manually in WooCommerce → Settings → Multicurrency.
5. Flush object/page cache; run the verification checklist in `MIGRATION.md`.
6. Cut over production; record deployed commit here.

### Rollback

| Action | Effect |
|---|---|
| Roll back UMC plugin zip | Prior behaviour; `_umc_*` order meta **preserved** |
| Re-enable old switcher | Deactivate UMC first; re-test on staging |
| Uninstall UMC | Deletes `umc_settings` only (ADR-0009) |

### Known limitations (M7 migration docs)

- No automatic import from FOX/WOOCS, CURCY, WPML, YayCurrency, or any third-party switcher.
- UMC CSV format is specified for possible future tooling only — no parser or admin UI in RC.
- Historical order metadata and WooCommerce order totals are never re-converted.

---

## Milestone 7 — translation readiness (Release Candidate)

### Summary

Completes i18n audit for merchant-facing strings, adds canonical POT template,
`load_plugin_textdomain()`, translator comments on ambiguous placeholders, and
automated POT drift protection. No JavaScript shipped; RTL audit documented only.

### Files created

| Path | Purpose |
|---|---|
| `languages/universal-multicurrency.pot` | Canonical gettext template |
| `bin/make-pot.sh` | Deterministic POT generation and `--check` drift guard |
| `docs/TRANSLATION.md` | Text domain, POT workflow, JS status, RTL audit |
| `tests/unit/TranslationReadinessTest.php` | Domain/POT/JS/RTL documentation guards |

### Files modified

| Path | Change |
|---|---|
| `src/Diagnostics/ConflictNotice.php` | i18n for evidence phrases and list conjunctions |
| `src/Admin/OrderCurrencyMetaBox.php` | i18n for UTC label and manual rate source |
| `src/SettingsUpgradeResult.php` | i18n for unsupported schema message |
| `src/Plugin.php` | `load_plugin_textdomain()` on `init` |
| `composer.json` | `make-pot` / `make-pot:check` scripts |
| `.github/workflows/ci.yml` | `pot` job |
| `bin/build-zip.sh` | Include `languages/` in release zip |
| `docs/ROADMAP.md`, `README.md`, `CLAUDE.md`, `docs/TEST_STRATEGY.md` | Translation pointers |

### New options / DB changes

None.

### Deployment sequence

1. Deploy as usual; no settings migration.
2. Translators may add `languages/universal-multicurrency-{locale}.mo` alongside the plugin.
3. No runtime behaviour change for English-only stores.

### Rollback

Safe to prior release; `.mo` files from this release can remain harmlessly.

### Known limitations (M7 translation)

- No bundled locale `.mo` files in RC — POT only.
- RTL audit documented; no dedicated `rtl.css` in RC.
- No JavaScript i18n (no shipped JS).

---

## Milestone 7 — security audit (Release Candidate)

### Summary

Whole-plugin security review with code hardening, executable guards, and
[`docs/SECURITY_REVIEW.md`](SECURITY_REVIEW.md). Zero unresolved Critical/High
findings at Commit 6.

### Files created

| Path | Purpose |
|---|---|
| `docs/SECURITY_REVIEW.md` | Audit record by severity |
| `tests/unit/SecuritySourceGuardTest.php` | Static security invariants |
| `tests/integration/SecurityBehaviourTest.php` | Negative authorization/input tests |

### Files modified

| Path | Change |
|---|---|
| `src/CurrencyContext.php` | ISO currency code normalization at input boundary |
| `src/Diagnostics/ConflictNotice.php` | Harden settings admin URL against open redirects |
| `docs/ROADMAP.md`, `docs/TEST_STRATEGY.md`, `docs/DEPLOYMENT.md` | Security progress pointers |

### Deployment sequence

No deployment-specific steps. Behaviour changes are defensive only (malformed
currency input ignored; external notice URLs rejected).

### Rollback

Safe to prior release.

### Known limitations (M7 security)

See accepted Medium/Low risks in `SECURITY_REVIEW.md` (currency switch nonce-less
GET, guest cookie readability, site-owner filter trust boundary).

---

## Milestone 7 — performance baselines (Release Candidate)

### Summary

Deterministic query/write-count ceilings documented in
[`docs/PERFORMANCE_BASELINES.md`](PERFORMANCE_BASELINES.md). No wall-clock CI
thresholds. No transients or persistent object-cache keys in `src/`.

### Files created

| Path | Purpose |
|---|---|
| `docs/PERFORMANCE_BASELINES.md` | Baseline record and ceiling change process |
| `tests/Support/PerformanceMetrics.php` | Scoped measurement helpers |
| `tests/integration/PerformanceBaselineTest.php` | WordPress/WooCommerce ceiling tests |
| `tests/unit/PerformanceBaselineTest.php` | Pure settings/upgrader idempotency |
| `tests/unit/PerformanceGuardTest.php` | Documentation/constant sync; no cache calls |

### Files modified

| Path | Change |
|---|---|
| `.github/workflows/ci.yml` | `performance` job |
| `docs/ROADMAP.md`, `docs/TEST_STRATEGY.md`, `docs/DEPLOYMENT.md` | Performance pointers |

### Deployment sequence

No deployment-specific steps. Ceilings are enforced in CI only.

### Rollback

Safe to prior release.

---

## Milestone 7 — release audit (Release Candidate)

### Summary

Executable release-blocking gate via `composer release-audit` /
[`docs/RELEASE_AUDIT.md`](RELEASE_AUDIT.md). Validates repository hygiene,
metadata, persisted-data contract, security/performance subsets, POT drift,
`composer audit`, and the production release ZIP contents.

### Files created

| Path | Purpose |
|---|---|
| `bin/release-audit.sh` | Canonical orchestrator |
| `bin/inspect-release-zip.php` | CLI ZIP inspection wrapper |
| `docs/RELEASE_AUDIT.md` | Audit record (RB1–RB15) |
| `tests/Support/ReleaseZipInspector.php` | Archive inclusion/exclusion rules |
| `tests/unit/ReleaseAuditTest.php` | Repository and package guards |

### Files modified

| Path | Change |
|---|---|
| `composer.json` | `release-audit` script |
| `bin/make-pot.sh` | `--allow-root`, wp-cli bootstrap for audit environments |
| `.github/workflows/ci.yml` | `release-audit` job |
| `docs/TEST_STRATEGY.md` | Release-audit section |

### Deployment sequence

Contributors and CI run `composer release-audit` before Commit 10 version bump.
No merchant-facing runtime change.

### Rollback

Safe to prior release.

---

## Milestone 7 — documentation synchronization (Release Candidate)

### Summary

Synchronizes the complete documentation set with the implemented Release
Candidate state. Adds minimal WordPress `readme.txt` (Stable tag **0.6.0**).
Does **not** bump plugin version or close Milestone 7.

### Files created

| Path | Purpose |
|---|---|
| `readme.txt` | WordPress plugin readme (merchant-oriented) |
| `tests/unit/DocumentationSyncTest.php` | Documentation/metadata/link guards |

### Files modified

| Path | Change |
|---|---|
| `README.md` | Developer readme aligned with readme.txt and RC commands |
| `CLAUDE.md` | Contributor workflow including `composer release-audit` |
| `docs/ROADMAP.md` | Milestone 7 item status; Commit 10 pending |
| `docs/ARCHITECTURE.md` | RC governance section |
| `docs/DEPLOYMENT.md` | Contributor commands; this record |
| `docs/RELEASE_AUDIT.md` | readme.txt in ZIP inclusion list |
| `docs/SECURITY_REVIEW.md` | SettingsUpgrader boundary note |
| `docs/TEST_STRATEGY.md` | Documentation sync guards |
| `tests/Support/ReleaseZipInspector.php` | Require `readme.txt` in release ZIP |

### Deployment sequence

No runtime change. Rebuild release zip after adding `readme.txt`:

```bash
composer install --no-dev
bash bin/build-zip.sh
composer release-audit
```

### Rollback

Safe to prior release; remove `readme.txt` only if downgrading before Commit 9.

### Known limitations (M7 documentation)

- No bundled locale `.mo` files (documented in `readme.txt` and `TRANSLATION.md`).
- UMC CSV format remains specification-only (`MIGRATION.md`).

---

## Milestone 7 — v0.7.0 Release Candidate finalization (Commit 10)

### Summary

Finalizes the repository as **v0.7.0** Release Candidate: version bump, changelog,
roadmap closure, release-audit record update, documentation guards, and validated
production ZIP. Does **not** create Git tag, GitHub release, merge, or PR closure.

### Files modified

| Path | Change |
|---|---|
| `universal-multicurrency.php` | Version → **0.7.0** (`Version:` header + `UMC_VERSION`) |
| `readme.txt` | Stable tag **0.7.0**; 0.7.0 changelog; retains 0.6.0 history |
| `README.md` | Current RC **0.7.0**; Milestone 7 complete; tag/release pending |
| `docs/ROADMAP.md` | Milestone 7 **complete**; all work items complete |
| `docs/RELEASE_AUDIT.md` | Closure record; RB results at **0.7.0** |
| `docs/ARCHITECTURE.md`, `docs/DEPLOYMENT.md`, `docs/TEST_STRATEGY.md`, `CLAUDE.md` | Final RC state |
| `tests/unit/DocumentationSyncTest.php` | Version **0.7.0** and closure guards |
| `tests/unit/MigrationDocumentationTest.php`, `tests/unit/ReleaseAuditTest.php` | Packaged version checks |

### Deployment sequence

1. Run `composer release-audit` on the **0.7.0** tree.
2. Build `dist/universal-multicurrency-0.7.0.zip` with `composer install --no-dev` + `bin/build-zip.sh`.
3. Deploy the zip; no settings schema bump beyond existing v0→v1 path.
4. Record deployed commit here after production cut-over.

### Rollback

Downgrade to **0.6.0** zip if needed. Order snapshots and `_umc_*` meta unchanged.
Settings schema remains v1.

### Post-review actions (out of scope for Commit 10)

- Create and push Git tag `v0.7.0`
- Publish GitHub release with `dist/universal-multicurrency-0.7.0.zip`
- Merge `milestone-7-release-candidate` after approval

All three completed; `v0.7.0` is tagged and released, and is superseded by
**v0.8.0** below.

---

## Milestone 8 — automatic exchange rates (v0.8.0)

### Summary

Adds scheduled and merchant-triggered exchange-rate updates from an external
provider. Conversion arithmetic is unchanged: `RateResolver` derives the
effective rate on read and nothing new is written on the money path. See
[`ARCHITECTURE.md`](ARCHITECTURE.md) § Exchange rate layer and ADR-0010 through
ADR-0013.

### What ships

| Area | Change |
|---|---|
| Provider layer | `Rates\ExchangeRateSource` contract; `Providers\FrankfurterRateSource`; `Rates\Http\HttpTransport` + `WordPressHttpTransport` (the only outbound-HTTP class) |
| Persistence boundary | `Rates\ExchangeRateStore` — the only writer of provider rates into `umc_settings` and of `umc_rate_state` |
| Orchestration | `Rates\RateUpdateService` (lock → fetch → apply → release) and `Rates\Scheduler` on Action Scheduler hook `umc_run_rate_update` |
| Admin | `Admin\ExchangeRateSettingsField`, rate columns in `Admin\CurrencyTableField`, `Admin\RateUpdateController` (`admin_post_umc_update_rates`), `Admin\RateFailureNotice` |
| Diagnostics | `umc_rate_health` Site Health test and two `debug_information` counters |
| Version | `0.8.0` in the plugin header, `UMC_VERSION`, and the `readme.txt` stable tag |

### New persisted data

| Option | Contents |
|---|---|
| `umc_settings` (schema **v2**) | Per currency: `manual_rate` (renamed from `rate`), `provider_rate`, `merchant_adjustment`, `rate_mode`. Global: `rate_mode`, `rate_provider`, `rate_update_interval`, `rate_max_age_hours` |
| `umc_rate_state` (**new**) | Last fetch time, per-currency status, consecutive failures, bounded failure history, update lock, provider cache validators |

`umc_rate_state` is operational only — it is never read on the conversion path
and carries no money-bearing value. Inventory:
[`PERSISTED_DATA.md`](PERSISTED_DATA.md).

### Deployment sequence

1. Run `composer release-audit` on the **0.8.0** tree.
2. Build `dist/universal-multicurrency-0.8.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy the zip. On first admin request `SettingsUpgrader` migrates
   `umc_settings` from schema v1 to v2 in place.
4. No merchant action is required: the global rate mode defaults to `manual`, so
   nothing is fetched and no schedule is created until a merchant opts a currency
   or the store into automatic mode.

### Migration impact

The v1 → v2 migration is conversion-neutral: `rate` becomes `manual_rate` with
the same string value, `provider_rate` starts empty, `merchant_adjustment` starts
at `0`, and every rate mode inherits `manual`. Enforced byte-for-byte by
`tests/unit/SettingsMigrationFidelityTest.php`. Details:
[`MIGRATION.md`](MIGRATION.md) § Internal settings schema migrations.

### Rollback

Downgrade to the **0.7.0** zip. Note that `umc_settings` stays at schema v2:
0.7.0's `SettingsUpgrader` refuses a stored version newer than its own
`SCHEMA_VERSION`, so it serves defaults in memory and leaves the option
untouched. Nothing is corrupted, but the store loses its configured currencies
until the v1 value is restored — take a copy of `umc_settings` before step 3
above and restore it as part of any downgrade. Order snapshots and `_umc_*`
meta are unaffected in either direction, so historical orders keep their
recorded rate. `umc_rate_state` is inert to 0.7.0 and can be left in place.

### Post-release corrections

Shipped on `main` after the v0.8.0 tag, with no version bump — all are fixes to
scheduling/timestamp behaviour plus test and documentation closure:

| Commit | Change |
|---|---|
| `0eee862` | `Scheduler::ensure_scheduled()` compares the pending recurrence against `rate_update_interval` and reschedules on a mismatch |
| `b826481` | `CurrencyTableField` bumps `rate_updated_at` when a merchant actually edits rate inputs |
| `137f129` | v1 → v2 conversion-fidelity regression test |
| `88bfa44` | `CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES = 0` |
| `045ac34` | Site Health and controller round-trip integration tests; Milestone 8 documentation synchronization |

A store already running v0.8.0 can take these by deploying a **0.8.1** build; there
is no data change and no migration step.

---

## v0.8.1 — maintenance release (prepared)

### Summary

Packages the post-v0.8.0 maintenance work on `main` under version **0.8.1**.
No settings schema bump; no new merchant-facing automatic-rate features.

### Merchant-visible changes

| Area | Change |
|---|---|
| Recurring updates | Changing `rate_update_interval` now reschedules the Action Scheduler job (`0eee862`) |
| Admin rate edits | Editing manual rate, adjustment, or per-currency rate mode refreshes `rate_updated_at` (`b826481`) |
| Plugin metadata | Header description reflects manual and automatic exchange-rate support (`7ee8e9b`) |

### Repository-only alignment (not user-facing features)

Post-v0.8.0 review closure, documentation synchronization, regression guards, and
the uninstall performance ceiling (`045ac34`, `470ba45`, `7ee8e9b`).

### Deployment sequence

1. Run `composer release-audit` on the **0.8.1** tree.
2. Build `dist/universal-multicurrency-0.8.1.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.8.0** in place. No migration step.

### Rollback

Downgrade to the **0.8.0** zip if needed. Settings schema remains v2; order
snapshots are unaffected.

---

## v0.9.0 — Display Configurator (prepared)

### Summary

Packages the Display settings configurator and storefront currency switcher on
`main` under version **0.9.0**. Settings schema remains **v3** — no new
migration beyond the v2 → v3 path already on `main`.

### Merchant-visible changes

| Area | Change |
|---|---|
| Display settings | Visual configurator with placement, style, appearance, behavior, and visibility controls |
| Positioning | Floating Side and Floating Bottom with offset controls; manual shortcode placement |
| Preview | Live responsive preview with sticky Display save and unsaved-change indicator |
| Storefront | Currency switcher renderer (dropdown / horizontal list), shortcode, and switcher assets |

### Deployment sequence

1. Run `composer release-audit` on the **0.9.0** tree.
2. Build `dist/universal-multicurrency-0.9.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.8.x** in place. No migration step for stores already on schema v3.

### Rollback

Downgrade to the **0.8.x** zip if needed. Display settings in `umc_settings` may
include a `display` subtree that older builds ignore safely; order snapshots are
unaffected.

---

---

## v0.19.0 — Authoritative Fixed Product Pricing (released)

Ships Milestone 20 under version **0.19.0** (ADR-0025). Settings schema remains **6**; PersistedKeys inventory **9**; order snapshot schema **4**. Optional merchant-authored fixed regular/sale prices per non-base foreign currency on simple products and variations; WooCommerce sale schedule gates fixed sale amounts; line-item pricing provenance.

**Released as:** **v0.19.0** — tag `v0.19.0`, GitHub release published, artifact `universal-multicurrency-0.19.0.zip` (SHA-256 `d11bc0da886177aba2e2400ada8cd511dfbb044aa1f3178834bab3dcf896e44c`).

### Deployment sequence (release verification completed)

1. Run `composer release-audit` on the **0.19.0** tree.
2. Build `dist/universal-multicurrency-0.19.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.18.0** in place. No migration step — products without `_umc_fixed_prices` behave unchanged. **Not performed as part of release closure.**

---

Ships Milestone 19 under version **0.18.0** (ADR-0024). Settings schema remains **6**; PersistedKeys inventory **8**; order snapshot schema **4**. Extension compatibility framework with E0–E3 evidence tiers. Opt-in fee conversion wired; third-party shipping pass-through formalized. Priority adapters: Subscriptions, Product Add-Ons, Product Bundles (**Characterized E2 only** — not Integrated or Supported). Real-extension E3 validation remains pending.

**Released as:** **v0.18.0** — tag `v0.18.0`, GitHub release published, artifact `universal-multicurrency-0.18.0.zip`.

### Deployment sequence (release verification completed)

1. Run `composer release-audit` on the **0.18.0** tree.
2. Build `dist/universal-multicurrency-0.18.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.17.x** in place. No migration step. **Not performed as part of release closure.**

---

## v0.17.0 — WooCommerce Compatibility & Transaction Integrity

Ships Milestone 18 under version **0.17.0** (ADR-0023). Settings schema remains **6**; PersistedKeys inventory **8**; order snapshot schema **4**. Free-shipping `min_amount` converts at eligibility time.

## v0.16.0 — Switcher Customization

### Summary

Ships Milestone 17 under version **0.16.0** (ADR-0022). Settings schema moves
**v5 → v6**: the `display` subtree is restructured into `content` (per-context
element composition and order), `design` (preset, theme, size, shape, sparse
overrides, motion), `responsive`, and `custom_css`. The migration is lossless
and visually neutral — legacy `theme` / `size` / `shape` are preserved as
first-class settings and `design.preset` is always set to `default`.

Advanced Custom CSS (`display.custom_css`) requires Display-save authority
**and** the `edit_css` capability. It is emitted on the storefront only, via
`wp_add_inline_style( 'umc-switcher', … )` while the stylesheet is enqueued, and
is never injected into wp-admin or the Display live preview. Persisted-data
inventory is unchanged (no new option or meta key).

### Deployment sequence

1. Run `composer release-audit` on the **0.17.0** tree.
2. Build `dist/universal-multicurrency-0.17.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.15.x** in place. The schema **v5 → v6** migration runs on the
   first canonical settings read; no manual step.
4. Confirm the storefront switcher renders unchanged, then review
   **WooCommerce → Settings → Multicurrency → Display**.

### Rollback

Downgrade to the **0.15.x** zip if needed. Older builds read `umc_settings` at
schema 6 through their own sanitizer: the legacy `display.content.show_*` and
`display.appearance.*` keys are no longer present, so a downgraded build falls
back to Display defaults for content and appearance until the settings are saved
again. Currencies, rates, checkout policy, geo rules, and order snapshots are
unaffected. Capture the `umc_settings` option before upgrading if an exact
appearance rollback is required.

---

## v0.15.0 — Exchange Rate Operations & Reliability

### Summary

Ships Milestone 16 under version **0.15.0** (ADR-0021). Settings schema
remains **v5** — no migration. Adds shared rate health reporting, presentation-
only aging, scheduler `has_automatic_targets`, structured refresh failures,
admin ops UX, order snapshot schema **4** provenance, and thin WP-CLI
(`wp umc rates`). Persisted-data inventory bumps 7→8. No live storefront
provider HTTP; stale rates remain usable for conversion.

### Deployment sequence

1. Run `composer release-audit` on the **0.15.0** tree.
2. Build `dist/universal-multicurrency-0.15.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.14.x** in place. No settings migration step.

### Rollback

Downgrade to the **0.14.x** zip if needed. Order schema 4 provenance keys are
additive; older readers ignore unknown `_umc_*` keys. Health/CLI/admin ops are
additive surfaces.

---

## v0.14.0 — Currency Resolution & Explainability

### Summary

Ships Milestone 15 under version **0.14.0** (ADR-0020). Settings schema
remains **v5** — no migration. Adds structured currency resolution results,
explanatory `umc_currency_origin` session provenance, an on-demand decision
explainer, and a stateless Decision Inspector admin section. Persisted-data
inventory bumps 6→7. No storefront currency-outcome change.

### Deployment sequence

1. Run `composer release-audit` on the **0.14.0** tree.
2. Build `dist/universal-multicurrency-0.14.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.13.x** in place. No settings migration step.

### Rollback

Downgrade to the **0.13.x** zip if needed. Admin Decision Inspector and
provenance metadata are additive; storefront precedence is unchanged.

---

## v0.13.0 — Visitor Location boundary alignment

### Summary

Ships Milestone 14 under version **0.13.0** (ADR-0018). Settings schema
remains **v5** — no migration. The Visitor Location hub shrinks from seven
panels to three (Overview, Currency Routing, Currency Simulation); provider,
trusted-proxy, and diagnostics content is removed in favor of Universal Geo
Context deep links. GeoContext (sandbox document) schema bumps 1→2, removing
unused reserved fields — the sandbox result cache is ephemeral and discards
stale-schema entries safely on read. No storefront behaviour change.

### Deployment sequence

1. Run `composer release-audit` on the **0.13.0** tree.
2. Build `dist/universal-multicurrency-0.13.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.12.x** in place. No settings migration step. Bookmarked
   URLs for the retired Providers/Trusted Proxies/Diagnostics/Settings
   panels redirect automatically.

### Rollback

Downgrade to the **0.12.x** zip if needed. All admin-only changes; the geo
provider chain, currency precedence ladder, and settings schema are
unchanged, so a downgrade is safe at any point.

---

## v0.12.0 — Geo Detection admin hub

### Summary

Ships Milestone 13 Geo admin hub under version **0.12.0**. Settings schema
remains **v5** — no migration. The monolithic Geo Detection page becomes a
panel-based hub with GeoContext sandbox simulation.

### Deployment sequence

1. Run `composer release-audit` on the **0.12.0** tree.
2. Build `dist/universal-multicurrency-0.12.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.11.x** in place. No settings migration step.

### Rollback

Downgrade to the **0.11.x** zip if needed. Geo hub UI changes are admin-only;
storefront geo routing behaviour is unchanged.

---

## v0.11.0 — Geo Detection

### Summary

Ships Milestone 12 Geo Detection under version **0.11.0**. Settings schema
**v4→v5** adds disabled Geo Detection defaults with empty rules — storefront
behaviour is unchanged until an administrator enables routing.

### Deployment sequence

1. Run `composer release-audit` on the **0.11.0** tree.
2. Build `dist/universal-multicurrency-0.11.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.10.x** in place. Settings migration runs automatically on first load.

### Rollback

Downgrade to the **0.10.x** zip if needed. v5 geo settings are ignored safely by
older builds; order snapshots remain readable.

---

## v0.10.0 — Checkout currency policy

### Summary

Ships Milestone 11 checkout currency policy under version **0.10.0**. Settings
schema **v3→v4** adds checkout defaults (`mode: selected`, `show_notice: true`)
that preserve v0.9.x checkout behaviour. Order snapshot schema **v3** adds
checkout audit metadata.

### Deployment sequence

1. Run `composer release-audit` on the **0.10.0** tree.
2. Build `dist/universal-multicurrency-0.10.0.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.9.x** in place. Settings migration runs automatically on first load.

### Rollback

Downgrade to the **0.9.x** zip if needed. v4 checkout settings and v3 order
snapshot fields are ignored safely by older builds; order snapshots remain readable.

---

## v0.9.1 — Compatibility diagnostics

### Summary

Packages the Compatibility diagnostics center and post-release validation/rate
fixes on `main` under version **0.9.1**. Settings schema remains **v3** — safe
in-place upgrade from **0.9.0**.

### Merchant-visible changes

| Area | Change |
|---|---|
| Compatibility tab | Read-only diagnostics center with grouped findings and support report |
| Configuration accuracy | Base currency and default symbol warnings no longer false-positive |
| Exchange rates | Single-currency Update now no longer marks other currencies failed |

### Deployment sequence

1. Run `composer release-audit` on the **0.9.1** tree.
2. Build `dist/universal-multicurrency-0.9.1.zip` with `composer install --no-dev`
   + `bin/build-zip.sh`.
3. Deploy over **0.9.0** in place. No migration step.

### Rollback

Downgrade to the **0.9.0** zip if needed. Order snapshots are unaffected.
