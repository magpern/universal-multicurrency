# ADR-0029: Fixed Pricing Catalog Operations

**Status:** Accepted (Milestone 24, target v0.23.0)

**Related:**
[`docs/architecture/fixed-pricing-catalog-operations.md`](../architecture/fixed-pricing-catalog-operations.md),
ADR-0025, ADR-0021, ADR-0023

## Context

M20 (ADR-0025) shipped a correct fixed-vs-converted per-currency pricing engine
but explicitly deferred every operational tool needed to use it at catalog
scale: "bulk edit, import/export, REST write APIs… WP-CLI pricing audit." The
only way to author a fixed price today is `ProductFixedPricesPanel`, one
product or one variation row at a time through the WooCommerce product editor.
For a store with more than a handful of SKUs this is impractical, not merely
inconvenient.

M24 adds catalog-wide coverage visibility and bounded bulk seed/clear
operations over the unchanged M20 domain model, without introducing a second
pricing engine, a second conversion authority, or any persistence/schema
change.

## Decision

### Reused authorities — no parallel engine

All writes flow through the **existing, unmodified** `FixedPriceRepository`,
`FixedPriceValidator`, and `FixedPriceDocument` (M20).

All arithmetic flows through **`DisplayPriceConverter::convert_to( $amount,
$target, $rate )`**, bound to the existing `PriceConversionService` in
production — the *only* seam through which any code outside `Converter.php`
itself may reach monetary arithmetic. This codebase enforces that boundary as
a hard architectural guard
(`StorefrontGuardTest::test_converter_is_only_used_through_the_seam`): no
file other than `Converter.php` and `PriceConversionService.php` may
reference the `Converter` class at all. `FixedPriceCatalogOperationsService`
therefore never references `Converter` directly — it depends on
`DisplayPriceConverter` (the interface `PriceConversionService` implements)
and calls `convert_to()`, exactly the method `PriceConversionService`
already exposes for converting into an **explicit** target currency at an
**explicit** rate (as opposed to `convert()`, which is scoped to the
request's "active" shopper currency and is unsuitable here since admin/CLI
operations target a merchant-chosen currency, not the active one).
`convert_to()` internally calls `Converter::apply_rate()` — the same
static arithmetic the storefront path already uses — so M24 reuses the
identical arithmetic authority without ever touching `Converter.php` and
without violating the seam boundary. **No change to `Converter.php` or
`PriceConversionService.php` is made or required.**

