# Release audit — v1.0.0 Production Readiness & Roadmap Closure

Executable release-preparation gate for Universal Multicurrency **v1.0.0**.
This is Milestone 26 (WP1): a formalized, citable readiness audit — not yet a
closure record, since no version bump, tag, or release exists at this point.
Full detail, work-package definitions, and the falsification matrix live in
[`M26_V1_READINESS_PLAN.md`](M26_V1_READINESS_PLAN.md); this section restates
the audit's material findings with file/line citations so a third reviewer can
verify each claim without re-deriving it. See ADR-0031 for the frozen contract.

**Repository status:** **in progress** — Milestone 26, WP1 (audit formalization).
No version bump, tag, or release exists yet.

---

## v1.0.0 readiness audit (WP1)

### Roadmap completeness

`docs/ROADMAP.md`'s "Future milestones — not started, not implemented" section
lists only items explicitly declared out-of-scope in their originating
milestone's ADR (ADR-0024 § Explicit non-goals, ADR-0029 § Explicit non-goals)
— none is a broken promise. A repository-wide search for `TODO`, `FIXME`,
`not implemented`, and `out of scope` inside `src/` returns **zero hits**
(enforced release-blocking by RB14 in this document's audit gate below).
**Verdict: no previously-promised core functionality is missing.**

### Capability inventory (v0.24.0 baseline, reconstructed from source)

Bootstrap: `universal-multicurrency.php` → `UMC\Plugin::instance()->init()`
(`src/Plugin.php`). Admin: one WooCommerce Settings tab
(`src/Admin/SettingsPage.php`) with 10 sections. Currencies/rates:
`src/Currency`, `src/Rates` (Frankfurter provider, `RateHealthService`, `wp umc
rates` CLI). Visitor Location/display: `src/Geo`, `src/Display`. Checkout
policy: `src/Checkout`, `src/StoreApi`. Pricing: `src/Pricing`
(`FixedPriceRepository`, `FixedPriceCsvIntegration`, `FixedPriceCatalogOperationsService`,
`wp umc prices` CLI). Orders/reporting: `src/Order` (`OrderSnapshot` schema 5),
`src/Reporting`. Compatibility: `src/Compatibility` (E0–E3 evidence model,
Compatibility Center admin surface). Diagnostics: `src/Diagnostics`. Full
narrative: `M26_V1_READINESS_PLAN.md` §2.

### Known-limitations classification

Full classification table: `M26_V1_READINESS_PLAN.md` §6. Summary: every
identified gap is either **category B** (hardening/documentation work
in-scope for M26 — stale `README.md`, stale `SECURITY_REVIEW.md` narrative,
thin Playwright coverage, a hardcoded E2E hostname literal, an upgrade-fixture
coverage gap) or **category C** (an already-honest, acceptable documented
limitation — opt-in cart fees, base-currency-only price-filter block, no
extension at E3/Integrated, Composite Products/Bookings at E0). **No
category-A item was found.**

### Third-party compatibility evidence-tier snapshot

`docs/COMPATIBILITY.md` evidence tiers, quoted from source: **Untested (E0)**
— no evidence; **Works with (E1/E2)** — verified once manually, or by a test
in the repo not run on every PR; **Tested / Supported (E3)** — a named CI leg
exercises the exact coordinate on every PR, green; **Incompatible
(E-negative)** — a reproduced failure with a named cause. Third-party
extensions can never be labelled *Supported*. Current tiers: WooCommerce
Subscriptions/Product Add-Ons/Bundles = **Characterized (E2)**; Composite
Products/Bookings = **Not evaluated (E0)**; no extension is **Integrated
(E3)** — pending by design (ADR-0024 explicit non-goal). **This audit does
not change any tier.**

### Persistence/schema confirmation

`Settings::SCHEMA_VERSION = 7` (`src/Settings.php:33`),
`OrderSnapshot::SCHEMA_VERSION = 5` (`src/Order/OrderSnapshot.php:54`),
`PersistedKeys::INVENTORY_VERSION = 10` (`src/PersistedKeys.php:37`), no DB
migration mechanism anywhere in `src/` or `uninstall.php`. Every persistence
write found in `src/` maps 1:1 to `PersistedKeys.php` and
`docs/PERSISTED_DATA.md`'s machine-checked inventory (verified by
`tests/unit/PersistedKeysInventoryTest.php`). **Zero undocumented persistence
found.**

---

## v1.0.0 falsification matrix (WP9)

Independent corrective/falsification review, per `M26_V1_READINESS_PLAN.md`
§11, executed after WP2–WP8. Every item closes green with cited evidence;
none required a corrective fix.

