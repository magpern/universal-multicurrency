# Performance baselines — Milestone 7 Release Candidate

Deterministic performance invariants for Universal Multicurrency at Commit 7.
**Wall-clock timing is informational only** and is never a release-blocking metric.

Executable enforcement lives in:

- `tests/integration/PerformanceBaselineTest.php` — WordPress/WooCommerce query and write ceilings
- `tests/unit/PerformanceBaselineTest.php` — pure service idempotency
- `tests/unit/PerformanceGuardTest.php` — no persistent cache calls in `src/`

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
| **Option write count** | Invocations passing through `pre_update_option_umc_settings` during the scoped operation |
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
| `uninstall.php` | 1 option delete | **1** | `CEILING_UNINSTALL_OPTION_DELETES` | Deletes `umc_settings` only |
| Dismissal user meta | 0 deletes | **0** | `CEILING_UNINSTALL_USER_META_DELETES` | ADR-0009 retention |

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
