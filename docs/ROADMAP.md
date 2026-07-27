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
7.  Release candidate (**v0.7.0**) — **complete** in this repository.

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

    **Resulting release candidate:** **v0.7.0** (`UMC_VERSION` and plugin header).

    **Not yet performed:** Git tag `v0.7.0`, GitHub release publication, branch
    merge, or pull-request closure — pending explicit approval after review.

Future: Auto rates, GeoIP, reporting, APIs.

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
