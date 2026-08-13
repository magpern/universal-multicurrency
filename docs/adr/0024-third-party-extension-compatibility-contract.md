# ADR-0024: Third-Party Extension Compatibility Contract

**Status:** Accepted (Milestone 19, target v0.18.0)

**Related:**
[`docs/architecture/extension-compatibility.md`](../architecture/extension-compatibility.md),
[`docs/architecture/woocommerce-transaction-integrity.md`](../architecture/woocommerce-transaction-integrity.md),
ADR-0023, ADR-0006, ADR-0007, ADR-0014

## Context

Milestone 18 (ADR-0023, v0.17.0) proved WooCommerce **core** transaction
integrity: one conversion seam, Classic ↔ Blocks parity, immutable orders, and
explicit boundaries for fees and third-party shipping. Third-party extensions
(Subscriptions, Product Add-Ons, Bundles, Composite, Bookings, dynamic pricing,
gift cards, memberships) were deferred.

Milestone 19 must establish a maintainable compatibility framework and apply it
to a bounded set of priority integrations without accumulating random
plugin-specific filters throughout the codebase or creating a second monetary
engine.

Licensed premium extension ZIPs are **not** an M19 release prerequisite. Public CI
cannot run proprietary WooCommerce.com extensions. Evidence classification must
remain honest.

## Decision

### Extension compatibility statuses

| Status | Evidence requirement |
|---|---|
| **Native** | Works via normal WC hooks; no adapter |
| **Integrated** | Adapter (if needed) + **E3 real-extension validation only** |
| **Characterized** | E1 contract tests and/or E2 hook-double tests |
| **Known limitation** | Documented constraints with reproduced or characterized behaviour |
| **Incompatible** | Reproduced failure |
| **Not evaluated** | Default (E0) |

### Evidence tiers

| Tier | Source | Max status | Merchant sub-label |
|---|---|---|---|
| E0 | None | Not evaluated | — |
| E1 | Unit contract tests | Characterized | Characterized — contract tests |
| E2 | UMC-owned hook-semantics test doubles | Characterized | Characterized — simulated extension hooks |
| E3 | Real licensed extension integration tests | Integrated | Integrated — real extension validated |

**E1 and E2 can never produce Integrated.** Test doubles must not be used to
claim real-plugin compatibility.

### Adapter boundary

Adapters live under `src/Compatibility/Extension/`. They may bridge
extension-specific monetary inputs into existing UMC services only:

- `PriceConversionService` / `PriceHooks`
- `CouponConversion`
- `ShippingConversion` (with `umc_convert_shipping_rate` opt-in)
- `FeeConversion` (with `umc_convert_fee` opt-in)
- `GatewayCompatibility` / `GatewayCurrencyClassifier`
- `OrderCurrencyContext`

Adapters must **not** implement CurrencyResolver, Converter, exchange-rate
lookup, checkout policy, or Visitor Location logic.

Conversion code outside `src/Compatibility/` must not reference third-party
extension classes.

### Detection

- Centralized `ExtensionDetector` with safe passive probes (ADR-0007 pattern)
- Memoized once per request
- No fatal class references when extension absent
- No hard Composer dependencies on premium extensions

### Fee conversion (opt-in only)

Wire `umc_convert_fee` via `FeeConversion`:

- Default `false` — fees pass through unchanged
- Extension declares base-authored fee → opt in → convert once
- No global automatic fee conversion

### Third-party shipping (formalized pass-through)

Existing M18 contract unchanged:

- Core methods convert by default
- Third-party rates pass through unless `umc_convert_shipping_rate` opts in
- Do not assume uniform monetary semantics across shipping plugins

### Dynamic pricing boundary

UMC converts base-authored unit prices. Price-modifying extensions must run
before the conversion seam or return active-currency amounts. No specific
third-party dynamic-pricing adapters in M19.

### Gateway compatibility

Validate against generic `GatewayCurrencyClassifier` policy. Prefer
`umc_gateway_supported_currencies` over gateway-name conditionals. No
Stripe/PayPal-specific monetary engines.

### Compatibility Center

Extension rows display:

- Primary status with characterized sub-label (E1/E2/E3 distinction visible without opening evidence)
- Tested-through vs detected version
- Untested-version warning when detected > tested-through
- Known limitations

Never auto-deactivate conflicting extensions (ADR-0007).

### Unsupported-version behaviour

When detected version exceeds tested-through version, continue operating but
display **Untested version** — do not falsely claim verified support.

### Persistence stop gate

Settings schema **6**, PersistedKeys inventory **8**, order snapshot schema **4**,
no DB migration — frozen unless explicitly escalated.

If an adapter requires new persisted monetary state: **STOP**, document the
requirement, seek architectural review. Do not silently expand schema.

## WooCommerce Subscriptions — monetary contract

### Phase A characterization (required before adapter)

