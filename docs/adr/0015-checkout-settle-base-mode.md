# ADR-0015: Checkout settle-base mode (display selected, settle in base)

**Status:** Accepted  
**Date:** 2026-07-29  
**Milestone:** ROADMAP follow-up to M11 — Checkout settle-base (v0.11.0)

## Context

Merchants may want shoppers to **see** prices in their selected currency throughout checkout while **payment, gateways, and order settlement** always use the store/base currency. This differs from existing modes:

| Mode | Checkout display | Settlement |
|---|---|---|
| `selected` | Selected | Selected |
| `store` | Base | Base |
| `settle_base` (new) | Selected | Base |

M11 intentionally used a single effective currency (`CurrencyContext::set_effective_override()`) for both display conversion and gateway filtering. Settle-base requires a **presentation vs settlement split**.

## Decision

1. **Third checkout mode** — `checkout.mode = settle_base` alongside `selected` and `store`. No settings schema version bump (mode remains a string in the v4 subtree).

2. **Transition state** — extend `CheckoutTransitionState` with `settlement_currency`. For `settle_base`:
   - Presentation phase: `effective_currency = shopper`, `settlement_currency = store`
   - Settlement phase (order creation): apply store override, recalculate cart, set `effective_currency = store` for snapshot write

3. **Policy phases** — `CheckoutPolicyCoordinator` accepts `presentation` vs `settlement`:
   - Classic: presentation on `woocommerce_before_checkout_form` / `woocommerce_checkout_update_order_review`; settlement on `woocommerce_checkout_create_order` (priority 5, before snapshot)
   - Store API: presentation on checkout GET; settlement on checkout POST

4. **Gateway filtering** — `GatewayCompatibility` filters against `settlement_currency` from transition state when the checkout coordinator is active, so gateways match settlement currency even when display currency remains selected.

5. **Notices** — new reason `settle_base_at_checkout` with copy stating checkout **shows** the shopper currency but **payment settles** in store currency.

6. **Order snapshot** — written after settlement phase; `_umc_transaction_currency` reflects store currency. `_umc_shopper_currency` preserves the browsing selection.

7. **Store API extension** — expose `settlement_currency` on `extensions.umc` cart/checkout payloads.

## Consequences

- Settle-base is an explicit exception to the single-currency transaction model in `docs/architecture/transaction-flow.md` for the presentation phase only; settlement still follows ADR-0004 at order creation.
- Customers may review selected-currency totals then pay in base currency — informational notices are strongly recommended.
- Selected-mode gateway fallback does not apply in settle-base mode (settlement is always base).
- Refunds follow the stored transaction (base) currency.

## Related

- ADR-0014 — Checkout currency policy (M11)
- ADR-0004 — Transaction currency and order snapshot
