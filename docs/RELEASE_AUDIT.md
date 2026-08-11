# Release audit — v0.14.0 Currency Resolution & Explainability

Executable release-blocking gate for Universal Multicurrency **v0.14.0**. This
document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Governing question:** If we published this release tomorrow, is there anything
left in the repository that clearly should not ship?

**Repository status:** **prepared for v0.14.0** on
`feature/m15-currency-explainability`. Milestone 15 (Currency Resolution &
Explainability; ADR-0020) awaits CI verification. Git tag **`v0.14.0`** and
GitHub release publication follow full CI matrix pass and explicit approval to
tag and push.

---

## Release closure record

| Item | Value |
|---|---|
| Version | **0.14.0** |
| Settings schema | **5** (unchanged; no keys added, renamed, or removed) |
| Order snapshot schema | **3** (unchanged) |
| GeoContext (sandbox document) schema | **2** (was 1; removed unused `network`/`providers` reserved subtrees, ADR-0018) |
| Persisted-data inventory version | **7** (was 6; `umc_currency_origin` session provenance key) |
| Production migrations | **v0 → v1**, **v1 → v2**, **v2 → v3**, **v3 → v4**, **v4 → v5** (unchanged; no new migration in this release) |
| Unresolved Critical security findings | **0** |
| Unresolved High security findings | **0** |
| Unresolved release blockers | **0** |
| Open Milestone 8 review findings | **0** |
| Deterministic performance gates | See § Performance gate |
| POT drift | See § Translation audit |
| Dependency audit (`composer audit`) | See § Security gate |
| Package inspection | See § Release ZIP audit |
| Git tag `v0.10.0` | **Created** (superseded) |
| GitHub release `v0.10.0` | **Published** (superseded) |
| Git tag `v0.8.0` | **Created** (superseded) |
| Git tag `v0.11.0` | **Not yet created** (superseded by M13/M14 prep) |
| GitHub release `v0.11.0` | **Not yet created** (superseded by M13/M14 prep) |
| Git tag `v0.12.0` | **Created** (superseded) |
| GitHub release `v0.12.0` | **Published** (superseded) |
| Git tag `v0.12.1` | **Created** |
| GitHub release `v0.12.1` | **Published** |
| Git tag `v0.14.0` | **Not yet created** |
| GitHub release `v0.14.0` | **Not yet created** |
| Milestone 8 | **Complete** — released and review-closed at v0.8.0 |
| Milestone 11 | **Complete** — Checkout currency policy at v0.10.0 |
| Milestone 12 | **Prepared** — Geo Detection engine (see v0.12.0 tag note above) |
| Milestone 13 | **Complete** — Geo admin hub at v0.12.0 |
| Milestone 14 | **Complete** — Visitor Location boundary alignment at v0.13.0 |
| Milestone 15 | **Prepared** — Currency Resolution & Explainability on feature branch |

---

## v0.14.0 Currency Resolution & Explainability scope

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

| ID | Criterion | Result (v0.9.0) |
|---|---|---|
| RB1 | No tracked secrets, dumps, caches, or `dist/` artifacts | **Pass** |
| RB2 | `docs/plans/` remains untracked (local-only planning) | **Pass** |
| RB3 | No foreign switcher runtime coupling outside allowlisted manifest | **Pass** |
| RB4 | Plugin header, `UMC_VERSION`, readme Stable tag, text domain, PHP/WC metadata consistent | **Pass** |
| RB5 | `Settings::SCHEMA_VERSION === 3`; production migrations v0 → v1 → v2 → v3 only | **Pass** |
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

| Field | Value (v0.14.0) |
|---|---|
| Plugin version (header + `UMC_VERSION`) | **0.14.0** |
| readme.txt Stable tag | **0.14.0** |
| Text domain | `universal-multicurrency` |
| Requires PHP | 8.1 |
| Requires Plugins | woocommerce |
| Composer license | GPL-2.0-or-later |
| Production Composer deps | `php >=8.1` only |

Compatibility matrix and CI legs: see [`COMPATIBILITY.md`](COMPATIBILITY.md).

---

## Persisted-data audit

Authoritative registry: [`PERSISTED_DATA.md`](PERSISTED_DATA.md) +
[`src/PersistedKeys.php`](../src/PersistedKeys.php) (`INVENTORY_VERSION = 3`).

- All persisted keys registered and documented, including the Milestone 8
  operational-state option `umc_rate_state`
- No undocumented transients or object-cache keys
- Uninstall policy matches ADR-0009

---

## Settings upgrade audit

- `Settings::SCHEMA_VERSION` is **3**
- Production migration map: **v0 → v1**, **v1 → v2**, and **v2 → v3**
- v1 → v2 is a real schema change (renames `rate` to `manual_rate`, adds the
  automatic-rate fields), not an artificial bump
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

Expected artifact: **`dist/universal-multicurrency-0.14.0.zip`**

### Included

- `universal-multicurrency.php` (header Version **0.14.0**), `uninstall.php`, `readme.txt` (Stable tag **0.14.0**)
- `src/` production PHP including `src/Admin/DisplayControlRenderer.php`, `src/Admin/DisplaySettingsField.php`, `src/Display/`
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
| NB4 | WP-CLI rate commands are a deliberate Milestone 8 non-goal; the service layer is CLI-ready without redesign |

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
