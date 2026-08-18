# ADR-0032 — External cache-state readiness

## Status

Accepted (post-1.0 release, target v1.1.0).

## Relationship to ADR-0031

ADR-0031 §6 is **honored, not superseded**: "no `M27` milestone is ever
created — future work moves to a Backlog, not another mandatory milestone."
This work is not M27 and does not reopen the closed M0–M26 roadmap. It is
identified only by its release, **v1.1.0**, tracked in `docs/ROADMAP.md`
under a new `# Post-1.0 releases` section that sits after the Backlog, not
inside the closed milestone list.

## Context

A generic WooCommerce site running Universal Multicurrency may sit behind an
externally managed full-page cache (nginx, Varnish, a CDN edge) whose
configuration depends on the plugin's semantic state: the base currency, the
set of currencies actually offered, and whether geo-based currency routing is
active. That external cache infrastructure is, by design, never touched by
this plugin (ADR-0007's passive-detection posture and `docs/GEO_DETECTION.md`
/ `docs/DEPLOYMENT.md`'s "advisory only" remediation stance both already
establish this boundary).

The risk this milestone closes is purely human: an operator activates the
plugin, or later changes one of these cache-critical settings, and forgets
that the external cache also needs reconciling. The cache then keeps serving
shared HTML built on stale assumptions — the wrong currency set, or missing
geo-routing variation — with nothing in the plugin surfacing that fact.

Because Universal Multicurrency is a generic, publishable plugin, most
installations never run behind this kind of cache at all. Any signal this
milestone adds must not manufacture a warning for a site that never opted
into the contract.

## Decision

### 1. Four hash factors, nothing else

`CacheState\CacheStateFactors` computes a hash over exactly:

```
contract_version : int    CacheStateFactors::CONTRACT_VERSION (starts at 1)
base_currency    : string CurrencyRegistry::get_base_code(), uppercase
currencies       : list   CurrencyRegistry::get_selectable_codes(), sorted, uppercase, unique
geo_enabled      : bool   GeoDetectionSettingsRepository::get()->is_enabled()
```

`currencies` reuses `CurrencyRegistry::get_selectable_codes()` verbatim — the
enabled-∧-rated intersection, base always included. This is not
reimplemented.

Explicitly excluded, by decision, forever within `CONTRACT_VERSION = 1`:
exchange-rate **values**, every timestamp, the plugin version, display /
switcher / checkout / fixed-pricing / reporting settings, and "the plugin is
active" (a hash that only exists while the plugin runs cannot encode its own
absence).

**Rate values are excluded because they are a different problem.** ADR-0004's
rate-identity caching (`code:rate`) and the existing `umc_rate_fetch_completed`
hook already handle "prices just changed" for internal purposes; adding rate
values to this hash would demand an external cache reconciliation on every
routine hourly refresh, which is operationally absurd and the fastest way to
get the signal ignored. The hash changes only when a currency's *availability*
changes — i.e. when it crosses the null/non-null boundary of
`Settings::get_rate()` and therefore enters or leaves
`get_selectable_codes()`.

### 2. sha256, not the repo's existing sha1 fingerprint convention

`ConflictDetector::fingerprint()` and `FixedPriceDocument::fingerprint()`
both already use `hash('sha1', …)` for unrelated purposes (notice dismissal
identity, variation price-cache identity). This is a new, independent
contract with no reason to inherit SHA-1. `CacheStateFactors::hash()` is
`substr(hash('sha256', $canonical), 0, 16)` — 16 lowercase hex, matching the
repo's existing hash-token *shape* convention
(`NoticeDismissal::is_valid_fingerprint()`, `/^[a-f0-9]{16}$/`,
`hash_equals()` comparison) without reusing its algorithm. The token is
opaque: infrastructure compares it byte-for-byte and never recomputes it.

### 3. `state_hash` is derived live, never persisted

There is no hook to keep it fresh, no drift is possible, and every trigger —
including a base-currency change made purely in WooCommerce settings, which
never fires `umc_settings_saved` — is detected uniformly on the next read.

### 4. Two independent properties: `reconciliation_required` and `monitoring_enrolled`

```
acknowledged_hash        persisted in option `umc_cache_state`
state_hash                derived live

reconciliation_required := state_hash !== acknowledged_hash   (raw machine state)
monitoring_enrolled      := acknowledged_hash !== ''            (derived, never persisted separately)
```

`reconciliation_required` is unconditional. It is **never** coerced to
`false`/`null`/"n/a" because the installation has never enrolled. On a fresh
install, `acknowledged_hash === ''`, so `state_hash !== ''` is true by
construction, and the field is honestly `true` — that is exactly how
external automation discovers that initial reconciliation is needed. The
JSON contract (§9 below) always reports it unfiltered.

`monitoring_enrolled` gates **merchant-facing severity and copy only** — Site
Health (`docs/adr/0032`, this ADR) and the Compatibility → Cache check both
render a never-enrolled installation as `good`/informational rather than
`recommended`/actionable, because a merchant who never opted into external
cache reconciliation has nothing to do about a machine-state field they never
asked to see. First `acknowledge()` **is** enrollment; there is no separate
enrollment step or flag.

### 5. Acknowledgement is a claim, never a verification

```
wp umc cache-state acknowledge <hash>
```

accepts only the current, freshly re-evaluated hash (`hash_equals()`),
rejects anything else with no write, and records only that an external
operator or tool *claims* successful reconciliation for that exact state. It
is never described as "verified" anywhere in code, CLI output, or docs — the
plugin has no visibility into nginx, a CDN, or any proxy runtime.

