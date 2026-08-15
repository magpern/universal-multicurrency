# Roadmap

0.  Tooling & bootstrap
1.  Domain model
2.  Storefront conversion
3.  Cart & checkout — **classic** cart/coupons/core-shipping/taxes/gateways +
    immutable order snapshot at creation. Fees not converted (opt-in only).
4.  Orders & refunds (**v0.4.0**) — historical order currency context (display,
    emails, admin/account rendering, order-pay, refunds); immutable formatting;
    legacy order viewing.
5.  Cart & Checkout **Blocks** (**v0.5.0**) — Store API parity: converted
    prices, coupons, core shipping, taxes and gateway availability in block
    carts and checkouts; snapshots on block orders; stored orders reported in
    their own currency. Server-side only — switching reloads the page.
6.  Compatibility (**v0.6.0**) — **shipped:**
    - Passive detection of known currency switchers with confidence grading
      (FOX/WOOCS, CURCY, WCML, YayCurrency built-in; extensible via
      `umc_conflict_detectors`)
    - Admin notices (dashboard, plugins, settings tab), per-user dismissal,
      Site Health tests, and debug section
    - Five-leg supported-version CI matrix and `docs/COMPATIBILITY.md`
    - Never auto-deactivate or modify another plugin
    **Deferred from this line (explicit non-goals):** community-submitted
    built-in detector labels without maintainer verification; automatic
    remediation of any kind.
7.  Release candidate (**v0.7.0**) — **complete and released**.

    | Milestone 7 work item | Status |
    |---|---|
    | Persisted-data inventory | **Complete** |
    | Uninstall policy (ADR-0009) | **Complete** |
    | Settings upgrade framework (schema v1, v0→v1 only) | **Complete** |
    | Merchant migration documentation | **Complete** — [`docs/MIGRATION.md`](MIGRATION.md) |
    | Translation readiness | **Complete** — [`docs/TRANSLATION.md`](TRANSLATION.md) |
    | Security audit | **Complete** — [`docs/SECURITY_REVIEW.md`](SECURITY_REVIEW.md) |
    | Performance baselines | **Complete** — [`docs/PERFORMANCE_BASELINES.md`](PERFORMANCE_BASELINES.md) |
    | Release audit | **Complete** — [`docs/RELEASE_AUDIT.md`](RELEASE_AUDIT.md), `composer release-audit` |
    | Documentation synchronization | **Complete** — `readme.txt`, aligned doc set |
    | Version bump and RC closure | **Complete** — repository prepared for **v0.7.0** |

    **Resulting release candidate:** **v0.7.0** — tagged and released; superseded
    by v0.8.0.

8.  Automatic exchange rates (**v0.8.0**) — **complete and released**.

    ### Shipped in v0.8.0

    | Milestone 8 work item | Status |
    |---|---|
    | Provider abstraction and derive-don't-persist (ADR-0010) | **Complete** |
    | Action Scheduler recurring updates (ADR-0011) | **Complete** |
    | Operational state separation (ADR-0012) | **Complete** |
    | Conditional HTTP caching (ADR-0013) | **Complete** |
    | Frankfurter provider, admin UI, Site Health | **Complete** |
    | Settings schema v2 and the v1 → v2 migration | **Complete** |

    **Released as:** **v0.8.0** (`UMC_VERSION`, plugin header, readme Stable
    tag). Git tag `v0.8.0` created and the GitHub release published.

    ### Post-release hardening (completed after v0.8.0)

    Corrective work from the Milestone 8 review, closed without a version bump:

    | Item | Status |
    |---|---|
    | Reschedule recurring updates when `rate_update_interval` changes | **Complete** — `0eee862` |
    | Bump `rate_updated_at` on merchant rate edits | **Complete** — `b826481` |
    | v1 → v2 conversion-fidelity regression test | **Complete** — `137f129` |
    | `CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES` 304 write ceiling | **Complete** — `88bfa44` |
    | Site Health rate diagnostics integration coverage | **Complete** — `045ac34` |
    | `admin_post_umc_update_rates` round-trip integration coverage | **Complete** — `045ac34` |
    | Milestone 8 documentation synchronization | **Complete** — `045ac34` |

    **Open Milestone 8 review findings: none.** See
    [`RELEASE_AUDIT.md`](RELEASE_AUDIT.md) § Post-release review findings.

    **Deferred from this line (explicit non-goals):** WP-CLI commands
    (`wp umc rates update|status|…`) — service layers are CLI-ready; a future
    milestone can add a thin command wrapper without redesign. Additional rate
    providers beyond Frankfurter; historical rate storage; per-currency
    provider selection.

    ### v0.8.1 maintenance release (prepared)

    | Item | Status |
    |---|---|
    | Version bump to **0.8.1** | **Prepared** — tag and GitHub release pending |
    | Scheduler interval reconciliation | **Shipped** — `0eee862` |
    | Merchant-edit `rate_updated_at` correction | **Shipped** — `b826481` |
    | Documentation and guard alignment | **Shipped** — `470ba45`, `7ee8e9b` |

    No settings schema change. Safe in-place upgrade from **0.8.0**.

