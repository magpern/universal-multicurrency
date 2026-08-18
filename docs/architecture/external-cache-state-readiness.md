# External cache-state readiness (v1.1.0)

**Status:** Authoritative implementation specification for v1.1.0 — a
post-1.0 release, not a numbered milestone (ADR-0031 §6, ADR-0032).

**Branch:** `feature/v1.1.0-external-cache-state`

**ADR:** [ADR-0032](../adr/0032-external-cache-state-readiness.md)

This document materializes the approved v1.1.0 plan. Production
implementation follows this specification.

---

## 1. Product objective

Give an external full-page cache (nginx, Varnish, a CDN edge) — deliberately
never touched by this plugin — a deterministic, read-only signal for "has the
cache-critical configuration changed since I was last reconciled?", without
the plugin gaining any ability to control that cache.

## 2. What is cache-critical

Exactly four factors, computed by `UMC\CacheState\CacheStateFactors`:

| Factor | Type | Source |
|---|---|---|
| `contract_version` | int | `CacheStateFactors::CONTRACT_VERSION` (starts at `1`) |
| `base_currency` | string | `CurrencyRegistry::get_base_code()`, uppercase |
| `currencies` | list\<string\> | `CurrencyRegistry::get_selectable_codes()`, uppercase, unique, **sorted** |
| `geo_enabled` | bool | `GeoDetectionSettingsRepository::get()->is_enabled()` |

`currencies` is the enabled-∧-rated intersection (base always included) —
the exact same seam already used to gate which currencies a shopper may
select. It is sorted before hashing so enable order never perturbs the hash.

**Never hashed:** exchange-rate values, any timestamp
(`rate_updated_at`/`acknowledged_at`/`rates_last_updated_at`), the plugin
version, display/switcher/checkout/fixed-pricing/reporting settings,
"plugin is active."

**Canonical serialization** (pure PHP, no WordPress):

```
umc-cache-state/v1|base=EUR|currencies=EUR,SEK,USD|geo=1
```

**Hash:** `substr(hash('sha256', $canonical), 0, 16)` — 16 lowercase hex,
matching `/^[a-f0-9]{16}$/` (the repo's existing hash-token shape, not its
sha1 algorithm — see ADR-0032 §2). Opaque to infrastructure: compared
byte-for-byte, never recomputed by the consumer.

## 3. Rate values are excluded — the split between configuration and freshness

An ordinary rate value update (`SEK 11.50 → 11.60`) is **not** configuration
reconciliation. It leaves `currencies` identical (the currency was already
selectable), so `state_hash` is unchanged and no reconciliation is required.
It **is** HTML-freshness relevant — already-rendered cached prices become
stale — but that is a TTL/purge policy the external cache owns, not something
this plugin invalidates. The existing `umc_rate_fetch_completed` hook
(`docs/HOOKS.md`) is the existing signal for "rates just changed"; this
milestone adds no new invalidation mechanism.

The only informational nod to rate freshness is a read-only report field:

> `rates_last_updated_at` = the maximum `rate_updated_at` among the
> currencies currently in `currencies` (the selectable, non-base set).
> Disabled, unselectable, and base-currency rates never contribute. It is
> excluded from the canonical string and from `state_hash`; moving it forward
> alone never sets `reconciliation_required`.

## 4. State model

```
state_hash                derived live on every read — never stored, never cached
acknowledged_hash          persisted in option `umc_cache_state`

reconciliation_required := state_hash !== acknowledged_hash    (raw, unconditional)
monitoring_enrolled      := acknowledged_hash !== ''             (derived, display-gate only)
```

`reconciliation_required` is computed the same way in every case and is never
independently forced to any value. On a fresh, never-acknowledged install,
`acknowledged_hash === ''`, so the comparison honestly evaluates `true` — this
is the signal external automation uses to discover that initial enrollment is
needed, and it is never hidden from the JSON contract (§8). Only
merchant-facing *severity and copy* — Site Health status, Compatibility
severity — branch on `monitoring_enrolled` in addition to
`reconciliation_required`; the underlying field is reported unfiltered
everywhere else, including the Site Health debug section.

