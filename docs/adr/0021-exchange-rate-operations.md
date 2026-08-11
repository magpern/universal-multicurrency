# ADR-0021: Exchange Rate Operations & Reliability

**Status:** Accepted (Milestone 16, target v0.15.0)

**Related:** [ADR-0010](0010-automatic-rate-providers.md),
[ADR-0011](0011-action-scheduler-rate-updates.md),
[ADR-0012](0012-operational-rate-state-separation.md),
[ADR-0013](0013-conditional-http-rate-caching.md),
[ADR-0002](0002-runtime-conversion-and-rounding.md),
[ADR-0004](0004-transaction-currency-and-order-snapshot.md),
[`docs/architecture/exchange-rate-operations.md`](../architecture/exchange-rate-operations.md)

## Context

Milestone 8 shipped a complete automatic-rate stack: Frankfurter provider,
Action Scheduler refresh, `umc_settings` / `umc_rate_state` separation,
derive-on-read conversion, manual/automatic modes, merchant adjustment, admin
refresh, Site Health, and Compatibility checks. Storefront conversion never
performs live provider HTTP.

Merchants still need operational trust answers:

- Where did this rate come from?
- When was it last updated, and is it fresh?
- What happens when the provider fails?
- Can I force a refresh safely?
- Is the exchange-rate subsystem healthy?

M16 hardens operations and reliability on the existing M8 architecture. It is
not a provider redesign and does not reopen M15 explainability work.

## Decision

### M8 remains authoritative for conversion

- `RateProvider` / `RateResolver` / `Converter` stay the storefront money path.
- Provider HTTP remains confined to `RateUpdateService` (scheduler, admin
  refresh, CLI).
- Derive-don't-persist for effective rates remains in force (ADR-0010).

### Operations façade, not a second rate engine

Introduce a read-only health model (`RateHealthService` / `RateHealthReport`)
that aggregates configuration, Action Scheduler schedule truth, lock state,
and per-currency status **without** provider HTTP and **without** mutating
state. Admin, Site Health, Compatibility, and CLI consume that model instead
of independently re-deriving stale/failure logic.

### Aging is presentation-only

Status bands for automatic rates, derived from existing `rate_max_age_hours`:

| Band | Age relative to max | Storefront |
|---|---|---|
| Fresh | age ≤ 50% of max | Converts |
| Aging | 50% &lt; age ≤ max | Converts |
| Stale | age &gt; max | Converts |

The **50% threshold is an operational/presentation convention**. It does not
change selectability, conversion, checkout, or Decision Inspector outcomes.
It does not invalidate a rate. The only storefront availability rule remains
whether a usable effective rate exists.

Stale rates remain usable indefinitely. M16 may classify, warn, diagnose, and
report age. M16 must not automatically unselect stale currencies, stop
conversion because of age, or silently change checkout behavior.

### Scheduling uses effective automatic targets

Recurring Action Scheduler registration must not depend solely on the global
`rate_mode`. If **any** currency requires provider refresh under effective
rate-mode resolution (`has_automatic_targets`), the recurring
`umc_run_rate_update` action must remain scheduled. When none do, it must be
unscheduled. Mode semantics must reuse the same effective-mode resolution as
rate configuration / `ExchangeRateStore::get_automatic_currency_codes()`.

### Action Scheduler is the only schedule authority

Admin, health, and CLI read next run from Action Scheduler
(`as_next_scheduled_action` / equivalent). Persisted
`umc_rate_state.next_run_at` is **not** authoritative. Do not mirror AS into
that field as a second scheduler truth. Prefer leaving it as deprecated unused
compatibility state unless a safe removal is separately documented and tested.

### Strict non-reentrant refresh lock

Any valid unexpired lock blocks another refresh attempt (including the same
owner). Lock expiry allows recovery. Release remains in `finally`. Nested or
duplicate refreshes are not allowed. Characterize the option-backed lock before
redesigning it; only introduce a minimal atomic WordPress-compatible acquire
if a real race is demonstrated. No Redis / distributed-lock infrastructure.

### No speculative refresh-started hook

Do **not** add `umc_rate_refresh_started` unless a concrete existing consumer
needs it. Prefer the existing `RateUpdateService` API and
`umc_rate_fetch_completed`. Speculative public extension points are out of
scope.

### No multi-provider fallback

Frankfurter remains the only built-in provider. The existing
`umc_exchange_rate_sources` filter remains the extension point. No primary /
secondary failover chain. No per-currency built-in provider selection in M16.

### Order snapshot schema 4 — additive provenance only

New orders may record:

- provider identifier
- merchant adjustment

Do not add raw `provider_rate` by default. Do not change rate identity format
(`code:rate`). Do not change monetary order behavior or refund parent-identity
semantics. Historical orders remain readable under schemas 1–3.

### CLI is a thin wrapper

`wp umc rates status|refresh|list` call the same services as admin/scheduler.
No second refresh implementation. Follow repository WP-CLI conventions (no
artificial browser-style logged-in user requirement unless required).

### Schema / persistence

- `Settings::SCHEMA_VERSION` stays **5** (no new merchant settings keys).
- No new DB tables.
- `PersistedKeys` inventory bumps only if new order meta keys require it
  (expected **7 → 8**).
- Aging thresholds derive from `rate_max_age_hours`; no new setting.

## Consequences

- New health / status presentation types and admin ops UX on the existing
  Admin Design System.
- Scheduler correctness fix for global-manual + per-currency-automatic.
- Structured failure taxonomy for operational state (sanitized codes + bounded
  detail).
- Optional lock acquire hardening only after characterization proves a race.
- Order snapshot schema 4 + inventory documentation.
- Thin WP-CLI command group.
- Version target: **v0.15.0**.

## Related documentation

- [`docs/architecture/exchange-rate-operations.md`](../architecture/exchange-rate-operations.md)
- [`docs/ROADMAP.md`](../ROADMAP.md) item 16
- [`docs/PERSISTED_DATA.md`](../PERSISTED_DATA.md)
- [`docs/ARCHITECTURE.md`](../ARCHITECTURE.md)
- [`docs/HOOKS.md`](../HOOKS.md)