Characterize from authoritative available evidence (public WCS documentation,
hook/filter references, and/or licensed-plugin inspection when available):

1. Subscription currency identity
2. Renewal currency identity
3. Exchange-rate policy (original vs current vs WCS-authoritative — **separate from currency**)
4. Signup fee vs recurring amount surfaces
5. Manual vs automatic renewal semantics
6. Historical renewal immutability

### Safe invariant (pre-policy)

> Browsing currency, Visitor Location, and shopper session state must never
> accidentally determine an existing subscription's renewal currency.

### Phase A findings (authoritative public evidence)

From WooCommerce Subscriptions public documentation and API surface:

- **Subscription currency identity:** WCS stores currency on the subscription
  object (`get_currency()` on `WC_Subscription`). Subscriptions inherit currency
  from the initial order unless explicitly changed through WCS APIs.
- **Renewal currency identity:** Renewal orders are created in the
  subscription's currency. WCS uses the subscription as the authoritative
  monetary context for renewals — not the current shopper browsing currency.
- **Exchange-rate policy:** WCS stores line-item prices on the subscription.
  Renewal totals derive from stored subscription amounts and WCS pricing logic,
  not from UMC's current browsing rate. UMC must **not** re-convert renewal
  amounts using the current session rate when the subscription already carries
  active-currency amounts from its creation context. For **initial** subscription
  purchase (including signup), UMC converts base-authored product/signup prices
  through the normal seam at checkout time and snapshots on the order. For
  **renewal** order generation, UMC enters `OrderCurrencyContext` from the
  parent subscription/order snapshot and suppresses browsing-currency conversion
  on subscription-owned recurring amounts.
- **Rate identity (Phase B decision):** Renewals use the **subscription's stored
  monetary context** (currency + rate identity from parent order snapshot when
  present), not the merchant's current browsing rate or Visitor Location. UMC
  does **not** freeze a historical FX rate independently of WCS — it respects
  amounts WCS considers authoritative for the subscription and avoids applying
  a fresh base→active conversion pass during renewal generation. If the parent
  order lacks UMC snapshot meta (legacy), fall back to subscription currency
  with current stored rate for that currency (same as order-pay historical path).
  **This policy requires E3 validation before Integrated status; E2 evidence
  covers browsing-currency isolation only.**
- **Signup fee vs recurring:** Signup fees are converted at initial checkout
  through product price hooks. Recurring amounts are subscription-persisted.
- **Manual vs automatic renewal:** Same monetary context rules; execution path
  differs only in payment timing.
- **Historical renewal immutability:** Completed renewal orders are immutable
  (M4/M18 order context).

### Phase B policy (implemented under E2)

1. **Initial subscription orders:** Normal UMC conversion at checkout; order
   snapshot written (schema 4 unchanged).
2. **Renewal generation:** When WCS creates a renewal order, UMC detects
   renewal context and enters order/subscription currency context, preventing
   browsing currency from altering renewal totals.
3. **Rate on renewal:** Do not apply a new base→active conversion pass to
   subscription-stored recurring amounts during renewal; use parent snapshot
   rate identity when available.

Status: **Characterized — simulated extension hooks** until E3 real WCS validation.

## Product Add-Ons — hook contract

Public WooCommerce Product Add-Ons documentation establishes:

- Add-on prices are configured in store base currency on the product
- Flat and quantity-based add-ons add to cart line totals via
  `woocommerce_product_addons_option_price_raw` and cart item data
- Percentage add-ons calculate against product/cart subtotals (already subject
  to UMC product price conversion)

**Authoritative hook contract (public docs):**

- Flat / quantity add-ons: base-authored → convert via adapter on
  `woocommerce_product_addons_option_price_raw` when amount is base-authored
- Percentage add-ons: operate on converted product totals → **Native** (no
  amount conversion on percentage itself)

Without licensed-plugin E3 validation, status remains **Characterized — simulated
extension hooks**.

## Product Bundles / Composite Products

Investigation-first. Parent/child price ownership determines adapter shape.
Implement at most one integration if single-conversion invariant holds.

## Bookings

Audit-first. Defer full adapter to M20 if pricing reconstruction is unbounded.

## Consequences

- Compatibility claims are evidence-backed and honest
- Adapters stay thin; M18 invariants preserved
- Premium extensions can promote E2 → E3 without architectural changes
- Merchants see whether validation used real plugins or simulations
- Persistence remains frozen unless escalated

## Alternatives considered

- **Support every WooCommerce extension in M19.** Rejected: unbounded scope.
- **Claim Integrated from test doubles.** Rejected: destroys Compatibility Center credibility.
- **Global fee conversion.** Rejected: ADR-0023; unsafe universal assumption.
- **Subscriptions renewal at current browsing rate.** Rejected: violates safe invariant.
- **Freeze original FX rate independently of WCS for all renewals.** Rejected without E3: rate policy is separate from currency policy and requires real-extension validation for Integrated status.
