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

## Future milestones — not started, not implemented

None of the following exists in the codebase today:

- Additional exchange-rate providers and per-currency provider selection
- WP-CLI command surface over the existing rate services
- GeoIP-based currency suggestion
- Multicurrency reporting and analytics
- Public APIs beyond the documented hooks in [`HOOKS.md`](HOOKS.md)

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
