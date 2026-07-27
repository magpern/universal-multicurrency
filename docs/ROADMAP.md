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
7.  Release candidate — **not closed by v0.6.0.** Remaining objectives:
    - **Migration handling** — **merchant playbook shipped:**
      [`docs/MIGRATION.md`](MIGRATION.md) (manual cut-over; ADR-0003/0007; no
      foreign import). Internal settings schema 0→1 upgrade (`SettingsUpgrader`).
      Optional UMC CSV format specified for future tooling only (no RC import UI).
    - **Uninstall audit** — **shipped (M7):** [`PERSISTED_DATA.md`](PERSISTED_DATA.md),
      [`ADR-0009`](adr/0009-uninstall-retention-policy.md), executable guards
    - **Translation readiness** — **shipped (M7):** [`docs/TRANSLATION.md`](TRANSLATION.md)
      (i18n audit, `languages/universal-multicurrency.pot`, CI drift guard, RTL audit)
    - **Whole-plugin security review** — **shipped (M7):** [`SECURITY_REVIEW.md`](SECURITY_REVIEW.md),
      `SecuritySourceGuardTest`, `SecurityBehaviourTest`; zero open Critical/High findings
    - **Performance baseline** — **shipped (M7):** [`PERFORMANCE_BASELINES.md`](PERFORMANCE_BASELINES.md),
      query/write-count guards (`PerformanceBaselineTest`, `PerformanceGuardTest`); no wall-clock CI gates

Future: Auto rates, GeoIP, reporting, APIs.

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
