# ADR-0023: WooCommerce Transaction Integrity Contract

**Status:** Accepted (Milestone 18, target v0.17.0)

**Related:**
[`docs/architecture/woocommerce-transaction-integrity.md`](../architecture/woocommerce-transaction-integrity.md),
[`docs/architecture/transaction-flow.md`](../architecture/transaction-flow.md),
ADR-0002, ADR-0004, ADR-0005, ADR-0006, ADR-0014

## Context

Milestones 2–5 established unit-price-authoritative conversion, Classic cart/
checkout integrity, historical order formatting, and Store API / Blocks parity.
Milestone 11 added checkout currency policy. Milestone 16 froze rate-ops and
snapshot provenance (schema 4). Milestone 17 was presentation-only.

Before broadening into third-party extension compatibility (M19), the project
must prove WooCommerce **core** monetary flows remain internally consistent:
one conversion, one currency context per calculation, Classic ↔ Blocks parity,
immutable orders, and correct threshold comparisons (including free-shipping
`min_amount`). Fees remain an intentional non-conversion boundary.

## Decision

### One monetary context per WooCommerce calculation

All monetary values participating in a single cart/checkout calculation use the
same effective currency context (active shopper currency, or checkout effective
currency after M11 policy, or order-owned currency on order-pay / historical
surfaces).

### No double conversion

Base-authored inputs cross `PriceConversionService` → `Converter` at most once.
The cart stores product references, never converted prices. The plugin never
calls `set_price()` to write converted amounts back onto cart items.

### WooCommerce owns tax, discount, and rounding mechanics

UMC does not implement a parallel tax or discount calculator. Taxes are never
converted; percentages apply to already-converted amounts. Rounding at the
conversion seam follows ADR-0002; downstream WooCommerce rounding is left to
WooCommerce.

### UMC converts authored base monetary inputs at defined boundaries

| Boundary | Service |
|---|---|
| Product / variation unit prices | `PriceHooks` |
| Fixed coupons + coupon min/max spend | `CouponConversion` |
| Core shipping costs (+ shipping-line taxes) | `ShippingConversion` |
| Free-shipping `min_amount` | `ShippingConversion` (evaluation-time) |

### Order context becomes immutable

WooCommerce stores order currency and active-currency totals. `OrderSnapshot`
writes `_umc_*` audit meta (schema **4**) write-once on classic checkout;
unpaid Store API drafts may refresh until payment. Later rate changes do not
reinterpret historical amounts.

### Refunds use parent / order historical context

Refund amounts are order-native. UMC writes parent currency + rate identity for
audit only. No live exchange-rate lookup alters refund value semantics.

### Classic and Blocks / Store API parity is a release requirement

Equivalent inputs must produce equivalent currency outcomes and totals.
Representation differences (decimal strings vs minor units) are allowed;
monetary disagreement is not. Evidence lives in integration tests (especially
`ClassicStoreApiParityTest` and related Store API suites).

### REST boundary

- `/wc/v3` (and non–Store-API REST) does **not** inherit shopper conversion.
- `/wc/store/` **does** participate in shopper currency semantics.

### Free-shipping `min_amount`

Configured free-shipping minimum order amount is **base-authored**. When
compared to an active-currency cart total, UMC converts the threshold
base → active once at evaluation time. Do not convert the cart back to base,
do not persist converted settings, and do not permanently mutate the shared
method’s `$min_amount`.

### Fees remain intentionally unwired in M18

No `woocommerce_cart_calculate_fees` conversion. Documented `umc_convert_fee`
stays unwired. Fee authors must price in the effective active currency.
Third-party fee integration may be revisited in M19.

### Third-party extensions deferred to M19

Subscriptions, Bookings, Bundles, Add-Ons, Composite, Dynamic Pricing, gift
cards, memberships, third-party shipping/checkout/gateway-specific adapters
are out of scope.

### No live rate-provider HTTP in monetary request paths

Storefront, cart, and checkout monetary calculations use locally stored rates
only (M16 freeze). Provider HTTP remains ops/scheduler/admin refresh paths.

### Persistence frozen

Settings schema **6**, PersistedKeys inventory **8**, order snapshot schema
**4**, no DB migration in M18 unless escalated to humans.

## Consequences

- Free-shipping eligibility in foreign currencies becomes apples-to-apples with
  coupon threshold conversion; merchants may see corrected eligibility vs
  pre-M18 behaviour.
- Compatibility claims must be evidence-backed (`Supported` /
  `Characterized` / `Known limitation` / `Out of scope`).
- Fee conversion remains a Known limitation, not a Supported feature.
- M19 can build on a proven WooCommerce-core integrity contract without
  redesigning CurrencyResolver, rates, or checkout policy.

## Alternatives considered

- **Compare free-shipping thresholds in base by converting the cart back.**
  Rejected: invents a second calculation currency and diverges from coupon
  threshold precedent.
- **Persist converted `min_amount` into shipping settings.** Rejected: corrupts
  merchant configuration and breaks base-currency admin display.
- **Wire fee conversion by default.** Rejected: reverses ADR-0004 without a
  safe universal fee-authoring contract; deferred.
- **Separate Classic vs Blocks conversion engines.** Rejected: ADR-0006; would
  guarantee drift.
