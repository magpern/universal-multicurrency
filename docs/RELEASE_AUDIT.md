# Release audit — v0.9.0 Display Configurator

Executable release-blocking gate for Universal Multicurrency **v0.9.0**. This
document records scope, criteria, commands, audit results, and the current
release-preparation state.

**Governing question:** If we published this release tomorrow, is there anything
left in the repository that clearly should not ship?

**Repository status:** **prepared for v0.9.0** on `main`. Milestone 9 (Display
configurator) is feature-complete on `main`. Git tag **`v0.9.0`** and GitHub
release publication follow release verification.

---

## Release closure record

| Item | Value |
|---|---|
| Version | **0.9.0** |
| Settings schema | **3** (unchanged from v0.8.x display work on `main`) |
| Persisted-data inventory version | **3** |
| Production migrations | **v0 → v1**, **v1 → v2**, **v2 → v3** (unchanged) |
| Unresolved Critical security findings | **0** |
| Unresolved High security findings | **0** |
| Unresolved release blockers | **0** |
| Open Milestone 8 review findings | **0** |
| Deterministic performance gates | **Passing** |
| POT drift | **Passing** |
| Dependency audit (`composer audit`) | **Passing** |
| Package inspection | **Passing** |
| Git tag `v0.8.0` | **Created** (superseded) |
| GitHub release `v0.8.0` | **Published** (superseded) |
| Git tag `v0.8.1` | **Not created** (superseded by v0.9.0 line) |
| GitHub release `v0.8.1` | **Not published** (superseded by v0.9.0 line) |
| Git tag `v0.9.0` | **Not yet created** |
| GitHub release `v0.9.0` | **Not yet created** |
| Milestone 8 | **Complete** — released and review-closed at v0.8.0 |
| Milestone 9 | **Prepared** — Display configurator on `main` |

---

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

| Field | Value (v0.9.0) |
|---|---|
| Plugin version (header + `UMC_VERSION`) | **0.9.0** |
| readme.txt Stable tag | **0.9.0** |
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

Expected artifact: **`dist/universal-multicurrency-0.9.0.zip`**

### Included

- `universal-multicurrency.php` (header Version **0.9.0**), `uninstall.php`, `readme.txt` (Stable tag **0.9.0**)
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
