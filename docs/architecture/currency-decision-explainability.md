# Currency Decision Explainability (Milestone 15)

**Status:** Authoritative implementation specification for Milestone 15
(**v0.14.0**).

**Branch:** `feature/m15-currency-explainability`

**ADR:** [ADR-0020](../adr/0020-currency-decision-explainability.md)

This document materializes the approved M15 plan plus mandatory amendments.
Production implementation must follow this specification. Working drafts under
untracked `docs/plans/` are not source of truth (`ReleaseAuditTest` forbids
tracked `docs/plans/`).

---

## 1. Product objective

Universal Multicurrency must answer, clearly and deterministically:

> Why is this shopper using this currency?

and, where checkout policy participates:

> What currency will this shopper actually pay in, and why?

Merchants must not reconstruct the answer manually from settings, cookies,
sessions, Visitor Location rules, rates, and gateway policy.

**Product surface:** Decision Inspector (admin settings section).

---

## 2. Baseline

| Item | Value |
|---|---|
| Prior release | M14 / **v0.13.0** |
| Baseline commit | `037f9e13ee5c28915ba2da29d60eec6aa94dce70` |
| Settings schema | **5** (unchanged by M15) |
| DB migration | None |
| Naming note | ADR-0018 retired a prior “M15 diagnostics from traces” plan. This M15 is **new** explainability scope, not a revival of that cancelled work. |

---

## 3. Architectural rules (non-negotiable)

1. Runtime decision remains authoritative.
2. No second currency resolver.
3. No second geo evaluator.
4. No storefront behavior moved into an admin-only inspector.
5. No precedence redesign for explainability convenience.
6. Explainability is **on-demand** (admin); no storefront-global trace collection.
7. No decision-log tables / visitor history / Inspector result persistence.
8. `CurrencyResolutionResult.winning_source` is truthful to resolver inputs
   (`explicit` | `session` | `cookie` | `base`) — never `geo`.
9. Provenance metadata is explanatory only and never affects precedence.
10. `GeoCurrencyDecisionService` consolidation only after proven parity.

Desired relationship:

```text
Runtime decision
      ↓
Structured decision / explanation model
      ↓
Admin presentation
```

Not:

```text
Admin simulator
      ↓
Reimplemented approximation of runtime behavior
```

---

## 4. Runtime pipeline (as implemented after M14)

```text
woocommerce_init
  ├─ CurrencySwitcher::maybe_switch()     # ?currency= → persist(manual)
  ├─ GeoDetectionApplicator::maybe_apply() # gates → evaluate → persist(non-manual)
  └─ later reads:
       CurrencyContext::get_active_currency()
         ├─ effective_override? (checkout) → Currency
         └─ else CurrencyResolver(explicit, session, cookie, base, selectable)
```

**Critical fact:** Visitor Location does **not** enter `CurrencyResolver` as a
candidate. Eligible geo writes currency via `CurrencySwitcher::persist($code)`
(non-manual) into session (and cookie if Display “remember” is on). Later
resolution sees **session**.

ADR-0016’s prose ladder listing geo as step 5 describes policy intent; runtime
geo is a **side-channel write** (confirmed by ADR-0019 removing the dead
`$geo` parameter from the resolver).

### Active display precedence

| Order | Layer | Mechanism |
|---|---|---|
| 0 | Order-owned context | Skips geo / normal shopper flow when active |
| 1 | Checkout effective override | Request-scoped; does not rewrite shopper preference |
| 2 | Explicit `?currency=` | Query var `currency` |
| 3 | WC session `umc_currency` | May be switcher **or** geo write |
| 4 | Cookie `umc_currency` | If Display “remember” enabled |
| 5 | Store base | Always selectable fallback |

### Geo application gates (`GeoDetectionApplicator`)

Geo disabled → non-convertible → order context → order-pay/received →
mode/session flags → `has_valid_shopper_currency_source()` →
`umc_manual_currency` → checkout lock → country resolve → rule evaluate →
persist.

---

## 5. Structured shopper resolution result

Introduce `CurrencyResolutionResult` (immutable), produced by
`CurrencyResolver::evaluate(...)`.

`CurrencyResolver::resolve(...)` remains a thin wrapper:

```php
return $this->evaluate(...)->currency();
```

Public `resolve()` signature and outcomes must not change.

### Result contents

- Resolved currency code
- Winning source: `explicit` | `session` | `cookie` | `base`
- Ordered candidate evaluations (source, raw, normalized, status, reject reason)
- Whether base fallback was used

**Do not** add `geo` or `checkout` as resolver sources.

Parity invariant:

```text
resolve(...) === evaluate(...)->currency()
```

---

## 6. Provenance metadata (approved amendment)

### Purpose

Allow the Decision Inspector to distinguish, for example, a session currency
written by Visitor Location from a session currency written by customer
selection — **without** changing which currency wins.

### Contract

