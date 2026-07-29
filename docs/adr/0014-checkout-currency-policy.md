# ADR-0014: Checkout currency policy

**Status:** Accepted  
**Date:** 2026-07-29  
**Milestone:** ROADMAP M11 / Product Milestone 5 — Checkout (v0.10.0)

## Context

Merchants need configurable checkout currency behaviour:

- keep the shopper-selected currency through checkout (default, v0.9.x parity), or
- browse/cart in the selected currency but switch to store currency at checkout entry, and
- when selected mode leaves no payment gateway that explicitly supports the shopper currency, retry once in store currency with an informational notice.

Classic checkout and Checkout Blocks must behave identically. Gateway availability must stay aligned with WooCommerce core.

## Decision

Introduce a checkout policy layer with these invariants:

1. **WooCommerce remains the sole authority** for gateway availability via `get_available_payment_gateways()`.
2. **`GatewayCurrencyClassifier`** is the sole currency classifier; it produces an immutable **`GatewayCurrencyEvaluation`** stored request-scoped on **`GatewayCompatibility`** after the UMC filter runs.
3. **`CheckoutPolicyCoordinator`** is the sole two-pass orchestrator; classic and Store API adapters delegate to it without duplicating policy logic.
4. **`CheckoutCurrencyPolicy`** is pure and WordPress-free; fallback requires proven explicit currency incompatibility for every gateway WooCommerce supplied before UMC filtering.
5. **Shopper vs effective currency** — `CurrencyContext::get_shopper_code()` preserves session preference; checkout effective currency is applied via `CheckoutEffectiveCurrencyProvider` without rewriting the shopper selection.
6. **Blocks notices** — Store API extension data (`extensions.umc.checkout_notice`) plus `assets/js/checkout-notice.js` rendering into `core/notices` with context `wc/checkout`.
7. **Order snapshot v3** — persist `_umc_checkout_mode`, `_umc_shopper_currency`, `_umc_fallback_occurred` from `CheckoutTransitionState`; never infer fallback from post-hoc currency comparison.

Settings schema v4 adds:

```php
'checkout' => [
    'mode'        => 'selected',
    'show_notice' => true,
],
```

## Consequences

- No mirrored WooCommerce `is_available()` loop inside UMC.
- Request-scoped `$request_evaluation` on `GatewayCompatibility` is an acceptable WordPress callback bridge.
- Default `mode=selected` preserves existing behaviour for upgraded stores.
- Checkout policy does not run on order-pay, order-owned routes, cart-only Store API, admin, or cron surfaces.

## Related

- ADR-0006 — Store API and Blocks parity
- ADR-0004 — Transaction currency and order snapshot
- [`docs/MIGRATION.md`](../MIGRATION.md) — schema v3→v4 migration
