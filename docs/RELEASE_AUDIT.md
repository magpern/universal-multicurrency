# Release audit — Milestone 8 (v0.8.0)

Executable release-blocking gate for Universal Multicurrency **v0.8.0**. This
document records scope, criteria, commands, audit results, and the Release
Candidate closure state.

**Governing question:** If we published this release tomorrow, is there anything
left in the repository that clearly should not ship?

**Repository status:** prepared for **v0.8.0**. Git tag and GitHub release
publication are **not yet created** — pending explicit approval after review.

---

## Release Candidate closure record

| Item | Value |
|---|---|
| Version | **0.8.0** |
| Settings schema | **2** |
| Persisted-data inventory version | **3** |
| Production migrations | **v0 → v1**, **v1 → v2** |
| Unresolved Critical security findings | **0** |
| Unresolved High security findings | **0** |
| Unresolved release blockers | **0** |
| Deterministic performance gates | **Passing** |
| POT drift | **Passing** |
| Dependency audit (`composer audit`) | **Passing** |
| Package inspection | **Passing** |
| Git tag `v0.8.0` | **Not yet created** |
| GitHub release publication | **Pending explicit approval** |
| Milestone 8 | **Complete** in repository |

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

| ID | Criterion | Result (v0.7.0 RC) |
|---|---|---|
| RB1 | No tracked secrets, dumps, caches, or `dist/` artifacts | **Pass** |
| RB2 | `docs/plans/` remains untracked (local-only planning) | **Pass** |
| RB3 | No foreign switcher runtime coupling outside allowlisted manifest | **Pass** |
| RB4 | Plugin header, `UMC_VERSION`, readme Stable tag, text domain, PHP/WC metadata consistent | **Pass** |
| RB5 | `Settings::SCHEMA_VERSION === 1`; single production migration | **Pass** |
| RB6 | Persisted-key inventory matches docs + implementation | **Pass** |
| RB7 | Uninstall deletes `umc_settings` only; preserves commerce + dismissal meta | **Pass** |
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

| Field | Value (v0.7.0 RC) |
|---|---|
| Plugin version (header + `UMC_VERSION`) | **0.7.0** |
| readme.txt Stable tag | **0.7.0** |
| Text domain | `universal-multicurrency` |
| Requires PHP | 8.1 |
| Requires Plugins | woocommerce |
| Composer license | GPL-2.0-or-later |
| Production Composer deps | `php >=8.1` only |

Compatibility matrix and CI legs: see [`COMPATIBILITY.md`](COMPATIBILITY.md).

---

## Persisted-data audit

Authoritative registry: [`PERSISTED_DATA.md`](PERSISTED_DATA.md) +
[`src/PersistedKeys.php`](../src/PersistedKeys.php) (`INVENTORY_VERSION = 2`).

- All persisted keys registered and documented
- No undocumented transients or object-cache keys
- Uninstall policy matches ADR-0009

---

## Settings upgrade audit

- `Settings::SCHEMA_VERSION` remains **1**
- Production migration map: **v0 → v1 only**
- No artificial schema v2
- Canonical reads avoid writes; failed migrations do not persist partial data
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
| `docs/SECURITY_REVIEW.md` | Present |
| Open Critical findings | 0 |
| Open High findings | 0 |
| Accepted Medium/Low documented | Yes (M1–M3, L1–L2) |
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

Expected artifact: **`dist/universal-multicurrency-0.7.0.zip`**

### Included

- `universal-multicurrency.php` (header Version **0.7.0**), `uninstall.php`, `readme.txt` (Stable tag **0.7.0**)
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
| NB4 | Git tag and GitHub release publication intentionally deferred until post-review approval |

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