- Explanatory metadata only.
- Must not participate in currency precedence.
- Must not alter which currency wins.
- Must not become a second resolver input.
- Must not be trusted over actual runtime currency state.
- Cleared/updated consistently when the persisted shopper currency changes.

### Write-path inventory (implementation must verify and finalize)

Production writers of persisted shopper currency (`umc_currency` session and/or
cookie) known at planning time:

| Writer | Path | Manual flag | Expected origin concept |
|---|---|---|---|
| `CurrencySwitcher::persist($code, true)` | `?currency=` switch | yes | customer / manual selection |
| `CurrencySwitcher::persist($code, false)` | Geo applicator success / technical fallback persist | no | visitor location / geo |
| Display / shortcode / blocks | Delegate to switcher (verify) | typically manual | customer / manual |

Checkout effective override does **not** rewrite shopper preference persistence
and is **not** a provenance writer for `umc_currency`.

### Value set (final)

| Session value | Constant | Production writer |
|---|---|---|
| `customer` | `CurrencySwitcher::ORIGIN_CUSTOMER` | `persist( $code, true )` (`?currency=` / manual) |
| `visitor_location` | `CurrencySwitcher::ORIGIN_VISITOR_LOCATION` | `persist( $code, false )` (geo applicator) |

Key: `umc_currency_origin` (`CurrencySwitcher::SESSION_CURRENCY_ORIGIN`).

No other production writers of `umc_currency` exist outside `CurrencySwitcher::persist()`.

### Truthful explanation example

```text
winning_source = session
session currency origin = Visitor Location
```

Not:

```text
winning_source = geo
```

---

## 7. Explanation model

