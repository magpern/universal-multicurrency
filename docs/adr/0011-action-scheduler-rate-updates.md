# ADR-0011 — Action Scheduler for recurring rate updates

## Status

Accepted (Milestone 8, target v0.8.0).

## Context

Milestone 8 introduces background exchange-rate fetches on a merchant-
configurable interval. WordPress pseudo-cron is disabled on Biopentra installs
(`DISABLE_WP_CRON`); real scheduling uses host cron plus Action Scheduler,
which WooCommerce bundles as a mandatory dependency.

Manual "Update now" / "Update all" actions run synchronously in an
`admin-post.php` request (capability-gated). Recurring updates must not
block admin screens or share that request lifecycle.

## Decision

- **Action Scheduler is the scheduling engine**, not raw `wp_schedule_event`.
  The hook `umc_run_rate_update` invokes `RateUpdateService::update( null )`.
  When automatic mode is disabled, the hook is unscheduled.

- **Narrowed `Scheduler` responsibilities.** The scheduler class knows only:
  - `RateUpdateInterval` — closed ISO-8601 set, seconds conversion, admin labels
  - Reading `rate_mode` and `rate_update_interval` via
    `ExchangeRateStore::get_configuration()` (never `Settings` directly)
  - Action Scheduler APIs (`as_next_scheduled_action`,
    `as_schedule_recurring_action`, `as_unschedule_action`)
  - Calling `RateUpdateService::update( null )` on its hook callback

  It does **not** import or reference `ExchangeRateSource`, `RateResolver`,
  `ProviderMetadata`, currency codes, or the store's persistence internals
  beyond `get_configuration()`.

- **ISO-8601 interval representation.** `RateUpdateInterval` wraps a closed
  set of duration literals: `PT6H`, `PT12H`, `P1D`, `P3D`, `P1W`. The canonical
  value is persisted in `umc_settings.rate_update_interval`; friendly labels
  (`Daily`, `Weekly`, …) exist only in the admin UI. `from_iso8601()` rejects
  anything outside the five literals — no general ISO-8601 parser.

- **Idempotent scheduling.** `ensure_scheduled()` mutates Action Scheduler
  state only when configuration requires a change. Triggers: `umc_settings_saved`
  and a guarded once-per-request `init` fallback.

- **Concurrent-run safety.** `run()` catches `UpdateInProgressException` (lock
  held) and exits quietly; the next scheduled run retries.

## Rationale

- Action Scheduler is already present wherever WooCommerce is, survives
  `DISABLE_WP_CRON`, and matches merchant expectations from other Woo extensions.
- Strict scheduler narrowing prevents scheduling logic from absorbing fetch,
  persistence, or provider concerns — the failure modes stay testable in
  isolation.
- ISO-8601 avoids project-invented interval vocabulary and reads unambiguously
  in migrations and support logs.

## Consequences

- Changing the update interval in settings reschedules the recurring action.
- Scheduler tests mock Action Scheduler functions; no network in scheduler unit
  tests.
- Interval changes require updating the closed set, admin labels, and guards —
  not a parser extension.

## Related

- ADR-0010 — `RateUpdateService` orchestration
- ADR-0012 — operational state written after each scheduled run
