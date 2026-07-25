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
