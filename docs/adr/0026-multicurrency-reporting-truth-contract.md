# ADR-0026: Multicurrency Reporting Truth Contract

**Status:** Accepted (Milestone 21, target v0.20.0)

**Related:**
[`docs/architecture/multicurrency-reporting.md`](../architecture/multicurrency-reporting.md),
ADR-0004, ADR-0005, ADR-0014, ADR-0020, ADR-0021, ADR-0025

## Context

Through v0.19.0 Universal Multicurrency converts and settles orders in the
customer's transaction currency, persisting immutable order snapshots (schema 4),
checkout-policy metadata (M11), rate provenance (M16), and line-item pricing
provenance (M20). Merchants still lack UMC-owned visibility into how multicurrency
orders perform over time — by currency, pricing path, shopper origin, and
checkout fallback behaviour.

A reporting layer could easily become a second monetary engine: re-pricing history
with today's FX, inverting stored rates, or normalizing mixed-currency totals into
a synthetic base currency. That would contradict ADR-0004 (immutable transaction
currency), ADR-0021 (no live provider HTTP on read paths), and M20 line-item
provenance semantics.

M21 establishes a **reporting truth contract**: admin reports read stored
transaction-currency facts only, with explicit legacy handling and no inverse FX.

## Decision

### Reporting truth (non-negotiable)

M21 Phase 1 reports **order-native amounts in transaction currency only**.

| Principle | Rule |
|---|---|
| Order-native authority | Monetary totals come from `WC_Order::get_total()` and line-item historical totals in the order's transaction currency |
| Provenance authority | Fixed vs converted splits use `_umc_line_price_source` at transaction time — never current product settings or live resolution |
| No live FX | Report generation must not call `RateProvider`, `RateUpdateService`, or any provider HTTP |
| No inverse FX | `_umc_exchange_rate` may appear in rate-provenance tables but must **not** drive monetary recomputation |
| No base normalization | Base-equivalent totals, cross-currency roll-ups, and `HistoricalAmountNormalizer` are **out of Phase 1** |

**Forbidden during report generation:**

- Live rate lookup or `Converter::convert()` on historical orders
- Re-running `ProductPriceResolutionService` or reading `_umc_fixed_prices`
- Inverse FX (`order_total / rate`) to derive base-equivalent amounts
- Applying today's FX to historical orders

**Terminology (Phase 1):**

```text
Order value      = WC_Order::get_total() for qualifying orders
Refunded value   = per frozen refund authority (see below)
Net order value  = order value − refunded value
```

Use *order value*, *refunded value*, and *net order value* — not "gross revenue"
unless WC tax/shipping/discount semantics are explicitly reconciled (they are not in
Phase 1).

### Transaction currency precedence

For monetary currency aggregation, resolve transaction currency in this order:

```text
1. Valid UMC transaction-currency snapshot → _umc_transaction_currency
2. Legacy / no UMC snapshot               → WC_Order::get_currency()
3. Invalid / missing both                 → exclude from monetary aggregation;
                                            classify diagnostically (non-monetary bucket)
```

Never use the **current store base currency** as a stand-in for a legacy order's
transaction currency.

### Origin persistence (schema 5)

M21 adds `_umc_currency_origin` to the order snapshot at checkout (classic +
Store API). **Persisted values are factual only:**

| Persisted value | Meaning |
|---|---|
| `customer` | Shopper manually selected the transaction currency (M15 provenance) |
| `visitor_location` | Visitor Location routing persisted the currency (M15 provenance) |
| *(meta absent)* | Origin not captured, pre-M21 order, or invalid/tampered provenance |

**Never persist `unknown`.** Reporting maps absent or invalid values to the
classification bucket `unknown` at read time — that bucket is **not** stored on
the order.

```text
Persisted historical fact:  customer | visitor_location | absent
Reporting classification: customer | visitor_location | unknown
```

Never infer origin post hoc from transaction currency, cookies, geo, country, or
resolver outcome.

**Capture authority:** `OrderSnapshot` may copy origin **only** from the existing
authoritative M15 session provenance state at checkout. It must **not**
independently determine origin from heuristics.

| Provenance state at checkout | Persisted meta |
|---|---|
| Manual customer selection → `customer` | `_umc_currency_origin = customer` |
| Visitor Location → `visitor_location` | `_umc_currency_origin = visitor_location` |
| Missing / tampered / invalid | **omit meta** |
| Transaction currency alone | **never implies origin** |

### Order snapshot schema 5

