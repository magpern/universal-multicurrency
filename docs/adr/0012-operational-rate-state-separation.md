# ADR-0012 — Operational rate state separation

## Status

Accepted (Milestone 8, target v0.8.0).

## Context

Automatic rates introduce data that is not merchant configuration: last fetch
status, failure history, concurrency locks, and HTTP cache metadata. Some of
this is diagnostic; one field — the provider-observed rate — is needed on
every storefront price read.

The hot path (`ManualRateProvider → Converter`) must stay at **one**
memoized `get_option( 'umc_settings' )` — no second option read per render
(M7 performance baselines).

WordPress options are independent writes with no cross-option transaction.

## Decision

- **Two options, deliberate split.**

  | Option | Holds | Uninstall |
  |---|---|---|
  | `umc_settings` | Merchant configuration **and** `provider_rate` + `rate_updated_at` per automatic currency | Deleted (ADR-0009) |
  | `umc_rate_state` | Operational bookkeeping: per-currency fetch status, failure history, lock, global `provider_metadata` | Deleted |

- **`provider_rate` lives in `umc_settings`.** It is operational data (an
  observation, not merchant intent) co-located with configuration purely so
  `RateResolver` can derive effective rates without a second database read.
  Only `ExchangeRateStore::apply_fetch_result()` writes `provider_rate`; the
  settings form never accepts it from POST.

- **`ExchangeRateStore` is the sole gateway.** No other class in `src/Rates/`
  calls `get_option` / `update_option` for rate-related keys.

- **Two-option write order (money before bookkeeping).** On a successful
  fetch with new quotes, `apply_fetch_result()` writes in this order:

  1. **`umc_settings` first** — batch-update `provider_rate` and
     `rate_updated_at` for every successful quote (one `Settings::save()`).
  2. **`umc_rate_state` second** — status rows, failure history, provider
     metadata, lock release (one `RateUpdateState::save()`).

  If the process dies between writes, monetary data is already durable; only
  diagnostics may lag. The reverse order would risk claiming success without
  a live rate — materially worse.

- **Accepted failure modes.** No reconciliation job. If settings succeed and
  state fails, `Converter` uses the correct new rate while Site Health badges
  may under-report freshness until the lock TTL (120 s) expires. Diagnostics
  can look stale; they cannot over-report a rate that was never written.

- **304 responses.** When the provider returns not-modified, `umc_settings` is
  untouched; only `umc_rate_state` timestamps and success counters advance
  (ADR-0013).

## Rationale

- Splitting configuration from pure diagnostics keeps `umc_settings` the
  single source for "what rate does the storefront use," while `umc_rate_state`
  answers "what happened on the last fetch attempt."
- Write order exploits WordPress's lack of transactions: prioritize the data
  that affects money, accept cosmetic diagnostic drift on partial failure.

## Consequences

- Future export/import must use a **field-level allowlist**, not whole-option
  dumps — `provider_rate` must not be treated as portable merchant intent.
- `uninstall.php` deletes both `umc_settings` and `umc_rate_state`.
- `ExchangeRateStoreTest` verifies write order with an injected state-save
  failure.

## Related

- ADR-0009 — uninstall deletes configuration options
- ADR-0010 — derive-don't-persist and `RateResolver`
- ADR-0013 — 304 handling and metadata placement