9.  Display configurator (**v0.9.0**) — **prepared on `main`**.

    ### Shipped in v0.9.0

    | Milestone 9 work item | Status |
    |---|---|
    | Display settings configurator and AdminPageShell integration | **Prepared** |
    | Visual placement and style controls | **Prepared** |
    | Floating Side and Floating Bottom positioning | **Prepared** |
    | Manual shortcode helper and copy action | **Prepared** |
    | Live responsive preview and sticky Display save | **Prepared** |
    | Storefront switcher renderer, assets, and shortcode | **Prepared** |
    | Settings schema v3 display subtree (v2 → v3 migration) | **Prepared** — already on `main` |

    **Shipped as:** **v0.9.0** (`UMC_VERSION`, plugin header, readme Stable tag).
    Git tag **`v0.9.0`**.

    No new settings schema bump beyond schema v3 already shipped on `main`.
    Safe in-place upgrade from **0.8.x**.

10. Compatibility diagnostics center — **shipped in v0.9.1**.

    ### Shipped in v0.9.1

    | Work item | Status |
    |---|---|
    | Compatibility scan domain and grouped checks | **Shipped** |
    | Configuration, integration, theme, cache, and environment diagnostics | **Shipped** |
    | Support report with redaction and Copy Report action | **Shipped** |
    | Admin Compatibility tab replacing the placeholder | **Shipped** |

    **Prepared as:** **v0.9.1** (`UMC_VERSION`, plugin header, readme Stable tag).
    Git tag **`v0.9.1`**.

    No settings schema change. Live REST/AJAX probes remain deferred.

11. Checkout currency policy (**v0.10.0**) — **complete and released**.

    ### Shipped in v0.10.0

    | Work item | Status |
    |---|---|
    | Checkout settings schema v4 (`checkout.mode`, `checkout.show_notice`) | **Complete** |
    | Selected-currency checkout and store-currency checkout entry mode | **Complete** |
    | Causality-proven gateway fallback to store currency | **Complete** |
    | Classic and Checkout Blocks policy parity | **Complete** |
    | Checkout Blocks transition notices via Store API extension + JS | **Complete** |
    | Order snapshot v3 checkout metadata | **Complete** |
    | Checkout admin settings UI and diagnostics | **Complete** |

    **Shipped as:** **v0.10.0**.

12. Geo Detection settings (**v0.11.0**) — **prepared on `feature/m12-geo-detection`**.

    ### Scope for v0.11.0

    | Work item | Status |
    |---|---|
    | Ordered first-match country/region/Other routing | **Prepared** |
    | Settings schema v5 (`geo` subtree, disabled by default) | **Prepared** |
    | Optional Universal Geo Context + WooCommerce fallback | **Prepared** |
    | Geo Detection admin section, recommended rules, simulation | **Prepared** |
    | Checkout lock and manual-selection precedence | **Prepared** |

    **Prepared as:** **v0.11.0**. See [`docs/GEO_DETECTION.md`](GEO_DETECTION.md).

