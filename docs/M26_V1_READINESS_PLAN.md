# M26 — v1.0 Production Readiness & Roadmap Closure

> **Frozen implementation specification.** This document is the authoritative
> M26 plan, reviewed and approved with four amendments (roadmap-closure
> sequencing moved to WP12; upgrade-matrix evidence split into deterministic
> PHP fixtures (WP2) vs. an operational historical-artifact rehearsal (WP8);
> WP8's clean-install/upgrade/rollback rehearsal moved to an isolated
> disposable environment, separate from DEV; and the release-run authorization
> scope clarified). From this commit forward, this file — not conversational
> context — is the specification WP0–WP12 execute against. Verdict at
> approval time: **PASS — READY TO FREEZE AND IMPLEMENT**.

## Context

Universal Multicurrency has shipped 25 milestones (M0–M25) as WordPress/WooCommerce
plugin releases v0.5.0 → v0.24.0, each gated by a documented release-audit process,
a five-leg CI matrix, and structural/security/performance guard tests. M25 (Fixed
Pricing CSV Interchange) closed cleanly: PR #26 merged, tag `v0.24.0` published,
artifact independently inspected, 14/14 CI jobs green, 22/22 Playwright scenarios
green against DEV.

The product owner believes the feature roadmap is complete and wants to know,
with repository evidence rather than assumption, whether the plugin is ready to
leave the 0.x series and become `v1.0.0`. This plan is the audit-and-harden
milestone that answers that question. It does not add product features. Its
default posture is: **harden, prove, document, release, close** — not discover
new work.

---

## 1. Baseline verification (done during planning)

