# Exchange Rate Operations & Reliability (Milestone 16)

**Status:** Authoritative implementation specification for Milestone 16
(**v0.15.0**).

**Branch:** `feature/m16-exchange-rate-operations`

**ADR:** [ADR-0021](../adr/0021-exchange-rate-operations.md)

This document materializes the approved M16 plan plus mandatory amendments.
Production implementation must follow this specification. Working drafts under
untracked `docs/plans/` are not source of truth (`ReleaseAuditTest` forbids
tracked `docs/plans/`).

---

## 1. Product objective

Make exchange rates operationally trustworthy so a merchant can answer:

- Where did this rate come from?
- When was it last updated?
- Is it fresh / aging / stale?
- What happens if the provider fails?
- Which provider is authoritative?
- Can I force a refresh safely?
- Can I use a manual rate safely?
- Which rate will actually be used for conversion?
- Is the exchange-rate subsystem healthy?

**Primary theme:** Exchange Rate Operations & Reliability.

M16 evolves the **existing** M8 rate implementation. It does not redesign
currency selection, Visitor Location, Display, Checkout policy, or Decision
Inspector.

---

## 2. Baseline

| Item | Value |
|---|---|
| Prior release | M15 / **v0.14.0** |
| Baseline commit | `4e8c848fba442e1405fc8800047bb3f7ad8cfa61` |
| Settings schema | **5** (unchanged by M16) |
| Persisted inventory | **7** at baseline; expected **8** if order meta keys added |
| DB migration | None |
| M8 rate stack | Authoritative — retain, harden, do not replace |

---

## 3. Architectural rules (non-negotiable)

1. The M8 exchange-rate stack is authoritative for conversion semantics.
2. Storefront **never** performs live provider HTTP.
3. M16 is operations/reliability — not provider redesign.
4. Stale rates remain usable indefinitely (non-blocking).
5. Aging is presentation/operations status only (50% of `rate_max_age_hours`).
6. No multi-provider fallback / no per-currency built-in provider selection.
7. Action Scheduler is the only scheduling truth for next run.
8. Persisted `umc_rate_state.next_run_at` is **not** authoritative.
9. Refresh lock is strict non-reentrant; characterize before redesign.
10. If any currency has effective automatic mode requiring refresh,
    recurring AS action must remain scheduled (`has_automatic_targets`).
11. Order snapshot schema 4 adds provider id + merchant adjustment only by
    default (no raw `provider_rate` unless proven necessary).
12. CLI is a thin wrapper over existing services.
13. No `Settings::SCHEMA_VERSION` bump expected.
14. No new DB tables.
15. Do **not** add `umc_rate_refresh_started` unless a concrete consumer needs
    it (default: omit; prefer `umc_rate_fetch_completed` + service API).
16. Do not reopen M15 / Decision Inspector / Visitor Location scope.

Desired relationship:

```text
Persisted rates + umc_rate_state + Action Scheduler
      ↓
RateHealthReport (read-only)
      ↓
Admin / Site Health / Compatibility / CLI
```

Storefront money path remains:

```text
ManualRateProvider → Settings::get_rate() → RateResolver → Converter
```

---

## 4. Existing M8 architecture (retained)

### Write path (admin / Action Scheduler / CLI only)

`RateUpdateService::update()` → lock → `ExchangeRateSource::fetch()` →
`ExchangeRateStore::apply_fetch_result()` → unlock →
`do_action( 'umc_rate_fetch_completed', $result )`.

### Read path (storefront)

`ManualRateProvider` → `RateResolver::effective_rate()` — **no HTTP**.

### Persistence

| Option | Role |
|---|---|
| `umc_settings` | Config + `provider_rate` / `rate_updated_at` / `manual_rate` / `merchant_adjustment` / modes |
| `umc_rate_state` | Ops bookkeeping, lock, failure history, provider metadata |

### Manual vs automatic

- Automatic: `provider_rate * (1 + merchant_adjustment/100)`.
- Manual: `manual_rate` only; refresh does not overwrite `manual_rate`.
- Per-currency `rate_mode` overrides global when set.

### Fallback contract (storefront)

1. Newly fetched valid quote (after successful refresh).
2. Else stored `provider_rate` (any age) when effective mode is automatic.
3. Else `manual_rate` when effective mode is manual.
4. Else no rate → currency not selectable.

Provider outage preserves last-known rates. Stale classification does not
remove rates from conversion.

---

## 5. Rate health model

### Types

- `UMC\Rates\RateHealthService` — builds reports; no mutations; no HTTP.
- `UMC\Rates\RateHealthReport` — immutable read model.

### Report contents (minimum)