**Normative operator transaction** (the whole mitigation for the ABA
limitation below):

```
1. read current state/hash
2. reconcile the external cache to that state
3. validate / reload / accept the change on the infrastructure side
4. re-read the current state/hash — not the value captured in step 1
5. acknowledge that exact, freshly re-read hash
```

**ABA limitation, accepted, not solved in code.** Acknowledge A → configuration
changes to B → external cache is reconciled to B but acknowledgement is
skipped → configuration changes back to A. `state_hash` now equals
`acknowledged_hash` again, even though the external cache may still be
configured for B. Hash equality proves *the current configuration matches
the last claimed reconciliation* — it cannot prove external runtime state.
This is not closed by adding nginx callbacks, generation counters, webhooks,
or any other infrastructure coupling; the mitigation is procedural (the
transaction above), and is documented as a known limitation, not silently
declared solved.

### 6. CLI-only surface, two subcommands

```
wp umc cache-state status [--format=<table|json>]
wp umc cache-state acknowledge <hash>
```

No `wp umc cache-state check` — the JSON `status` output already is the
machine-readable check interface; a separate command would duplicate it. No
admin button, REST route, or AJAX handler — `SecuritySourceGuardTest` already
forbids `register_rest_route(`/`wp_ajax_` in `src/`, and the actor performing
reconciliation is infrastructure automation, not a human clicking in
wp-admin.

**External command failure contract.** `status` exits 0 whenever it emits the
documented JSON object — `reconciliation_required: true` inside that object
is a normal, successful result, not a command failure. Any non-zero exit
means the state is unknown/unavailable; infrastructure must fail closed on
that, and must never read a non-zero exit as "the plugin is inactive" without
checking WordPress's own plugin-activation state through its own means. This
milestone adds no separate signal for "plugin inactive" (§1's exclusion of
plugin-active state from the hash is the same decision).

### 7. Persistence: one new, additive, self-defaulting option

```php
umc_cache_state = array(
    'schema_version'    => 1,
    'acknowledged_hash' => '',
    'acknowledged_at'   => 0,
)
```

Registered with autoload `false`, following the `umc_rate_state` standalone-
option precedent (ADR-0012): own option, own gateway class
(`CacheState\CacheStateStore`), never mixed with `umc_settings`.
`Settings::SCHEMA_VERSION` stays `7`; there is no settings migration.
`PersistedKeys::INVENTORY_VERSION` moves 10 → 11 solely because
`umc_cache_state` is a new persistence surface being inventoried, not because
any existing surface's shape changed. Deleted on uninstall (ADR-0009
category: ephemeral plugin configuration, same as `umc_settings` and
`umc_rate_state`).

**Persistence ownership, enforced by guard test, not by convention alone.**
`CacheStateStore` is the sole class permitted to pass the cache-state option
key to an option-write/read primitive (`get_option`/`add_option`/
`update_option`/`delete_option`) anywhere in `src/`. This is a narrower rule
than "only this file may contain the literal string `umc_cache_state`" —
Site Health field ids, CLI labels, and test identifiers may legitimately
reference that text without touching storage, and `PersistedKeys::option_keys()`
referencing `CacheStateStore::OPTION` as a plain constant (not a call) is
explicitly permitted.

### 8. Site Health / Compatibility / debug fields — three read-only surfaces, no new hook

Registered inside the existing `site_status_tests` / `debug_information`
filters already wired by `Diagnostics::register()` — no eighth hook is added,
and `DiagnosticsGuardTest`'s seven-hook allowlist is unchanged.
`CompatibilityServices::scanner()` gains an optional `?CacheStateService`
parameter and one more check, reusing the existing `Cache` category
established by `CacheCheck`. No new admin page, no new global admin notice —
Site Health, the Compatibility tab, and the CLI are the three surfaces,
matching where an admin investigating cache behavior already looks.

### 9. No general post-1.0 semver policy is adopted here

Why *this* release is v1.1.0 has, until now, been unwritten anywhere in the
repository. This ADR records — narrowly, for this release only — that v1.1.0
follows the same feature-⇒-minor precedent every prior milestone already
established (and ADR-0008 §3's existing floor-raise-⇒-minor rule). This ADR
does **not** adopt, declare, or claim ownership of a standing, repository-wide
semver policy, and does not supersede or amend ADR-0008 or ADR-0031. A
general policy, if wanted, is a separate future decision.

## Non-goals

No SSH client, no nginx/Varnish/CDN client, no config writer, no reload or
purge trigger, no webhook, no daemon, no scheduled job, no outbound HTTP, no
cache-server credential storage, no BioPentra-specific (or any other
site-specific) path or hostname anywhere in this generic plugin, no redesign
of currency or geo resolution, no change to Universal Geo Context, no change
to any external cache infrastructure. Every direction of control flows from
infrastructure to the plugin (`acknowledge`), never the reverse.

## Related

- Existing cache posture: `src/Compatibility/Checks/CacheCheck.php`,
  `docs/GEO_DETECTION.md`, `docs/DEPLOYMENT.md`
- Rate identity / caching: ADR-0004
- Standalone operational-option precedent: ADR-0012
- Uninstall retention: ADR-0009
- v1.0 release contract and the "no M27" rule: ADR-0031
- Full specification: [`docs/architecture/external-cache-state-readiness.md`](../architecture/external-cache-state-readiness.md)
