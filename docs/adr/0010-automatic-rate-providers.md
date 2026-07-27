# ADR-0010 — Automatic rate providers and derive-don't-persist

## Status

Accepted (Milestone 8, target v0.8.0).

## Context

Since Milestone 1, `Rates\RateProvider` has been the read-side contract
`Converter` depends on — synchronous, no I/O, one rate lookup at a time.
Milestone 8 adds automatic fiat retrieval without changing that interface.
A fetch-side abstraction is required for batched HTTP calls, failure
semantics, and provider metadata.

The first draft persisted a computed `effective_rate` in `umc_settings`.
That value is a function of `manual_rate`, `provider_rate`,
`merchant_adjustment`, and `rate_mode`. Storing it duplicates inputs and
creates staleness when merchants edit adjustment or mode without a refresh.

## Decision

- **Two interfaces, distinct roles.**
  - `RateProvider` — runtime read path (`get_rate` / `has_rate`); unchanged
    contract for `Converter` and order snapshots.
  - `ExchangeRateSource` — fetch path (`fetch` batch, provider identity,
    capability methods). Frankfurter is the only built-in implementation in M8.

- **Derive, never persist.** A pure `RateResolver::effective_rate()` computes
  the storefront rate on every read from the four inputs above. Nothing named
  `effective_rate` or `rate` is written to the database. `ManualRateProvider`
  and `Settings::get_rate()` delegate to `RateResolver`; the hot path adds one
  pure function call and zero extra queries (ADR-0002 decimal-string rules
  unchanged).

- **Capability methods on `ExchangeRateSource`.** The interface exposes
  `supports_conditional_requests()`, `supports_historical_rates()`, and
  `supports_currencies( array $codes )`. Frankfurter implements them
  truthfully now. **Nothing in Milestone 8's control flow consults them** —
  they exist for M9+ capability-aware callers only.

- **Versioned `ProviderMetadata`.** Batch-level metadata (provider id, as-of
  date, optional dataset version, HTTP cache headers) lives in one immutable
  value object with `schema_version` starting at `1`. It is stored once in
  `umc_rate_state`, not duplicated per currency quote. `from_array()` treats
  a missing `schema_version` as `1`; no migration logic until a real v2 exists.

- **Orchestration layering.** `ExchangeRateSource → ExchangeRateStore →
  RateUpdateService`. The service orchestrates lock, fetch, and persist; it
  never calls `get_option` / `update_option` directly.

## Rationale

- Preserving `RateProvider` keeps every M1–M7 caller (`Converter`,
  `OrderSnapshot`, performance baselines) untouched at the interface level.
- Deriving on read eliminates the staleness class where stored effective rates
  lag input changes — the primary review concern for M8.
- Capability methods and versioned metadata cost little at definition time and
  avoid interface churn when crypto or multi-provider work arrives in M9.

## Consequences

- Currency rows gain `manual_rate`, `provider_rate`, and `merchant_adjustment`
  instead of a single persisted `rate` field (schema v2 upgrade).
- Order snapshots continue to record the rate used at checkout; source becomes
  `manual` or `automatic` based on effective mode.
- Third-party providers register via `umc_exchange_rate_sources`; M8 ships
  Frankfurter only.

## Related

- ADR-0002 — decimal-string computation and rounding
- ADR-0012 — where `provider_rate` is persisted and write order
- ADR-0013 — conditional HTTP requests and metadata headers
