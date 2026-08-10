# ADR-0019: Visitor Location spec conformance and M14 hardening scope

**Status:** Accepted (Milestone 14, v0.13.0)

## Context

An external feature specification for "Visitor Location" was reviewed against
the Universal Multicurrency codebase to assess whether the requested
capabilities were already implemented or required new work.

The specification included:
- Ordered country/region/Other currency-routing rules with first-match-wins
  semantics
- Merchant presets (Sweden→SEK, Denmark→DKK, Norway→NOK, Poland→PLN,
  UK→GBP, EU region→EUR, Other→EUR)
- Optional Universal Geo Context (UGC) adapter with graceful degradation
- Currency precedence hierarchy ("manual selection always wins")
- Deterministic domain evaluator with structured trace output
- Admin simulation/testing UI for currency outcome prediction
- Diagnostics integration
- Privacy safeguards (no raw IP persistence)
- Cache-behavior guidance
- Validation (rule deduplication, Other-rule constraints, etc.)

Audit result: **Every one of these capabilities is already implemented and
shipped**, spanning two completed milestones:

- **Milestone 12 (v0.11.0):** ordered country/region/Other rules
  (`GeoCurrencyRuleEvaluator`, `GeoRoutingRule`), presets
  (`RecommendedGeoRules`), precedence ladder (ADR-0016),
  domain validation (`GeoRoutingRuleValidator`), and the simulation/evaluation
  core (`GeoCurrencyDecisionService`).
- **Milestone 13 (v0.12.0):** admin hub (7-panel Visitor Location section),
  Currency Simulation panel (→ `simulate()`), Site Health diagnostics, and
  `GeoContext` schema v1 + serializer.
- **Milestone 14 (v0.13.0, this release):** boundary-alignment refactor
  (M13's 7 panels → 3), `UgcIntegrationStatus` consolidation, GeoContext
  schema v2 (removed reserved fields), and established documentation. This
  is not a greenfield "Visitor Location" implementation — the term refers to
  a narrower admin-information-architecture and UGC-boundary alignment
  milestone, as recorded in [ADR-0018](0018-visitor-location-boundary-alignment.md)
  and [`docs/ROADMAP.md` item 14](../ROADMAP.md).

## Decision

### No new domain code

Do not rebuild the geo-routing architecture, evaluator, presets, or provider
chain. They are correct as implemented and tested across M12/M13. Storefront
currency-decision behavior remains unchanged and is locked by characterization
tests predating this milestone's admin refactor.

### Verify the documented UGC integration surface

The specification assumed the integration entry point was
`universal_geo_get_context()`. Audit of the actual codebase confirms this
function is **never called** anywhere in the plugin's `src/` tree. The real
(and, per ADR-0018, frozen) integration surface is:

- `universal_geo_get_country_code()` — primary output consumed by geo
  detection flow
- `universal_geo_get_source()` — metadata (provider name) for reporting
- `universal_geo_get_confidence()` — metadata (confidence score) for reporting
- `universal_geo_api_version()` — feature detection gate (`api_version >= 1`)

This ADR corrects the documentation assumption; the **implementation is
correct** — see `src/Geo/UniversalGeoContextAdapter::resolve()` (L37-58).
`universal_geo_get_context()` and `universal_geo_get_region_code()` are not
part of Universal Multicurrency's documented contract with UGC.

### Close identified hardening gaps

During spec audit, four genuine, narrow engineering gaps were identified:

1. **`GeoDetectionApplicator` test coverage:** The storefront class that gates
   whether geo detection ever writes a currency has zero integration-test
   coverage despite being critical to the currency-decision flow. This
   violates `CLAUDE.md` invariant #8 ("Tests accompany every feature").
   Remediation: focused integration tests covering guard conditions
   (mode/session/checkout lock) and the successful write path.

2. **Malformed JSON in `GeoContextSerializer::decode()`:** Only the
   stale-schema-version branch is currently tested; the branch handling
   genuinely malformed JSON (`! is_array($data)`) has no coverage.
   Remediation: add a unit test case exercising that branch.

3. **Dead `$geo` parameter on `CurrencyResolver::resolve()`:** The 6th optional
   parameter `?string $geo = null` is never populated by any production caller
   (confirmed via repo-wide grep) and represents an abandoned candidate-list
   integration pattern. Remediation: remove the parameter and dead branch;
   document the reason (architecture uses `GeoDetectionApplicator` →
   `CurrencySwitcher` side-channel write, not parameter passing).

4. **Caching guidance for first-visit geo detection:** Documentation lacks
   explicit guidance on the cache-behavior risk: a full-page cache served
   before `GeoDetectionApplicator::maybe_apply()` executes could show a
   first-time visitor a currency computed for a different geography.
   Remediation: documentation + advisory text only (no new cache-control
   behavior); guidance on how merchants should configure page-cache exclusions
   or cookie/session variation.

## Consequences

- M12/M13 implementation is the authoritative Visitor Location feature
  delivery. This ADR and the accompanying hardening work (WP2–WP4) are
  conformance validation and cleanup, not new capability.
- No settings-schema version bump, no DB migration, no storefront
  currency-resolution behavior change, no new dependency or UGC hard-link.
  `v0.13.0` remains a safe in-place upgrade from `v0.12.1`.
- The four hardening gaps (GeoDetectionApplicator coverage, malformed JSON,
  dead parameter, caching guidance) are addressed as standalone commits
  in the M14 release but are not version-gating or release-blocking —
  they are pre-existing code quality/documentation gaps discovered during
  audit, not regressions.
- Documentation (`docs/ROADMAP.md`, `docs/GEO_DETECTION.md`) is updated
  post-implementation with the final commit hashes for traceability.
- Future work that touches geo-location facts must source them from UGC's
  public API; future work that adds new currency-routing conditions (beyond
  country/region) should follow the Condition/Result vocabulary established
  in M14's admin UI redesign but must not duplicate geo-detection logic.
- The external spec's requirements are met by existing shipped code; this
  ADR records that fact to prevent silent re-litigations or re-implementations
  of M12/M13 work in later milestones.

## Related

- [ADR-0016](0016-geo-detection-ordered-routing.md) — the geo-routing engine,
  precedence ladder, and validation that M14 does not change.
- [ADR-0018](0018-visitor-location-boundary-alignment.md) — the boundary
  decision between this plugin's currency policy and UGC's geo facts.
- [`docs/GEO_DETECTION.md`](../GEO_DETECTION.md) — administrator guide
  covering the full Visitor Location feature as implemented across M12/M13/M14.
- [`docs/ROADMAP.md`](../ROADMAP.md) item 14 — milestone tracking including
  post-release hardening scope for WP2–WP4.