| Contract | M20 (baseline) | M21 |
|---|---|---|
| `OrderSnapshot::SCHEMA_VERSION` | 4 | **5** |
| Additive meta | — | `_umc_currency_origin` (`customer` \| `visitor_location` only) |
| Settings schema | 6 | **6** (unchanged) |
| `PersistedKeys::INVENTORY_VERSION` | 9 | **10** |
| DB migration | — | **none** |
| Custom reporting table | — | **none** |

Extend `OrderSnapshotReader` and `OrderCurrencySnapshot` for M11 checkout fields
(checkout mode, shopper currency, fallback) plus M21 origin.

### PersistedKeys inventory 10

Add `_umc_currency_origin` to the authoritative inventory
(`OrderSnapshot::META_CURRENCY_ORIGIN`). Inventory version **9 → 10**. No other
new persisted keys in M21.

### Average order value (AOV)

```text
Average order value (AOV)
  = total order value ÷ qualifying order count
```

- Computed **per transaction currency** in the Currency Performance report
- **Net average order value is not** a separate M21 metric
- Refunds reduce net order value explicitly; they do not silently redefine AOV

### Refund authority

WP0 must characterize WooCommerce refund APIs and freeze exactly one refund
authority before implementing aggregation (WP4).

**Frozen authority (pending WP0 characterization confirmation):**
`WC_Order::get_total_refunded()` in the parent order's transaction currency, with
guardrails against double-counting partial refunds.

Required refund test matrix (WP4 / WP9):

| Scenario | Expected behaviour |
|---|---|
| No refund | Refunded value = 0 |
| Partial refund | Refunded value = sum of partial refunds, no double-count |
| Multiple partial refunds | Cumulative total matches `get_total_refunded()` |
| Full refund | Refunded value equals order value (within WC rounding) |
| Refunded order status | Parent still in population when status filter includes it; net reduced |
| Refund with line/tax/shipping components | Single authority; no component double-count |

Refund amounts remain in parent order currency (native WC). No FX normalization on
refunds.

### Default order statuses

**Default report population:** `processing`, `completed`.

Merchants may change the status filter in the Reporting UI. By default,
`refunded`, `cancelled`, `failed`, and `pending` are **excluded** from the
order-value population.

Refund objects linked to **included** parent orders reduce net order value per the
frozen refund contract. This is an explicit product decision — not an assumed
universal WooCommerce revenue definition.

### Pricing source scope

- Aggregate **product line items** only via `_umc_line_price_source` (`fixed` \|
  `converted`)
- Use `line.get_total()` (historical transaction amount)
- Mixed carts: each line bucketed independently
- Shipping, fees, taxes, coupons: excluded; footnote *"Product lines only."*
- Pre-M20 lines without provenance: `unknown` bucket

**Filter scope (frozen):**

> The pricing-source filter applies **only** to the Pricing Source report. It does
> **not** modify order-level Currency Performance, Origin, or Fallback metrics.

Example: mixed order (fixed SEK 1,000 + converted SEK 500, total SEK 1,700) —
Currency Performance always reflects full order value; Pricing Source report can
filter to fixed lines only.

### Checkout reporting limits

**Supported (existing M11 snapshot fields):**

- Checkout fallback count (`_umc_fallback_occurred`)
- Shopper vs transaction currency mismatch (`_umc_shopper_currency` vs transaction
  currency)
- Checkout mode (`_umc_checkout_mode`)

**Not supported in M21:**

- Transition reason codes (not persisted)
- Gateway attribution (do not infer)
- Geographic revenue (deferred)

### Phase 1 reports

**In scope (UMC-owned Reporting admin section — no WooCommerce Analytics
integration):**

1. **Currency Performance** — order count, order value, refunded value, net order
   value, AOV by transaction currency
2. **Pricing Source** — product-line value split: `fixed` / `converted` / `unknown`
3. **Currency Origin** — order counts: `customer` / `visitor_location` / `unknown`
4. **Checkout Fallback Summary** — `_umc_fallback_occurred=yes`; shopper vs
   transaction currency mismatch (M11+)

**Table-only secondary:**

- Rate source (`manual` vs `automatic`) and provider id counts (schema 4+)

### Deferred items (explicit non-goals)

- Base-equivalent / cross-currency normalization
- WooCommerce Analytics widgets or REST write APIs
- Geographic revenue, BI builder, transition-reason breakdown
- Per-order PII in CSV export
- Custom DB reporting tables
- Action Scheduler precompute (unless perf guards fail in WP11)
- Charting library

