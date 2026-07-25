# ADR-0004 — Transaction currency, cart conversion and the order snapshot

## Status

Accepted (Milestone 3).

## Context

Milestone 2 converts product **display** prices at runtime. Milestone 3 must make
the whole classic transaction — cart totals, coupons, core shipping, taxes,
checkout and the created order — consistent in the customer's selected currency,
with a permanent, immutable record of the rate used, and without ever converting
an amount twice. WooCommerce owns the cart/discount/shipping/tax engine and the
order store (including HPOS); the plugin must cooperate with it, not replace it.

## Decision

- **Invariant.** `selected currency = cart calculation currency = checkout
  currency = order currency`, always identical in M3. The only exceptions are
  existing-order operations (Milestone 4) and pre-order fallback to base.

- **Unit-price-authoritative conversion.** The Milestone 2 `view`-context product
  price getters remain the **single** product-price converter and feed
  WooCommerce's native totals engine. M3 adds conversion **only** for the inputs
  M2 never touched — fixed coupon amounts, coupon min/max thresholds, and **core**
  shipping costs (`flat_rate`, `free_shipping`, `local_pickup`). Everything else
  WooCommerce computes natively from those converted amounts. The rejected
  alternative — setting a converted price back onto the cart item in
  `woocommerce_before_calculate_totals` — re-enters the M2 view filter and double
  converts.

- **No double conversion, by construction.** The cart stores product references,
  never prices; the plugin never calls `set_price()`; coupon/shipping conversions
  read base amounts from configuration, not from converted cart values; each fires
  once per calculation. Only `PriceConversionService` may call `Converter`
  (enforced by a structural test).

- **Taxes are never converted.** Tax rates are currency-agnostic percentages;
  WooCommerce computes taxes natively on the converted amounts.

- **Fees are not converted** (disabled by product decision). An opt-in
  `umc_convert_fee` filter is documented but not wired.

- **Rounding (continues ADR-0002).** Each unit price is converted base→active and
  rounded to the active currency's decimals at WooCommerce's own display-price
  boundary. A converted unit price rounded to active decimals is exactly what a
  native store priced in that currency would hold, so all downstream WooCommerce
  math is identical to a native store. Converted intermediate values are never
  re-rounded. Shipping cost and its per-class taxes are scaled by the same rate so
  `tax = cost × tax_rate` stays consistent. Sum-of-parts reconciliation
  (`total = subtotal − discount + shipping + fees + tax`) is asserted by tests.

- **Rate identity for caches.** `CurrencyContext::get_currency_signature()` returns
  `code:rate` (e.g. `SEK:11.50`), filterable via `umc_currency_signature`. A
  currency code alone is insufficient because a rate can change; the identity is
  used to isolate the cart-totals recalculation, the shipping-rate cache and any
  currency-specific cache, so they self-invalidate on a switch **or** a rate edit.

- **Immutable order snapshot.** WooCommerce natively stores the order currency and
  active-currency line totals. At `woocommerce_checkout_create_order` the plugin
  writes, once, via `WC_Order` CRUD (HPOS-safe; never post meta / SQL), the audit
  snapshot:

  | Meta key | Meaning |
  |---|---|
  | `_umc_base_currency` | store base currency at order time |
  | `_umc_transaction_currency` | order (active) currency |
  | `_umc_exchange_rate` | exact base→transaction rate used |
  | `_umc_rate_timestamp` | when the rate was last set (else order time) |
  | `_umc_rate_source` | `manual` |
  | `_umc_plugin_version` | plugin version |
  | `_umc_rate_identity` | `code:rate` at order time |

  The snapshot is write-once and never overwritten, so later store-rate changes
  cannot alter a historical order. Base-currency reference totals are **not**
  stored (they are derivable and would create competing authoritative totals);
  they are deferred to a reporting milestone. `_umc_*` order meta is permanent and
  is never removed on uninstall.

- **Gateways.** Gateways that do not support the active currency are removed from
  `woocommerce_available_payment_gateways` before order placement (support
  declared via `umc_gateway_supported_currencies`; null = all). The plugin never
  rewrites a gateway's amount or currency.

- **Fallback.** Before an order exists, an invalid/rate-less selection collapses to
  base and fully recalculates. Once an order exists, its stored currency + snapshot
  are authoritative (Milestone 4 handles existing-order display/refunds).

## Consequences

- The cart, discount, shipping and tax maths are WooCommerce's own, so behaviour
  matches a native single-currency store and stays correct as WooCommerce evolves.
- Tax-inclusive multi-quantity lines round at the converted-unit-price boundary
  rather than "convert only at the very end"; this is deliberate and bounded by
  reconciliation tests.
- Blocks/Store API is out of scope: classic checkout is the only supported path in
  M3, and Blocks compatibility is not claimed. Fees, order display, emails and
  refunds arrive in later milestones.
