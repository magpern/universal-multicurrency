# ADR-0018: Visitor Location boundary alignment with Universal Geo Context

**Status:** Accepted (Milestone 14, target v0.13.0)

**Supersedes:** the M14 ("editable provider UI") and M15 ("diagnostics from
resolution traces") plans recorded in
[ADR-0017](0017-geocontext-admin-hub.md)'s Consequences section.

## Context

Universal Geo Context ("UGC") is a separate, independently-installed
WordPress plugin that has matured into the authority for visitor-location
facts: provider chains, trusted proxies, IP resolution, country simulation,
provider health, a detection inspector, diagnostics, and caching. It exposes
a small frozen public API (six global functions in `src/api.php`, gated by
`function_exists()` and `universal_geo_api_version() >= 1`) that this plugin
already consumed, minimally, via `Geo\UniversalGeoContextAdapter`.

ADR-0017 (M13, v0.12.0) built a 7-panel Visitor Location admin hub —
Overview, Detection, Geo Sandbox, Providers, Trusted Proxies, Diagnostics,
Settings — and its Consequences section explicitly planned to *extend* three
of those panels in future milestones: "M14 can extend `providers` and
editable provider UI", "M15 can populate Diagnostics from resolution
traces". Repository inspection ahead of this milestone found that plan
would have made this plugin a second geo-detection platform, duplicating
work UGC already does — while three of the seven panels (Providers, Trusted
Proxies, Diagnostics) were, in practice, read-only stubs pointing at UGC
anyway.

## Decision

### The boundary

Universal Geo Context answers *"What geographic facts are known about this
visitor?"* Universal Multicurrency answers *"Given those facts and store
policy, which currency should WooCommerce use?"* This plugin must never
re-implement location detection; UGC must never decide currency. This was
already true in the runtime code (`Geo\CountryContextResolver` +
`Geo\UniversalGeoContextAdapter` + `Geo\WooCommerceFallbackProvider`) before
this milestone — M14 aligns the *admin surface* and the *documented
trajectory* with that boundary, and formally retires the M14/M15 plans
above.

### Consumed UGC contract (frozen, unchanged by this milestone)

- The six global functions (`universal_geo_get_context()`,
  `universal_geo_get_country_code()`, `universal_geo_get_region_code()`,
  `universal_geo_get_source()`, `universal_geo_get_confidence()`,
  `universal_geo_api_version()`), feature-detected via `function_exists()`.
- Feature detection only: `api_version() >= 1`. No UGC version string is
  ever compared — `UNIVERSAL_GEO_VERSION` is read once, for display only, in
  the incompatible/misconfigured state.
- Timing: UGC boots at `plugins_loaded` priority 10; this plugin's provider
  chain is built at `woocommerce_init`, already safely after that.
- UGC's admin page slugs (`AdminPageSlugs`, e.g.
  `universal-geo-context-detection`) are documented as stable but are a
  *soft* contract, not part of UGC's frozen API — every deep link this
  plugin builds is capability-gated (`manage_options`) and tolerates UGC
  being absent, outdated, or removed.
- No import of any `@internal` UGC class. No new UGC API is requested by
  this milestone — the existing six functions are sufficient for everything
  M14 does (see `Geo\UgcIntegrationStatus`).

### `UgcIntegrationStatus`: one source of truth for availability

Before M14, three call sites independently reimplemented the availability
check, and two of them omitted the `api_version` gate entirely (an
incompatible UGC build would have been reported "Available"). `Geo\
UgcIntegrationStatus` is now the sole authority: `Geo\
UniversalGeoContextAdapter::is_available()` delegates to it, and every
admin surface (Overview, Currency Routing provider card, the Site Health
`umc_geo_configuration` test) reads through it too.

### Visitor Location hub: three panels, not seven