**Rate-value-only changes leave `reconciliation_required` at whatever value
it already had.** The field is `state_hash !== acknowledged_hash`; if a rate
value change leaves both sides of that comparison exactly as they were, the
comparison result does not change either — it stays `true` on an unenrolled
or already-mismatched install, and stays `false` on an enrolled/reconciled
one. It is never coerced independently in either direction.

### Trigger table

| Trigger | `state_hash` | `reconciliation_required` |
|---|---|---|
| Initial activation, never acknowledged | computed | `true` (honest — enrollment itself is outstanding) |
| First `acknowledge(current_hash)` | computed | `false` |
| `CONTRACT_VERSION` bump | changes | `true` |
| `geo.enabled` toggled | changes | `true` |
| Currency enabled/disabled, or rate availability adds/removes one | changes | `true` |
| Base currency changed in WooCommerce | changes | `true` |
| Ordinary plugin upgrade (version not hashed) | unchanged | unchanged |
| **Rate value change, same currency set** | **unchanged** | **unchanged** (`state_hash` unchanged) |
| `rate_updated_at` moves forward only | unchanged | unchanged |

## 5. Persistence

```php
// option: umc_cache_state, autoload false
array(
    'schema_version'    => 1,
    'acknowledged_hash' => '',   // 16-hex, or '' when never acknowledged
    'acknowledged_at'   => 0,    // unix ts, informational only
)
```

`CacheState\CacheStateStore` is the sole gateway: `defaults()`, `sanitize()`,
`acknowledged_hash()`, `acknowledged_at()`, `is_enrolled()`, `record(string
$hash, int $now)`. No option other than `umc_cache_state` is written from
`src/CacheState/`.

**Persistence-ownership rule** (enforced by `CacheStateBoundaryGuardTest`,
§9): only `CacheStateStore.php` may pass the option key
(`CacheStateStore::OPTION` or the literal `'umc_cache_state'`) as the key
argument to `get_option()`/`add_option()`/`update_option()`/`delete_option()`.
`PersistedKeys::option_keys()` referencing the constant `CacheStateStore::OPTION`
is a plain constant reference, not a call to an option primitive, and is
explicitly permitted.

`Settings::SCHEMA_VERSION` stays `7` — no migration. `PersistedKeys::INVENTORY_VERSION`
moves 10 → 11 because this is a new persistence surface, not a changed one.
`uninstall.php` deletes `umc_cache_state` in the same order as
`PersistedKeys::option_keys()`.

## 6. Service

`CacheState\CacheStateService` composes `CurrencyRegistry`,
`GeoDetectionSettingsRepository`, `Settings`, and `CacheStateStore`:

- `current_factors(): CacheStateFactors` — builds the four-factor value
  object from live state.
- `report(): CacheStateReport` — the flat, read-only report (§8).
- `acknowledge(string $hash): bool` — validates shape (`/^[a-f0-9]{16}$/`),
  re-derives the current authoritative hash, compares with `hash_equals()`,
  and on match calls `CacheStateStore::record()`. On any mismatch or
  malformed input, returns `false` and writes nothing.

`acknowledge()` never touches `umc_settings`, currencies, rates, or geo — the
only write is `CacheStateStore::record()`.

## 7. CLI

```
wp umc cache-state status [--format=<table|json>]
wp umc cache-state acknowledge <hash>
```

`CLI\CacheStateCommand` is modeled directly on `CLI\RatesCommand`:
constructor-promoted `CacheStateService`, `status()` prints
`\WP_CLI\Utils\format_items('table', …)` (bools as `yes`/`no`) by default or
`wp_json_encode($report->to_array())` with `--format=json`. `acknowledge()`
calls `\WP_CLI::error()` (exit 1) with both the submitted and current hash on
rejection; on success it prints a claim, never "verified". No `check`
subcommand — `status --format=json` already is the machine-readable
interface. `status` always exits `0` on successful execution; a non-zero exit
means the state is unknown, never "reconciled" and never "plugin inactive."

### Normative operator transaction

```
1. wp umc cache-state status --format=json      # read current hash
2. reconcile the external cache to that state    # infrastructure-side
3. validate / reload / accept on the infra side
4. wp umc cache-state status --format=json      # re-read — do not reuse step 1's value
5. wp umc cache-state acknowledge <hash from step 4>
```

### ABA limitation