- Configured provider id / global mode
- Automatic / manual / disabled target counts
- Fresh / aging / stale / unavailable counts
- Last attempt / last successful update / last failure
- Sanitized last failure code + bounded detail
- Consecutive failures (as already tracked)
- Next scheduled run **from Action Scheduler**
- Whether refresh lock is currently held
- Whether scheduler is missing while `has_automatic_targets` is true

Admin statistics, Compatibility, Site Health, and CLI **must** consume this
model (or shared evaluators it wraps) rather than duplicating stale logic.

---

## 6. Aging / status semantics

Derive from existing `rate_max_age_hours` (no new setting):

| Status | Definition |
|---|---|
| Fresh | automatic + usable rate + age ≤ 50% of max |
| Aging | automatic + usable rate + 50% &lt; age ≤ max |
| Stale | automatic + usable rate + age &gt; max |
| Manual | effective manual mode with usable rate |
| Unavailable | no usable effective rate (never / failed empty / missing) |

**Invariant tests required:** stale (and aging) rates still resolve and convert
exactly as before M16 for the same inputs.

Extend `RateStatusEvaluator` (or successor) with an aging label while keeping
failed/never/stale/ok semantics coherent for badges and health.

---

## 7. Scheduler correctness (`has_automatic_targets`)

### Confirmed gap (pre-M16)

`Scheduler::ensure_scheduled()` used `RateConfiguration::is_automatic_enabled()`
(global mode only). `ExchangeRateStore::get_automatic_currency_codes()` already
uses `Settings::get_effective_rate_mode()`. Global **manual** + per-currency
**automatic** override therefore left automatic currencies without a recurring
job.

### Required contract

Define a single reusable answer for `has_automatic_targets` using the **same**
effective-mode semantics as rate resolution / `get_automatic_currency_codes()`
(prefer calling that store method or a thin shared helper — do not fork mode
logic inside Scheduler).

| Situation | Schedule |
|---|---|
| Global automatic + inherited automatic currencies | Scheduled |
| Global manual + all effective manual | Unscheduled |
| Global manual + ≥1 per-currency automatic | Scheduled |
| Last automatic currency disabled / overridden away | Unscheduled |
| Interval change | Reconcile recurrence |
| Missing recurring action while targets exist | Restore on `init` / settings save |

Next run display: Action Scheduler only. Do not write/read `next_run_at` as
schedule truth.

---

## 8. Lock / concurrency

Default contract:

- Any valid unexpired lock blocks another refresh (strict non-reentrant).
- Expiry allows recovery.
- Release in `finally`.
- Nested/duplicate refreshes are not allowed.

Characterization first:

1. Acquire / block / expire / release / finally-after-failure tests.
2. Determine whether option read/modify/write has a demonstrable race under
   the supported execution model.

If no real race: keep mechanism; improve status/error semantics only.

If race proven: smallest atomic WordPress-compatible acquire (prefer
`add_option()`-style sentinel or equivalent). No Redis / distributed locks.

---

## 9. Failure taxonomy

Normalize operational failure categories as enum-like constants consistent with
PHP 8.1+ project style. Include only categories representable by current
Frankfurter / HTTP transport behavior (and generic extension failures):

- `provider_unavailable`
- `network_error`
- `timeout`
- `invalid_response`
- `unsupported_currency`
- `rate_limited`
- `not_returned_by_provider`
- `update_in_progress`
- `storage_failure`

Persist safe code + sanitized bounded detail. Do not persist raw HTTP bodies,
secrets, tokens, or request headers. Do not invent Frankfurter auth failures
unless a generic extension category is needed later.

---

## 10. Refresh result contract

All refresh entry points (admin, AS, CLI) use `RateUpdateService`.

Callers must distinguish:

- complete success
- partial success
- not modified (304)
- no automatic targets
- total failure
- update already in progress

Preserve stored rates on failure. Partial success updates successful quotes and
retains previous rates for failed currencies.

**Do not add** `umc_rate_refresh_started` by default.

---

## 11. Admin experience

Upgrade Exchange Rates using the existing UMC Admin Design System
(`AdminComponentRenderer` statistics cards + quick actions).

### Statistics (illustrative)

Provider · Last successful update · Fresh rates · Aging/Stale rates · Next
scheduled refresh (from AS)

### Quick actions

Refresh now · Review currencies · Open Compatibility · Site Health (if
practical)

### Rate table columns

Currency · Effective rate · Source · Last updated · Status · Mode · Action

Source clarity: `Manual` or `Automatic — Frankfurter` (provider id label).

Do not revive orphaned `CurrencyTableField` unless it is proven the correct
reusable implementation. Do not create a competing second table.