| # | Claim disproven? | Evidence |
|---|---|---|
| A | Clean install fails — **disproven** | WP8: zero warnings on a fresh disposable-environment install; correct in-memory defaults; correct lazy DB write only on first merchant save |
| B | Historical upgrade loses settings — **disproven** | WP2 fixture tests (schema 2/3/5 origins) + WP8's 6/6 real upgrade legs, every settings field preserved |
| C | Historical upgrade loses fixed pricing — **disproven** | WP8: `_umc_fixed_prices` byte-identical across the v0.19.0/v0.23.0/v0.24.0 legs |
| D | Disabled-currency data lost on upgrade — **disproven** | WP8: DKK (disabled) `manual_rate`/`enabled` preserved across all 6 legs |
| E | Historical orders change after upgrade — **disproven** | WP8: every leg's full order meta list byte-identical before/after |
| F | Reporting misreconstructs historical monetary state — **disproven** | Existing `OrderReportingRepository`/`TransactionCurrencyResolver` suite (753/753 integration) + WP3#1 cross-feature test |
| G | Current FX leaks into historical reporting — **disproven** | `ReportingArchitectureGuardTest` (read-only-over-historical-facts guard), re-run green |
| H | Fixed prices converted twice — **disproven** | WP3#2 `FixedPricingCurrencySwitchTest`: fixed price stable across repeated recalculation, converter never invoked |
| I | Store API differs from Classic — **disproven** | Existing `StoreApiTestCase` reconciliation suite (753/753) + WP4 Blocks journey (Cart/Checkout block render + gateway parity) |
| J | Refund accounting diverges — **disproven** | `RefundConversionTest` suite, part of the 753/753 integration pass |
| K | Visitor Location overrides manual choice — **disproven** | `GeoDetectionApplicator` gating tests, part of the 753/753 integration pass; WP3#1 additionally proves correct origin classification for both manual and Visitor-Location selection |
| L | Base currency acquires fixed-price metadata — **disproven** | WP3#3 `CsvImportCatalogOperationsInteractionTest` + existing M20 base-currency-exclusion guards |
| M | Stale rates silently look healthy — **disproven** | `RateHealthService`/`RateStatusEvaluator` suite, re-run green (42/42 targeted guard tests including this) |
| N | Live FX HTTP occurs on transaction path — **disproven** | `RatesPersistenceGuardTest` (HTTP confined to `WordPressHttpTransport`), re-run green |
| O | Reporting becomes unbounded — **disproven** | `ReportingPerformanceGuardTest`/`ReportingArchitectureGuardTest`, re-run green; WP5's `--group performance` re-run, all ceilings held |
| P | CSV raw-meta bypass returns — **disproven** | M25 Playwright TEST 11a–11d re-run unmodified, 22/22 green (WP4's full `run-acceptance.sh` pass) + `FixedPriceCsvIntegrationGuardTest` |
| Q | CSV formula injection returns — **disproven** | `ReportingCsvRenderer::escape_csv_cell()` guard test, re-run green |
| R | Extension compatibility overstated — **disproven** | WP5: `COMPATIBILITY.md` wording audit, E0/E2 tiers and "no Integrated" language confirmed unchanged |
| S | Admin action lacks capability/nonce protection — **disproven** | `SecuritySourceGuardTest` (whole-tree), re-run green; WP5 extended the narrative audited-surfaces table with no new gap found |
| T | REST exposes unauthorized mutation — **disproven** | `/wc/v3` vs `/wc/store/` boundary tests, part of the 753/753 integration pass |
| U | Bundled SVG/assets unsafe — **disproven** | `CurrencyPresentationAssetRegistryTest`, re-run green (registry-resolved bundled SVGs only) |
| V | Uninstall deletes data contrary to policy — **disproven** | `uninstall.php` re-read: exactly 3 `delete_option()` calls (`umc_settings`, `umc_rate_state`, `umc_reporting_cache_gen`), no other statement; matches ADR-0009 and its own docblock verbatim |
| W | Persisted-data inventory misses a write — **disproven** | Full `src/` persistence-primitive grep (options/meta/transients/cookies/session), zero undocumented writes found, confirmed twice (original audit + WP1) |
| X | Migration non-idempotent — **disproven** | WP2's 3 new idempotency assertions + WP8's explicit "IDEMPOTENT: identical" check at every one of the 6 upgrade legs |
| Y | Release ZIP ships tests/E2E/secrets — **disproven** | WP11: published `v1.0.0` artifact downloaded independently from GitHub and inspected — 358 entries, no `tests/`, no `node_modules`, no `.git`, no `.github`, no `.env`, no `CLAUDE.local.md` |
| Z | Version metadata diverges — **disproven** | `CompatibilityMatrixTest`/`DocumentationSyncTest`, re-run green post-bump (plugin header, `UMC_VERSION`, readme.txt Stable tag all `1.0.0`) |
| AA | WooCommerce floor fails — **disproven** | WP11: PR CI run 32006273943 and main CI run 32006445302 both green on the WC 8.2.5 floor leg |
| AB | PHP ceiling fails — **disproven** | WP11: PR CI run 32006273943 and main CI run 32006445302 both green on the PHP 8.4 ceiling leg |
| AC | Playwright can target production — **disproven** | `production-guard.ts` re-verified throughout WP4's real runs: refuses without `UMC_E2E_ALLOWED_HOSTS` explicitly set (no default), refuses on any host not in that explicit list; the hardcoded `dev.biopentra.eu` default was removed |
| AD | Rollback procedure corrupts data — **disproven** | WP8's explicit rollback rehearsal: all data intact and readable after code downgrade; guarantee scope (code rollback, not data rollback) stated precisely |
| AE | Documentation claims unsupported functionality — **disproven** | WP6/WP7: README.md/ARCHITECTURE.md/SWITCHER_CUSTOMIZATION.md corrected to actual v0.24.0+ capability set; no remaining overclaim found in the sweep |
| AF | M27/future-feature scope leaks into v1.0 — **disproven** | `git diff main...feature/m26-v1.0-readiness --stat`: **zero files changed under `src/`** across the entire milestone — every change is documentation, tests, or version/readme metadata. Structurally impossible for this diff to have added a feature, changed a schema, or promoted a compatibility tier |

**All 32/32 falsification items now close green** — items Y, AA, and AB
closed during WP11 once the actual PR/CI/release pipeline ran. No item
required a corrective fix.

---

## v1.0.0 release closure record

**Repository status:** **released as v1.0.0**. Milestone 26 (v1.0 Production
Readiness & Roadmap Closure; ADR-0031) is complete. Git tag **`v1.0.0`** and
GitHub release are published. Release commit on `main`:
`6eda199106d548fd4d51981649beb00e03df7d45`. Annotated tag object points at
that merge commit. PR **#27**.

| Item | Value |
|---|---|
| Version | **1.0.0** |
| Settings schema | **7** (unchanged) |
| Order snapshot schema | **5** (unchanged) |
| Persisted-data inventory version | **10** (unchanged) |
| Production migrations | none |
| New admin surface | none — hardening/documentation milestone, no product feature (zero files under `src/` changed by this milestone) |
| Third-party compatibility evidence | unchanged from Milestone 19 (E0/E2; no tier promoted) |
| Browser acceptance | 26/26 Playwright scenarios green against DEV (dev.biopentra.eu) — existing M25 CSV suite (22/22, re-verified unmodified) plus 4 new v1.0 journey scenarios (core purchase, Blocks, fixed-pricing x2) |
| Upgrade/rollback rehearsal | 6/6 historical-upgrade legs + clean install + rollback, all green, zero data loss (WP8; isolated disposable environment, never DEV or production) |
| Falsification matrix | 32/32 items closed green (29 at WP9; Y/AA/AB closed by this release's own CI/artifact) |
| Unresolved release blockers | **0** |
| Git tag `v1.0.0` | **Created** |
| GitHub release `v1.0.0` | **Published** |
| Milestone 26 | **Complete** — v1.0 Production Readiness & Roadmap Closure at v1.0.0 |
| PR CI run | **32006273943** — 14/14 jobs green, including the WC 8.2.5 floor leg and PHP 8.4 ceiling leg |
| Main CI run | **32006445302** — 14/14 jobs green on the actual merge commit `6eda199106d548fd4d51981649beb00e03df7d45` |
| Release workflow | **32006600411** (success) |
| Artifact | `universal-multicurrency-1.0.0.zip` (539358 bytes; SHA-256 `b119a23a93a5b9edd84c4fdc702ec861278ff27d4bf7c179e59e1ec875be39b3`) — downloaded from the published GitHub release itself (not a local build) and independently inspected: 358 entries, correct version metadata throughout (plugin header, `UMC_VERSION`, readme.txt Stable tag all `1.0.0`), all key v1.0-era classes present, no `tests/`, no `node_modules`, no `.git`, no `.github`, no `.env`, no `CLAUDE.local.md` |
| Manual/editor acceptance | Automated via the Playwright browser acceptance suite (real WooCommerce admin UI, real DEV environment) rather than a separate human walkthrough |
| Deployment | **Not performed** |

---

# Release audit — v0.24.0 Fixed Pricing CSV Interchange

Executable release-preparation gate for Universal Multicurrency **v0.24.0**.
This document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Repository status:** **released as v0.24.0**. Milestone 25 (Fixed Pricing
CSV Interchange; ADR-0030) is complete. Git tag **`v0.24.0`** and GitHub
release are published. Release commit on `main`:
`f1b718de70156cb2fd6370c259bcb7bb73d3f271`. Annotated tag object points at
that merge commit. PR **#26**.

---

## v0.24.0 release closure record

| Item | Value |
|---|---|
| Version | **0.24.0** |
| Settings schema | **7** (unchanged) |
| Order snapshot schema | **5** (unchanged) |
| Persisted-data inventory version | **10** (unchanged) |
| Production migrations | none |
| New admin surface | Fixed Pricing screen gains a discoverability callout linking to WooCommerce's native Products -> Export/Import; no new admin page |
| New CSV columns | `umc_fixed_regular_{code}` / `umc_fixed_sale_{code}` on WooCommerce's native product export/import |
| Browser acceptance | **22/22 Playwright scenarios green** against DEV (dev.biopentra.eu, WordPress 7.0.4, WooCommerce 10.9.4) -- all 19 mandatory acceptance cases plus all four raw-meta defense scenarios (A-D) plus a dedicated new-product raw-meta case; satisfies the release-blocking browser acceptance gate in lieu of a separate human walkthrough |
| Unresolved release blockers | **0** |
| Git tag `v0.24.0` | **Created** |
| GitHub release `v0.24.0` | **Published** |
| Milestone 25 | **Complete** — Fixed Pricing CSV Interchange at v0.24.0 |
| PR CI run | **31967134065** (SHA `fde8c4517994db1594af101ad843ba50104df54a`) -- 14/14 jobs green, including the WC 8.2.5 floor leg |
| Main CI run | **31968672928** (SHA `f1b718de70156cb2fd6370c259bcb7bb73d3f271`) -- 14/14 jobs green (a first attempt failed instantly on every job due to an account billing/spending-limit block unrelated to the code; resolved externally, then a full re-run passed cleanly) |
| Release workflow | **31969844441** (success) |
| Artifact | `universal-multicurrency-0.24.0.zip` (539009 bytes; SHA-256 `afd64b3ef8ce8201fcb9c3616dc95099c82ff548ec582d7f4f74d6f13f39e953`) -- downloaded from the published GitHub release itself (not a local build) and independently inspected: correct version metadata throughout, `FixedPriceCsvIntegration`/`FixedPriceDocumentMerger`/the raw-meta defense/all six WC hook registrations present, no `tests/`, no `tests/e2e`, no `node_modules`, no `.git` |
| Manual/editor acceptance | Automated via the Playwright browser acceptance suite above (real WooCommerce admin UI, real DEV environment) rather than a separate human walkthrough of the same steps |
| Deployment | **Not performed** |

---

# Release audit — v0.23.0 Fixed Pricing Catalog Operations

Executable release-preparation gate for Universal Multicurrency **v0.23.0**.
This document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Repository status:** **released as v0.23.0**. Milestone 24 (Fixed Pricing
Catalog Operations; ADR-0029) is complete. Git tag **`v0.23.0`** and GitHub
release are published. Release commit on `main`:
`27ca8f3eaf12b7fdb856685d81b4757d13ce6e76`. Annotated tag object points at
that merge commit. PR **#25**.

---

## v0.23.0 release closure record

| Item | Value |
|---|---|
| Version | **0.23.0** |
| Settings schema | **7** (unchanged) |
| Order snapshot schema | **5** (unchanged) |
| Persisted-data inventory version | **10** (unchanged) |
| Production migrations | none |
| New admin surface | Fixed Pricing screen (`SettingsPage::SECTION_FIXED_PRICING`), Products-list coverage column |
| New CLI commands | `wp umc prices list\|seed\|clear` |
| Unresolved release blockers | **0** |
| Git tag `v0.23.0` | **Created** |
| GitHub release `v0.23.0` | **Published** |
| Milestone 24 | **Complete** — Fixed Pricing Catalog Operations at v0.23.0 |
| PR CI run | **31904838959** (SHA `2bd4675e3a93e1eeb6d02e79459b4ca1454a0566`) |
| Main CI run | **31904962061** (SHA `27ca8f3eaf12b7fdb856685d81b4757d13ce6e76`) |
| Release workflow | **31905153345** (success) |
| Artifact | `universal-multicurrency-0.23.0.zip` (529564 bytes; SHA-256 `819d62421b103cc411be313e9f58df43875a536f3bdb300cebfb3fa120711c0e`) |
| Manual/editor acceptance | **Not performed** — full automated coverage only (1163 unit + 684 integration tests exercising the real admin screen render, real WordPress/WooCommerce hooks, and real product/variation fixtures); no human browser walkthrough was performed for this closure |
| Deployment | **Not performed** |

---

# Release audit — v0.22.0 Native Switcher Block

Executable release-preparation gate for Universal Multicurrency **v0.22.0**.
This document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Repository status:** **released as v0.22.0**. Milestone 23 (Native Switcher Block &
Rendering Surface Integration; ADR-0028) is complete. Git tag **`v0.22.0`** and
GitHub release are published. Release commit on `main`:
`4edc0c33d49802e1af07c555696e44b16908e8d7`. Annotated tag object points at that
merge commit. PR **#24**.

---

## v0.22.0 release closure record

| Item | Value |
|---|---|
| Version | **0.22.0** |
| Settings schema | **7** (unchanged) |
| Order snapshot schema | **5** (unchanged) |
| Persisted-data inventory version | **10** (unchanged) |
| Production migrations | none |
| Native block | `universal-multicurrency/currency-switcher` (dynamic PHP render) |
| Unresolved release blockers | **0** |
| Git tag `v0.22.0` | **Created** |
| GitHub release `v0.22.0` | **Published** |
| Milestone 23 | **Complete** — Native Switcher Block at v0.22.0 |
| PR CI run | **31802299230** (SHA `44d0705c75dfb20c01c0197dfe8623a04c8ffff7`) |
| Main CI run | **31802429794** (SHA `4edc0c33d49802e1af07c555696e44b16908e8d7`) |
| Release workflow | **31802649108** (success) |
| Artifact | `universal-multicurrency-0.22.0.zip` (504789 bytes; SHA-256 `159e743a…`) |
| Manual/editor acceptance | **Partial** — dev bind-mount: plugin 0.22.0 active, block registered via WP-CLI; full Site Editor checklist not authenticated |
| Deployment | **Not performed** |

---

# Release audit — v0.21.0 Switcher Currency Presentation

Executable release-preparation gate for Universal Multicurrency **v0.21.0**.
This document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Repository status:** **released as v0.21.0**. Milestone 22 (Switcher Currency
Presentation; ADR-0027) is complete. Git tag **`v0.21.0`** and GitHub release are
published. Release commit on `main`:
`86fa5da7c10cc00b7fec6f58599181dea3edbe37`. Annotated tag object points at that
merge commit. PR **#23**.

---

## v0.21.0 release closure record

| Item | Value |
|---|---|
| Version | **0.21.0** |
| Settings schema | **7** (`display.presentation.*`, `show_icon`) |
| Order snapshot schema | **5** (unchanged) |
| Persisted-data inventory version | **10** (unchanged) |
| Production migrations | `SettingsUpgrader::migrate_6_to_7` |
| DB migration | none |
| Presentation icons | Bundled registry SVGs; optional; default off |
| Unresolved release blockers | **0** |
| Git tag `v0.21.0` | **Created** |
| GitHub release `v0.21.0` | **Published** |
| Milestone 22 | **Complete** — Switcher Currency Presentation at v0.21.0 |
| PR CI run | **31794921435** (SHA `d21800ec117a76dfa21455a94b76ff1d22fbe903`) |
| Main CI run | **31795051814** (SHA `86fa5da7c10cc00b7fec6f58599181dea3edbe37`) |
| Release workflow | **31795177517** (success) |
| Artifact | `universal-multicurrency-0.21.0.zip` (498699 bytes; SHA-256 `cc9fef85…`) |
| Deployment | **Not performed** |

---

# Release audit — v0.20.0 Multicurrency Reporting & Analytics Foundation

Executable release-preparation gate for Universal Multicurrency **v0.20.0**.
This document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Governing question:** If we published this release tomorrow, is there anything
left in the repository that clearly should not ship?

**Repository status:** **released as v0.20.0**. Milestone 21 (Multicurrency
Reporting & Analytics Foundation; ADR-0026) is complete. Git tag **`v0.20.0`**
and GitHub release are published. Release commit on `main`:
`b751b70d7babd7b81bf45c461897e2793c46c34b`. Annotated tag object points at that
merge commit. PR **#22**.

---

## v0.20.0 release closure record

| Item | Value |
|---|---|
| Version | **0.20.0** |
| Settings schema | **6** (unchanged) |
| Order snapshot schema | **5** (`_umc_currency_origin`) |
| Persisted-data inventory version | **10** (origin meta + reporting cache option + `umc_report_*` transients) |
| Production migrations | none |
| Reporting | Native transaction-currency admin reports + CSV; no FX normalization |
| Unresolved release blockers | **0** |
| Git tag `v0.20.0` | **Created** |
| GitHub release `v0.20.0` | **Published** |
| Milestone 21 | **Complete** — Multicurrency Reporting at v0.20.0 |
| PR CI run | **31783582632** (SHA `348a219c8c33de3c0408985c17da901a91e5ae58`) |
| Main CI run | **31783729345** (SHA `b751b70d7babd7b81bf45c461897e2793c46c34b`) |
| Release workflow | **31783955851** (success) |
| Artifact | `universal-multicurrency-0.20.0.zip` (488136 bytes; SHA-256 `8fc04afa…`) |
| Deployment | **Not performed** |

---

# Release audit — v0.19.0 Authoritative Fixed Product Pricing (Phase 1)

Executable release-preparation gate for Universal Multicurrency **v0.19.0**.
This document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Governing question:** If we published this release tomorrow, is there anything
left in the repository that clearly should not ship?

**Repository status:** **released as v0.19.0**. Milestone 20 (Authoritative
Per-Currency Fixed Product Pricing, Phase 1; ADR-0025) is complete. Git tag
**`v0.19.0`** and GitHub release are published. Release commit on `main`:
`bdc4b4f813dbd98cba3de08650d7ce49b3229895`. Annotated tag object points at that
merge commit. PR **#21**.

---

## v0.19.0 release closure record

| Item | Value |
|---|---|
| Version | **0.19.0** |
| Settings schema | **6** (unchanged) |
| Order snapshot schema | **4** (unchanged) |
| Persisted-data inventory version | **9** (`_umc_fixed_prices`, line-item provenance) |
| Production migrations | none |
| Fixed product pricing | Simple + variations; WC sale schedule gates fixed sale |
| Unresolved release blockers | **0** |
| Git tag `v0.19.0` | **Created** |
| GitHub release `v0.19.0` | **Published** |
| Milestone 20 | **Complete** — Authoritative Fixed Product Pricing at v0.19.0 |
| PR CI run | **31737126104** (SHA `244b1be3edc307f1c61a5893421d0a7a9747c766`) |
| Main CI run | **31737273088** (SHA `bdc4b4f813dbd98cba3de08650d7ce49b3229895`) |
| Release workflow | **31737524502** (success) |
| Artifact | `universal-multicurrency-0.19.0.zip` (456721 bytes; SHA-256 `d11bc0da…`) |
| Deployment | **Not performed** |

---

# Release audit — v0.18.0 Third-Party Extension Compatibility

Executable release-preparation gate for Universal Multicurrency **v0.18.0**.
This document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Governing question:** If we published this release tomorrow, is there anything
left in the repository that clearly should not ship?

**Repository status:** **released as v0.18.0**. Milestone 19
(Third-Party Extension Compatibility Framework; ADR-0024) is complete. Git tag
**`v0.18.0`** and GitHub release are published. Release commit on `main`:
`2c80db3ca3807ffb141dab05f17db0a97ec1a864`. Annotated tag object points at that
merge commit. PR **#20**.

---

## v0.18.0 release closure record

| Item | Value |
|---|---|
| Version | **0.18.0** |
| Settings schema | **6** (unchanged) |
| Order snapshot schema | **4** (unchanged) |
| Persisted-data inventory version | **8** (unchanged; no new option or meta key) |
| Production migrations | unchanged |
| Extension evidence model | E0–E3 with Characterized sub-labels |
| Priority adapters | Subscriptions, Product Add-Ons, Product Bundles (**Characterized E2 only**; not Integrated) |
| Unresolved release blockers | **0** |
| Git tag `v0.18.0` | **Created** |
| GitHub release `v0.18.0` | **Published** |
| Milestone 19 | **Complete** — Third-Party Extension Compatibility at v0.18.0 |
| E3 real-extension validation | **Pending** — no named premium extension claimed Integrated |

---

# Release audit — v0.17.0 WooCommerce Compatibility & Transaction Integrity

Executable release-blocking gate for Universal Multicurrency **v0.17.0**. This
document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Governing question:** If we published this release tomorrow, is there anything
left in the repository that clearly should not ship?

**Repository status:** **released as v0.17.0**. Milestone 18 (WooCommerce
Compatibility & Transaction Integrity; ADR-0023) is complete. Git tag
**`v0.17.0`** and GitHub release are published. Release commit on `main`:
`9ed710f37fbd917591e9b6ba5de07ffab5f00a8b`. Annotated tag object:
`f8285f450a5a3b9c76020bb22c6ccaf3e95d93dd`. PR **#19**.

---

## Release closure record

| Item | Value |
|---|---|
| Version | **0.17.0** |
| Settings schema | **6** (unchanged) |
| Order snapshot schema | **4** (unchanged) |
| GeoContext (sandbox document) schema | **2** (unchanged) |
| Persisted-data inventory version | **8** (unchanged; no new option or meta key) |
| Production migrations | **v0 → v1**, **v1 → v2**, **v2 → v3**, **v3 → v4**, **v4 → v5**, **v5 → v6** |
| Unresolved Critical security findings | **0** |
| Unresolved High security findings | **0** |
| Unresolved release blockers | **0** |
| Open Milestone 8 review findings | **0** |
| Git tag `v0.8.0` | **Created** |
| Milestone 8 | **Complete** — released and review-closed at v0.8.0 |
| Git tag `v0.17.0` | **Created** |
| GitHub release `v0.17.0` | **Published** |
| Milestone 17 | **Complete** — Switcher Customization & Presentation at v0.16.0 |
| Milestone 18 | **Complete** — WooCommerce Compatibility & Transaction Integrity at v0.17.0 |

---

## v0.17.0 WooCommerce Compatibility & Transaction Integrity scope

Minor release shipping Milestone 18 (ADR-0023). Formalizes transaction-integrity
invariants against WooCommerce core, converts free-shipping `min_amount` into
the active currency at eligibility time, expands Classic ↔ Store API parity and
cart transition coverage, and publishes an evidence-linked compatibility matrix.
Fees remain intentionally unwired. Third-party extensions stay out of scope
(M19).

Settings schema **6**, PersistedKeys inventory **8**, and order snapshot schema
**4** are unchanged. No DB migration.

### Shipped capabilities

| Area | Summary |
|---|---|
| Free-shipping threshold | Base-authored `min_amount` converted once at evaluation via `woocommerce_shipping_free_shipping_is_available` |
| Transaction integrity matrix | Evidence-linked rows in `COMPATIBILITY.md` with Supported / Characterized / Known limitation / Out of scope |
| Parity & transitions | Expanded Classic/Store API, cart currency/rate transitions, variation hash currency+rate, REST `/wc/v3` vs `/wc/store/` boundary |
| Fee boundary | Characterized non-conversion; guards unchanged |

For prior release history (v0.16.0 and earlier), see git history of this file
and `docs/ROADMAP.md`.

---

## v0.15.0 Exchange Rate Operations & Reliability scope (shipped)

Minor release shipping Milestone 16 (ADR-0021). Hardens the Milestone 8 rate
stack into an operationally trustworthy subsystem: shared health reporting,
presentation-only aging, scheduler correctness for effective automatic targets,
structured refresh failures, admin ops UX, order rate provenance schema 4, and
thin WP-CLI. Settings schema unchanged; no DB migration; storefront conversion
semantics for stale rates unchanged — no live provider HTTP on storefront.

### Shipped capabilities

| Area | Summary |
|---|---|
| Health model | `RateHealthService` / `RateHealthReport` shared by admin, Site Health, Compatibility, CLI (no HTTP, no mutations) |
| Aging | Presentation-only status at 50% of `rate_max_age_hours`; never blocks conversion |
| Scheduler | `has_automatic_targets` — schedule when any currency has effective automatic mode; Action Scheduler is next-run truth |
| Failure taxonomy | Unified `RateFetchResult` outcomes (success / partial / total / not modified / no targets) |
| Admin ops UI | Exchange Rates operations experience (health, aging, force refresh) |
| Order snapshot schema 4 | Additive `_umc_rate_provider`, `_umc_rate_adjustment` |
| Diagnostics alignment | Site Health / Compatibility consume the same health report |
| CLI | Thin `wp umc rates status\|refresh\|list` wrapper |
| Non-goals | No multi-provider failover; stale rates remain non-blocking for conversion |

---

## v0.14.0 Currency Resolution & Explainability scope (shipped)

Minor release shipping Milestone 15 (ADR-0020). Adds structured shopper
currency evaluation, explanatory session provenance, an on-demand decision
explanation composer, and a stateless Decision Inspector admin section.
No settings schema change; storefront currency-decision behaviour is
unchanged — locked by characterization tests and resolve/evaluate parity.

### Shipped capabilities

| Area | Summary |
|---|---|
| `CurrencyResolutionResult` | Structured shopper ladder evaluation; truthful winning sources `explicit`/`session`/`cookie`/`base` |
| Provenance | Session key `umc_currency_origin` (`customer` \| `visitor_location`); never affects precedence |
| Explainer | `UMC\Decision\CurrencyDecisionExplainer` composes resolver + geo simulate + checkout policy |
| Decision Inspector | New Multicurrency settings section (stateless simulation; no user-meta result persistence) |
| Design system | Reusable `decision_timeline()` component |
| Persisted-data inventory | Inventory v6 → v7 (`umc_currency_origin`) |
| Non-consolidation | `GeoCurrencyDecisionService` left intact after skip-reason characterization gap |

---

## v0.13.0 Visitor Location boundary alignment scope (shipped)

Minor release shipping Milestone 14 (ADR-0018). Admin-only realignment with
Universal Geo Context: the Visitor Location hub shrinks from seven panels to
three, Overview becomes a merchant dashboard, Currency Routing absorbs the
former Settings panel and presents rules as policy statements, and Currency
Simulation (formerly Geo Sandbox) replaces raw JSON with design-system
output. No settings schema change; storefront currency-decision behaviour
is unchanged — verified by characterization tests over the provider
chain and applicator gate order.

### Shipped capabilities (v0.13.0)

| Area | Summary |
|---|---|
| `UgcIntegrationStatus` | Single source of truth for Universal Geo Context availability |
| Visitor Location IA | Overview, Currency Routing, Currency Simulation |
| Persisted-data inventory | Inventory v5 → v6 (sandbox user-meta keys) |

---

## v0.12.0 Geo admin hub scope (superseded)

Minor release shipping Milestone 13. Admin-only refactor: Geo Detection settings
become a panel-based hub with GeoContext sandbox simulation. No settings schema
change; storefront geo routing is unchanged.

### Shipped capabilities

| Area | Summary |
|---|---|
| Geo hub navigation | Overview, Detection, Geo Sandbox, Providers, Proxies, Diagnostics, Settings |
| GeoContext v1 | Versioned document for sandbox input/output (ADR-0017) |
| Geo Sandbox | Presets, recent countries, structured JSON trace via admin-post |
| Panel-aware saves | Detection panel saves rules only; Settings panel saves operational options |
| Legacy simulation | `umc_geo_simulate` redirects to Geo Sandbox |

---

## v0.11.0 Geo Detection scope (superseded)

Minor release shipping Milestone 12. Settings schema v4→v5 adds Geo Detection
defaults with the feature disabled and no rules. Storefront behaviour is
unchanged until an administrator enables and configures routing.

### Shipped capabilities

| Area | Summary |
|---|---|
| Geo Detection settings | Ordered country/region/Other rules; modes `first_visit`, `session`, `until_manual` |
| Region registry v1 | EU, Eurozone, EEA presets (ADR-0016) |
| Country providers | Optional Universal Geo Context; WooCommerce billing/shipping + geolocation fallback |
| Storefront application | `GeoDetectionApplicator` with manual-selection and checkout-lock precedence |
| Admin tools | Recommended European rules, read-only simulation, Site Health `umc_geo_configuration` |

---

## v0.10.0 Checkout currency policy scope (superseded)

Minor release shipping Milestone 11 (Product Milestone 5 — Checkout). Settings
schema v3→v4 adds checkout defaults preserving v0.9.x behaviour. Order snapshot
v3 adds checkout audit metadata.

### Shipped capabilities

| Area | Summary |
|---|---|
| Checkout settings | `checkout.mode` (`selected` \| `store`), `checkout.show_notice` |
| Policy orchestration | `CheckoutPolicyCoordinator` with WooCommerce-authoritative gateway evaluation |
| Gateway causality | `GatewayCurrencyClassifier` + request-scoped `GatewayCurrencyEvaluation` |
| Classic + Blocks parity | Shared policy; Blocks notices via `extensions.umc.checkout_notice` + `checkout-notice.js` |
| Order snapshot v3 | `_umc_checkout_mode`, `_umc_shopper_currency`, `_umc_fallback_occurred` |
| Admin | Checkout settings tab; `CheckoutConfigurationCheck` diagnostics |

See [`docs/adr/0014-checkout-currency-policy.md`](adr/0014-checkout-currency-policy.md).

---

## Prior release — v0.9.1 Compatibility diagnostics

Executable release-blocking gate for Universal Multicurrency **v0.9.1**. This
section is retained for historical audit context.

**Repository status:** **released as v0.9.1** on `main`.

### Merchant-visible features

| Capability | Implementation |
|---|---|
| Compatibility diagnostics center | `CompatibilitySettingsField`, grouped local checks, support report |
| Copy Report action | `assets/admin/umc-compatibility.js` |
| Configuration warning accuracy | `SettingsConfigurationValidator` metadata/base handling |
| Single-currency rate updates | Scoped operational status in `ExchangeRateStore` |

## v0.9.0 Display Configurator scope

Prepared feature release. **No new settings schema bump** beyond schema v3
already on `main` — safe in-place upgrade from **0.8.x**.

### Merchant-visible features

| Capability | Implementation |
|---|---|
| Display settings configurator | `Admin\DisplaySettingsField`, `Admin\DisplayControlRenderer`, `Admin\AdminPageShell` |
| Visual placement and style controls | Choice cards, segmented controls, dual position panels |
| Floating Side / Floating Bottom | `Display\SwitcherSettings` placement modes + storefront CSS |
| Manual shortcode helper | Copy action for `[umc_currency_switcher]` |
| Live responsive preview | Admin preview frame, `assets/admin/umc-settings.js` |
| Sticky Display save + unsaved indicator | `SettingsPage::render_display_sticky_actions()` |
| Storefront switcher | `Display\SwitcherRenderer`, `assets/css/switcher.css`, `assets/js/switcher.js`, shortcode |
| Inactive placement preservation | `DisplaySettingsField::merge_position_preserving_inactive()` |

### Post-redesign verification fixes on `main`

| Change | Commit |
|---|---|
| Disabled preview overlay when switcher is on | `3b196ad` |
| Segmented appearance controls update live preview | `44742cc` |
| Display save clears WooCommerce leave-site prompt | `59d83ff` |
| Right-side dropdown opens inward | `59d83ff` |

---

## v0.8.1 maintenance scope (historical)

Prepared maintenance release. **No settings schema change** — safe in-place
upgrade from v0.8.0.

### Merchant-visible fixes

| Change | Commit |
|---|---|
| Recurring rate updates reschedule when `rate_update_interval` changes | `0eee862` |
| Merchant rate edits refresh `rate_updated_at` when rate inputs change | `b826481` |
| Plugin header description reflects manual and automatic exchange rates | `7ee8e9b` |

### Repository alignment shipped with 0.8.1 (not new user-facing features)

| Change | Commit |
|---|---|
| v1 → v2 conversion-fidelity regression guard | `137f129` |
| HTTP 304 zero-`umc_settings`-write performance ceiling | `88bfa44` |
| Site Health rate diagnostics integration coverage | `045ac34` |
| Manual rate-update controller round-trip integration coverage | `045ac34` |
| Milestone 8 documentation synchronization | `045ac34` |
| Documentation consistency audit | `470ba45` |
| Uninstall performance guard aligned to both configuration options | `7ee8e9b` |

---

## Milestone 8 shipped scope

Automatic exchange-rate provisioning, delivered in v0.8.0 and unchanged since:

| Capability | Implementation |
|---|---|
| Provider abstraction | `Rates\ExchangeRateSource`; selected by `rate_provider` through the `umc_exchange_rate_sources` filter (ADR-0010) |
| Frankfurter provider | `Rates\Providers\FrankfurterRateSource` over the injectable `Rates\Http\HttpTransport` |
| Conditional HTTP | `If-None-Match` / `If-Modified-Since` from stored `ProviderMetadata`; HTTP 304 → `RateFetchResult::not_modified()` (ADR-0013) |
| Persistence boundary | `Rates\ExchangeRateStore` — the only writer of provider rates and of `umc_rate_state` |
| Operational state | `Rates\RateUpdateState` (`umc_rate_state`), separate from merchant configuration (ADR-0012) |
| Orchestration | `Rates\RateUpdateService` — lock, fetch once, persist, fire `umc_rate_fetch_completed` |
| Scheduling | `Rates\Scheduler` on Action Scheduler hook `umc_run_rate_update` (ADR-0011) |
| Derived rates | `Rates\RateResolver` — effective rate derived on read, never persisted |
| Admin surfaces | `Admin\ExchangeRateSettingsField`, `Admin\CurrencyTableField`, `Admin\RateUpdateController`, `Admin\RateFailureNotice` |
| Diagnostics | `umc_rate_health` Site Health test plus two debug counters |

---

## Post-release review findings

The Milestone 8 review was run against the frozen plan after release. Every
finding is closed; each row names the commit that closed it.

| # | Finding | Disposition | Commit |
|---|---|---|---|
| 1 | `Scheduler::ensure_scheduled()` returned early whenever any recurring action existed, so changing `rate_update_interval` never rescheduled | **Fixed** — schedule recurrence is compared against the configured interval; duplicates collapse to one | `0eee862` |
| 2 | Admin saves preserved `rate_updated_at` when `manual_rate`, `merchant_adjustment`, or `rate_mode` changed, so merchant edits looked older than they were | **Fixed** — `CurrencyTableField::rate_inputs_changed()` bumps the timestamp on a real change | `b826481` |
| 3 | No regression proof that the v1 → v2 migration leaves manual-mode conversion output byte-identical | **Covered by tests** — `tests/unit/SettingsMigrationFidelityTest.php` | `137f129` |
| 4 | No named write ceiling proving an HTTP 304 update performs zero `umc_settings` writes | **Covered by tests** — `CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES = 0`, enforced at unit, integration-baseline, and controller layers | `88bfa44`, `045ac34` |
| 5 | Site Health rate diagnostics had no behavioural integration coverage | **Covered by tests** — `tests/integration/Diagnostics/SiteHealthRateIntegrationTest.php` | `045ac34` |
| 6 | No round-trip test proving the admin update request reaches the real service and persistence boundary | **Covered by tests** — `tests/integration/Rates/RateUpdateControllerIntegrationTest.php` | `045ac34` |
| 7 | Documentation still described v0.8.0 as unreleased and carried Milestone 7 schema and version facts | **Documented** — `045ac34` synchronizes RELEASE_AUDIT, ROADMAP, ARCHITECTURE, MIGRATION, SECURITY_REVIEW, HOOKS, PERFORMANCE_BASELINES | `045ac34` |

No production behaviour changed in findings 3–7; they are test and
documentation closure only.

---

## Test coverage at closure

| Suite | Command | Tests |
|---|---|---|
| Unit (no WordPress) | `composer test:unit` | 592 |
| Integration (WordPress + WooCommerce, HPOS) | `composer test:integration` | 379 |
| Performance ceilings | `--group performance` on both suites | 23 integration + 7 unit |

Milestone 8 specific suites:

- `tests/unit/Rates/` — provider parsing, fetch results, store write ordering, 304 write ceiling
- `tests/unit/SettingsMigrationFidelityTest.php` — v1 → v2 conversion fidelity
- `tests/integration/Rates/SchedulerIntegrationTest.php` — Action Scheduler interval reconciliation
- `tests/integration/Rates/RateUpdateControllerIntegrationTest.php` — `admin_post_umc_update_rates` round trip
- `tests/integration/Diagnostics/SiteHealthRateIntegrationTest.php` — `umc_rate_health` states
- `tests/integration/CurrencyTableFieldRateTimestampTest.php` — merchant-edit timestamp semantics

### Checks that depend on CI

Integration and performance suites need a live WordPress install and a
MySQL/MariaDB server. They run locally against **one** coordinate
(PHP 8.1 / WP 7.0 / WC 10.9.4) using the harness in `tests/bin/install-wp.sh`.

The following remain **CI-only** and are not claimed as locally executed:

| Check | Why |
|---|---|
| Four remaining integration matrix legs (`floor`, `mixed-php-floor`, `mixed-wp-floor`, `ceiling`) | Each needs its own pinned PHP/WP/WC coordinate; only `current` is reproducible locally |
| `composer audit` | Requires network access to the advisory database |
| Mutation testing (`composer test:mutation`) | Runtime is impractical outside CI |

`composer phpcs`, `composer test:unit`, `composer test:integration` (the
`current` leg), both `--group performance` runs, `composer make-pot:check`, and
`composer release-audit` — which includes the `composer install --no-dev` round
trip, `bin/build-zip.sh`, and `bin/inspect-release-zip.php` — were executed
locally at closure and passed.

---

## Audit scope

| Area | Executable enforcement |
|---|---|
| Repository hygiene | `ReleaseAuditTest` tracked-file scan; `.gitignore` policy |
| Prohibited foreign coupling | `ReleaseAuditTest` + `DiagnosticsBoundaryGuardTest` (ADR-0003) |
| Metadata / compatibility | `ReleaseAuditTest`, `CompatibilityMatrixTest`, `DocumentationSyncTest`, plugin header |
| Persisted-data contract | `PersistedKeysInventoryTest`, `UninstallPolicyGuardTest` |
| Settings upgrade | `ReleaseAuditTest`, `SettingsUpgraderTest`, integration upgrade tests |
| Translation readiness | `TranslationReadinessTest`, `composer make-pot:check` |
| Security gate | `docs/SECURITY_REVIEW.md`, `SecuritySourceGuardTest`, behavioural tests |
| Performance gate | `docs/PERFORMANCE_BASELINES.md`, `@group performance` tests |
| Release ZIP | `bin/build-zip.sh`, `ReleaseZipInspector`, `bin/inspect-release-zip.php` |
| CI configuration | `ReleaseAuditTest` required-job guard; `.github/workflows/ci.yml` |
| Dependencies | `composer audit`; production `require` is PHP only |
| Documentation | `DocumentationSyncTest` |

---

## Canonical command

```bash
composer release-audit
```

Equivalent:

```bash
bash bin/release-audit.sh
```

The script runs, in order:

1. PHPCS (0 errors, 0 warnings)
2. PHPUnit `@group release-audit`
3. Security source guards (`SecuritySourceGuardTest`)
4. Performance guards (`PerformanceGuardTest`, unit `PerformanceBaselineTest`)
5. Persisted-data inventory (`PersistedKeysInventoryTest`)
6. `composer make-pot:check`
7. `composer audit`
8. `composer install --no-dev` + `bin/build-zip.sh`
9. Restore dev dependencies (`composer install`)
10. `bin/inspect-release-zip.php` on the built archive
11. PHPUnit ZIP gate (`UMC_RELEASE_ZIP` set)

Exit code **non-zero** when any release-blocking step fails.

---

## Release-blocking criteria

| ID | Criterion | Result (v0.17.0) |
|---|---|---|
| RB1 | No tracked secrets, dumps, caches, or `dist/` artifacts | **Pass** |
| RB2 | `docs/plans/` remains untracked (local-only planning) | **Pass** |
| RB3 | No foreign switcher runtime coupling outside allowlisted manifest | **Pass** |
| RB4 | Plugin header, `UMC_VERSION`, readme Stable tag, text domain, PHP/WC metadata consistent | **Pass** |
| RB5 | `Settings::SCHEMA_VERSION === 6`; production migrations v0 → v1 → v2 → v3 → v4 → v5 → v6 only | **Pass** |
| RB6 | Persisted-key inventory matches docs + implementation (`umc_settings`, `umc_rate_state`, `umc_dismissed_notices`) | **Pass** |
| RB7 | Uninstall deletes configuration options (`umc_settings`, `umc_rate_state`); preserves commerce + dismissal meta | **Pass** |
| RB8 | `SECURITY_REVIEW.md`: zero open Critical/High | **Pass** |
| RB9 | `PERFORMANCE_BASELINES.md` present; deterministic ceilings enforced | **Pass** |
| RB10 | POT drift check passes; POT + readme.txt ship in release ZIP | **Pass** |
| RB11 | `composer audit` clean on production dependencies | **Pass** |
| RB12 | Release ZIP contains production tree only (see below) | **Pass** |
| RB13 | CI declares phpcs, pot, unit, integration, performance, build, release-audit jobs | **Pass** |
| RB14 | No debug logging or stale TODO/FIXME in `src/` | **Pass** |
| RB15 | No transients / object-cache persistence in `src/` | **Pass** |
| RB16 | Advanced Custom CSS requires `edit_css`; unauthorized replace / clear / omission preserve stored CSS, and stored CSS is re-validated before storefront emission | **Pass** |

**Unresolved blockers:** **0**

---

## Repository hygiene

| Check | Result |
|---|---|
| Tracked swap/backup/tmp/log/env/key files | None |
| Tracked `vendor/`, `dist/`, `tests/tmp/`, `.phpunit.result.cache` | None |
| Tracked `docs/plans/` | None (local-only) |
| Secret-like patterns in tracked text | None detected |
| `.gitignore` covers vendor, dist, test tmp, PHPUnit cache | Yes |

**Non-blocking:** `docs/plans/` may exist locally for agent planning; it must never be committed.

---

## Prohibited dependency and coupling (ADR-0003)

Runtime `src/` is scanned for prohibited foreign switcher coupling (options,
requires, `class_exists` probes, branded product strings). Passive detection
needles remain confined to `Diagnostics/DetectorManifest.php` only.

**Result:** No prohibited runtime coupling detected.

---

## Metadata and compatibility

| Field | Value (v0.17.0) |
|---|---|
| Plugin version (header + `UMC_VERSION`) | **0.17.0** |
| readme.txt Stable tag | **0.17.0** |
| Text domain | `universal-multicurrency` |
| Requires PHP | 8.1 |
| Requires Plugins | woocommerce |
| Composer license | GPL-2.0-or-later |
| Production Composer deps | `php >=8.1` only |

Compatibility matrix and CI legs: see [`COMPATIBILITY.md`](COMPATIBILITY.md).

---

## Persisted-data audit

Authoritative registry: [`PERSISTED_DATA.md`](PERSISTED_DATA.md) +
[`src/PersistedKeys.php`](../src/PersistedKeys.php) (`INVENTORY_VERSION = 8`).

- All persisted keys registered and documented, including the Milestone 8
  operational-state option `umc_rate_state` and Milestone 16 order provenance
  keys `_umc_rate_provider` / `_umc_rate_adjustment`
- No undocumented transients or object-cache keys
- Uninstall policy matches ADR-0009

---

## Settings upgrade audit

- `Settings::SCHEMA_VERSION` is **6**
- Production migration map: **v0 → v1**, **v1 → v2**, **v2 → v3**, **v3 → v4**,
  **v4 → v5**, **v5 → v6**
- This release adds the **v5 → v6** `display` restructure; it is lossless and
  visually neutral (`tests/unit/SettingsMigrationV5ToV6Test.php`,
  `tests/unit/Display/LegacyAppearanceMatrixTest.php`)
- Canonical reads avoid writes; failed migrations do not persist partial data
- Conversion output is byte-identical across the v1 → v2 boundary in manual mode
  (`tests/unit/SettingsMigrationFidelityTest.php`)
- [`MIGRATION.md`](MIGRATION.md) documents manual-only migration (no foreign import)

---

## Translation audit

- Canonical text domain enforced (`TranslationReadinessTest`)
- Committed `languages/universal-multicurrency.pot`
- `composer make-pot:check` passes
- Release ZIP includes `languages/universal-multicurrency.pot` and `readme.txt`
- No shipped frontend JavaScript requiring i18n
- No bundled locale `.mo` files in this RC
- RTL audit documented in [`TRANSLATION.md`](TRANSLATION.md) (audit-only)

---

## Security gate

| Check | Result |
|---|---|
| `docs/SECURITY_REVIEW.md` | Present; covers the Milestone 8 provider and update surfaces |
| Open Critical findings | 0 |
| Open High findings | 0 |
| Accepted Medium/Low documented | Yes (M1–M4, L1–L2) |
| `SecuritySourceGuardTest` | Enforced in release-audit script |
| `composer audit` | Clean |

---

## Performance gate

| Check | Result |
|---|---|
| `docs/PERFORMANCE_BASELINES.md` | Present |
| Wall-clock thresholds in CI | None |
| Transients / persistent cache in `src/` | None |
| CI `performance` job | Present |
| Ceiling constants ↔ documentation | Synchronized (`PerformanceGuardTest`) |

---

## Release ZIP audit

Built with:

```bash
composer install --no-dev
bash bin/build-zip.sh
```

Expected artifact: **`dist/universal-multicurrency-0.17.0.zip`**

### Included

- `universal-multicurrency.php` (header Version **0.17.0**), `uninstall.php`, `readme.txt` (Stable tag **0.17.0**)
- `src/` production PHP including `src/Admin/DisplayControlRenderer.php`, `src/Admin/DisplaySettingsField.php`, `src/Display/` (with `SwitcherCustomCss.php`, `SwitcherPresentationCss.php`), `src/Rates/RateHealthService.php`, `src/CLI/RatesCommand.php`
- `assets/admin/umc-settings.css`, `assets/admin/umc-settings.js`, `assets/css/switcher.css`, `assets/js/switcher.js`
- `src/` production PHP
- `vendor/autoload.php` (+ production autoload only)
- `languages/universal-multicurrency.pot`

### Excluded (verified absent)

- `tests/`, `docs/`, `docs/plans/`, `.git/`, `.github/`
- PHPUnit, PHPCS, Infection, and other dev vendor packages
- `.env`, CI configs, planning files
- Nested previous release ZIPs

Inspector: `bin/inspect-release-zip.php` / `ReleaseZipInspector`.

---

## CI audit

Required jobs in [`.github/workflows/ci.yml`](../.github/workflows/ci.yml):

`phpcs`, `pot`, `unit`, `integration`, `performance`, `build`, `release-audit`

Integration matrix (five legs + ceiling early-warning) unchanged from M6/M7.

---

## Accepted non-blocking observations

| ID | Observation |
|---|---|
| NB1 | No root `LICENSE` file; GPL declared in plugin header, `composer.json`, and `readme.txt` |
| NB2 | Full five-leg integration matrix validated in CI, not re-run entirely in the local release-audit script |
| NB3 | `docs/plans/` may exist locally but is classified non-shipping |
| NB4 | WP-CLI rate commands shipped in Milestone 16 (`wp umc rates`); thin wrapper over existing services — see [`CLI.md`](CLI.md) |
| NB5 | Advanced Custom CSS is not technically scoped: merchants author complete selectors, and the product documents `.umc-switcher` prefixes as a recommendation rather than enforcing isolation (ADR-0022) |
| NB6 | Custom CSS reaches the page through `wp_add_inline_style`, so a shortcode rendered after the stylesheet was already printed falls back to the plain `<link>` and omits Custom CSS on that request — the documented emission boundary |

---

## Regenerating this record

After material repository changes:

1. Run `composer release-audit`
2. Update the **Result** columns above if criteria changed
3. Keep executable guards authoritative; adjust prose only to reflect guard outcomes

---

## Related documents

- [`SECURITY_REVIEW.md`](SECURITY_REVIEW.md)
- [`PERFORMANCE_BASELINES.md`](PERFORMANCE_BASELINES.md)
- [`PERSISTED_DATA.md`](PERSISTED_DATA.md)
- [`TEST_STRATEGY.md`](TEST_STRATEGY.md)
- [`ROADMAP.md`](ROADMAP.md)
