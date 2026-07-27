# Performance baselines

Deterministic performance invariants for Universal Multicurrency, established at
Milestone 7 Commit 7 and extended by the Milestone 8 automatic-rate work.
**Wall-clock timing is informational only** and is never a release-blocking metric.

Executable enforcement lives in:

- `tests/integration/PerformanceBaselineTest.php` — WordPress/WooCommerce query and write ceilings
- `tests/unit/PerformanceBaselineTest.php` — pure service idempotency
- `tests/unit/Rates/RateUpdateNotModifiedWriteCeilingTest.php` — WordPress-free 304 write ceiling
- `tests/unit/PerformanceGuardTest.php` — no persistent cache calls in `src/`; every ceiling constant below exists in the integration baseline

See also [`TEST_STRATEGY.md`](TEST_STRATEGY.md) § Milestone 7 performance.

---

## Environment assumptions

| Assumption | Notes |
|---|---|
| WordPress test suite bootstrap | Plugin loaded via integration bootstrap; HPOS enabled |
| WooCommerce active | Session, cart, Store API routes available on current CI leg |
| Measurement style | Scoped `$wpdb->num_queries` deltas or explicit write counters |
| Framework variance | Ceilings include a **small allowance** above observed baselines on the current leg (`wc:10.9.4`, PHP 8.3) |
| Not measured | CPU time, memory bytes, microseconds, sleep-based timing |

Regenerate observed values by running:

```bash
vendor/bin/phpunit -c phpunit-integration.xml.dist --group performance
vendor/bin/phpunit -c phpunit.xml.dist --group performance
```

---

## Metric definitions

| Metric | Definition |
|---|---|
| **Option write count** | Invocations passing through `pre_update_option_umc_settings` during the scoped operation (WordPress-free unit tests count the equivalent `update_option()` calls via `OptionWriteMetrics`) |
| **Option read count** | Invocations passing through `pre_option_umc_settings` after first load on a memoized `Settings` instance |
| **Query delta** | `$wpdb->num_queries` after minus before a scoped callback |
| **Snapshot write count** | Invocations of `umc_order_snapshot_created` |
| **Detector query delta** | Query count change across repeated `ConflictDetector::findings()` |
| **Hook callback count** | UMC-originating callbacks registered on a named hook |
| **Upgrade persist flag** | `SettingsUpgradeResult::should_persist()` — must be false on canonical re-entry |

---

## Scenarios and enforced ceilings

Ceiling constants are declared on `PerformanceBaselineTest` and enforced in CI.

### Settings load and memoization

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| Canonical v1 load | 0 writes | **0** | `CEILING_SETTINGS_WRITE_CANONICAL_LOAD` | Reading normalized settings must not rewrite the option |
| Absent option read | 0 writes | **0** | `CEILING_SETTINGS_WRITE_ABSENT_LOAD` | Defaults are in-memory until an explicit save |
| Legacy v0 → v1 upgrade | 1 write | **1** | `CEILING_SETTINGS_WRITE_V0_UPGRADE` | One normalization persist, then stable |
| Repeated `get()` on same instance | 0 writes, 0 reads after first load | **0 / 0** | `CEILING_SETTINGS_WRITE_REPEATED_GET` / `CEILING_SETTINGS_READS_REPEATED_GET` | Request-local memoization in `Settings::$data` |
| Upgrader re-entry on canonical data | `should_persist()` false | n/a (unit) | — | Upgrade runner must not loop writes |

**Settings memoization result:** Existing request-local `$data` cache is sufficient. No new persistence, transients, or object-cache integration were added.

### Currency resolution

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| Base / cookie / session / query resolution | 0 settings writes | **0** | `CEILING_CURRENCY_RESOLUTION_WRITES` | Resolution is read-only; switching persists elsewhere |
| Malformed cookie/session/query | Falls back to base, 0 writes | **0** | `CEILING_CURRENCY_RESOLUTION_WRITES` | Invalid candidates rejected at boundary |
| Repeated resolution (10×) | 0 writes | **0** | `CEILING_CURRENCY_RESOLUTION_WRITES` | `CurrencyContext` memoizes active/rate/convertible state |

**Currency-context result:** No production changes required; memoization already prevents repeated work.

### Plugin bootstrap

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| Repeated `Plugin::init()` | 0 extra queries | **0** | `CEILING_PLUGIN_INIT_REPEAT_QUERIES` | Composition root is idempotent |

### Storefront / cart / checkout paths

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| Product price resolution | 0 snapshot events | **0** | `CEILING_STOREFRONT_ORDER_META_WRITES` | Browsing must not stage order audit meta |
| Cart `calculate_totals()` | 0 snapshot events | **0** | `CEILING_STOREFRONT_ORDER_META_WRITES` | Cart work stays pre-order |
| Scoped resolution query delta | ≤ 4 queries | **4** | `CEILING_CART_RESOLUTION_QUERY_DELTA` | Allows WP/WC option cache reads with small headroom |
| Checkout snapshot hook registrations | 1 callback | **1** | `CEILING_CHECKOUT_SNAPSHOT_HOOKS` | Single writer on `woocommerce_checkout_create_order` |

