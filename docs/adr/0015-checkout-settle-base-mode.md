# ADR-0015: Checkout settle-base mode (display selected, settle in base)

**Status:** Withdrawn  
**Date:** 2026-07-29  
**Withdrawn:** 2026-07-29  
**Milestone:** ROADMAP follow-up to M11 — Checkout settle-base (v0.11.0, not shipped)

## Context

Merchants may want shoppers to **see** prices in their selected currency throughout checkout while **payment, gateways, and order settlement** always use the store/base currency. This differs from existing modes:

| Mode | Checkout display | Settlement |
|---|---|---|
| `selected` | Selected | Selected |
| `store` | Base | Base |
| `settle_base` (proposed) | Selected | Base |

M11 intentionally used a single effective currency (`CurrencyContext::set_effective_override()`) for both display conversion and gateway filtering. Settle-base required a **presentation vs settlement split**.

## Decision (withdrawn)

The mode was implemented briefly but **withdrawn before release**. WooCommerce sets `$order->set_currency( get_woocommerce_currency() )` **before** `woocommerce_checkout_create_order`, so redirect gateways such as BTCPay read the shopper display currency (e.g. SEK) instead of the intended settlement currency (EUR). A late settlement hook cannot reliably fix invoice creation for those gateways without also changing what the customer sees at payment time.

Existing `settle_base` settings values are sanitized to `store` on read.

## Related

- ADR-0014 — Checkout currency policy (M11)
- ADR-0004 — Transaction currency and order snapshot
