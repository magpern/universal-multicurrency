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
- **Exchange-rate policy:** **Not determined at E2.** Public WCS documentation
  establishes that renewal orders copy subscription line-item amounts and that
  renewals are created through `wcs_create_renewal_order()`, but it does not
  publish a complete UMC-facing FX policy (original rate vs current stored rate
  vs WCS-authoritative stored amounts). UMC must not invent renewal-rate
  semantics without E3 real-extension validation.
- **Signup fee vs recurring:** Signup fees surface at initial checkout through
  normal product price hooks. Recurring amounts are subscription-persisted by WCS.
- **Manual vs automatic renewal:** Public docs describe timing differences only;
  monetary semantics require E3 validation before Integrated status.
- **Historical renewal immutability:** Completed renewal orders are immutable
  (M4/M18 order context).

### E2 implementation scope (what production code does)

At Characterized E2, UMC implements **one safe invariant only**:

> While renewal generation context is active, suppress browsing-currency product
> price conversion via `umc_should_convert_product_price` so Visitor Location,
> session state, cookies, and unrelated request context cannot alter amounts
> that WCS already owns.

Mechanism:

1. `ExtensionCompatibilityContext` stores a request-scoped renewal flag + currency
   code supplied by the adapter entry point.
2. `SubscriptionsAdapter` sets that context from the UMC-owned E2 test-double
   actions only (`umc_test_extension_subscriptions_renewal_start/end`).
3. `SubscriptionsAdapter` does **not** enter `OrderCurrencyContext`, read order
   snapshot meta, select exchange rates, or rewrite WCS subscription totals.

Real WCS hook registration (`wcs_renewal_order_created` /
`woocommerce_subscriptions_renewal_order_created`, etc.) is **deferred to E3**
because public documentation does not establish a verified *before-creation*
hook contract sufficient to bracket renewal generation for browsing isolation,
and the documented post-creation filter is too late to suppress conversion during
order assembly.

### Renewal FX / rate policy (explicitly pending E3)

UMC does **not** implement any of the following at E2:

- Parent-order snapshot rate identity selection during renewal generation
- Legacy-subscription fallback to current stored rate for subscription currency
- Freezing an independent historical FX rate separate from WCS stored amounts
- Re-converting subscription-stored recurring amounts with the current browsing rate

These remain architectural questions requiring E3 real WCS validation. Until
then, WCS/subscription monetary values remain authoritative; UMC only prevents
accidental browsing-currency interference on product price hooks during the
characterized renewal context window.

Status: **Characterized — simulated extension hooks** until E3 real WCS validation.

## Product Add-Ons — hook contract

Official WooCommerce.com Product Add-Ons merchant documentation describes add-on
price types (flat fee, quantity-based, percentage-based) and display filters
(`woocommerce_addons_add_cart_price_to_value`, etc.) but **does not publish a
developer filter reference** naming a raw option-price conversion hook.

Third-party/community sources cite `woocommerce_product_addons_option_price_raw`
in plugin templates, but that hook is **unverified at E2** and is not registered
by UMC production code.

### E2 implementation scope

- UMC generic seam: `umc_test_extension_product_addons_price_raw` (test double)
- Opt-in filter: `umc_convert_product_addon_price`
- Percentage add-ons: if they operate on already-converted product totals, no
  separate amount conversion is applied by this adapter (Native behaviour when
  verified at E3)

Without licensed-plugin E3 validation, status remains **Characterized — simulated
extension hooks**.

## Product Bundles / Composite Products

Official WooCommerce Product Bundles filters reference documents display and
cart-configuration hooks (e.g. `woocommerce_bundled_item_price_html`) but **no
authoritative raw bundled-item price conversion filter** suitable for a single
base→active conversion seam.

### E2 implementation scope

- UMC generic seam: `umc_test_extension_bundled_item_price` (test double)
- Opt-in filter: `umc_convert_bundled_item_price`
- Parent/child price ownership and real extension hook timing require E3

Composite Products: investigation deferred; **Not evaluated (E0)**.

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