### CSV same models as UI

CSV export is in M21. Architecture frozen:

```text
ReportingQuery → ReportingService → immutable report models
                                        ├── Admin renderer
                                        └── ReportingCsvRenderer
```

CSV serializes the **same immutable report result models** as the admin UI — no
second aggregation path.

- Capability: `manage_woocommerce`
- Nonce-protected export action
- Aggregate rows only (no per-order PII in Phase 1)
- Bounded by same query limits as UI

### Query and cache architecture

**No custom DB table in M21.**

```text
ReportingQuery (immutable spec)
  → OrderReportingRepository (batched wc_get_orders, fields=>ids)
  → per-order lightweight readers (snapshot DTO + line item meta)
  → ReportingAggregator (pure PHP sums)
  → immutable report models
  → ReportingCache (transient, keyed by full query hash)
  → Admin renderer + ReportingCsvRenderer (same models)
```

**Scaling rules:**

- Paginate order IDs (batch 100–250)
- No unbounded `wc_get_orders( -1 )`
- Hard cap: refuse unbounded "all time" above threshold (~50k) without narrower range
- Manual "Refresh report" bypasses cache
- HPOS: `wc_get_orders()` meta_query only; no direct `$wpdb` postmeta

**Cache key dimensions:** date range, statuses, transaction currency, origin,
fallback, pricing source (when applicable), report schema/version.

**Invalidation:** order creation, payment-complete, status transitions affecting
population, refund creation/deletion. 15-minute TTL is acceptable; stale reports
after new orders/refunds are not intentional.

### Legacy order handling

| Class | Criteria | Reporting behaviour |
|---|---|---|
| **Full** | Valid snapshot + expected keys | All applicable reports; currency from rule 1 |
| **Partial** | Snapshot with missing keys | Include in order-value totals when currency resolves; flag in UI |
| **Legacy** | No UMC snapshot | Include using `WC_Order::get_currency()` (rule 2); provenance/origin = `unknown` |
| **Unresolvable** | Invalid/missing currency authority | Exclude from monetary aggregation; diagnostic classification |
| **Pre-M20 lines** | No line provenance | Pricing-source = `unknown` bucket |
| **Pre-M21 / absent origin** | No `_umc_currency_origin` meta | Origin = `unknown` |

Snapshot version helps distinguish pre-schema-5 orders from M21+ orders where
origin capture failed (meta absent). Never infer `fixed`/`converted`/`visitor_location`/`customer` for legacy or absent data.

### Work packages (M21)

| WP | Deliverable |
|---|---|
| **WP0** | This ADR + architecture spec + ROADMAP + WC refund API characterization + frozen refund authority + transaction currency precedence + AOV definition (**docs-only freeze gate**) |
| **WP1** | OrderSnapshot schema 5: write `_umc_currency_origin` (factual only); origin capture authority; extend reader/DTO for M11 + M21 fields |
| **WP2** | Reporting domain models + truth-contract unit tests |
| **WP3** | `OrderReportingRepository` + batch aggregation |
| **WP4** | `RefundValueResolver` + refund test matrix |
| **WP5** | `LineItemProvenanceAggregator` + filter-scope tests |
| **WP6** | `ReportingService` + cache + invalidation hooks |
| **WP7** | Admin Reporting section UI |
| **WP8** | `ReportingCsvRenderer` (same models as UI) |
| **WP9** | Integration tests: HPOS, refunds, legacy, filters, permissions, origin capture |
| **WP10** | Architecture guards: no RateProvider, no Converter live path, no product meta, no inverse FX |
| **WP11** | Performance guards: bounded batches, query count limits, 10k fixture ceiling, cache hit reduces work |
| **WP12** | Release prep **v0.20.0** — stop at PR boundary |

## Consequences

- Merchants gain UMC-owned multicurrency reporting without a second monetary engine
- Order snapshot schema **5** and PersistedKeys **10** must be documented in
  `PERSISTED_DATA.md` during implementation
- M20 line-item provenance becomes consumable for pricing-source analytics
- M15 session origin (`umc_currency_origin`) is snapshotted once at checkout for
  historical reporting; session state alone is not reportable
- WooCommerce Analytics integration remains deferred; no WC Admin dashboard widgets
- Architecture guards (WP10) block regression into live FX or inverse normalization
- Refund semantics depend on WP0 characterization of `WC_Order::get_total_refunded()`
- Settings schema **6** unchanged; safe in-place upgrade from **v0.19.0**
