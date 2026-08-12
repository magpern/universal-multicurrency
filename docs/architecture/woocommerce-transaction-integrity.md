# WooCommerce transaction integrity (Milestone 18)

This document is the authoritative specification for Milestone 18
(**v0.17.0**) — **released**. It formalizes transaction-integrity invariants,
conversion boundaries, Classic / Blocks / Store API parity, and the known
limitation surface before third-party extension work (M19).

Related:

- [`transaction-flow.md`](transaction-flow.md) — unit-price-authoritative flow
- [`store-api-request-lifecycle.md`](store-api-request-lifecycle.md)
- ADR-0002 (rounding), ADR-0004 (transaction currency), ADR-0005 (historical),
  ADR-0006 (Store API), ADR-0014 (checkout policy), **ADR-0023** (this contract)
- [`COMPATIBILITY.md`](../COMPATIBILITY.md) — evidence-linked matrix

## Scope

**In scope:** WooCommerce core product prices, cart, coupons, core shipping,
taxes, Classic checkout, Cart/Checkout Blocks via Store API, HPOS order CRUD,
refunds, order-pay, order-received, My Account order views, WooCommerce emails,
WooCommerce REST (`/wc/v3`) context boundary, currency-sensitive caches.

**Out of scope (M19+):** Subscriptions, Bookings, Product Add-Ons, Bundles,
Composite Products, Dynamic Pricing, gift cards, memberships, third-party
checkout plugins, third-party shipping plugins, per-gateway adapters beyond
generic currency capability, new rate providers, switcher presentation,
Visitor Location, Decision Inspector redesign.

## Architecture (unchanged)

UMC does **not** replace WooCommerce’s cart, discount, tax, or shipping
engines. It converts **authored base-currency monetary inputs** at defined
boundaries through a single seam:

```text
Base amount (product / fixed coupon / coupon threshold / core shipping cost /
             free-shipping min_amount)
  → PriceConversionService → Converter
  → WooCommerce native totals / tax / discounts
  → Order stores active-currency totals
  → OrderSnapshot writes immutable _umc_* audit (schema 4)
```

Classic templates and the Store API share that engine. Store API classes in
`src/StoreApi` adapt **timing** only (snapshot refresh, order-currency lock,
checkout policy application).

## Invariants

| ID | Invariant |
|---|---|
| **A** | **Single conversion.** A base-authored monetary input crosses into the active currency at most once. |
| **B** | **One currency context per calculation.** Product prices, tax basis, shipping, coupon thresholds, and checkout totals are internally coherent. |
| **C** | **Order immutability.** After the payment boundary, later rate/session/config changes cannot reinterpret stored order amounts. Snapshot write-once (classic) / unpaid Store API draft refresh only. |
| **D** | **Refund consistency.** Refunds use parent order monetary context; rate identity is audit. No live FX for refund math. |
| **E** | **Classic / Blocks parity.** Equivalent inputs produce equivalent currency outcomes and totals (representation may differ: decimal strings vs minor units). |
| **F** | **Display / transaction consistency.** Displayed catalogue/cart/checkout values agree with values WooCommerce ultimately stores. |
| **G** | **Base-currency integrity.** Canonical/base WooCommerce values outside the active shopper context are not corrupted. |
| **H** | **Admin historical safety.** Admin views do not reinterpret historical orders using current shopper/session currency. |
| **I** | **REST boundary.** `/wc/v3` is administrative / base-or-stored. `/wc/store/` participates in shopper currency semantics. |
| **J** | **HPOS parity.** Order/refund `_umc_*` meta uses WooCommerce CRUD, not direct postmeta assumptions. |
| **K** | **Threshold consistency.** Fixed monetary thresholds (coupon min/max spend, free-shipping `min_amount`) are compared in the same effective currency as cart values. |
| **L** | **No live FX HTTP.** Storefront/cart/checkout monetary requests never fetch live exchange rates. |

## Conversion boundaries

| Input | Authored in | Converted? | Owner |
|---|---|---|---|
| Product unit / regular / sale / variation prices | base | Yes, once (view getters) | `PriceHooks` |
| Fixed coupon amount | base | Yes, once | `CouponConversion` |
| Coupon min/max spend | base | Yes, once | `CouponConversion` |
| Percentage coupon | percent | No | WooCommerce |
| Core shipping cost + shipping-line taxes | base | Yes, once | `ShippingConversion` |
| Free-shipping **`min_amount`** | base | Yes, once at eligibility evaluation | `ShippingConversion` (M18) |
| Line/cart taxes | percent | No — WC on converted amounts | `WC_Tax` |
| Fees | caller-defined | **No** (intentionally unwired) | Fee author |
| Order totals after creation | order currency | Never reconverted | WooCommerce + snapshot |

## Free-shipping minimum threshold (M18)

WooCommerce free shipping compares configured `min_amount` to the cart’s
displayed subtotal (and discount adjustments). The configured amount is
**base-authored**. When the active currency is not base, UMC converts the
threshold **base → active** at evaluation time, then lets WooCommerce’s
comparison run against active-currency cart totals.

Contract:

- Convert request/calculation-scoped only.
- Do **not** persist a converted threshold into shipping-method settings.
- Do **not** permanently mutate `$method->min_amount` on the shared instance.
- Do **not** convert the cart back to base for comparison.
- Respect WooCommerce `requires` / coupon / `ignore_discounts` semantics.
- Classic and Store API must agree.

## Fees (known limitation)

Fees are **not** converted. Documented filter `umc_convert_fee` remains
**unwired**. Fee authors must supply amounts in the **effective active
currency** unless a future explicit extension contract (M19+) says otherwise.
Do not label fee conversion as Supported.

## Cart rate lifetime

The cart uses the **current live, locally stored** rate (request-memoized via
`CurrencyContext`) until order creation. Session `umc_cart_signature`
(`code:rate`) forces recalculation when currency **or** rate changes. There is
**no** cart-session FX lock across requests. The order snapshot freezes rate
identity at order creation.

## REST / Store API

| Namespace | Convertible? |
|---|---|
| `/wc/store/*` | Yes — shopper semantics |
| `/wc/v3/*` and other REST | No — base / stored values |

## Caches

Currency-sensitive caches vary by rate identity (`code:rate`), not currency
code alone:

- variation prices hash (`woocommerce_get_variation_prices_hash`)
- cart session signature
- shipping package signature (`umc_currency_signature`)

Do not disable WooCommerce caches globally.

## Persistence (frozen for M18)

| Item | Value |
|---|---|
| Settings schema | **6** |
| PersistedKeys inventory | **8** |
| Order snapshot schema | **4** |
| DB migration | none |

## Compatibility labels

Claims in [`COMPATIBILITY.md`](../COMPATIBILITY.md) use:

- **Supported** — automated evidence on a blocking CI leg
- **Characterized** — inspected/tested but not a full matrix claim
- **Known limitation** — intentional boundary (e.g. fees)
- **Out of scope** — deferred (e.g. third-party extensions)

## Escalation

Stop rather than expand M18 if work requires CurrencyResolver redesign, rate
architecture change, checkout-policy redesign, schema/snapshot/PersistedKeys
change, DB migration, breaking public API, separate Classic vs Blocks monetary
engines, WooCommerce forks, Playwright for correctness, or broad third-party
adapters.