13. Geo Detection admin hub (**v0.12.0**) — **prepared on `feature/m13-geo-admin-hub`**.

    ### Scope for v0.12.0

    | Work item | Status |
    |---|---|
    | Geo hub sub-navigation (`geo_panel`) | **Prepared** |
    | GeoContext document schema v1 + serializer | **Prepared** |
    | Geo Sandbox with presets and structured output | **Prepared** |
    | Panel-aware Detection/Settings saves | **Prepared** |
    | Providers, Proxies, Diagnostics panel stubs | **Prepared** |

    **Prepared as:** **v0.12.0**. See [`docs/adr/0017-geocontext-admin-hub.md`](adr/0017-geocontext-admin-hub.md).

14. Visitor Location boundary alignment (**v0.13.0**) — **prepared on
    `feature/m14-visitor-location-boundary`**. Supersedes the M14/M15 plans
    recorded in ADR-0017's Consequences section (editable provider UI,
    diagnostics from resolution traces) — see ADR-0018.

    ### Scope for v0.13.0

    | Work item | Status |
    |---|---|
    | `UgcIntegrationStatus` — single availability source of truth | **Prepared** |
    | Visitor Location hub: 7 panels → 3 (Overview, Currency Routing, Currency Simulation) | **Prepared** |
    | Retired-panel redirects with one-time notice (`GeoLegacyPanelRedirect`) | **Prepared** |
    | Overview merchant dashboard (integration health, detected outcome, needs-attention) | **Prepared** |
    | Currency Routing: folded Settings panel, condition/result/priority rule presentation | **Prepared** |
    | Currency Simulation: design-system output, Universal Geo Context simulation awareness | **Prepared** |
    | GeoContext schema v2 (removed `network`/`providers` reserved subtrees) | **Prepared** |
    | Persisted-data inventory v6 (documented two previously-undocumented sandbox user-meta keys) | **Prepared** |

    No settings schema change; storefront currency-decision behaviour is
    unchanged (locked by new characterization tests predating the refactor).

    **Prepared as:** **v0.13.0**. See
    [`docs/adr/0018-visitor-location-boundary-alignment.md`](adr/0018-visitor-location-boundary-alignment.md)
    and [`docs/GEO_DETECTION.md`](GEO_DETECTION.md).

    ### Post-v0.13.0 hardening

    Conformance-validation and gap-closure work completed after the main M14
    scope (see [`docs/adr/0019-visitor-location-spec-conformance.md`](adr/0019-visitor-location-spec-conformance.md)):

    | Item | Status |
    |---|---|
    | ADR-0019 spec conformance + ROADMAP hardening documentation | **Complete** — `3ba9a74` |
    | Malformed JSON (`GeoContextSerializer::decode()`) unit test coverage | **Complete** — `155d692` |
    | Remove dead `$geo` parameter from `CurrencyResolver::resolve()` | **Complete** — `6b076bf` |
    | First-visit geo detection caching guidance (`CacheCheck` advisory, docs) | **Complete** — `42123f4` |
    | `GeoDetectionApplicator` storefront gating integration test coverage | **Complete** — `9adba08` |

    No settings schema change, storefront behavior change, or version bump.
    Safe in-place upgrade from **0.13.0**.

15. Currency Resolution & Explainability (**v0.14.0**) — **complete and
    released**. This is a **new** Milestone 15 (currency decision
    explainability). It does **not** revive the M15 plan retired by ADR-0018
    (“diagnostics from GeoContext resolution traces”).

    ### Shipped in v0.14.0

    | Work item | Status |
    |---|---|
    | Structured `CurrencyResolutionResult` + `CurrencyResolver::evaluate()` | **Complete** |
    | Shopper currency provenance metadata (explanatory only; no precedence) | **Complete** |
    | `CurrencyDecisionExplanation` / explainer composition layer | **Complete** |
    | Stateless Decision Inspector admin section | **Complete** |
    | Visitor Location + checkout explanation stages | **Complete** |
    | ADR-0020 + architecture specification | **Complete** |

    No settings schema change. No DB migration. No storefront currency-outcome
    change. Inspector simulation is side-effect-free and does not persist
    results. `GeoCurrencyDecisionService` left unconsolidated after
    characterization (skip-reason labeling differs for base-as-explicit). See
    [`docs/adr/0020-currency-decision-explainability.md`](adr/0020-currency-decision-explainability.md)
    and
    [`docs/architecture/currency-decision-explainability.md`](architecture/currency-decision-explainability.md).

    **Released as:** **v0.14.0**.