### Store API

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| `GET /wc/store/v1/cart` | 0 snapshot events | **0** | `CEILING_STOREFRONT_ORDER_META_WRITES` | Read-only extension path |
| Cart GET query delta (warmed) | ≤ 6 queries | **6** | `CEILING_STORE_API_CART_QUERY_DELTA` | Second GET after warm-up; first call excluded |
| Cart extension registration | 1 composition-root instance | source guard | — | `CartExtensionData` constructed once in `Plugin.php` |

**Store API result:** Read-only extension remains read-only; no order/refund metadata writes on cart GET.

### Order and refund snapshots

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| Classic snapshot second write | `write_snapshot_for()` false | n/a | — | Write-once without refresh |
| Refund snapshot meta keys | 2 keys staged | **2** | `CEILING_REFUND_SNAPSHOT_META_KEYS` | Parent currency + rate identity only |

**Order/refund result:** Existing write-once semantics preserved; no duplicate classic snapshot refresh.

### Diagnostics (wp-admin)

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| Repeated `findings()` | 0 query delta | **0** | `CEILING_DIAGNOSTICS_QUERY_DELTA` | Memoized detector + probe |
| Detection read | 0 user-meta writes | **0** | — | Dismissal is the only supported write path |

**Diagnostics result:** No production changes; existing memoization holds.

### Uninstall

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| `uninstall.php` | 1 `umc_settings` delete in integration spy | **1** | `CEILING_UNINSTALL_OPTION_DELETES` | Deletes `umc_settings` and `umc_rate_state`; spy counts `umc_settings` only |
| Dismissal user meta | 0 deletes | **0** | `CEILING_UNINSTALL_USER_META_DELETES` | ADR-0009 retention |

### Automatic rate updates (Milestone 8)

| Scenario | Observed baseline | Enforced ceiling | Constant | Rationale |
|---|---:|---:|---|---|
| Provider returns HTTP 304 / `not_modified` | 0 settings writes | **0** | `CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES` | Nothing money-bearing changed, so `umc_settings` must not be rewritten |

A conditional request that the provider answers with **HTTP 304 Not Modified**
produces `RateFetchResult::not_modified()`. Handling it must perform:

- **zero** writes to `umc_settings` — no `Settings::save()` call on this path;
- only the expected operational-state writes to `umc_rate_state`
  (`ExchangeRateStore::apply_not_modified_state()` persists one state snapshot;
  a full service run adds the lock acquire and lock release writes);
- **no provider-rate mutation** — `provider_rate` and `rate_updated_at` keep
  their previous values, so a 304 never looks like a fresh fetch;
- **no effective-rate persistence** — the derived rate is recomputed by
  `RateResolver` on read and is never written back (ADR-0010).

The ceiling is enforced at three layers, all of which must agree:

| Layer | Test | Instrumentation |
|---|---|---|
| Store / service (WordPress-free) | `tests/unit/Rates/RateUpdateNotModifiedWriteCeilingTest.php` | `UMC\Tests\Support\OptionWriteMetrics` counters in the unit bootstrap's `update_option()` stub |
| Integration baseline | `PerformanceBaselineTest::test_not_modified_rate_update_performs_no_settings_write` | `pre_update_option_umc_settings` spy (`PerformanceMetrics`) |
| Admin request path | `tests/integration/Rates/RateUpdateControllerIntegrationTest.php` | Same option-write spy, driven through `admin_post_umc_update_rates` |

See ADR-0012 (operational state separation) and ADR-0013 (conditional HTTP caching).

---

## Known framework variance

- WordPress caches options after first read; a **second** `Settings` instance in the same request may not increment the option-read counter even though production code called `get_option()` again.
- Store API and cart tests inherit WooCommerce/REST bootstrap queries; ceilings measure **scoped deltas**, not whole-request totals.
- HPOS order/refund saves include WooCommerce persistence queries unrelated to UMC snapshot staging; tests count **UMC snapshot actions**, not total order save cost.

---

## Changing a ceiling

1. Re-run the performance group locally and record the new observed value in this document.
2. Update the matching `CEILING_*` constant in `PerformanceBaselineTest` with a one-line rationale in the commit message.
3. Do **not** widen a ceiling without demonstrating the extra work is unavoidable or fixing an accidental regression.
4. Never replace query/write guards with wall-clock thresholds.

---

## Release-blocking vs informational metrics

| Release-blocking (CI) | Informational only |
|---|---|
| Option/write counts | Wall-clock timings |
| Scoped query deltas | Memory usage |
| Snapshot / meta write counts | CPU profiling |
| Hook callback counts | Local browser metrics |
| Idempotent service flags | Object-cache hit rates (not integrated in M7) |

---

## Production optimizations in Commit 7

**None.** Baseline measurement confirmed existing request-local memoization in `Settings` and `CurrencyContext` is sufficient. No speculative optimization was applied.

---

## Production optimizations in Milestone 8

**None.** The 304 write ceiling was added as a regression guard, not as a fix:
`ExchangeRateStore::apply_fetch_result()` already returned after
`apply_not_modified_state()` without touching `umc_settings`. Conditional HTTP
(`If-None-Match` / `If-Modified-Since`) is a provider-side bandwidth
optimization decided in ADR-0013, not a caching layer inside `src/`.
