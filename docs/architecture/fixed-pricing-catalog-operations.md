# Fixed Pricing Catalog Operations — M24 architecture

**Milestone:** 24 · **Target version:** 0.23.0 · **Baseline:** origin/main
`5b39cc2e97c8c635527dfe42f0b50b1f9b7d20f2` (M23 / v0.22.0 closure)

**ADR:** [`docs/adr/0029-fixed-pricing-catalog-operations.md`](../adr/0029-fixed-pricing-catalog-operations.md)

---

## 1. Scope

Catalog-wide fixed-price coverage visibility and bounded bulk seed/clear
operations over the unchanged M20 domain model
([`docs/architecture/authoritative-fixed-product-pricing.md`](authoritative-fixed-product-pricing.md)).
No new pricing engine, no new conversion authority, no schema change.

---

## 2. Component map

```text
                 RateProvider::get_rate()          FixedPriceRepository / Validator / Document
                 (existing, M8 — unchanged)          (existing, M20 — unchanged)
                          │                                    │
                          │  resolved once per operation       │ get() / save()
                          ▼                                    ▼
              ┌──────────────────────────────────────────────────────────┐
              │         FixedPriceCatalogOperationsService (new)          │
              │  seed()/clear(): resolve scope → per-product/variation    │
              │  compute → DisplayPriceConverter::convert_to() → persist  │
              │  → FixedPriceOperationResult (succeeded/skipped, rate)    │
              └───────────┬─────────────────────────────┬────────────────┘
                          │                             │
             ┌────────────▼────────────┐    ┌───────────▼────────────┐
             │ FixedPricingOperation    │    │ FixedPricesCliCommand   │
             │ Controller (new)         │    │ (new — wp umc prices)   │
             │ admin-post.php preview/  │    └────────────┬────────────┘
             │ confirm/execute          │                 │
             └────────────┬─────────────┘                │
                          │                               │
              ┌───────────▼───────────────────────────────▼───────────┐
              │        FixedPriceCoverageReport (new, shared)          │
              │  batched wc_get_products()/variation enumeration;      │
              │  Fixed / Partial / FX-fallback / no-priceable-variants │
              └─────────────────────────────────────────────────────┘

   DisplayPriceConverter::convert_to( $amount, $target, $rate ) — bound to
   the existing PriceConversionService — is the only conversion call the
   service makes. `Converter` itself is a strict seam
   (StorefrontGuardTest::test_converter_is_only_used_through_the_seam): only
   Converter.php and PriceConversionService.php may reference it directly, so
   FixedPriceCatalogOperationsService depends on the DisplayPriceConverter
   interface, never on Converter. convert_to() internally still calls
   Converter::apply_rate() — the same static arithmetic the storefront path
   uses — so the underlying math is unchanged and unduplicated.

   ProductFixedPricesPanel (existing, unchanged) remains the correctness
   baseline every seed/clear write is parity-tested against.
```

`FixedPriceCatalogOperationsService` is the single new orchestration path —
the admin controller and the CLI command both call it, so seed/clear logic
exists exactly once.

---

## 3. Coverage model

### Simple products

| State | Condition |
|---|---|
| Fixed | Fixed regular price authored for the currency (`FixedPriceRepository::get()->get_currency($code)->regular() !== ''`) |
| FX-fallback | No fixed regular price authored |

### Variable products — structural population

Population = variations where **both**:

1. `get_status( 'edit' ) === 'publish'` (WooCommerce's "Enabled" checkbox
   state — see ADR-0029 § Variation-enabled API for the exact evidence this
   is the correct, floor-safe API), and
2. `get_regular_price( 'edit' ) !== ''` (an authored native regular price
   exists).

Excluded from the population (and from the denominator): disabled
variations, and variations without an authored regular price. The latter are
optionally surfaced separately as a data anomaly, mirroring the seed
skip-and-report rule (§ 4).

| State | Condition |
|---|---|
| Fixed | Every population member has a fixed regular price for the currency |
| Partial | Some, not all, population members do |
| FX-fallback | None do |
| No priceable variations | Population is empty |

**Never** derived from `get_stock_status()` or `is_purchasable()` — a stock
or purchasability change must not change coverage classification. This is
enforced by `FixedPriceCoverageReportTest` fixtures that toggle stock state
only and assert classification is unchanged.