M24 separately depends on `RateProvider` (a different, unrestricted
collaborator under `UMC\Rates\`) to resolve the single rate snapshot itself
(below) — the seam restriction applies only to the `Converter` class, not to
rate resolution.

### Coverage model

Coverage is computed per non-base currency.

**Simple product:**

| State | Condition |
|---|---|
| **Fixed** | Fixed regular price authored for the currency |
| **FX-fallback** | No fixed regular price authored |

No "Partial" state for simple products.

**Variable product — structural population, not runtime state:**

Population = every variation where `get_status( 'edit' ) === 'publish'`
(WooCommerce's own "Enabled" checkbox state — see § Variation-enabled API
below) **and** an authored native regular price exists
(`get_regular_price( 'edit' ) !== ''`). Disabled variations and variations
with no authored regular price are excluded from the population entirely.

| State | Condition |
|---|---|
| **Fixed** | Every population member has a fixed regular price for the currency |
| **Partial** | Some, but not all, population members do |
| **FX-fallback** | None do |
| **No priceable variations** | Population is empty — reported distinctly, never silently classified FX-fallback |

Coverage is deliberately **not** a function of `is_purchasable()` or stock
status — a variation going out of stock or becoming temporarily unpurchasable
must not change its coverage classification.

### Variation-enabled API

**Chosen:** `WC_Product_Variation::get_status( 'edit' ) === 'publish'`
(inherited from `WC_Product`/`WC_Data`, available since early WooCommerce —
present at the 8.2 floor).

**Evidence:** WooCommerce's own admin variation-save handler
(`WC_Meta_Box_Product_Data::save_variations()`,
`includes/admin/meta-boxes/class-wc-meta-box-product-data.php`) sets exactly
this field from the "Enabled" checkbox:

```php
'status' => isset( $_POST['variable_enabled'][ $i ] ) ? ProductStatus::PUBLISH : ProductStatus::PRIVATE,
```

and the admin variation row checkbox (`html-variation-admin.php`) is checked
based on the same field:

```php
checked( in_array( $variation_object->get_status( 'edit' ), array( 'publish', false ), true ), true );
```

This is WooCommerce's own merchant-controlled enabled/disabled toggle — fully
independent of `get_stock_status()` and of `is_purchasable()` (which
additionally factors in price presence and parent product status, and is
explicitly excluded from M24's coverage model). Compared against the literal
string `'publish'` rather than the newer `Automattic\WooCommerce\Enums\ProductStatus`
enum class, to remain valid at the 8.2 floor without a namespace-existence
guard.

### Seeding semantics — "seed from current FX conversion"

Not a numeric copy of the base amount. For each simple product or eligible
variation:

1. Read the **authored** native values: `get_regular_price( 'edit' )` and, if
   set, `get_sale_price( 'edit' )`. Never `get_price()` (current effective
   price) or WooCommerce's "is currently on sale" state — both depend on the
   sale schedule, which M24 does not read or write.
2. Convert each authored value via `DisplayPriceConverter::convert_to()` using
   the single rate resolved for this operation (below) and the target currency's
   decimals (`CurrencyRegistry`).
3. Persist the converted regular value as the fixed regular price. If an
   authored sale value existed, persist its converted value as the fixed sale
   price; if not, no fixed sale value is written.
4. A missing/malformed authored regular price is skipped and reported — never
   defaulted to zero or silently omitted from the result.
5. Variation seeding is **variation-native**: each variation's own authored
   values are its own source. The parent product is never used as a
   substitute source for a variation, and one variation's authored values are
   never used to seed a different variation.

WooCommerce's sale schedule (dates, active/inactive state) is untouched — M24
seeds from **authored amounts**, never from current schedule state. This
preserves ADR-0025's sale-state gating (`ProductSaleStateResolver`) unchanged.

### Single execution-rate snapshot per operation

A seed operation resolves **one** `base → target` rate via
`RateProvider::get_rate()` at the start of execution, and reuses that exact
rate for every product, every variation, and every batch (admin scope or CLI
`--all`) processed by that one operation. Rate resolution never happens
per-product or per-batch. If no rate is available, the operation aborts
before any writes — never partially seeded from an inconsistent set of rates.

The admin preview step's displayed rate is informational only (it may be
stale by the time the merchant confirms). The result of `execute()` reports
the actual rate used, and the admin flow explicitly surfaces any difference
from the previewed rate.

### Clear

Removes only the selected currency's entry from `_umc_fixed_prices`,
preserving every other currency's document and any other product's document
untouched. Uses the same merge algorithm `ProductFixedPricesPanel::persist_submission()`
already uses: read existing currencies, remove the target entry, rebuild via
`FixedPriceDocument::from_array()`, save.

### Base-currency exclusion (defense in depth, unchanged pattern from ADR-0025)

The currency selector (admin screen and CLI argument) is populated from
`CurrencyRegistry::get_currencies()` filtered to non-base, identical to
`ProductFixedPricesPanel`. The orchestration service independently rejects a
base-currency target even if one somehow reaches it.

### Preview → confirm → execute (admin only)

WordPress's `bulk_actions-edit-product` / `handle_bulk_actions-edit-product`
executes immediately on Apply with no native confirmation step and no way to
carry an explicit currency selection — unsuitable for a destructive,
currency-scoped operation. M24 instead adds a **dedicated admin screen**
(new `SettingsPage` section, no new top-level WordPress menu), following the
existing pattern used by Reporting/Compatibility/Decision Inspector.

- **Preview** (`admin-post.php`, nonce-verified): resolves the same filter/
  scope semantics execution will use, enforces the admin scope cap, evaluates
  per-product `current_user_can( 'edit_post', $product_id )`, and renders an
  affected-count + bounded product-name sample + currency + (for seed) an
  informational current rate.
- **Confirm → execute** (separate `admin-post.php` request, independently
  nonce-verified): recomputes scope from the submitted filter criteria (no
  stored/transient ID list — see § Persistence), re-checks per-product
  capability, re-enforces the scope cap, invokes
  `FixedPriceCatalogOperationsService`, and redirects with a deterministic
  result notice (succeeded / skipped / failed counts, and for seed, the
  actual execution rate).

This mirrors `RateUpdateController`'s existing nonce + flash-notice pattern.

### Authorization

- **Admin screen:** row-level `current_user_can( 'edit_post', $product_id )`
  per product, on both preview and execute, mirroring
  `ProductFixedPricesPanel::can_save_product()`.
- **CLI:** **no** `current_user_can()` check anywhere. WP-CLI execution is
  trusted administrative/system access, identical to the established
  `wp umc rates` precedent — `src/CLI/RatesCommand.php` performs zero
  capability checks across `status`, `refresh`, and `list`. This is
  intentional parity, not an oversight: WP-CLI may run without a normal
  logged-in user or with an explicit `--user`, and gating it on capability
  checks would make legitimate administrative CLI usage fail
  unpredictably. Access control for CLI usage is the host's shell/SSH
  boundary, the same as every other `wp umc` command today.

### CLI

```
wp umc prices list  [--currency=<code>] [--status=fixed|partial|fx]
wp umc prices seed  --currency=<code> [--product=<id>|--all] [--dry-run]
wp umc prices clear --currency=<code> [--product=<id>|--all] [--dry-run]
```

Shares `FixedPriceCatalogOperationsService`/`FixedPriceCoverageReport` with
the admin screen — no second seed/clear implementation. Rejects base
currency, invalid currency, invalid product ID, and ambiguous/missing scope.
Processes in documented batches (100–250, matching `ReportingService`'s
existing convention) but is not bounded in total catalog size — the CLI is
the sanctioned large-catalog path; the admin screen enforces a scope cap and
refuses beyond it. `--dry-run` runs the identical computation/classification
path and performs zero `FixedPriceRepository::save()` calls; its reported
outcome represents exactly what the real run would do.

Both `seed` and `clear` are idempotent: rerunning with identical inputs after
an interruption reprocesses already-handled products harmlessly. No
offset-based resumability is required.

### `umc_fixed_prices_saved` action

M24 writes fire the same `umc_fixed_prices_saved` action
(`($product_id, $document)`) that `ProductFixedPricesPanel` fires today — the
documented contract is "fixed prices were saved," not "saved via the product
editor" specifically, and an integrator relying on this hook to invalidate a
downstream cache must be notified regardless of which surface wrote the data.
`docs/HOOKS.md` is updated to reflect both origins.

### Persistence

No Settings schema bump (remains **7**). No OrderSnapshot schema bump
(remains **5**). No `PersistedKeys` inventory bump (remains **10** — no new
option, transient, meta key, or DB table). "All products matching the current
filter" is **recomputed from the filter parameters** at both preview and
execute time rather than stored in a transient/session/option, so no new
persistence surface is introduced for that flow. A product that changes
between preview and execute is processed under its state at execute time and
reported accordingly.

### Explicit non-goals

CSV import/export (both directions — deferred together to a future
milestone); REST write API for fixed prices; flat-markup bulk seeding
(e.g. "base × 1.10"); Quick Edit inline fixed-price fields; per-variation
differentiated bulk editing beyond uniform seed-from-FX/clear applied
variation-natively; new exchange-rate providers, provider fallback, or
per-currency provider selection (frozen by ADR-0021); WordPress
Products-list bulk-action dropdown entries (superseded by the dedicated
screen); any change to storefront price resolution, `PriceHooks`, sale-state
gating, WooCommerce's sale schedule, or variation cache identity.

## Consequences

- M20's runtime contract (fixed OR converted, never fixed-then-converted;
  base exclusion; disabled-currency retention; WC-authoritative sale
  scheduling) is preserved exactly — M24 changes *how many products can be
  edited per action*, never *what a resolved price means*.
- `Converter`'s public static arithmetic methods gain their first production
  caller outside `PriceConversionService`, exercising the class exactly as
  its own documentation already describes ("the single owner of monetary
  arithmetic in the plugin").
- Merchants can adopt fixed pricing at realistic catalog scale by seeding
  from today's market rate and then hand-tuning only what needs adjustment.
- CSV import/export, REST pricing API, flat-markup, and Quick Edit remain
  candidate future milestones, informed by real M24 usage patterns.
