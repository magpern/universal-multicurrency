# ADR-0033 — Variable-product price range currency identity (v1.1.1)

## Status

Accepted (post-1.0 corrective release, target **v1.1.1**).

## Relationship to ADR-0031 / ADR-0032

ADR-0031 §6 remains honored: this is **not** M27 and does not reopen the
closed M0–M26 roadmap. It is a focused bug-fix release identified only as
**v1.1.1**, tracked under `# Post-1.0 releases` in `docs/ROADMAP.md`.

ADR-0032 (external cache-state readiness / `CacheState` contract v1) is
**unchanged** by this release.

## Context

WooCommerce builds variable-product min/max ranges via
`WC_Product_Variable_Data_Store_CPT::read_price_data()`, applying
`woocommerce_variation_prices_{price,regular_price,sale_price}` and caching
under `wc_var_prices_{id}` keyed by `woocommerce_get_variation_prices_hash`.

UMC already hooks those filters and appends `[active_code, rate, fixed
fingerprint]` to the hash (M2 / M20). Selected variation prices (via
`woocommerce_product_variation_get_*`) therefore convert correctly.

A regression appears when something calls the **variable parent**
`$product->get_price()` before the range cache is warm:

1. `PriceHooks::resolve()` sets a re-entrancy guard (`$resolving = true`).
2. `ProductSaleStateResolver` calls `$product->is_on_sale( 'edit' )`.
3. `WC_Product_Variable::is_on_sale()` **ignores the context argument** and
   always calls `get_variation_prices()`.
4. Nested range construction hits `PriceHooks` while `$resolving` is true, so
   variation-price filters return **base amounts unchanged**.
5. Those base amounts are stored under the **foreign-currency** hash (identity
   was already appended).
6. Later `get_price_html()` serves the poisoned range (EUR numbers + DKK/SEK
   symbol) while individual variation getters still convert correctly.

Observed on DEV: parent range `35,99 kr. – 65,99 kr.` with selected variation
`269,05 kr.` after EUR→SEK→DKK (and equivalent EUR→foreign paths when parent
`get_price()` runs first).

## Decision — frozen corrective contract

### A. Shared pricing authority

A variable product's displayed min/max range must use the same active-currency
pricing authority as its variations (`ProductPriceResolutionService`).

### B. Per-variation resolution (unchanged semantics)

| Condition | Result |
|---|---|
| Active currency = base | Native WooCommerce price |
| Foreign + authoritative fixed price | Fixed authored value (never FX again) |
| Foreign + no fixed price | Normal UMC FX conversion |

### C. Mixed variation sets

Fixed and converted variations may coexist; the parent range is min/max of
**resolved** variation prices, never unconverted base amounts under a foreign
symbol.

### D–E. Sale scheduling and range semantics

WooCommerce sale scheduling remains authoritative. Regular/sale range semantics
remain native WooCommerce semantics **after** UMC price resolution.

### F–I. Cache identity and transitions

- Currency/rate transitions must invalidate or distinguish cached variation
  ranges (existing hash identity retained).
- EUR → foreign → EUR must not leave stale ranges.
- Same-currency effective-rate change must update converted ranges.
- Rate change must not change authoritative fixed variation amounts.

### J–L. Non-goals (hard)

- No double conversion.
- No new persistence/schema.
- No changes to order snapshots, checkout policy, reporting, CacheState v1,
  Visitor Location architecture, or fixed-price persistence.
- No HTML regex/`woocommerce_variable_price_html` string rewriting as the
  primary fix — correct the monetary values entering range calculation.
- Do not globally disable WooCommerce variation price caching.

## Implementation constraints

1. Prefer fixing the re-entrancy / sale-state seam so
   `woocommerce_variation_prices_*` still resolve while a parent getter is in
   flight, and/or stop calling `WC_Product_Variable::is_on_sale()` from
   `ProductSaleStateResolver` (it cannot honor `'edit'`).
2. Reuse `ProductPriceResolutionService` — no parallel conversion algorithm.
3. Keep variation cache hash bounded (currency + rate + fixed/sale identity).

## Consequences

- Corrective automated tests must prove the numeric error (parent
  `get_price()` then `get_variation_prices()` in a foreign currency), not
  merely currency symbols.
- Patch release **1.1.1**; persistence inventory unchanged from v1.1.0.