| Check | Result |
|---|---|
| `git fetch origin` | Clean, no new refs beyond what's already local |
| `main` == `origin/main` | Both at `5c04dbdd7654fd858a255d7807138b537a38c402` |
| Working tree | Clean (`git status` — nothing to commit) |
| `v0.24.0` tag | Exists, points at `8ce1ffe` → resolves to `f1b718d` (PR #26 merge commit) |
| `main` vs `v0.24.0` | `main` is exactly one commit ahead: `5c04dbd` "docs(m25): close milestone after v0.24.0 release" — a docs-only closure commit (ROADMAP.md, RELEASE_AUDIT.md, DEPLOYMENT.md, one test string), no code change |
| Plugin header / `UMC_VERSION` / `readme.txt` Stable tag | All read `0.24.0`, consistent |
| Upstream drift | None — baseline as given by the task is current and authoritative |

No rebasing or advancing of the baseline is needed. M26 branches from `main` @ `5c04dbd`.

---

## 2. Capability inventory at v0.24.0 (reconstructed from source, not ROADMAP.md)

**Bootstrap**: `universal-multicurrency.php` → `\UMC\Plugin::instance()->init()`
(`src/Plugin.php`), manual constructor wiring (no DI container), phased init
(rates/scheduler → admin → currency/pricing → CLI → display/switcher →
cart/checkout). PHP-version guard fails closed below 8.1.

**Admin surface**: one WooCommerce Settings tab (`src/Admin/SettingsPage.php`)
with 10 sections — Currencies, Exchange Rates, Geo Detection (Visitor Location),
Display, Checkout, Decision Inspector, Compatibility, Reporting, Fixed Pricing,
Advanced — plus a shared admin design system (`assets/admin/umc-settings.css`,
`AdminComponentRenderer`, `AdminPageShell`, `src/Admin/ViewModel/*`).

**Currencies / rates**: unlimited currencies, manual or automatic (Frankfurter
provider) exchange rates, `RateHealthService`/staleness model, Action-Scheduler
based recurring updates (`umc_run_rate_update`), thin `wp umc rates` CLI.

**Visitor Location / display**: optional, disabled-by-default ordered
country/region→currency routing (`src/Geo`), always subordinate to manual
selection and checkout locks; customizable storefront switcher (shortcode,
widget, Gutenberg block) with structured presentation settings + optional
Advanced CSS (`src/Display`).

**Checkout policy**: explicit `CheckoutCurrencyPolicy` decision model — selected-
currency or store-currency entry modes, gateway-fallback causality, Classic and
Blocks parity (`src/Checkout`, `src/StoreApi`).

**Pricing**: runtime FX conversion plus optional authoritative fixed per-currency
prices on simple products/variations (`src/Pricing`), catalog-wide bulk
seed/clear operations with a dedicated admin screen and `wp umc prices` CLI, and
CSV interchange through WooCommerce's native product export/import with a
raw-meta resync defense (`FixedPriceCsvIntegration`, `FixedPriceDocumentMerger`).

**Orders/reporting**: immutable per-order currency/rate snapshots
(`OrderSnapshot`, schema 5), historical order/refund display, and UMC-owned
reporting (Currency Performance, Pricing Source, Currency Origin, Checkout
Fallback) reading native transaction currency only — no live/inverse FX.

**Compatibility**: passive detection of other currency switchers (never
deactivates/modifies them), a documented E0–E3 evidence model, and a
Compatibility Center admin surface; WooCommerce Subscriptions/Add-Ons/Bundles
at E2 (Characterized), Composite Products/Bookings at E0 (Not evaluated); no
extension claims Integrated (E3 pending, by design).

**Diagnostics**: Compatibility scan + Site Health integration + redacted
support-report copy action.

**Release engineering**: `bin/release-audit.sh` (11-step RC gate), allowlist-copy
ZIP builder (`bin/build-zip.sh`), independent ZIP inspector, five-leg CI matrix
(PHP 8.1/8.3/8.4 × WP floor/current × WC 8.2.5 floor/10.9.4 current/latest
ceiling), Diagnostics-scoped mutation testing (Infection, MSI 85% / Covered MSI
95%), and one Playwright E2E spec (M25 CSV journey) with a hard-fail
production-safety guard.

No `TODO`/`FIXME` exist anywhere in `src/`. This is a mature, self-consistently
tested, self-documenting codebase — the audit below found no missing promised
functionality, only documentation staleness and narrow hardening gaps.

---

## 3. Roadmap completeness audit — verdict: **no missing promised core functionality**

`docs/ROADMAP.md`'s "Future milestones — not started, not implemented" section
(current, accurate) lists only genuinely deferred, explicitly-non-goal items:
a REST write API for fixed prices, flat-markup bulk seeding, Quick Edit inline
fixed-price fields, custom switcher media/Library icons, additional rate
providers, `country_change` geo mode, gift cards/memberships/further extension
adapters, and full Bookings support. Every one of these was explicitly declared
out-of-scope in its originating milestone's ADR (0024, 0029) — none is a broken
promise. **These are category D (future enhancement) and must not enter M26.**

Repository-wide search for `TODO`/`FIXME`/`not implemented`/`out of scope` found
**zero hits inside `src/`** (RB14 in `RELEASE_AUDIT.md` enforces this as a
release-blocking check). All scope boundaries live in documentation as
deliberate architectural decisions, not code stubs.

**Answer to the mandatory question: No.** There is no previously-promised core
functionality missing that would make a v1.0.0 release dishonest.

---

## 4. v1.0 product contract — area-by-area (condensed; full detail in WP1)

| # | Area | Implemented | Blocker? |
|---|---|---|---|
| 1–4 | Currencies, rates, freshness/failure, Visitor Location | Yes, evidenced by dedicated ADRs 0010–0013, 0016–0021 + guard tests | No |
| 5–6 | Manual switching, switcher presentation | Yes, ADR-0016/0022/0027/0028 | No |
| 7–8 | FX conversion, authoritative fixed pricing | Yes, ADR-0002, ADR-0025 | No |
| 9–11 | Simple/variable products, sale schedules | Yes, M2/M19/M20 | No |
| 12–17 | Cart, coupons, shipping, free-shipping threshold, taxes, checkout | Yes, ADR-0014/0015/0023, `TEST_STRATEGY.md` §M3/M17 invariants | No |
| 18–19 | Classic checkout, Blocks/Store API | Yes, ADR-0006, `StoreApiTestCase` | No |
| 20–22 | Order snapshots, refunds, historical integrity | Yes, ADR-0004/0005, OrderSnapshot schema 5 | No |
| 23 | Reporting | Yes, ADR-0026, native-currency-only truth contract | No |
| 24–25 | CSV interchange, catalog fixed-pricing ops | Yes, ADR-0029/0030, 22/22 Playwright green | No |
| 26 | Diagnostics | Yes, Site Health + Compatibility scan | No |
| 27–28 | Compatibility Center, extension evidence model | Yes, honestly bounded (E0/E2, no E3 claims) | **No — this is intentional, do not "fix" it** |
| 29 | Admin UX | Implemented, consistent design system; minor staleness (see §6) | No — polish only |
| 30–31 | Security, performance | Continuously guarded (structural tests); **narrative docs stale since v0.8.0/M8** | No — doc gap, not a control gap |
| 32 | Persistence/migrations | Settings schema 7, OrderSnapshot schema 5, PersistedKeys 10, no DB migration — all confirmed against source, zero undocumented writes | No |
| 33 | Uninstall | `uninstall.php` matches ADR-0009 exactly (3 options only; commerce meta permanent) | No |
| 34 | Release/build integrity | ZIP is allowlist-built, independently inspected at v0.24.0, confirmed clean | No |

**No category-A release blocker exists anywhere in this contract.**

---

## 5. Third-party compatibility — preserve as-is

`docs/COMPATIBILITY.md` evidence tiers (quoted verbatim):
- **Untested (E0)** — no evidence.
- **Works with (E1/E2)** — verified once manually, or by a test in the repo not
  run on every PR.
- **Tested / Supported (E3)** — a named CI leg exercises the exact coordinate on
  every PR, green. "Tested" and "Supported" are used interchangeably at this tier.
- **Incompatible (E-negative)** — a reproduced failure with a named cause.

Third-party extensions can never be labelled *Supported*; their ceiling is
*Works with*. Current state: Subscriptions/Product Add-Ons/Bundles =
**Characterized (E2)**; Composite Products/Bookings = **Not evaluated (E0)**;
no extension is **Integrated (E3)** — documented as pending, by design (ADR-0024
explicit non-goal: "licensed premium ZIPs as M19 release prerequisite").

**M26 must not**: obtain premium ZIPs, promote any tier, or reword this into an
implied v1.0 completeness claim. **M26 may**: confirm the wording still matches
reality after M20–M25 additions and add one sentence to `COMPATIBILITY.md`'s
intro noting v1.0.0 does not change the evidence model.

---

## 6. Known limitations — classification

| Limitation | Classification |
|---|---|
| Cart fees not auto-converted (opt-in seam `umc_convert_fee` only) | **C — acceptable documented limitation**, load-bearing architectural decision (ADR-0023/0024) |
| Product price-filter block: base-currency only | **C — acceptable**, documented since M5 |
| No named extension at E3/Integrated | **C — acceptable**, explicit non-goal (§5) |
| Composite Products / Bookings not evaluated (E0) | **C — acceptable**, explicitly deferred pending bounded investigation |
| Multisite / headless / third-party CSV import tools not exercised by CI | **C — acceptable**, "carries no compatibility claim in either direction" (already honestly worded) |
| `SECURITY_REVIEW.md` narrative not re-audited since v0.8.0 (M8) — 16 milestones of surface added since, though `SecuritySourceGuardTest` and sibling structural guards run over the **whole current source tree** on every CI run, so the *control* is current even though the *document* is not | **B — hardening/documentation issue, belongs in M26** |
| `TEST_STRATEGY.md` per-milestone narrative sections stop at M8, resume at M19, silent on M9–M18/M20–M25 (tests for those milestones exist and pass; only the narrative catalog is incomplete) | **B — documentation issue** |
| `README.md` states "Current release: v0.13.0" and links a `v0.13.0` zip — 11 releases stale | **B — documentation issue, most visible finding of this audit** |
| `docs/ARCHITECTURE.md` line 529 says "prepared as 0.17.0 (Milestone 18)" | **B — documentation issue** |
| `docs/SWITCHER_CUSTOMIZATION.md` header says "Milestone 17 / v0.16.0" though body content correctly covers M22/M23 | **B — documentation issue (header only)** |
| `tests/e2e/fixtures/production-guard.ts` and `tests/e2e/README.md` hardcode the literal hostname `dev.biopentra.eu` as the default allowlist | **B — repo-hygiene issue.** `CLAUDE.md`'s own code rules forbid hosting-domain literals in committed files ("the plugin must work on any WooCommerce site... Check before every commit"). `tests/` is excluded from the shipped ZIP, so this is not a *product* defect, but it is a literal policy violation in a committed file and must be corrected in M26. |
| Only one Playwright spec exists (M25 CSV); no browser coverage of the core purchase journey, Blocks path, or fixed-pricing-at-checkout | **B — belongs in M26 per the task's own instructions (§7–8)** — a small, bounded addition, not a framework build-out |
| `docs/PERFORMANCE_BASELINES.md` ceilings set at M7/M8, not re-baselined since | **B — sanity-check required, not necessarily a rewrite** |
| No standalone merchant onboarding guide (only feature-scoped docs + FAQ + changelog) | **B — documentation gap, in scope per task §19** |
| No dedicated fixture test starting an upgrade from the v2/v3/v5 settings-schema states directly (only v0/v1 fixtures plus the full chained-upgrade path and the 4→5/5→6/6→7 steps) | **B — upgrade-matrix coverage gap, closed by WP2** |
| Future enhancements (REST write API, flat-markup seeding, Quick Edit fields, more rate providers, `country_change` geo mode, gift cards/memberships, custom switcher media icons, Bookings full support) | **D — future work, explicitly excluded from M26** |

**No category-A item exists in this table.**

---

## 7. Persistence / schema state — confirmed, no bump justified

Directly verified against source (not just docs):

| Item | Value | Source |
|---|---|---|
| `Settings::SCHEMA_VERSION` | **7** | `src/Settings.php:33` |
| `OrderSnapshot::SCHEMA_VERSION` | **5** | `src/Order/OrderSnapshot.php:54` |
| `PersistedKeys::INVENTORY_VERSION` | **10** | `src/PersistedKeys.php:37` |
| DB migration mechanism | **none** — no `dbDelta`/`CREATE TABLE`/raw DDL anywhere in `src/` or `uninstall.php` | grep-confirmed |

Every persistence write found in `src/` (options, product/variation meta, order
meta via `WC_Order` CRUD, refund meta, line-item meta, user meta, transients,
cookies, WC session keys, Action Scheduler) maps 1:1 to `PersistedKeys.php` and
`docs/PERSISTED_DATA.md`'s machine-checked inventory block (verified by
`tests/unit/PersistedKeysInventoryTest.php`). **Zero undocumented persistence
found.** `uninstall.php` deletes exactly `umc_settings`, `umc_rate_state`,
`umc_reporting_cache_gen` — matches ADR-0009 and its own docblock exactly.

**M26 must not bump any schema.** No production-code change identified in this
audit requires one.

### Schema history (for the upgrade matrix)

| Settings schema | Introduced | Release |
|---|---|---|
| 1 → 2 | Automatic-rate shape | v0.8.0 (M8) |
| 2 → 3 | Display switcher block | v0.9.0 (M9) |
| 3 → 4 | Checkout policy defaults | v0.10.0 (M11) |
| 4 → 5 | Geo Detection defaults | v0.11.0 (M12) |
| 5 → 6 | Display restructure (ADR-0022) | v0.16.0 (M17) |
| 6 → 7 | Presentation icons (ADR-0027) | v0.21.0 (M22) |

| OrderSnapshot schema | Introduced | Release |
|---|---|---|
| 2 → 3 | Checkout policy metadata | v0.10.0 (M11) |
| 3 → 4 | Rate provider/adjustment | v0.15.0 (M16) |
| 4 → 5 | `_umc_currency_origin` (ADR-0026) | v0.20.0 (M21) |