---

## 4. Seed algorithm

```text
for each simple product / eligible variation in scope:
    regular_native = product.get_regular_price('edit')
    if regular_native === '':
        result.skip(product_id, reason: 'no authored regular price')
        continue

    sale_native = product.get_sale_price('edit')   # '' if none authored

    regular_target = DisplayPriceConverter::convert_to(regular_native, target, rate)
    sale_target    = sale_native !== ''
        ? DisplayPriceConverter::convert_to(sale_native, target, rate)
        : ''

    # Same merge algorithm as ProductFixedPricesPanel::persist_submission():
    existing = FixedPriceRepository::get(product_id)
    merged   = existing.currencies() as arrays
    merged[target_code] = { regular: regular_target, sale: sale_target }
    document = FixedPriceDocument::from_array(merged, base_code)
    FixedPriceRepository::save(product_id, document)
    do_action('umc_fixed_prices_saved', product_id, document)

    result.succeed(product_id)
```

Never reads `get_price()`. Never reads "is on sale". Never sources a
variation's amounts from its parent or from a sibling variation. A
malformed/negative amount cannot reach this path — `get_regular_price('edit')`
returns WooCommerce's own stored decimal string or `''`; `FixedPriceValidator::normalize_price()`
still runs during the merge/save step exactly as it does for manual authoring,
so a corrupted underlying value is normalized to `''` (and thus skipped) by
the same validator the panel uses, not a new one.

### Clear algorithm

```text
for each simple product / eligible variation in scope:
    existing = FixedPriceRepository::get(product_id)
    if existing.get_currency(target_code) === null:
        result.skip(product_id, reason: 'no fixed price set for this currency')
        continue
    merged = existing.currencies() as arrays
    unset(merged[target_code])
    document = FixedPriceDocument::from_array(merged, base_code)
    FixedPriceRepository::save(product_id, document)
    do_action('umc_fixed_prices_saved', product_id, document)
    result.succeed(product_id)
```

All other currencies in the document, and all other products, are untouched.

---

## 5. Single execution-rate snapshot

```text
FixedPriceCatalogOperationsService::seed(scope, target_code):
    assert target_code !== base_code                      # defense in depth
    rate = RateProvider::get_rate(base_code, target_code)  # resolved exactly once
    if rate is null:
        return OperationResult::aborted('no rate available for ' + target_code)

    for each batch in scope (100–250 products/variations per batch):
        for each product in batch:
            ... seed algorithm above, always using the same `rate` ...

    return OperationResult::completed(succeeded, skipped, failed, rate)
```

`rate` is a local variable captured once and threaded through every batch —
never re-fetched. This holds for both the admin execute request (single
PHP request, scope capped) and a CLI `--all` invocation spanning many
batches within one process. `FixedPriceCatalogOperationsGuardTest` proves
this structurally using a `RateProvider` test double whose return value
changes on successive calls; the operation must still apply only the
first-resolved value to every product.

The admin preview step calls `RateProvider::get_rate()` **separately**,
purely for display — it is informational, may be minutes stale by the time
the merchant confirms, and is never the value actually used at execute time.
`OperationResult::rate_used()` is always the execute-time value; the
confirmation notice explicitly flags when it differs from the previewed
value.

---

## 6. Admin surface

New `SettingsPage::SECTION_FIXED_PRICING` section, added alongside the
existing 9 sections (`SettingsPage::get_sections()`), rendered via a
`woocommerce_admin_field_umc_fixed_pricing` custom field type — the same
mechanism Reporting/Compatibility/Decision Inspector already use. No new
top-level WordPress admin menu.

**Screen:**

- Non-base currency selector.
- Coverage table: product/variation-parent name, SKU, per-selected-currency
  status (Fixed / Partial / FX-fallback / No priceable variations),
  searchable by name/SKU, filterable by status, paginated.
- Checkbox selection of the current page, or "apply to all products matching
  the current filter" (recomputed from filter parameters at execute time —
  never a stored ID list; see § 8).
- Two actions: **Seed fixed price from current FX conversion**, **Clear
  fixed price**.

**Preview → confirm → execute:**

