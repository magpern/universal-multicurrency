# ADR-0013 — Conditional HTTP rate caching

## Status

Accepted (Milestone 8, target v0.8.0).

## Context

Milestone 8 fetches fiat rates from Frankfurter, a free public HTTP API.
Rates often do not change between scheduled runs (weekends, non-publishing
days). Unconditional full fetches on every interval waste bandwidth, trigger
unnecessary `umc_settings` writes, and add load to a shared service.

HTTP conditional requests (`If-None-Match`, `If-Modified-Since`) can elide
unchanged payloads when the provider honours them.

## Decision

- **Adopt conditional requests.** `ExchangeRateSource::fetch()` accepts an
  optional prior `ProviderMetadata`. When stored `etag` or `last_modified`
  values exist, the provider sends the corresponding request headers.

- **304 Not Modified handling.** HTTP 304 → `RateFetchResult::not_modified()`.
  Distinct from success: no quotes, no failures, `is_not_modified()` true.
  `ExchangeRateStore::apply_fetch_result()` treats this as a **successful
  attempt with no monetary change**:
  - `umc_settings` — **not written** (rates genuinely unchanged)
  - `umc_rate_state` — `last_fetch_at` advances, per-currency success status
    and `consecutive_failures` reset to zero

- **All other responses behave as before.** HTTP 200 with a body, or any code
  other than 304, is handled exactly like an unconditional fetch. A provider
  that ignores conditional headers never returns 304 — **no new failure mode**
  is introduced by building this mechanism.

- **Metadata storage.** On successful (non-304) fetches, response `ETag` and
  `Last-Modified` headers are captured into versioned `ProviderMetadata` and
  persisted once in `umc_rate_state`. Values are opaque strings — never parsed,
  never trusted beyond a 200-character cap before persistence (same discipline
  as `last_error`).

- **First fetch / missing headers.** No prior metadata → no conditional
  headers sent; behaviour matches a plain GET.

- **Capability flag.** `FrankfurterRateSource::supports_conditional_requests()`
  returns `true`. M8 control flow does not branch on it (ADR-0010); it exists
  for future capability-aware callers.

## Rationale

- Safe-by-construction: providers that do not support conditionals degrade to
  today's behaviour automatically.
- Fewer full payloads and fewer spurious settings writes when rates are stable.
- Storing headers in `ProviderMetadata` (not per-currency rows) keeps cache
  state aligned with batch fetches.

## Consequences

- `WordPressHttpTransport` returns status, flat headers, and body so sources
  can detect 304 and read cache headers.
- CI exercises 304 via fixtures — no live calls to `api.frankfurter.dev` for
  conditional-path tests.
- Oversized or malformed cache headers are truncated or dropped; they cannot
  break persistence or the next request.

## Related

- ADR-0010 — `ProviderMetadata` versioning and capability methods
- ADR-0012 — 304 write path (state only, not settings)