`PersistedKeys` inventory last bumped to 10 at M23 (v0.22.0); M20/M24/M25 reused
the existing `_umc_fixed_prices` key without a bump — correctly, per
`PERSISTED_DATA.md`.

Existing migration tests: per-step unit coverage (`SettingsUpgraderTest`,
`SettingsMigrationV4ToV5Test`, `V5ToV6Test`, `V6ToV7Test`), fidelity test
(byte-identical v1→v2 output), integration test (`SettingsUpgradeIntegrationTest`
— v0 option upgrade, idempotent re-load, unsupported-future-version fallback,
failed-migration non-overwrite), and a doc/impl drift guard
(`MigrationDocumentationTest`). **Corrected during WP2 implementation**:
schema-2/3/5-origin coverage already existed (`SettingsUpgraderTest`,
`SettingsMigrationV5ToV6Test` each already ran the full `upgrade()` starting
from that schema and asserted it reached `Settings::SCHEMA_VERSION`); the
actual gap was narrower — idempotency-on-re-entry from those origins, closed
by WP2 with three small additions (see WP2's correction note in §9). WP8
separately proves the same schema boundaries operationally using real
historical release artifacts (§8).
`OrderSnapshotReader.php` defines `SCHEMA_VERSION_1..5` read-branches; no
migration is needed for order snapshots (write-once per order) but read-path
branch coverage should be confirmed.

---

## 8. Proposed upgrade matrix

This matrix has two independent lines of evidence, deliberately kept separate
(see WP2/WP8): **deterministic PHP fixtures** (WP2 — schema 2/3/5 origins,
hand-constructed payloads, no historical installs) and an **operational
historical-artifact rehearsal** (WP8 — real published releases, on a
disposable environment). The version selection below is shared by both, but
only WP8 actually installs historical builds; WP2 never boots a historical
plugin version inside PHPUnit.

Selected on schema/migration boundaries, using the project's own published
GitHub release tags:

| From | Rationale |
|---|---|
| `v0.5.0` | Earliest published tag — floor of realistically supported 0.x upgrade state |
| `v0.8.1` | Settings schema 2, pre-checkout-policy (schema 3/4), pre-geo (schema 5) |
| `v0.16.0` | Settings schema 6, pre-presentation-icons (schema 7), pre-M19 extension compat, pre-M20 fixed pricing, pre-M21 reporting (OrderSnapshot schema pre-5) |
| `v0.19.0` | Settings schema 7 but PersistedKeys 9, pre-reporting (OrderSnapshot schema 4), pre-switcher-presentation |
| `v0.23.0` | One milestone before current — pre-CSV-interchange |
| `v0.24.0` | Current baseline — trivial/no-op upgrade sanity check |

**WP8's operational rehearsal**, for each row: source the artifact using the
priority order defined in WP8 (published GitHub release artifact first; a
from-tag reconstruction only if genuinely unavailable, explicitly recorded as
such; never silently substituted) — install it on the disposable environment,
exercise it briefly (create a currency, a rate, an order if the version
supports orders), then upgrade code to the `v1.0.0` candidate and verify:
settings preserved/migrated, currencies preserved, exchange-rate config
preserved, display settings preserved, fixed-price data preserved (where
applicable), disabled-currency data preserved, historical orders remain valid
and unchanged, reporting remains valid (where applicable),
`PersistedKeysInventoryTest` still passes, no destructive migration, no
duplicate migration, migrations idempotent (load twice, second load must not
re-persist).

**WP2's fixture line** exercises the same schema-2/3/5 boundaries as fast,
deterministic PHPUnit tests using constructed option payloads, independent of
which artifacts WP8 manages to source — so a full CI-level signal exists even
before/without the operational rehearsal running.

---

## 9. Work packages

Dependencies flow roughly WP0/WP1 → {WP2, WP3, WP4, WP5, WP6} (parallelizable
hardening/audit streams) → WP7 (documents the results of all of the above) →
WP10a (version bump) → WP8 (needs a bumped candidate) → WP9 (final falsification
gate) → WP10b (release-audit finalize) → WP11 (PR/CI/merge/tag/release) → WP12
(closure).

### WP0 — Freeze v1.0 product/release contract
**Objective**: Lock M26 scope in writing before any other work starts, so later
packages have an unambiguous contract to test against.
**Rationale**: Every prior milestone opened with an ADR + architecture spec;
v1.0 deserves the same discipline, and it prevents scope creep into category-D
work mid-milestone.
**Files**: `docs/adr/0031-v1.0-release-contract.md` (new), `docs/ROADMAP.md`
(M26 section scaffold, "in progress").
**Tasks**: Write ADR-0031 recording: the v1.0 contract (§4 of this plan), the
explicit exclusion list (§14), the confirmed unchanged schema/persistence state
(§7) as a *fact*, not a target, and the falsification matrix as the milestone's
acceptance bar.
**Tests**: `DocumentationSyncTest`/`MigrationDocumentationTest`-style guards
continue to pass with the new ADR referenced from ROADMAP.md.
**Acceptance criteria**: ADR-0031 merged to the M26 branch; ROADMAP.md shows
Milestone 26 as "in progress" with a scope table mirroring this plan's WP list.
**Hard stop**: If writing the contract surfaces a scope disagreement between
this plan and the actual repository state, stop and re-audit before proceeding
— do not silently reconcile in the ADR.
**Dependencies**: None (first work item).

### WP1 — Roadmap + repository completeness audit (formalize)
**Objective**: Turn this planning session's audit (§2–§6 above) into a
committed, reviewable artifact so the "no missing core functionality" and
"no category-A blocker" findings are falsifiable by a third reviewer, not just
asserted in a plan document.
**Rationale**: Task §3 requires this to be an explicit, evidenced answer, not
an assumption inherited from ROADMAP.md.
**Files**: `docs/RELEASE_AUDIT.md` (new "v1.0.0 readiness audit" section, same
pattern as each prior version's closure record).
**Tasks**: Restate the capability inventory, the TODO/FIXME grep result (zero
in `src/`), the classification table (§6), and the third-party evidence-tier
snapshot, each with file/line citations.
**Tests**: None new — this is a documentation deliverable, checked by existing
`DocumentationSyncTest` link/structure guards.
**Acceptance criteria**: A reviewer can verify every claim in the audit section
by following the cited file paths without re-deriving them.
**Hard stop**: If formalizing the audit surfaces a genuine category-A item
missed during planning, halt WP2+ and escalate — do not proceed to hardening
work while a real blocker is unresolved.
**Dependencies**: WP0.

### WP2 — Persistence / schema-boundary migration fixture validation (deterministic)

> **Correction recorded during implementation (M26 WP2, commit-time
> discovery):** this package's original premise — "no fixture test exercises
> starting an upgrade from schema 2, 3, or 5 directly" — was **wrong**.
> `tests/unit/SettingsUpgraderTest.php::test_v2_to_v3_migration_preserves_existing_settings_and_adds_display_defaults`
> and `::test_v3_to_v4_migration_preserves_settings_and_adds_checkout_defaults`
> already call the full `SettingsUpgrader::upgrade()` starting at schema 2 and
> 3 respectively and assert the result reaches `Settings::SCHEMA_VERSION`
> (7). `tests/unit/SettingsMigrationV5ToV6Test.php::test_v5_to_v6_upgrade_produces_canonical_settings`
> does the same starting at schema 5. Origin coverage for all three already
> existed. Per this milestone's own "correctness over plan compliance" rule
> (do not fake compliance, do not conceal a discrepancy), the actual gap is
> narrower: none of those three tests asserted **idempotency on re-entry**
> (upgrading the already-migrated-to-7 result a second time), and
> `OrderSnapshotReader`'s `SCHEMA_VERSION_1..5` read branches had explicit
> synthetic fixtures for versions 1, 2 (via
> `OrderCurrencySnapshotClassificationTest`), and 5 (via real order round-trip
> in `M21OrderSnapshotOriginTest`) — but not 3 or 4. **Revised scope actually
> implemented**: three small idempotency assertions added to the existing
> schema-2/3/5-origin tests (no new files), plus two new schema-3/4
> classification fixtures added to `OrderCurrencySnapshotClassificationTest.php`
> following its existing pattern. This is smaller than originally planned and
> touches zero production code — confirmed a correctness gap, not a defect.

**Objective**: Close the narrow idempotency- and read-branch-coverage gap
identified above with deterministic, CI-enforced PHP tests. This package is
PHP-fixture-level only — it does **not** install historical plugin builds;
that operational concern is WP8's (see below).
**Rationale**: See the correction note above.
**Files**: `tests/unit/SettingsUpgraderTest.php` (two new idempotency tests:
schema-2 and schema-3 origin re-entry), `tests/unit/SettingsMigrationV5ToV6Test.php`
(one new idempotency test: schema-5 origin re-entry),
`tests/integration/OrderCurrencySnapshotClassificationTest.php` (two new
classification fixtures: schema versions 3 and 4).
**Tasks**: For each of schema 2, 3, and 5: upgrade the existing origin
fixture to current schema, then upgrade the result a second time, and assert
the second result is identical and does not report `should_persist()`. For
`OrderSnapshotReader`: add a synthetic order-meta fixture at
`_umc_snapshot_version` 3 (asserting `checkout_mode()`/`shopper_currency()`/
`fallback_occurred()` populate, `rate_provider()`/`rate_adjustment()` stay
null) and at version 4 (asserting `rate_provider()`/`rate_adjustment()` also
populate), mirroring the existing version-1/2 fixtures in the same file.
**Tests**: The five new test methods above.
**Acceptance criteria**: All three schema-origin fixtures upgrade cleanly and
idempotently to schema 7; falsification items B–D, X (§11, PHP-fixture half)
close green.
**Hard stop**: Any settings-shape corruption or non-idempotent re-persist found
for any schema-origin fixture is a **release blocker** — root-cause before
proceeding.
**Dependencies**: WP0, WP1.

### WP3 — Full-system PHP acceptance
**Objective**: Prove cross-milestone interaction correctness that no
single-milestone test suite was ever positioned to prove.
**Rationale**: Per-feature integration coverage is deep (M2–M25 each added
their own), but nothing yet exercises, in one test, e.g. a Visitor-Location-
selected currency on a fixed-priced product flowing through checkout into
reporting. This is the actual "full-system acceptance" the task requests —
new cross-feature integration tests, not a re-run of existing per-feature ones.
**Files**: `tests/integration/CrossFeature/` (new directory).
**Tasks**: Add a small number of targeted integration tests:
1. Visitor-Location currency → fixed-priced product → cart → checkout → order
   snapshot → reporting shows correct `pricing_source` and `currency_origin`.
2. Currency switch mid-session on a fixed-priced product → single conversion,
   no compounding (reuses the M3 no-double-conversion invariant, now against
   fixed pricing specifically, which M20 didn't combine with a live switch).
3. CSV-imported fixed price interacting correctly with a subsequent M24
   catalog seed/clear operation (both write through the same
   `FixedPriceDocumentMerger`/`FixedPriceRepository` — confirm no
   last-writer-surprises).
**Tests**: The three tests above; do not duplicate existing per-feature suites.
**Acceptance criteria**: All three cross-feature scenarios pass; falsification
items A, H, L (§11) close green.
**Hard stop**: A cross-feature interaction bug found here that requires a
production-code fix is in-scope for M26 (it's a correctness defect, not a new
feature) — fix it, but do not let the fix grow into a redesign.
**Dependencies**: WP0, WP1.

### WP4 — Playwright v1.0 smoke acceptance
**Objective**: Add the minimum browser coverage that PHP integration tests
structurally cannot provide (real WP-admin JS interactions, real checkout page
rendering, real Store API client-side flow) — and fix the E2E hostname-
hardcoding hygiene violation.
**Rationale**: Only one spec exists today (M25 CSV, 19 scenarios). Per task
§7/§8, M26 should add a *small* number of new journeys, reusing the existing
Playwright infra and production-safety guard as-is, not build a framework.
**Files**: `tests/e2e/specs/v1-core-purchase-journey.spec.ts` (new — journey A:
Visitor-Location/manual selection → currency → product → cart → classic
checkout → order), `tests/e2e/specs/v1-blocks-journey.spec.ts` (new — journey F:
same via Store API/Blocks checkout), `tests/e2e/specs/v1-fixed-pricing-journey.spec.ts`
(new — journeys C+D combined: fixed-priced simple product + a variable
product's fixed/converted variation → cart → checkout → provenance → reporting).
`tests/e2e/fixtures/production-guard.ts` and `tests/e2e/README.md` — remove the
hardcoded `dev.biopentra.eu` literal; require `UMC_E2E_ALLOWED_HOSTS` to be set
explicitly with no shipped default hostname (fail closed if unset, same
fail-closed posture, just without a domain literal in committed code).
**Explicitly NOT added** (already reliably proven by PHP integration per
`TEST_STRATEGY.md` §M3/M4/M5/StoreApi and `RefundConversionTest`/
`HistoricalOrderDisplayTest`): journey B (FX order snapshot → historical
reporting), journey E (switch-mid-shop no-compounding — covered by WP3#2 at
integration level), journey G (refund → reporting). Journey H (CSV → edit →
import → storefront) is already the existing M25 spec — reused as-is, not
copied.
**Tasks**: Write the three new specs against DEV (or an equivalent disposable
environment satisfying the production-guard allowlist); update
`tests/e2e/README.md` with a "Release acceptance" section describing exactly
which specs are release-blocking and how to run them (mirrors M25's precedent
of treating Playwright as the release-blocking manual-acceptance substitute).
**Tests**: The three new specs; the guard fixture's own unit-level self-test
(if one exists) updated to cover the no-hardcoded-host behavior.
**Acceptance criteria**: 3 new specs green against DEV; existing M25 spec still
green unmodified; production-guard fixture contains no committed hostname
literal; falsification items I, AC (§11) close green.
**Hard stop**: If a new spec cannot pass without a production-code change,
treat that as a real defect (in-scope fix), not a spec-writing problem to be
worked around.
**Dependencies**: WP0, WP1, WP3 (reuses its cross-feature scenarios as the
browser-journey source of truth so PHP and Playwright assert the same
contract).

### WP5 — Security + performance + compatibility hardening
**Objective**: Re-audit the narrative `SECURITY_REVIEW.md` against the current
source tree (last updated at M8/v0.8.0); confirm `PERFORMANCE_BASELINES.md`
ceilings still hold; confirm `COMPATIBILITY.md` wording stays honest for v1.0.
**Rationale**: The *controls* are continuously enforced by
`SecuritySourceGuardTest` and siblings (whole-tree static guards, not
milestone-scoped), but the *narrative document* — the one a security-conscious
merchant or auditor actually reads — has not been updated since M8, 16
milestones ago. This is a real, if narrow, v1.0 readiness gap.
**Files**: `docs/SECURITY_REVIEW.md` (update "Audited surfaces" table +
document-control footer to cover M9–M25 surfaces: Geo Detection, Currency
Explainability, Rate Operations, Switcher Customization, WooCommerce
Transaction Integrity, Extension Compatibility, Fixed Pricing, Reporting,
Presentation Icons, Native Block, Catalog Operations, CSV Interchange).
`docs/PERFORMANCE_BASELINES.md` (add ceilings for CSV per-column reads, catalog
bulk seed/clear, reporting cache invalidation if genuinely uncovered — confirm
first before adding). `docs/COMPATIBILITY.md` (one-sentence v1.0 note only).
**Tasks**: For each M9–M25 surface, confirm it is either covered by
`SecuritySourceGuardTest`'s file-scoped exception lists or that its exclusion
is deliberate and documented; update the audited-surfaces table accordingly.
Re-run `--group performance` suites and diff against documented ceilings — only
add new ceiling constants where a genuine uncovered risk exists (task
explicitly warns against adding tests for non-material risk).
**Tests**: No new test framework; extend `PerformanceGuardTest`/
`PerformanceBaselineTest` only if a real uncovered ceiling is found.
**Acceptance criteria**: `SECURITY_REVIEW.md`'s audited-surfaces table has no
milestone gap through M25; `composer test:mutation` still passes at existing
thresholds (Diagnostics-only scope preserved, not expanded — task explicitly
forbids claiming mutation coverage outside configured scope); performance
suites green; falsification items M, N, O, S, T, U (§11) close green.
**Hard stop**: Any live HTTP call found on a transaction-path hook (item N) or
any admin action found missing capability/nonce checks (item S) is a release
blocker.
**Dependencies**: WP0, WP1.

### WP6 — Admin/storefront operational acceptance
**Objective**: Walk the 10 admin sections and the storefront switcher as one
product; fix genuine staleness/consistency defects only.
**Rationale**: Task §16/§17 explicitly forbid redesign; the concrete defects
already found are narrow.
**Files**: `docs/ARCHITECTURE.md` (fix line ~529's stale "0.17.0 (Milestone 18)"
reference), `docs/SWITCHER_CUSTOMIZATION.md` (fix stale "Milestone 17 / v0.16.0"
header to reflect M22/M23 content already present in the body).
**Tasks**: Grep `docs/` and admin-rendered help text for other stale milestone-
number references beyond the two already found; spot-check each of the 10
admin sections and the switcher (trigger + menu, RTL, keyboard/focus, mobile
compact mode) against DEV for broken empty states, dead quick actions, or
inconsistent badges — use the new WP4 Playwright specs' incidental screenshots
plus a short manual DEV pass for anything Playwright doesn't already visit.
**Tests**: None new by default; only add a guard test if a *class* of staleness
(not just these two instances) is found, to prevent recurrence.
**Acceptance criteria**: No milestone-number staleness remains in any doc;
no broken/dead admin UI element found in the pass; falsification item AE (§11)
closes green.
**Hard stop**: None expected; if a defect requires more than a text/CSS/markup
fix to resolve, downgrade it to a documented known limitation (category C)
rather than expanding scope.
**Dependencies**: WP0, WP1.

### WP7 — Documentation completion
**Objective**: Bring merchant- and developer-facing documentation up to
v1.0.0 accuracy, and close the merchant-onboarding-guide gap.
**Rationale**: `README.md` currently states "Current release: v0.13.0" and
links a stale zip — the single most visible defect in this entire audit, since
it's the first thing anyone landing on the GitHub repo reads. `readme.txt`
(the WP.org-format doc) is, by contrast, fully current — the two must be
reconciled.
**Files**: `README.md` (full rewrite of the version/changelog/install
references to v1.0.0; align feature list with the actual v0.24.0+ capability
set from §2), `docs/GETTING_STARTED.md` (new — merchant guide following the
task's suggested structure: install → initial setup → currencies → rates →
Visitor Location → switcher → fixed pricing → checkout behavior → reporting →
CSV import/export → compatibility → diagnostics/troubleshooting →
backup/upgrade/uninstall), `docs/TEST_STRATEGY.md` (add a short closing note
pointing M9–M25 test evidence to their respective `docs/architecture/*.md` +
ADRs, rather than back-filling 17 milestone-narrative sections that would
duplicate existing per-milestone docs), `HOOKS.md`/`EXTENSION_INTEGRATION.md`
(spot-check against current hook registrations — already self-verified by
`HooksDocumentationSyncTest`, low risk, confirm only).
**Tasks**: Rewrite `README.md`; write `GETTING_STARTED.md`; add the
`TEST_STRATEGY.md` closing note; fix the minor `CLAUDE.md` cross-doc gap noting
`umc_reporting_cache_gen` alongside `umc_settings`/`umc_rate_state` in the
uninstall description (trivial, fix in passing, not a separate work item).
**Tests**: `DocumentationSyncTest` continues to pass; add a link-resolution
check for `GETTING_STARTED.md` if the existing guard doesn't already generalize
to new docs files (confirm before adding).
**Acceptance criteria**: `README.md` states v1.0.0 accurately; a merchant can
follow `GETTING_STARTED.md` start-to-finish without reading any ADR;
falsification item AE (§11) closes green for documentation specifically.
**Hard stop**: None expected — pure documentation work.
**Dependencies**: WP0, WP1, WP2–WP6 (documents their findings, so must follow).

### WP8 — Clean-install / historical-upgrade / rollback rehearsal (operational)
**Objective**: Prove, as operational evidence rather than assertion, that (a) a
clean install of the v1.0.0 candidate works, (b) each representative historical
release in §8's matrix upgrades safely to it, and (c) rollback leaves the site
operational. This package owns the **environment policy** for all three
rehearsals — deliberately separated from DEV so the "clean install" claim is
never contaminated by an environment that has already run other fixtures.
**Rationale**: Task §9/§12 require this as evidence, not assertion; §8's matrix
needs a real execution home now that WP2 has been narrowed to deterministic PHP
fixtures only; `DEPLOYMENT.md` already has a "Rollback" subsection for every
prior milestone.
**Environment policy**:
- **Clean-install, historical-upgrade, and rollback rehearsals** (this
  package) run on an **isolated, disposable WordPress/WooCommerce environment**
  (e.g. a fresh `wp-env`/Docker instance provisioned and torn down for this
  purpose) — **not** `dev.biopentra.eu`. DEV is a long-lived, already-fixtured
  instance (M25's CSV data, WP4's new journeys); reusing it here would make the
  "clean install" and "historical upgrade" claims unfalsifiable, since a
  before/after diff on an already-populated site can't distinguish "upgrade
  preserved this" from "this was already there."
- **DEV** (`dev.biopentra.eu`) remains the target for WP4's Playwright merchant
  journeys and the existing M25 CSV spec only — realistic browser acceptance,
  a different and complementary kind of evidence.
- Neither environment is ever the production Biopentra site, consistent with
  the host-level `/opt/biopentra/CLAUDE.md` independence rule.
**Historical-artifact sourcing priority** (do not silently substitute one tier
for another without recording which was used):
1. The **published GitHub release artifact** for that version (downloaded from
   the release itself, same as how `v0.24.0`'s artifact was independently
   verified) — the strongest evidence, since it's exactly what a merchant
   would have installed.
2. If a published artifact is genuinely unavailable for a given tag, build
   from that **exact historical tag using its own documented build process at
   that point in history** (its own `bin/build-zip.sh` if present, else its
   own `composer install --no-dev` + packaging instructions) — record this
   explicitly as a reconstructed build, not described as equivalent to a
   published artifact.
3. Never substitute a reconstructed build for a published one without stating
   that substitution in `DEPLOYMENT.md`'s v1.0.0 entry.
**Files**: `docs/DEPLOYMENT.md` (new "v1.0.0" entry following the established
per-version pattern, including a "Rollback" subsection and an "Upgrade
rehearsal" subsection recording which artifact tier was used for each of
§8's six versions).
**Tasks**: (1) **Clean install** — on the disposable environment, install the
v1.0.0-candidate build from WP10a fresh, activate, confirm no PHP
warnings/fatals, defaults initialize correctly, all 10 admin sections load,
basic currency configuration works, storefront functions, uninstall behaves
per `uninstall.php`/ADR-0009. (2) **Historical-upgrade rehearsal** — for each
of §8's six versions, install the sourced artifact (per the priority order
above) onto a fresh instance of the disposable environment, exercise it
briefly (currency, rate, an order where supported), upgrade code to the
v1.0.0 candidate, and verify per §8's checklist (settings/currencies/rates/
display/fixed-price/disabled-currency data preserved; historical orders and
reporting valid; `PersistedKeysInventoryTest` passes; idempotent re-load).
(3) **Rollback** — from the v0.24.0→v1.0.0 leg of (2), deactivate/downgrade
code back to the v0.24.0 zip, confirm the site remains operational and no
order/currency/rate/fixed-price data was destroyed (code rollback only — do
not claim data rollback, per the task's explicit warning that these are not
the same guarantee). Re-upgrade to the v1.0.0 candidate to leave a known-good
end state.
**Tests**: No new automated test suite; this is an operational rehearsal
recorded as evidence (command transcript + observations, and which artifact
tier was used per version) in `DEPLOYMENT.md`, same pattern every prior
milestone used.
**Acceptance criteria**: Clean install on the disposable environment produces
zero warnings/fatals; all six historical-upgrade legs preserve data per §8's
checklist; rollback leaves the site operational with commerce data intact;
`DEPLOYMENT.md`'s v1.0.0 entry states exactly what is and isn't guaranteed
across upgrade/downgrade, and which artifact tier was used per version;
falsification items B–D (operational half), AD (§11) close green.
**Hard stop**: Any data corruption or site breakage during upgrade or rollback
is a release blocker — do not tag v1.0.0 until root-caused.
**Dependencies**: WP2 (deterministic fixtures proven first, as a cheaper
first-pass signal before the more expensive operational rehearsal), WP10a
(needs a version-bumped candidate to install).

### WP9 — Corrective review and falsification
**Objective**: Execute the full falsification matrix (§11) as the final
independent-review gate before version finalization, mirroring M25's
"WP9 independent corrective review" precedent.
**Rationale**: Every prior milestone closed with an independent corrective pass
before release; v1.0.0, being the terminal milestone, deserves the same rigor
applied across the *whole* product rather than one milestone's diff.
**Files**: None new by default — this package *runs* checks; it only produces
new files if a check fails and requires a fix.
**Tasks**: Work through §11 item by item; for each, run the cited test/command
and record pass/fail with evidence (test name, log excerpt, or manual
observation). Any failure becomes a scoped corrective fix, re-verified, then
re-recorded.
**Tests**: Reuses WP2–WP8's tests; no new suite.
**Acceptance criteria**: All falsification items in §11 close green with cited
evidence.
**Hard stop**: Any item that cannot be closed green — and cannot be honestly
reclassified as an accepted known limitation (category C) — blocks tagging
v1.0.0.
**Dependencies**: WP2, WP3, WP4, WP5, WP6, WP7, WP8.

### WP10 — v1.0.0 release preparation (two phases)
**Objective (10a, early)**: Produce a version-bumped candidate for WP8's
rehearsal. **Objective (10b, final)**: Run the full release-audit gate and
close the roadmap.
**Rationale**: `bin/release-audit.sh` and the version-sync convention
(`CLAUDE.md`) already define exactly how every prior release was finalized;
v1.0.0 follows the same mechanism, deliberately skipping v0.25.0 (per task §24).
**Files**: `universal-multicurrency.php` header, `UMC_VERSION` constant,
`readme.txt` Stable tag + new changelog entry, `docs/COMPATIBILITY.md` version
reference — bumped together per the established convention.
`docs/ROADMAP.md` — update the Milestone 26 entry to reflect implementation
progress, but keep its status as **"release pending"**, not complete. The
"M0–M26 complete" statement and the Backlog/Future-enhancements conversion are
explicitly **deferred to WP12**, after WP11's PR/CI/merge/tag/release/artifact
verification actually succeeds — marking the roadmap complete before the
release is published would misrepresent repository state (a release candidate
is not a release).
**Tasks (10a)**: Bump `0.24.0` → `1.0.0` on a branch, no other change, so WP8
has a real candidate to install. **Tasks (10b, after WP9 passes)**: run
`composer phpcs`, `composer test:unit`, `composer test:integration`,
`composer test:mutation` (Diagnostics scope), `composer make-pot` +
`make-pot:check`, `composer audit`, `composer release-audit` in full; regenerate
`languages/universal-multicurrency.pot`; finalize `docs/RELEASE_AUDIT.md`'s
v1.0.0 closure-record row (same table shape as every prior version) as
"prepared" — mirroring how `RELEASE_AUDIT.md` records each version before its
tag exists, not after.
**Tests**: The full existing gate — no new tests, this package consumes them.
**Acceptance criteria**: `bin/release-audit.sh` exits 0; `readme.txt` Stable
tag, plugin header, `UMC_VERSION`, and `COMPATIBILITY.md` all read `1.0.0`
consistently; `ROADMAP.md` still shows Milestone 26 as release-pending (not
"complete") — completion language is exclusively WP12's to write.
**Hard stop**: Any release-audit failure blocks proceeding to WP11.
**Dependencies**: 10a depends on WP0/WP1; 10b depends on WP9 passing in full.

### WP11 — PR / fresh CI / merge / release / artifact verification
**Objective**: Land M26 through the same review discipline as every prior
milestone.
**Rationale**: Established precedent (PR #20–#26 pattern); no reason to deviate
for the terminal milestone.
**Files**: None beyond what WP0–WP10 already changed.
**Tasks**: Open PR from `feature/m26-v1.0-readiness` (or similarly named
branch) to `main`; require a fresh, full CI run (all five integration legs
green including the WC 8.2.5 floor leg, unit, phpcs, pot, performance,
release-audit, and mutation if Diagnostics files changed); merge; tag `v1.0.0`
on the merge commit; let `release.yml` build and publish the GitHub release;
download the published artifact independently (not a local build) and inspect
it exactly as `v0.24.0` was inspected — confirm no `tests/`, no `tests/e2e`,
no `node_modules`, no `.git`, correct version metadata throughout, all v1.0
classes present.
**Tests**: CI's existing suite in full, plus the manual artifact-inspection
step from `RELEASE_AUDIT.md`'s established pattern.
**Acceptance criteria**: PR merged with 100% green CI; tag `v1.0.0` created;
GitHub release published; artifact independently verified clean; falsification
item Y (§11) closes green.
**Hard stop**: Do not tag or release on anything less than a fully green fresh
CI run. Whether tagging/publishing requires a discrete approval step or is
covered by the implementation run's own standing authorization is governed by
that run's instruction wording — see the "Authorization scope" note under
Verification, below — not by this work package in isolation.
**Dependencies**: WP10b.

### WP12 — Roadmap closure documentation
**Objective**: Record the release exactly as the M25 closure commit
(`5c04dbd`) did, so the repository's own history remains the authoritative
audit trail.
**Rationale**: Established, working pattern — reuse it verbatim.
**Files**: `docs/ROADMAP.md`, `docs/RELEASE_AUDIT.md`, `docs/DEPLOYMENT.md`
(final closure updates: PR number, merge SHA, tag, CI run IDs, artifact
hash, whether production deployment was performed — expected: **not
performed**, per task §30).
**Tasks**: One closure commit, same shape as `5c04dbd`, recording: PR # and
merge SHA, tag `v1.0.0` and GitHub release URL, CI run IDs (both PR and main),
artifact filename + SHA-256, Playwright acceptance summary (spec count,
scenario count, all green), explicit statement that production deployment was
**not** performed and is a separate, later, explicitly-authorized operation.
**This is the only work package that marks the roadmap complete.** Update
`docs/ROADMAP.md` to add the top-level "Milestones M0–M26 complete" statement
and convert the "Future milestones — not started, not implemented" section
into "Backlog / Future enhancements" (content carried forward from §3/§14
unchanged in substance; do **not** invent M27).
**Tests**: `DocumentationSyncTest` closure-state guards.
**Acceptance criteria**: Repository state after this commit reads, end to end,
as "v1.0.0 released and verified; roadmap closed; production deployment not
performed" — matching task §30's default ending exactly; `ROADMAP.md` shows
M0–M26 complete with a Backlog section, no M27, only now that WP11 has
actually published the release.
**Hard stop**: None.
**Dependencies**: WP11.

---

## 10. Test matrix summary

| Category | Reused | New |
|---|---|---|
| Unit | All existing suites (~M0–M25) | Schema-2/3/5-origin migration fixtures (WP2) |
| Integration | All existing suites incl. `StoreApiTestCase`, `RefundConversionTest`, `HistoricalOrderDisplayTest` | 3 cross-feature tests (WP3) |
| Playwright/E2E | M25 CSV spec, reused as-is | 3 new v1.0 journey specs (WP4) |
| Upgrade | None existed at this breadth before | 3 schema-origin PHP fixtures, deterministic (WP2) + 6-version operational rehearsal on published artifacts (WP8) |
| Clean-install | CI's `integration` legs prove bootstrap; no dedicated fresh-environment rehearsal existed | Rehearsal on an isolated disposable environment (WP8) |
| Persistence guards | `PersistedKeysInventoryTest` | None — confirmed sufficient |
| Performance guards | Existing ceilings | Only if a genuine uncovered ceiling is found (WP5) |
| Security guards | `SecuritySourceGuardTest` + siblings (whole-tree, continuous) | None — narrative doc catch-up only (WP5) |
| Documentation guards | `DocumentationSyncTest`, `MigrationDocumentationTest`, `HooksDocumentationSyncTest` | Extend only if `GETTING_STARTED.md` needs link-checking (WP7) |
| Release audit | `bin/release-audit.sh`, `ReleaseZipInspector` | None |
| Mutation | Diagnostics-only, MSI 85%/Covered 95% — **stays Diagnostics-only; not expanded for v1.0** | None |

---

## 11. Falsification matrix

| # | Claim to disprove | Evidence / method | WP |
|---|---|---|---|
| A | Clean install fails | Fresh WP+WC install rehearsal, zero warnings/fatals | WP8 |
| B | Historical upgrade loses settings | Schema-2/3/5 fixture tests (deterministic) + operational rehearsal across §8's 6 published-artifact states | WP2, WP8 |
| C | Historical upgrade loses fixed pricing | Operational rehearsal, asserting `_umc_fixed_prices` survives (fixed pricing predates schema 2/3/5, so this is WP8-only, not a fixture concern) | WP8 |
| D | Disabled-currency data lost on upgrade | Schema-2/3/5 fixture tests + operational rehearsal, asserting disabled-currency subtree survives | WP2, WP8 |
| E | Historical orders change after upgrade | Operational rehearsal, byte-comparing `_umc_*` order meta before/after | WP8 |
| F | Reporting misreconstructs historical monetary state | Existing reporting tests + WP3#1 cross-feature check | WP3, WP5 |
| G | Current FX leaks into historical reporting | `docs/adr/0026` truth-contract guards, re-confirmed | WP5 |
| H | Fixed prices converted twice | WP3#2 (fixed price + live currency switch, no compounding) | WP3 |
| I | Store API differs from Classic | Existing `StoreApiTestCase` reconciliation suite + WP4 Blocks spec | WP3, WP4 |
| J | Refund accounting diverges | Existing `RefundConversionTest`, re-run, no change needed | WP1 (confirm only) |
| K | Visitor Location overrides manual choice | Existing `GeoDetectionApplicator` gating tests, re-confirmed | WP5 (confirm only) |
| L | Base currency acquires fixed-price metadata | WP3#3 (CSV + catalog-ops interaction), existing M20 guards | WP3 |
| M | Stale rates silently look healthy | `RateHealthService`/`RateStatusEvaluator` tests, re-confirmed | WP5 |
| N | Live FX HTTP occurs on transaction path | `RatesPersistenceGuardTest` (HTTP confined to `WordPressHttpTransport`) | WP5 |
| O | Reporting becomes unbounded | `ReportingPerformanceGuardTest`, `ReportingArchitectureGuardTest`, re-run | WP5 |
| P | CSV raw-meta bypass returns | Existing M25 raw-meta resync tests + M25 Playwright spec, re-run unmodified | WP1 (confirm only) |
| Q | CSV formula injection returns | `ReportingCsvRenderer::escape_csv_cell()` guard test, re-confirmed | WP5 |
| R | Extension compatibility overstated | `COMPATIBILITY.md` wording audit — confirm E0/E2/no-E3 unchanged | WP1, WP5 |
| S | Admin action lacks capability/nonce protection | `SecuritySourceGuardTest` + full-tree re-audit | WP5 |
| T | REST exposes unauthorized mutation | Existing REST-boundary tests (`/wc/v3` vs `/wc/store/`), re-confirmed | WP5 |
| U | Bundled SVG/assets unsafe | Existing asset-license/security guards, re-confirmed | WP5 |
| V | Uninstall deletes data contrary to policy | `uninstall.php` re-read against ADR-0009 — already confirmed clean | WP1 (confirm only) |
| W | Persisted-data inventory misses a write | Full `src/` persistence grep, re-run — already confirmed zero gaps | WP1, WP2 |
| X | Migration non-idempotent | `should_persist()` re-entry assertion across all 6 existing schema steps, the 3 new schema-2/3/5 fixtures, and the WP8 operational rehearsal | WP2, WP8 |
| Y | Release ZIP ships tests/E2E/secrets | Independent artifact download + inspection (same as v0.24.0) | WP11 |
| Z | Version metadata diverges | `CompatibilityMatrixTest`/`DocumentationSyncTest`, re-run post-bump | WP10 |
| AA | WooCommerce floor fails | CI's WC 8.2.5 floor leg, fresh run | WP11 |
| AB | PHP ceiling fails | CI's PHP 8.4 ceiling leg, fresh run | WP11 |
| AC | Playwright can target production | `production-guard.ts` fail-closed re-test, hostname literal removed | WP4 |
| AD | Rollback procedure corrupts data | Rollback rehearsal, commerce-data diff before/after | WP8 |
| AE | Documentation claims unsupported functionality | Full doc pass — README.md, ARCHITECTURE.md, SWITCHER_CUSTOMIZATION.md corrected | WP6, WP7 |
| AF | M27/future-feature scope leaks into v1.0 | ADR-0031 exclusion list cross-check against final diff | WP0, WP9 |

---

## 12. Manual acceptance policy

No manual acceptance requirement is introduced beyond what M25 already
established as precedent: Playwright browser acceptance against DEV
substitutes for a human click-through when it exercises the real admin/
storefront UI end to end (as M25's 22/22 scenario suite did). WP4's three new
specs extend that same substitution to the core purchase, Blocks, and
fixed-pricing journeys. WP6's admin/storefront pass uses a short manual DEV
walkthrough only for the narrow slice (visual consistency, empty states)
that browser automation doesn't meaningfully cover — not a full manual
re-test of anything already automated.

---

## 13. Expected final release state

| Item | Expected | Confirmed by |
|---|---|---|
| Version | 1.0.0 | WP10 |
| Settings schema | 7 (unchanged) | §7 |
| OrderSnapshot schema | 5 (unchanged) | §7 |
| PersistedKeys | 10 (unchanged) | §7 |
| DB migration | none | §7 |
| Mutation scope | Diagnostics-only (unchanged) | §5 in ROADMAP.md/TEST_STRATEGY.md |
| WC floor | 8.2.5 (unchanged) | CI matrix, confirmed present |

No production-code schema or persistence change is expected as an outcome of
this milestone. If WP2/WP3 uncover a genuine defect requiring one, that is a
release blocker to resolve, not a target to plan toward.

---

## 14. Explicit exclusions from M26

REST write API for fixed prices; flat-markup bulk seeding; Quick Edit inline
fixed-price fields; custom switcher media/Library icons; additional exchange-
rate providers or per-currency provider selection; `country_change` geo mode;
gift cards, memberships, or further third-party extension adapters beyond the
M19 priority set; full WooCommerce Bookings support; promoting any extension's
evidence tier toward E3/Integrated; obtaining licensed premium extension code;
expanding mutation-testing scope beyond Diagnostics; any settings/order-
snapshot/persisted-keys schema bump not forced by a genuine defect found during
the audit; a large storefront/admin redesign; a general-purpose frontend test
framework; production deployment (a separate, later, explicitly-authorized
operation); inventing an M27 milestone.

---

## 15. Release sequence

WP0 → WP1 → {WP2, WP3, WP4, WP5, WP6 in any order/parallel} → WP7 → WP10a
(version bump on branch) → WP8 (rehearsal against the bumped candidate) → WP9
(falsification gate over everything) → WP10b (full release-audit, finalize
docs) → WP11 (PR → fresh CI → merge → tag `v1.0.0` → GitHub release → artifact
verification) → WP12 (closure commit).

---

## 16. Roadmap closure sequence

After WP11 succeeds: `docs/ROADMAP.md` gets a top-level statement "Milestones
M0–M26 complete" directly under the title; the existing "Future milestones —
not started, not implemented" section is renamed to "Backlog / Future
enhancements" and its content (already accurate per §3) is carried forward
unchanged in substance; no ADR or architecture doc is deleted — historical
record is preserved per task §18. `docs/RELEASE_AUDIT.md` and
`docs/DEPLOYMENT.md` get their v1.0.0 closure entries per WP12.

---

## 17. Hard stop conditions (repository-wide)

- Any data loss/corruption on any upgrade or rollback path (§8, §11 B–E, AD).
- Any live HTTP call discovered on a monetary/transaction-path hook (§11 N).
- Any admin action or REST route found missing capability/nonce/permission
  checks (§11 S, T).
- Any CSV formula-injection or raw-meta-bypass regression (§11 P, Q).
- Any settings/order-snapshot schema divergence from the confirmed values in
  §7 without a genuine defect justifying it.
- Any CI leg (including the WC 8.2.5 floor and PHP 8.4 ceiling) failing on a
  fresh run before tagging.
- Any released artifact found to contain `tests/`, `tests/e2e/`, `node_modules/`,
  or `.git/` (§11 Y).
- Any attempt, during execution, to expand scope into a §14 exclusion.

---

## 18. Risks

- **Schema-2/3/5 fixture construction (WP2) — resolved, smaller than
  planned**: implementation found the origin fixtures already existed
  (reusing them directly, not re-inventing shapes), so the actual WP2 work was
  three idempotency assertions plus two `OrderSnapshotReader` classification
  fixtures — see WP2's correction note in §9. The residual risk this item
  originally flagged (hand-constructed payloads not matching real historical
  shape) is now moot for the fixture line; it still applies to WP8's
  operational rehearsal, which uses real published artifacts rather than
  constructed payloads specifically to avoid it.
- **Environment policy for WP4/WP8 — resolved, not left ambiguous**: WP8's
  clean-install, historical-upgrade, and rollback rehearsals run on an
  isolated, disposable environment provisioned fresh for that purpose, never
  on `dev.biopentra.eu`. DEV is reserved for WP4's Playwright merchant
  journeys (the existing M25 CSV spec plus the 3 new v1.0 journeys) — a
  different, complementary kind of evidence. This removes the sequencing
  hazard the earlier draft of this plan flagged (a "clean install" claim
  cannot be made on an environment that already carries other milestones'
  fixtures); the remaining execution risk is purely operational — provisioning
  and tearing down six disposable environment instances (one per §8 version)
  takes real time and should be budgeted for, not treated as free.
- **Narrative-doc catch-up (WP5, WP7)** is broad in surface area (12+ features
  across `SECURITY_REVIEW.md`) even though narrow in depth (the controls
  already exist) — scope it as "confirm coverage exists + update the audited-
  surfaces table," not "re-derive the security posture from scratch."
- **E2E hostname fix (WP4)** touches a safety-critical fail-closed guard —
  changing it to remove the hardcoded literal must not weaken the fail-closed
  behavior; the fix is removing the *default value*, not the *enforcement*.

---

## 19. Final planning report (summary)

1. **Executive recommendation: GO** for v1.0.0, contingent on completing the
   WP0–WP12 hardening/documentation/acceptance work defined above. No
   category-A release blocker was found anywhere in this audit.
2. Verified baseline: `main` == `origin/main` == `5c04dbd`, working tree clean.
3. `v0.24.0` tag verified, resolves to the correct M25 merge commit, one
   docs-only commit behind `main`.
4. Capability inventory: §2.
5. Roadmap completeness: §3 — no missing promised functionality.
6. TODO/deferred classification: §3, §6.
7. v1.0 product contract: §4.
8. Known limitations accepted for v1.0: §6.
9. Actual v1.0 blockers: **none found**.
10. Compatibility/evidence-tier state: §5 — preserved as-is, not promoted.
11. Persistence/schema state: §7 — Settings 7, OrderSnapshot 5, PersistedKeys
    10, no DB migration, zero undocumented writes.
12. Migration history: §7.
13. Upgrade matrix: §8.
14. Clean-install strategy: WP8.
15. Full-system integration acceptance: WP3.
16. Playwright acceptance strategy: WP4.
17. Security audit scope: WP5.
18. Performance audit scope: WP5.
19. WooCommerce/PHP/WP compatibility strategy: §11 AA/AB, WP11 (matrix
    preserved unchanged, including the WC 8.2.5 floor).
20. Admin UX acceptance: WP6.
21. Storefront acceptance: WP6.
22. Documentation gaps: §6, WP7 — README.md is the standout finding.
23. Merchant documentation plan: WP7 (`GETTING_STARTED.md`).
24. Developer documentation plan: WP7 (confirm HOOKS.md/EXTENSION_INTEGRATION.md).
25. Diagnostics/Site Health review: covered within WP5's security pass.
26. Release-engineering review: §2, WP10 — process is sound, reused as-is.
27. Production ZIP audit: WP11 — independent download + inspection, same as
    v0.24.0's precedent.
28. Rollback rehearsal: WP8.
29. WP0–WP12 implementation plan: §9.
30. Test matrix: §10.
31. Falsification matrix: §11.
32. Manual acceptance requirements: §12 — none beyond Playwright substitution
    already established at M25.
33. Expected version/schema/persistence state: §13.
34. Explicit exclusions: §14.
35. Release sequence: §15.
36. Roadmap closure sequence: §16.
37. Hard stop conditions: §17.
38. Risks: §18.
39. Final recommendation: v1.0.0 is justified by repository evidence. The
    product is feature-complete against its own documented roadmap, has no
    undocumented persistence, no live blocker in security/performance/
    compatibility posture, and a mature release process. The work remaining
    is exactly what a terminal "harden, prove, document, release, close"
    milestone should contain — nothing more.

---

## Verification (for whoever executes this plan)

- Re-run this planning session's baseline checks (`git fetch`, `main` ==
  `origin/main`, clean tree, `v0.24.0` tag) before branching — do not assume
  they still hold if time has passed.
- After each WP, run the specific tests/commands cited in that WP's
  "Tests"/"Acceptance criteria" fields — do not defer verification to WP9.
- WP9 is not a substitute for per-WP verification; it is the final
  cross-check that nothing regressed between packages.
- Before tagging (WP11), the full `composer release-audit` gate must exit 0
  on a clean checkout, and CI must be green on the actual merge commit (a
  second full run after merge, not just the PR run — mirrors the exact
  precedent from v0.24.0's closure, where the PR run and the main run were
  both required and recorded separately).
- **Authorization scope for the implementation run**: this planning document
  itself authorizes nothing — it is a plan, not an execution. When this plan
  is frozen and handed back as a single combined implementation instruction
  ("freeze → implement → corrective review → PR/CI → v1.0.0 release →
  artifact verification → roadmap closure"), that instruction's own wording
  is what determines whether branch creation, commits, push, PR creation,
  merge, tagging, GitHub release publication, and closure documentation are
  pre-authorized as one continuous sequence, or whether execution should pause
  for approval at specific steps. If the combined instruction explicitly
  authorizes the full sequence end-to-end, the executing agent should not stop
  to re-ask for approval solely because execution has reached the tag/release
  stage — repeatedly re-asking after a scope has already been granted defeats
  the purpose of issuing a combined instruction. Execution should still hard-
  stop, regardless of how the authorization is worded, when: (a) an actual
  gate fails (CI red, release-audit non-zero, a falsification-matrix item
  cannot close green), (b) repository or environment policy technically
  prevents the operation, or (c) this environment's standing git-safety rules
  flag the specific action as requiring confirmation regardless of prior
  authorization (e.g. force-push, history rewrite) — none of which the normal
  WP0–WP12 sequence as planned should ever trigger. Production deployment
  remains outside this authorization in all cases (§14, §17) — it is a
  separate, later, explicitly-authorized operation regardless of how the M26
  implementation instruction is worded.

M26 PLAN VERDICT: PASS — READY TO FREEZE AND IMPLEMENT
