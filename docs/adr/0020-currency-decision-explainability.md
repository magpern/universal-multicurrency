# ADR-0020: Currency Decision Explainability

**Status:** Accepted (Milestone 15, target v0.14.0)

**Related:** [ADR-0016](0016-geo-detection-ordered-routing.md) (precedence),
[ADR-0018](0018-visitor-location-boundary-alignment.md) (retired prior M15),
[ADR-0019](0019-visitor-location-spec-conformance.md),
[`docs/architecture/currency-decision-explainability.md`](../architecture/currency-decision-explainability.md)

## Context

Merchants need a deterministic answer to:

> Why is this shopper using this currency?

and, where checkout policy participates:

> What currency will this shopper actually pay in, and why?

After M12–M14 the plugin already has pure shopper resolution
(`CurrencyResolver`), Visitor Location evaluation traces
(`GeoRuleEvaluationResult`), simulation (`GeoCurrencyDecisionService::simulate()`),
and checkout policy decisions (`CheckoutCurrencyDecision`). None of these are
composed into a single merchant-facing explanation.

ADR-0018 retired an earlier “M15 diagnostics from GeoContext resolution
traces” plan. This ADR defines a **new** Milestone 15 focused on currency
decision explainability — not a revival of UMC-owned geo Diagnostics.

## Decision

### Runtime remains authoritative

Storefront currency outcomes continue to be decided by the existing pipeline:

1. `CurrencySwitcher` / session / cookie / explicit query
2. `GeoDetectionApplicator` (policy-gated side-channel persist)
3. `CurrencyContext` + `CurrencyResolver`
4. Checkout effective override via `CheckoutPolicyCoordinator`

Explainability **derives from** those decisions. It must not become a parallel
decision engine.

### No second resolver / no second geo evaluator

- Do not create a second currency resolver.
- Do not create a second geo rule evaluator.
- Do not move storefront behavior into an admin-only inspector.
- Do not redesign precedence to make explanation easier.

### Truthful source model

`CurrencyResolutionResult` describes what `CurrencyResolver` actually sees:

| Resolver input | `winning_source` value |
|---|---|
| Explicit `?currency=` | `explicit` |
| Session `umc_currency` | `session` |
| Cookie `umc_currency` | `cookie` |
| None valid | `base` |

If Visitor Location previously wrote EUR into the session, the resolver’s
winning source is still **`session`**, not `geo`.

The explanation layer may additionally report:

> session currency origin = Visitor Location

using provenance metadata (below). That origin is explanatory only.

### Provenance is explanatory metadata only

A session key records how the current persisted shopper currency was written
(for example customer/manual selection vs Visitor Location). Contract:

- Provenance **MUST NOT** participate in currency precedence.
- Provenance **MUST NOT** alter which currency wins.
- Provenance **MUST NOT** become a second resolver input.
- Provenance **MUST NOT** be trusted over actual runtime currency state.
- Provenance is cleared/updated whenever the persisted shopper currency is
  written or cleared through production write paths.

Exact key name and value set are defined in the architecture specification and
`PersistedKeys` / `docs/PERSISTED_DATA.md` after write-path inventory.

### On-demand explainability

- Explanation objects are built **on demand** (admin Decision Inspector).
- No storefront-global trace collection.
- No duplicate live geo / rate / gateway calls solely for explainability.
- No decision-log database tables.
- No visitor decision history.

### Decision Inspector is stateless in M15

Simulation POST may render an explanation in the response flow. M15 does
**not** persist Inspector results in user meta, saved scenarios, or history.
Existing Visitor Location Currency Simulation persistence remains untouched.

### Admin information architecture

Decision Inspector is a dedicated Multicurrency settings section:

Currencies → Exchange Rates → Visitor Location → Display → Checkout →
**Decision Inspector** → Compatibility → Advanced

- Not inside Visitor Location.
- Not under Advanced.
- No new top-level WordPress menu.

### Surface boundaries

| Surface | Answers |
|---|---|
| Compatibility | Is the system configured and operating correctly? |
| Decision Inspector | Why did this particular currency decision occur? |
| Currency Simulation (Visitor Location) | What-if geo routing for a supplied country/shopper flags? |

Inspector may Quick-Action link to Compatibility / Simulation / Currency
Routing / UGC; it must not duplicate diagnostic content.

### GeoCurrencyDecisionService consolidation is conditional

`GeoCurrencyDecisionService` currently contains private shopper-resolution
logic that resembles `CurrencyResolver`. Consolidation is allowed **only**
after characterization proves behavioral equivalence for shared inputs.

If exact parity cannot be shown cleanly:

- leave the runtime path intact;
- share a lower-level pure evaluator only if behavior remains identical; or
- let the explainer compose both existing results.

Explainability is not a justification for changing runtime behavior.

### Settings / schema

No `Settings::SCHEMA_VERSION` bump. No database migration.

## Consequences

- New domain types: structured resolution result, explanation stages, explainer.
- Lightweight provenance session key + inventory documentation.
- Admin Decision Inspector UI using the existing Admin Design System.
- Characterization tests before refactors; runtime/explanation parity tests
  as an M15 invariant.
- Version target: **v0.14.0**.

## Related documentation

- [`docs/architecture/currency-decision-explainability.md`](../architecture/currency-decision-explainability.md)
- [`docs/ROADMAP.md`](../ROADMAP.md) item 15
- [`docs/PERSISTED_DATA.md`](../PERSISTED_DATA.md)
- [`docs/GEO_DETECTION.md`](../GEO_DETECTION.md)
- [`docs/ARCHITECTURE.md`](../ARCHITECTURE.md)