16. Exchange Rate Operations & Reliability (**v0.15.0**) — **implementation
    complete** on `feature/m16-exchange-rate-operations`; **prepared** as
    **v0.15.0**. Hardens the Milestone 8 rate stack into an operationally
    trustworthy subsystem (health model, aging presentation, scheduler
    correctness for per-currency automatic targets, refresh/lock reliability,
    admin ops UX, order rate provenance schema 4, thin WP-CLI). Does **not**
    redesign providers, add failover, or change stale storefront conversion
    semantics.

    ### Scope for v0.15.0

    | Work item | Status |
    |---|---|
    | Authoritative architecture spec + ADR-0021 | **Complete** |
    | Characterization of current rate/refresh/scheduler/order behavior | **Complete** |
    | `RateHealthService` / `RateHealthReport` (no HTTP, no mutations) | **Complete** |
    | Aging status (presentation-only; 50% of `rate_max_age_hours`) | **Complete** |
    | Scheduler `has_automatic_targets` (effective per-currency mode) | **Complete** |
    | Lock characterization (+ minimal harden only if race proven) | **Complete** |
    | Structured failure taxonomy + unified refresh result contract | **Complete** |
    | Exchange Rates admin ops UX (Admin Design System) | **Complete** |
    | Order snapshot schema 4 (provider + adjustment) | **Complete** |
    | Diagnostics / Site Health alignment | **Complete** |
    | Thin `wp umc rates` CLI | **Complete** |

    No `Settings::SCHEMA_VERSION` bump. No DB migration. Stale rates remain
    usable. Action Scheduler remains schedule truth. See
    [`docs/adr/0021-exchange-rate-operations.md`](adr/0021-exchange-rate-operations.md)
    and
    [`docs/architecture/exchange-rate-operations.md`](architecture/exchange-rate-operations.md).

    **Released as:** **v0.15.0**.

17. Switcher Customization (**v0.16.0**) — **complete and released as
    v0.16.0**. Makes the storefront switcher customizable through structured
    settings plus optional Advanced Custom CSS, without adding a second
    renderer or a template system. One semantic DOM serves every placement;
    presets are CSS layers.

    ### Shipped in v0.16.0

    | Work item | Status |
    |---|---|
    | Authoritative architecture spec + ADR-0022 | **Complete** |
    | Settings schema **v5 → v6** display restructure (lossless, visually neutral) | **Complete** |
    | Per-context content composition (trigger vs menu) + element order + chevron | **Complete** |
    | Six CSS-layer presets under theme / size / shape | **Complete** |
    | Sparse structured overrides emitted as `--umc-switcher-*` custom properties | **Complete** |
    | Motion setting honoring `prefers-reduced-motion` | **Complete** |
    | Responsive adjustments (`hide_name_on_mobile`, `compact_on_mobile`) | **Complete** |
    | Advanced Custom CSS (`edit_css` gated, storefront-only, sanitized) | **Complete** |
    | Display admin sub-navigation (Placement / Content / Design / Advanced) | **Complete** |
    | Merchant + developer guide | **Complete** — [`docs/SWITCHER_CUSTOMIZATION.md`](SWITCHER_CUSTOMIZATION.md) |

    Explicit non-goals: no filesystem CSS compiler, no iframe preview
    subsystem, no Custom JS or HTML, no per-shortcode design overrides, and no
    changes to Visitor Location, checkout policy, or rate operations. Flags /
    currency icons remain deferred. See
    [`docs/adr/0022-switcher-customization-css-contract.md`](adr/0022-switcher-customization-css-contract.md)
    and
    [`docs/architecture/switcher-customization.md`](architecture/switcher-customization.md).

    **Released as:** **v0.16.0**.

