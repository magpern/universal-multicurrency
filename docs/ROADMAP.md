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
    - **Migration handling** — upgrade paths, schema-version bumps, and the
      story for stores arriving from another switcher
    - **Uninstall audit** — inventory every key the plugin has ever written;
      decision on orphaned dismissal meta; tighten the structural guard to pin
      the real invariant (no `_umc_*` order meta deleted)
    - **Translation readiness** — plugin-wide i18n audit, `.pot` generation,
      translator comments, RTL/locale review
    - **Whole-plugin security review** — M2–M5 surfaces (price filters, Store
      API extension data, order snapshot writes, switcher redirect), not only
      Diagnostics
    - **Performance baseline** — catalogue query counts, cart recalculation
      cost, Store API timing, object-cache behaviour under load

Future: Auto rates, GeoIP, reporting, APIs.

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