See ADR-0032 §5. Documented here as the operator-facing warning: skipping
step 4/5 after a real reconciliation, followed by a later revert back to a
previously-acknowledged configuration, can leave `reconciliation_required`
falsely `false` while the external cache still reflects the intermediate
state. The only mitigation is following the transaction above every time,
not a code fix.

## 8. Report contract

`CacheStateReport::to_array()`:

```json
{
  "contract_version": 1,
  "state_hash": "a1b2c3d4e5f60718",
  "acknowledged_hash": "",
  "monitoring_enrolled": false,
  "reconciliation_required": true,
  "base_currency": "EUR",
  "currencies": ["EUR", "SEK", "USD"],
  "geo_enabled": true,
  "acknowledged_at": "",
  "rates_last_updated_at": ""
}
```

Field names and types are additive-only within one `contract_version`;
removing or retyping a field requires a `CONTRACT_VERSION` bump.

## 9. Admin surfaces

### Site Health test `umc_cache_state`

Registered in `SiteHealthReport::tests()` under the existing
`site_status_tests` filter (no new hook), gated by
`current_user_can('activate_plugins')` like the other four tests.

| Condition | Status | Label |
|---|---|---|
| `monitoring_enrolled === false` | `good` | "External cache state monitoring is not enrolled" |
| enrolled, `reconciliation_required === false` | `good` | "External cache state is reconciled" |
| enrolled, `reconciliation_required === true` | `recommended` | "External full-page cache reconciliation required" |
| `CacheStateService` unexpectedly unavailable in active-plugin runtime | `critical` | "External cache state diagnostics are unavailable" |

The first row's description is explicit that it is **not** a claim that no
external cache exists — only that this installation has not enrolled in the
contract.

### Debug section

Five fields appended to the existing `universal-multicurrency` debug section:
`cache_state_hash`, `cache_state_acknowledged_hash`,
`cache_state_monitoring_enrolled`, `cache_state_reconciliation_required`
(raw, unfiltered — reported truthfully even when not enrolled),
`cache_state_contract_version`.

### Compatibility → Cache

`Compatibility\Checks\CacheStateCheck` emits one `CompatibilityResult` in the
existing `Cache` category:

| Condition | id | severity |
|---|---|---|
| not enrolled | `cache.state_not_enrolled` | `INFO` |
| enrolled, reconciled | `cache.state_reconciled` | `INFO` |
| enrolled, required | `cache.state_reconciliation_required` | `WARNING` |

`CompatibilityServices::scanner()` gains an optional 5th parameter
`?CacheStateService $cache_state = null`; the check is only registered when
it is non-null.

### No admin notice

Site Health, the Compatibility tab, and the CLI are the three surfaces. No
global admin notice is added — a generic UMC install that never enrolls sees
nothing, and an enrolled install already has three independent places to
notice drift.

## 10. Boundaries (mechanically enforced)

`CacheStateBoundaryGuardTest` (a static source guard, the same kind as
`PerformanceGuardTest`/`SecuritySourceGuardTest`, validated with a controlled
guard self-test — not Infection/mutation testing) scans
`src/CacheState/` and `src/CLI/CacheStateCommand.php` for:

```
ssh | proc_open | shell_exec | exec( | nginx | varnish | reload | purge |
wp_remote_ | curl_init | fsockopen | file_put_contents | $wpdb
```

and separately enforces the persistence-ownership rule (§5). The existing
whole-tree guards (`SecuritySourceGuardTest`, `RatesPersistenceGuardTest`,
`PerformanceGuardTest`) already cover the new files by scanning all of
`src/`.

## 11. Non-goals

No SSH, no nginx/Varnish/CDN client, no config writer, no reload/purge
trigger, no webhook, no daemon, no scheduled job, no outbound HTTP, no
credential storage, no REST/AJAX route, no new WordPress hook, no
site-specific path, no redesign of currency or geo resolution, no change to
Universal Geo Context or any external cache infrastructure.

## 12. Related

- [ADR-0032](../adr/0032-external-cache-state-readiness.md)
- `docs/CLI.md`, `docs/COMPATIBILITY.md`, `docs/PERSISTED_DATA.md`,
  `docs/DEPLOYMENT.md`
- ADR-0004 (rate identity), ADR-0012 (standalone operational option
  precedent), ADR-0009 (uninstall retention)