18. WooCommerce Compatibility & Transaction Integrity (**v0.17.0**) —
    **complete and released as v0.17.0**. Proves and hardens Universal
    Multicurrency against WooCommerce **core** commerce semantics before
    third-party extension compatibility (M19): transaction integrity
    invariants, Classic / Blocks / Store API parity, tax / shipping / coupon /
    threshold correctness (including free-shipping `min_amount`), order /
    refund / order-pay historical context, REST boundary, and an
    evidence-linked compatibility matrix.

    ### Shipped in v0.17.0

    | Work item | Status |
    |---|---|
    | Authoritative architecture spec + ADR-0023 | **Complete** |
    | Characterization of monetary integration boundaries | **Complete** |
    | Product / variation cache parity (currency **and** rate identity) | **Complete** |
    | Tax reconciliation on converted amounts (WC owns tax) | **Complete** |
    | Coupons + free-shipping threshold conversion | **Complete** |
    | Fees remain unwired (Known limitation) | **Complete** |
    | Cart currency / rate transition integrity | **Complete** |
    | Classic ↔ Store API parity expansion | **Complete** |
    | Orders / HPOS / refunds / order-pay / emails | **Complete** (existing + regression) |
    | `/wc/v3` vs `/wc/store/` REST boundary | **Complete** |
    | Compatibility matrix in `COMPATIBILITY.md` | **Complete** |
    | Developer monetary-boundary guidance | **Complete** |
    | Full CI matrix + release readiness for **v0.17.0** | **Complete** |

    No `Settings::SCHEMA_VERSION` bump. No PersistedKeys inventory bump. No
    order snapshot schema bump. No DB migration. No CurrencyResolver,
    checkout-policy, or rate-architecture redesign. Fees stay unwired.
    Third-party extensions deferred to M19. See
    [`docs/adr/0023-woocommerce-transaction-integrity-contract.md`](adr/0023-woocommerce-transaction-integrity-contract.md)
    and
    [`docs/architecture/woocommerce-transaction-integrity.md`](architecture/woocommerce-transaction-integrity.md).

    **Released as:** **v0.17.0**.