The hub shrinks to **Overview**, **Currency Routing**, **Currency
Simulation**. Provider configuration, trusted proxies, and geo diagnostics
were never genuinely owned by this plugin (Providers was two read-only
cards and a "coming soon" notice; Trusted Proxies was a single placeholder;
Diagnostics was a rule-count stub) — that content is removed in favor of a
deep link into UGC. The standalone Settings panel folds into Currency
Routing, which becomes this plugin's single saveable Visitor Location
panel. Panel *ids* (`detection`, `sandbox`) are unchanged for URL
stability even though their labels changed; the four retired panel ids
(`providers`, `proxies`, `diagnostics`, `settings`) redirect via
`Admin\Geo\GeoLegacyPanelRedirect`, kept for at least two minor releases
(through v0.15.0).

### GeoContext schema v2

`Geo\GeoContext`'s `network` and `providers` subtrees were reserved
scaffolding for the now-retired provider-chain-simulation direction. They
are removed in schema v2; `GeoContextSerializer::decode()` discards a
stored document from a prior schema version rather than reviving those
fields. This is safe because the sandbox result is an ephemeral per-user
cache (`umc_geo_sandbox_last_result` user meta), never merchant
configuration — no migration, no data loss of anything a merchant
configured.

### What does not change

- Settings keys and `Settings::SCHEMA_VERSION` (still 5) — every `geo.*`
  key remains consumed under its current name; this is a presentation and
  information-architecture change only.
- The runtime provider chain, `Geo\GeoDetectionApplicator`'s gate order,
  `CurrencySwitcher` persistence semantics, and the currency precedence
  ladder (ADR-0016) — all storefront behavior is frozen.
- The rule engine (`GeoCurrencyRuleEvaluator`, `GeoRoutingRuleValidator`,
  `GeoRegionRegistry`, `RecommendedGeoRules`) — routing policy is a
  currency-domain decision and stays in this plugin regardless of where
  location facts come from.
- `WooCommerceFallbackProvider` — retained exactly as before. This plugin
  remains fully functional without Universal Geo Context installed,
  degrading to WooCommerce checkout country / geolocation, then to the
  configured technical fallback currency, then to store base.

## Consequences

- No settings migration, no schema bump, no UGC release, and no new public
  API on either plugin. Rollback to v0.12.1 is safe at any point.
- Future currency-routing condition types beyond country/region (e.g.
  language, customer role, campaign, date window, store channel, custom
  attributes) are UMC currency-policy scope if introduced later — but any
  condition that is itself a *location fact* (region, city, timezone) must
  be sourced from UGC's public API, never independently detected here. The
  Currency Routing UI's "Condition"/"Result" vocabulary (M14) was chosen so
  such an addition would not require a page redesign; no such condition
  type is implemented by this milestone.
- Site Health's `umc_geo_configuration` test gains a `recommended` status
  (enabled, WooCommerce fallback allowed, UGC absent) alongside its
  existing `critical` status (enabled, fallback disabled, no provider at
  all) — both now correctly gated on `UgcIntegrationStatus`, closing the
  incompatible-build gap above.
- Two previously-undocumented user-meta keys
  (`umc_geo_sandbox_last_result`, `umc_geo_sandbox_recent`) were added to
  `PersistedKeys` and `docs/PERSISTED_DATA.md` as part of this milestone —
  an inventory-accuracy fix unrelated to the boundary change itself,
  discovered while touching the sandbox code. Classified as preserved on
  uninstall, matching `uninstall.php`'s existing behavior (no code change).

## Related

- [ADR-0016](0016-geo-detection-ordered-routing.md) — the routing engine and
  currency precedence ladder this milestone does not change.
- [ADR-0017](0017-geocontext-admin-hub.md) — the admin hub this milestone
  simplifies; its M14/M15 Consequences are superseded by this document.
- [`docs/GEO_DETECTION.md`](../GEO_DETECTION.md) — administrator guide,
  rewritten for the three-panel hub.
- [`docs/PERSISTED_DATA.md`](../PERSISTED_DATA.md) — updated user-meta
  inventory.