1. **Preview** — a plain GET render inside `FixedPricingSettingsField`
   (same screen, same request cycle as browsing/filtering; no
   `admin-post.php` round trip, since preview performs no write and
   therefore needs no nonce, consistent with how `ReportingSettingsField`
   already renders its "view report" preview via GET). Resolves scope from
   the submitted filter/selection criteria, enforces the scope cap
   (`FixedPricingOperationController::FILTERED_SCOPE_CAP` for "all matching
   filter", `CHECKED_SCOPE_CAP` for a checked-ID list), evaluates
   `current_user_can('edit_post', $id)` per product, and renders affected
   count + a bounded sample of product names + currency + (seed only) the
   currently-resolved rate, labeled informational and subject to change.
2. **Confirm/execute** (`admin-post.php?action=umc_fixed_pricing_execute`,
   handled by `FixedPricingOperationController`, mirroring
   `RateUpdateController`): nonce-verified (`umc_fixed_pricing_execute`),
   **recomputes** scope from the same submitted filter criteria for "all
   matching filter" (never trusts a client-supplied ID list beyond what the
   confirm form resubmits for a checked scope), re-checks per-product
   capability, re-enforces both scope caps, calls
   `FixedPriceCatalogOperationsService`, redirects with a result notice:
   succeeded / skipped counts and, for seed, the actual execution rate
   (flagged if different from the preview rate).

A scope exceeding either cap is refused with guidance to use the CLI —
never silently truncated.

---

## 7. Products-list column (passive)

`FixedPriceCoverageColumn` adds a read-only column to the WooCommerce
Products list (`manage_edit-product_columns` /
`manage_product_posts_custom_column`): one compact badge per enabled
non-base currency showing Fixed / Partial / FX-fallback, each linking into
the dedicated screen pre-filtered to that product and currency. No write
behavior. No WordPress Products-list bulk-action dropdown entries are
registered — `bulk_actions-edit-product` / `handle_bulk_actions-edit-product`
are explicitly not used (ADR-0029).

---

## 8. CLI

```text
wp umc prices list  [--currency=<code>] [--status=fixed|partial|fx]
wp umc prices seed  --currency=<code> [--product=<id>|--all] [--dry-run]
wp umc prices clear --currency=<code> [--product=<id>|--all] [--dry-run]
```

Registered in `Plugin::init()` identically to `RatesCommand`
(`\WP_CLI::add_command('umc prices', new PricesCommand(...))`), sharing
`FixedPriceCatalogOperationsService` and `FixedPriceCoverageReport` — no
second implementation. Rejects base currency, unknown currency, invalid
product ID, and ambiguous scope (`--product` and `--all` both given, or
neither) with a non-zero exit code. Batches 100–250 products/cycle
(documented constant, matching `ReportingService`'s existing discipline);
unbounded in total catalog size, unlike the admin screen. `--dry-run`
executes the identical `FixedPriceCatalogOperationsService` computation path
with persistence disabled (a `persist: bool` constructor/method flag — not a
separate code path) and reports what the real run would do. No
`current_user_can()` check anywhere on this path (ADR-0029 § Authorization).

---

## 9. Persistence versions (M24)

| Contract | Version |
|---|---|
| `Settings::SCHEMA_VERSION` | 7 (unchanged) |
| `PersistedKeys::INVENTORY_VERSION` | 10 (unchanged) |
| `OrderSnapshot::SCHEMA_VERSION` | 5 (unchanged) |

No new option, transient, meta key, or DB table. `_umc_fixed_prices`
documents produced by M24 are shape-identical to documents produced by
`ProductFixedPricesPanel` for equivalent input (proven by parity tests in
WP2/WP3 against WP1 characterization fixtures).

---

## 10. Explicit non-goals

See ADR-0029. No CSV import/export, no REST write API, no flat-markup
seeding, no Quick Edit, no WordPress Products-list bulk-action dropdown
entries, no per-variation differentiated bulk editing beyond uniform
seed/clear, no new exchange-rate providers, no storefront/`PriceHooks`
change.

---

## 11. Work packages

WP0 docs (this freeze) → WP1 characterization → WP2 domain/orchestration
(`FixedPriceCoverageReport`, `FixedPriceCatalogOperationsService`) → WP3
admin surface (dedicated screen, coverage column) → WP4 CLI → WP5
architecture/security/performance guards → WP6 regression sweep + release
preparation.
