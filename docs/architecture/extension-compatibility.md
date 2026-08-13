# Third-Party Extension Compatibility Architecture

**Milestone:** 19 · **Target:** v0.18.0 · **Baseline:** ADR-0023 / v0.17.0

This document is the authoritative M19 specification for integrating WooCommerce
third-party extensions with Universal Multicurrency (UMC). It extends — never
replaces — the M18 transaction-integrity contract.

## Purpose

UMC must answer three questions for any extension:

1. Does it work natively with UMC?
2. Does it require a UMC adapter?
3. Is it incompatible or only partially supported?

M19 establishes a maintainable framework and applies it to a bounded set of
priority integrations. It does **not** attempt to support every WooCommerce
extension.

## Architectural boundaries

### What adapters may do

Adapters bridge extension-specific monetary inputs into **existing UMC seams**:

| Seam | Service |
|---|---|
| Product / variation unit prices | `PriceHooks` via `PriceConversionService` |
| Fixed coupons + thresholds | `CouponConversion` |
| Core / opt-in shipping costs | `ShippingConversion` |
| Opt-in fees | `FeeConversion` |
| Gateway availability | `GatewayCompatibility` via `GatewayCurrencyClassifier` |
| Historical order display | `OrderCurrencyContext` |

### What adapters must never do

Adapters must **not** implement:

- A second `CurrencyResolver`
- A second `Converter` or independent exchange-rate lookup
- Checkout policy or Visitor Location logic
- Automatic fee conversion globally
- Automatic third-party shipping conversion without opt-in

Conversion code under `src/Integration/` and `src/PriceHooks.php` must not
reference third-party extension classes. Detection and adapters live under
`src/Compatibility/Extension/`.

### M18 invariants (unchanged)

- One monetary context per WooCommerce calculation (ADR-0023)
- No double conversion
- WooCommerce owns tax, discount, and rounding mechanics
- Fees pass-through unless explicitly opted into `umc_convert_fee`
- Third-party shipping pass-through unless explicitly opted into `umc_convert_shipping_rate`
- No live FX HTTP in storefront monetary paths

## Compatibility statuses

| Status | Meaning |
|---|---|
| **Native** | Extension works through normal WooCommerce hooks; no adapter needed |
| **Integrated** | UMC adapter + **E3 real-extension validation** only |
| **Characterized** | Tested behaviour known (E1 or E2); not a full support claim |
| **Known limitation** | Works with documented constraints |
| **Incompatible** | Reproduced failure |
| **Not evaluated** | Default — no evidence |

### Characterized merchant-visible sub-labels

| Evidence tier | Primary status line |
|---|---|
| E1 | Characterized — contract tests |
| E2 | Characterized — simulated extension hooks |
| E3 | Integrated — real extension validated |

E1 and E2 can **never** produce `Integrated`.

## Evidence tiers

| Tier | Source | Max status |
|---|---|---|
| E0 | No evidence | Not evaluated |
| E1 | Unit contract tests with fake interfaces | Characterized — contract tests |
| E2 | UMC-owned hook-semantics test-double plugins | Characterized — simulated extension hooks |
| E3 | Real licensed extension integration tests | Integrated (or Known limitation / Incompatible) |

**Critical rule:** Do not claim an extension as Integrated solely because a fake
implementation passes. For a named premium extension, either run integration
tests against the real extension in an authorized environment, or label it
Characterized with an honest sub-label.

Promotion E2 → E3 is metadata + test addition only; no adapter redesign.

## Detection flow

```
Extension installed?
        ↓
ExtensionDetector (safe probes, no autoload fatals)
        ↓
ExtensionCompatibilityRegistry
        ↓
Optional adapter (if registered + extension active)
        ↓
Existing UMC services
```

Detection resolves **once per request** (memoized). No extension detection in
product-price hot paths.

### Safe detection rules

- `class_exists( $name, false )` only
- `defined()` / `function_exists()` for constants and functions
- Plugin header version reads from WordPress plugin registry
- No hard Composer dependencies on premium extensions
- No fatal references when extension absent

## Fee boundary (M19)

Fees remain **pass-through by default**. `FeeConversion` wires the documented
`umc_convert_fee` filter:

- Default: `false` — fee untouched
- Extension explicitly opts in per fee when amount is base-authored
- UMC converts once through `PriceConversionService`
- Arbitrary WooCommerce fees remain untouched

## Shipping boundary (M19 formalization)

Existing M18 behaviour is the contract:

- Core methods (`flat_rate`, `free_shipping`, `local_pickup`): convert by default
- Third-party rates: pass-through unless `umc_convert_shipping_rate` opts in
- Do not assume all shipping plugins use base-authored semantics

