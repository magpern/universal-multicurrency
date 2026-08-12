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
    **in progress** on `feature/m18-woocommerce-compatibility`. Proves and
    hardens Universal Multicurrency against WooCommerce **core** commerce
    semantics before third-party extension compatibility (M19): transaction
    integrity invariants, Classic / Blocks / Store API parity, tax / shipping /
    coupon / threshold correctness (including free-shipping `min_amount`),
    order / refund / order-pay historical context, REST boundary, and an
    evidence-linked compatibility matrix.

    ### Scope for v0.17.0

    | Work item | Status |
    |---|---|
    | Authoritative architecture spec + ADR-0023 | **In progress** |
    | Characterization of monetary integration boundaries | Planned |
    | Product / variation cache parity (currency **and** rate identity) | Planned |
    | Tax reconciliation on converted amounts (WC owns tax) | Planned |
    | Coupons + free-shipping threshold conversion | Planned |
    | Fees remain unwired (Known limitation) | Planned |
    | Cart currency / rate transition integrity | Planned |
    | Classic ↔ Store API parity expansion | Planned |
    | Orders / HPOS / refunds / order-pay / emails | Planned |
    | `/wc/v3` vs `/wc/store/` REST boundary | Planned |
    | Compatibility matrix in `COMPATIBILITY.md` | Planned |
    | Developer monetary-boundary guidance | Planned |
    | Full CI matrix + release readiness for **v0.17.0** | Planned |

    No `Settings::SCHEMA_VERSION` bump. No PersistedKeys inventory bump. No
    order snapshot schema bump. No DB migration. No CurrencyResolver,
    checkout-policy, or rate-architecture redesign. Fees stay unwired.
    Third-party extensions deferred to M19. See
    [`docs/adr/0023-woocommerce-transaction-integrity-contract.md`](adr/0023-woocommerce-transaction-integrity-contract.md)
    and
    [`docs/architecture/woocommerce-transaction-integrity.md`](architecture/woocommerce-transaction-integrity.md).

    **Target release:** **v0.17.0**.

## Future milestones — not started, not implemented

None of the following exists in the codebase today:

- Currency icon / flag presentation for the switcher (deferred from M17)
- Additional exchange-rate providers and per-currency provider selection
- `country_change` geo detection mode and broader continent presets
- Multicurrency reporting and analytics
- Public APIs beyond the documented hooks in [`HOOKS.md`](HOOKS.md)
- Third-party extension compatibility matrix (Subscriptions, Bookings,
  Bundles, Add-Ons, Composite, Dynamic Pricing, gift cards, memberships,
  third-party shipping/checkout) — deferred to **M19**

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