19. Third-Party Extension Compatibility Framework & Priority Integrations
    (**v0.18.0**) — **complete and released**. Establishes a maintainable
    third-party extension compatibility framework on top of M18 transaction
    integrity, then applies it to bounded priority integrations with honest
    evidence classification.

    ### Shipped in v0.18.0

    | Work item | Status |
    |---|---|
    | Authoritative architecture spec + ADR-0024 | **Complete** |
    | Extension compatibility status / evidence contract (E0–E3) | **Complete** |
    | Extension test harness (Layer 1 contract + Layer 2 hook doubles) | **Complete** |
    | ExtensionCompatibilityRegistry + ExtensionDetector | **Complete** |
    | Fee opt-in seam (`umc_convert_fee` wired) | **Complete** |
    | Third-party shipping contract formalization | **Complete** |
    | WooCommerce Subscriptions E2 isolation characterization | **Complete** |
    | Product Add-Ons E2 generic seam characterization | **Complete** |
    | Product Bundles E2 generic seam characterization | **Complete** |
    | Composite Products → Not evaluated (E0) | **Complete** |
    | Bookings audit → Not evaluated (E0) | **Complete** |
    | Dynamic pricing boundary documentation | **Complete** |
    | Gateway compatibility validation | **Complete** |
    | Compatibility Center extension evidence UX | **Complete** |
    | Developer extension-integration guide | **Complete** |
    | Release closure for **v0.18.0** | **Complete** |

    **Released as:** **v0.18.0** (PR **#20**, merge `2c80db3`, tag `v0.18.0`).
    No named premium extension is Integrated or Supported. E3 validation pending.

    ### Success criteria

    - Compatibility framework + evidence model + Compatibility Center complete
    - ≥2 priority adapters validated at E1/E2 (Characterized)
    - Integrated status requires E3 real-extension evidence only
    - Subscriptions monetary contract documented before adapter
    - M18 transaction invariants remain green
    - Settings schema **6**, PersistedKeys **8**, snapshot schema **4** unchanged

    ### Explicit non-goals

    - Support every WooCommerce extension
    - Licensed premium ZIPs as M19 release prerequisite
    - Claim Integrated from test doubles
    - Global automatic fee conversion
    - Bookings full support unless investigation proves bounded

    See
    [`docs/adr/0024-third-party-extension-compatibility-contract.md`](adr/0024-third-party-extension-compatibility-contract.md)
    and
    [`docs/architecture/extension-compatibility.md`](architecture/extension-compatibility.md).

20. Authoritative Per-Currency Product Pricing — Phase 1 (**v0.19.0**) —
    **complete and released**. Optional merchant-authored regular/sale prices per
    **non-base** foreign currency on simple products and variations, with FX
    conversion as fallback.

    ### Shipped in v0.19.0

    | Work item | Status |
    |---|---|
    | Authoritative architecture spec + ADR-0025 | **Complete** |
    | PriceHooks characterization + architecture guards | **Complete** |
    | Fixed-price domain model + repository | **Complete** |
    | ProductPriceResolutionService + sale-state gating | **Complete** |
    | Simple + variation storefront integration | **Complete** |
    | Variation cache fingerprint extension | **Complete** |
    | Product editor admin UX | **Complete** |
    | Line-item pricing provenance | **Complete** |
    | Cart/checkout/Store API parity | **Complete** |
    | Diagnostics + compatibility docs | **Complete** |
    | Release closure for **v0.19.0** | **Complete** |

    **Released as:** **v0.19.0** (PR **#21**, merge `bdc4b4f`, tag `v0.19.0`).
    M19 extension evidence tiers unchanged. No generic dynamic-pricing claim.

    No `Settings::SCHEMA_VERSION` bump. `PersistedKeys` **8 → 9**. Order
    snapshot schema **4** unchanged. See
    [`docs/adr/0025-authoritative-fixed-product-pricing.md`](adr/0025-authoritative-fixed-product-pricing.md)
    and
    [`docs/architecture/authoritative-fixed-product-pricing.md`](architecture/authoritative-fixed-product-pricing.md).

21. Multicurrency Reporting & Analytics Foundation (**v0.20.0**) — **complete
    and released**. UMC-owned admin reporting that reads immutable order facts in
    **native transaction currency only** — no live FX, no inverse FX, no
    base-equivalent normalization.

    ### Shipped in v0.20.0

    | Work item | Status |
    |---|---|
    | Authoritative architecture spec + ADR-0026 | **Complete** |
    | WC refund API characterization + frozen refund authority | **Complete** |
    | OrderSnapshot schema 5 (`_umc_currency_origin`) | **Complete** |
    | Reporting domain, repository, aggregators, cache | **Complete** |
    | Admin Reporting section + CSV export (same models as UI) | **Complete** |
    | Integration, architecture, and performance guards | **Complete** |
    | Release closure for **v0.20.0** | **Complete** — PR **#22**, tag `v0.20.0` |

    Phase 1 reports: Currency Performance (incl. AOV), Pricing Source, Currency
    Origin, Checkout Fallback; rate provenance table-only. Origin persistence:
    `customer` \| `visitor_location` only — **never persist `unknown`**. Legacy
    orders use `WC_Order::get_currency()` when no UMC snapshot.

    No `Settings::SCHEMA_VERSION` bump. `PersistedKeys` **9 → 10**. Order
    snapshot schema **4 → 5**. No DB migration. No WooCommerce Analytics
    integration. See
    [`docs/adr/0026-multicurrency-reporting-truth-contract.md`](adr/0026-multicurrency-reporting-truth-contract.md)
    and
    [`docs/architecture/multicurrency-reporting.md`](architecture/multicurrency-reporting.md).

    **Released as:** **v0.20.0**.

## Milestone 22 — Switcher Currency Presentation (**v0.21.0**) — complete and released

**Released as:** v0.21.0 · ADR-0027 · PR **#23**

Optional bundled presentation icons (`icon` content element) on the existing M17
switcher. Settings schema **6 → 7**; `display.presentation.*` and
`show_icon` per trigger/menu. No OrderSnapshot or PersistedKeys change; no DB
migration. See
[`docs/architecture/switcher-currency-presentation.md`](architecture/switcher-currency-presentation.md).

| Work item | Status |
|---|---|
| ADR-0027 + architecture spec + asset license gate | **Complete** |
| Settings schema 7 migration | **Complete** |
| Asset registry + bundled SVGs | **Complete** |
| SwitcherElementComposer + SwitcherRenderer icon support | **Complete** |
| Admin Display UI + preview parity | **Complete** |
| Integration, security, migration tests | **Complete** |
| Release closure for **v0.21.0** | **Complete** — PR **#23**, tag `v0.21.0` |

## Milestone 23 — Native Switcher Block & Rendering Surface Integration (**v0.22.0**) — complete and released

**Released as:** v0.22.0 · ADR-0028 · PR **#24**

Dynamic Gutenberg block `universal-multicurrency/currency-switcher` as a new
rendering surface on the existing M17/M22 switcher engine. Settings schema **7**
unchanged; no OrderSnapshot, PersistedKeys, or DB migration changes. See
[`docs/architecture/native-switcher-block.md`](architecture/native-switcher-block.md).

| Work item | Status |
|---|---|
| ADR-0028 + architecture spec | **Complete** |
| Bounded switcher presence detection | **Complete** |
| Dynamic block registration + PHP render | **Complete** |
| Editor ServerSideRender preview | **Complete** |
| Multi-instance + Store API integration tests | **Complete** |
| Release closure for **v0.22.0** | **Complete** — PR **#24**, tag `v0.22.0` |

## Milestone 24 — Fixed Pricing Catalog Operations (**v0.23.0**) — complete and released

**Released as:** v0.23.0 · ADR-0029 · PR **#25**

Catalog-wide fixed-price coverage visibility and bounded bulk seed/clear
operations over the unchanged M20 domain model: a dedicated Fixed Pricing
admin screen (preview → confirm → execute), a passive Products-list coverage
column, and a symmetric `wp umc prices list|seed|clear` CLI. Seeding converts
each product's/variation's **authored** native regular/sale price through the
existing conversion engine (`DisplayPriceConverter::convert_to()`, the same
seam the storefront path already uses) using one FX rate snapshot per
operation — never a numeric copy of the base amount, never derived from
`get_price()` or the current sale-active state. Variable-product coverage
uses a structural population (enabled variations with an authored regular
price), independent of stock/`is_purchasable()` state. Settings schema **7**
unchanged; no OrderSnapshot, PersistedKeys, or DB migration changes. See
[`docs/architecture/fixed-pricing-catalog-operations.md`](architecture/fixed-pricing-catalog-operations.md).

| Work item | Status |
|---|---|
| ADR-0029 + architecture spec | **Complete** |
| WP1 characterization (manual authoring baseline + CLI authorization precedent) | **Complete** |
| WP2 `FixedPriceCoverageReport` + `FixedPriceCatalogOperationsService` | **Complete** |
| WP3 dedicated admin screen + coverage column | **Complete** |
| WP4 `wp umc prices` CLI | **Complete** |
| WP5 architecture/security/performance guards | **Complete** |
| Release closure for **v0.23.0** | **Complete** — PR **#25**, tag `v0.23.0` |

## Future milestones — not started, not implemented

None of the following exists in the codebase today:
- CSV import/export for fixed prices; a REST write API for fixed prices;
  flat-markup bulk seeding; Quick Edit inline fixed-price fields (all
  deferred from M24 — see ADR-0029 § Explicit non-goals)
- Custom switcher media / Media Library icons (deferred from M22/M23)
- Additional exchange-rate providers and per-currency provider selection
- `country_change` geo detection mode and broader continent presets
- Public APIs beyond the documented hooks in [`HOOKS.md`](HOOKS.md)
- Gift cards, memberships, and additional third-party extension adapters beyond
  M19 priority set (may follow in a future compatibility milestone)
- Bookings full integration if M19 audit defers it

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