Manual refresh feedback must cover full success, partial success, total
failure, lock busy, no automatic currencies, and preservation of last-known
rates. Preserve capability + nonce + redirect safety. No provider HTTP during
normal settings page render.

---

## 12. Order snapshot schema 4

### Additive meta for **new** orders

| Concern | Meta | Notes |
|---|---|---|
| Provider id | `_umc_rate_provider` (exact name must match `PersistedKeys`) | e.g. `frankfurter` or empty for manual |
| Merchant adjustment | `_umc_rate_adjustment` | Percentage string as stored in settings |

### Unchanged

- Base / transaction currency
- Effective `_umc_exchange_rate`
- Timestamp / `manual`|`automatic` source
- Rate identity format (`code:rate`)
- Refund parent identity behavior
- Historical orders (schemas 1–3 remain readable)

### Explicitly not added by default

Raw `provider_rate` — only if implementation proves a concrete audit need that
effective rate + provider + adjustment + existing fields cannot satisfy.

Settings schema stays 5. Inventory expected 7 → 8 when new meta keys land.
No DB migration.

---

## 13. Diagnostics / Site Health

Refactor to consume `RateHealthReport` where appropriate.

Findings (actionable):

- Automatic targets but scheduler missing
- Unavailable rate for an enabled currency
- Stale rates
- Repeated refresh failures
- Lock held only when operationally relevant
- Provider / configuration state

Severity guidance:

- **Aging:** informational / good, or at most mild recommendation per existing
  conventions.
- **Stale:** recommended unless combined with unavailable rates or severe
  repeated failures.
- **Unavailable enabled currency:** may be critical.

Diagnostics must not alter runtime conversion/selectability.

Decision Inspector remains the “why this shopper currency?” surface — not rate
ops management.

---

## 14. WP-CLI

Command group: `wp umc rates`

| Command | Service |
|---|---|
| `status` | `RateHealthService` |
| `refresh [--currency=CODE]` | `RateUpdateService` |
| `list` | Existing rate/configuration read models |

Exit semantics:

- Status: success (0) for normal output even when unhealthy (document if
  unhealthy uses non-zero — prefer 0 for status reporting unless repo
  convention differs; refresh failures use non-zero).
- Refresh: non-zero on total failure; non-zero on lock contention; partial
  success reported explicitly (document chosen exit code — prefer 0 with
  clear partial messaging, or 1 if WP-CLI convention for partial prefers
  non-zero; pick one and test it).

No artificial capability/logged-in user unless repository convention requires
it. No interactive UI. No history / provider-management commands.

---

## 15. Security / privacy

- Admin refresh: existing capability + nonce unchanged in spirit.
- CLI: no secrets; same services.
- Sanitized failure details only.
- No shopper PII in provider requests.
- No storefront provider calls (`RatesPersistenceGuardTest` remains sacred).
- Frankfurter needs no credentials; keep architecture safe for future
  credentialed extension sources (redaction).

---

## 16. Persistence / schema summary

| Surface | M16 |
|---|---|
| `Settings::SCHEMA_VERSION` | **5** (unchanged) |
| Order snapshot schema | **4** (additive) |
| `PersistedKeys` inventory | **7 → 8** if new meta |
| DB tables | None |
| `next_run_at` | Deprecated/unused; not schedule truth |
| Rate history tables | Out of scope |

---

## 17. Work package order

1. Docs (this file + ADR-0021 + ROADMAP) — first commit
2. Characterization tests (no production changes)
3. Health model + aging
4. Scheduler `has_automatic_targets` fix
5. Lock characterization (+ minimal harden if race proven)
6. Failure taxonomy + refresh result contract
7. Admin refresh feedback + ops UI
8. Order snapshot schema 4
9. Diagnostics / Site Health alignment
10. CLI
11. Documentation sync + regression + v0.15.0 preparation

---

## 18. Explicit non-goals

- `umc_rate_refresh_started` (unless concrete consumer appears)
- Multi-provider fallback / failover
- Per-currency built-in provider selection
- Rate history database
- Hard stale storefront cutoff
- Decision Inspector / Visitor Location / switcher redesign
- Psychological price rounding
- Settings schema bump for aging thresholds
- Deployment / tagging / GitHub release in this branch’s definition of done
  (release prep metadata only at the end)

---

## 19. Acceptance checklist

- [x] Scheduler schedules when any effective automatic target exists
- [x] Stale conversion semantics unchanged (characterization + regression)
- [x] Storefront free of live provider HTTP
- [x] Health model shared by admin / SH / Compatibility / CLI
- [x] Order schema 4 additive; schemas 1–3 still read
- [x] Full unit + integration suites green
- [x] Canonical `composer release-audit` green
- [x] Version prepared as **0.15.0** (no tag/push required by this spec alone)