Preferred package: `UMC\Decision\`.

Immutable structured types (names follow repo conventions):

- `CurrencyDecisionExplanation`
- `ExplanationStage`
- supporting value objects / constants as needed

Domain objects carry **codes and structured payloads**, not merchant prose.
Presentation labels belong in admin renderers.

### Stage statuses (use only what is needed)

`won` | `considered` | `skipped` | `blocked` | `info`

### Stages (include only when applicable)

| Stage id | Role |
|---|---|
| `order_context` | Order-owned context active / inactive |
| `shopper_selection` | `CurrencyResolutionResult` + manual flag + provenance |
| `visitor_location` | Geo skip / evaluation; **candidate vs won** |
| `display_currency` | Final browse/display code |
| `checkout_policy` | Mode, effective, settlement, transition reason |
| `gateway_compatibility` | Support summary (no secrets) |
| `customer_notice` | Whether notice would apply |

Critical distinction: **candidate produced ≠ candidate won**.

Example: Visitor Location produces EUR, but customer manual USD wins — explain
both facts.

---

## 8. CurrencyDecisionExplainer

On-demand composer service:

- Consumes existing pure/runtime results (resolution, provenance, geo simulate/
  evaluate, checkout decision, gateway evaluation, notice eligibility).
- Never reimplements precedence.
- Never mutates shopper state.
- Avoids unnecessary duplicate provider calls.
- Produces `CurrencyDecisionExplanation`.

Storefront does not build full explanations on every request.

---

## 9. Conditional GeoCurrencyDecisionService consolidation

Required sequence:

1. Characterize `GeoCurrencyDecisionService` shopper path and skip reasons.
2. Characterize `CurrencyResolver`.
3. Prove behavioral equivalence for shared inputs (normalization, selectability,
   base-as-explicit edge cases, skip-reason semantics).
4. Consolidate **only** if equivalence is demonstrated.

If parity cannot be shown cleanly:

- leave runtime path intact;
- share a lower-level pure evaluator only if identical; or
- let the explainer compose both existing results.

Acceptance criterion is **explainability parity**, not maximum deduplication.

If a real bug is discovered: **STOP** and report separately — do not fold a
policy change into M15 silently.

---

## 10. Visitor Location explanation

Compose from existing M12–M14 services:

- Provider availability (`UgcIntegrationStatus`)
- Normalized country + source category
- Matched rule / index / type / target currency
- Other vs technical fallback / warnings / trace
- Skip reason when geo did not apply
- Whether geo participated vs actually won
- Provenance when explaining a live session currency

Merchant labels (not internal names):

| Internal | Merchant-facing |
|---|---|
| `explicit` / manual persist | Customer selection |
| `session` (generic) | Saved customer currency |
| provenance geo | Visitor Location |
| `cookie` | Remembered currency |
| `base` | Store default |
| checkout decision | Checkout policy |
| gateway evaluation | Payment gateway |
| `umc_manual_currency` | (do not show raw key) |
| `GeoDetectionApplicator` | (do not show class name) |

---

## 11. Checkout explanation

Compose from existing M11 infrastructure without changing policy:

- Display vs effective vs settlement currency
- Checkout mode
- Transition required? reason codes → merchant labels
- Fallback occurrence
- Gateway support summary
- Recalculation implication (describe existing behavior)
- Customer notice applicability

If notice eligibility is not a pure query, add the smallest non-mutating
adapter rather than duplicating notice policy.

Reuse: `CheckoutCurrencyDecision`, coordinator/providers,
`GatewayCurrencyEvaluation`, `CheckoutTransitionState`, `CheckoutNoticeService`.

---

## 12. Decision Inspector

### Placement (approved)

Dedicated settings section **after Checkout, before Compatibility**:

Currencies → Exchange Rates → Visitor Location → Display → Checkout →
**Decision Inspector** → Compatibility → Advanced

### Modes

- **Simulation (primary, stateless):** deterministic admin inputs → explanation
  rendered in the response. **No** user-meta persistence of Inspector results.
- **Context assist (optional secondary):** prefill from settings / optional live
  country via existing safe paths; never show IP; never mutate real shopper state.

### Relationship to Currency Simulation

- Keep Visitor Location **Currency Simulation** as the geo-focused what-if tool.
- Decision Inspector **composes** shopper + geo + checkout via shared services.
- Do not place Inspector inside Visitor Location.
- Quick Actions link Simulation ↔ Inspector where helpful.

### Persistence (rejected for M15)

Do **not** add:

- saved Inspector scenarios
- admin simulation history
- visitor decision history
- decision-log tables

Existing sandbox user-meta keys remain untouched.

### Inputs (deterministic)

Country; explicit / session / cookie currencies; manual-selection state;
geo enabled; checkout mode; checkout locked; gateway selection or admin-only
support snapshot DTO when needed for deterministic tests.

### Security

Capability + nonce (follow `GeoSandboxController` `admin_post` pattern).
Sanitize/validate all inputs. Side-effect-free. No secrets / raw gateway
internals / IPs / session IDs / verbatim cookies.

---

## 13. Admin design system

Reuse `AdminComponentRenderer`, `AdminPageShell`, `SectionNavigation`.

Add a reusable Decision Timeline component only if it clearly fits
(`decision_timeline()` or `DecisionTimelineRenderer`). Extend
`assets/admin/umc-settings.css` consistently — no page-specific mini-framework.

Accessibility: semantic ordered sequence; text status plus badges; keyboard
controls; meaningful headings; no color-only meaning.

---

## 14. Diagnostics vs Inspector

| Surface | Question |
|---|---|
| Compatibility | Is the system healthy? |
| Decision Inspector | Why did this decision happen? |

Quick Actions may deep-link to Compatibility, Currency Routing, Currency
Simulation, and UGC when the explanation detects configuration/provider issues.
Do not duplicate diagnostic content.

---

## 15. Performance

- No storefront-global explanation construction.
- No extra geo / rate / gateway calls for explainability on storefront.
- Provenance write is one session set on existing persist paths (negligible).
- Inspector is admin/on-demand only.

---

## 16. Work packages

| WP | Objective |
|---|---|
| WP0 | Feature branch + this specification + ADR-0020 + ROADMAP (docs-only) |
| WP1 | Characterization tests (resolver, context, switcher, geo decision, checkout) |
| WP2 | `CurrencyResolutionResult` + `evaluate()` / `resolve()` wrapper |
| WP3 | Provenance key after write-path inventory |
| WP4 | Explanation domain + `CurrencyDecisionExplainer` |
| WP5 | Conditional geo decision service consolidation (parity-gated) |
| WP6 | Visitor Location + checkout explanation stages |
| WP7 | Stateless Decision Inspector service + `admin_post` |
| WP8 | Admin UI section + timeline + quick actions |
| WP9 | Documentation sync + regression |
| WP10 | Prepare **v0.14.0** metadata when implementation-complete (no tag/merge/deploy) |

---

## 17. Testing invariants

1. Characterization locks pre-refactor behavior.
2. `resolve(...) === evaluate(...)->currency()`.
3. Explanation display currency equals runtime result for shared inputs.
4. Changing provenance alone cannot change resolved currency.
5. Geo simulation final currency equals explanation when checkout omitted
   (shared geo inputs).
6. Checkout explanation matches checkout decision for shared inputs.
7. Inspector POST creates no user-meta / shopper session mutation.
8. Existing storefront currency outcomes unchanged.

---

## 18. Explicit non-goals

New rate providers / margins / history / rounding; new switcher modes;
new geo rule types; `country_change`; continent expansion; new gateway policies;
subscriptions/bookings expansion; competitor migration; persistent decision
logging; deployment; UMC-owned geo provider UI/diagnostics revival; settings
schema bump.

---

## 19. Version

Target release: **v0.14.0**. No tag/release/deploy as part of feature-branch
completion unless separately requested after review.

---

## 20. Stop / escalation conditions

Stop and report if: main materially changed; characterization reveals a real
precedence bug; geo vs resolver semantics block safe consolidation and a “bug
fix” is tempting; provenance requires schema migration; Inspector requires
persisted user state; checkout explanation requires policy change; privacy
cannot be met; broad new runtime tracing appears necessary; a new DB table
appears necessary.