## Dynamic pricing boundary

UMC converts **base-authored unit prices**. Extensions that modify the current
WooCommerce price must either:

1. Run before the conversion seam and supply base-authored amounts, or
2. Deliberately return active-currency amounts (UMC must not convert again)

No specific third-party dynamic-pricing plugin adapters in M19.

## Gateway compatibility

M11 generic gateway currency capability policy remains authoritative. M19 validates
representative gateways against `GatewayCurrencyClassifier` — no Stripe/PayPal-specific
monetary engines. Prefer `umc_gateway_supported_currencies` metadata over
gateway-name conditionals.

## Priority integrations

### WooCommerce Subscriptions

Highest priority. Recurring monetary behaviour requires explicit characterization
before policy selection. See § Subscriptions monetary contract below and
ADR-0024 § WooCommerce Subscriptions.

### Product Add-Ons

Named integration requires an **authoritative hook/monetary contract** from public
documentation or licensed-plugin inspection. Without confident contract
establishment, ship generic adapter seam + hook-double only.

### Product Bundles / Composite Products

Investigation package. At most one adapter if single-conversion path is proven.
Avoid double conversion where parent and children pass through product price filters.

### Bookings

Audit-first. Implement only if scope remains bounded; otherwise Not evaluated + M20 deferral.

## Subscriptions monetary contract (Phase A characterization dimensions)

Before adapter implementation, characterize:

1. **Subscription currency identity** — how WCS stores/exposes subscription currency
2. **Renewal currency identity** — which currency a renewal order charges
3. **Exchange-rate policy** — original rate vs current stored rate vs WCS-authoritative value (**separate from currency identity**)
4. **Signup fee vs recurring amount** — distinct surfaces and hook timing
5. **Manual vs automatic renewal** — whether monetary semantics differ
6. **Historical renewal immutability** — whether past renewal orders are fixed

### Safe pre-characterization invariant

> Browsing currency, Visitor Location, and shopper session state must never
> accidentally determine an existing subscription's renewal currency.

This invariant does **not** predetermine exchange-rate policy.

### Rate policy

Freezing the original exchange rate for every future renewal is a consequential
commercial decision. M19 documents the chosen policy after characterization;
it is not assumed.

## Persistence stop gate

Expected contracts remain frozen unless explicitly escalated:

| Contract | Value |
|---|---|
| Settings schema | **6** |
| PersistedKeys inventory | **8** |
| Order snapshot schema | **4** |
| DB migration | **none** |

If correct implementation requires a new persisted monetary key, subscription
snapshot field, Settings schema change, or DB migration: **STOP**, document the
requireity, and seek architectural review before implementing.

## Unsupported-version behaviour

When detected version > tested-through version:

- Plugin continues operating unless actual incompatibility exists
- Compatibility Center shows **Untested version** warning
- Status does not falsely claim verified support for the newer version

## Version matrix strategy

For each integration, target:

- Oldest explicitly supported version (when known)
- Current tested-through version
- Newest available licensed version (opportunistic E3)

Prefer capability detection over version checks. Version checks only for known
API differences.

## Performance constraints

Compatibility adapters must not cause:

- Repeated extension detection in price hot paths
- Repeated plugin-version reads per product getter
- Additional FX calls
- Nested double conversion
- Cart-wide scans for every product getter

## Security

Third-party integrations must never directly trust:

- Extension request parameters
- Order metadata without validation through WooCommerce/extension APIs
- Serialized plugin data

No new remote network integrations in M19.

## Inventory: existing seams → M19 extensions

| Existing seam | M19 use |
|---|---|
| `IntegrationRegistry` | Passive detection metadata (legacy Compatibility Center) |
| `ExtensionCompatibilityRegistry` | M19 extension catalog + evidence |
| `umc_convert_shipping_rate` | Third-party shipping opt-in |
| `umc_convert_fee` | Third-party fee opt-in (wired in M19) |
| `umc_gateway_supported_currencies` | Gateway capability policy |
| `OrderCurrencyContext` | Renewal / historical order surfaces |
| `OrderSnapshot` (schema 4) | Order audit meta — no M19 expansion without stop gate |

## Related documents

- [ADR-0024: Third-Party Extension Compatibility Contract](../adr/0024-third-party-extension-compatibility-contract.md)
- [ADR-0023: WooCommerce Transaction Integrity Contract](../adr/0023-woocommerce-transaction-integrity-contract.md)
- [COMPATIBILITY.md](../COMPATIBILITY.md)
- [HOOKS.md](../HOOKS.md)
- [TEST_STRATEGY.md](../TEST_STRATEGY.md)
