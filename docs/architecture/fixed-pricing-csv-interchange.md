# Fixed Pricing CSV Interchange — M25 architecture

**Milestone:** 25 · **Target version:** 0.24.0 · **Baseline:** origin/main
`27507f3122de4b361038d43726f75c72c3192b83` (M24 / v0.23.0 closure)

**ADR:** [`docs/adr/0030-fixed-pricing-csv-interchange.md`](../adr/0030-fixed-pricing-csv-interchange.md)

---

## 1. Scope

Bulk, explicitly-authored interchange of per-currency fixed prices via
WooCommerce's own native product CSV export/import, over the unchanged M20
domain model
([`docs/architecture/authoritative-fixed-product-pricing.md`](authoritative-fixed-product-pricing.md))
and reusing M24's established reuse discipline
([`docs/architecture/fixed-pricing-catalog-operations.md`](fixed-pricing-catalog-operations.md)).
No new pricing engine, no new conversion authority, no schema change, no
second CSV format.

---

## 2. Component map

```text
        FixedPriceDocument / Validator / Repository (existing, M20 — unchanged)
                                    │
                                    │ get() / save()
                                    ▼
                  ┌──────────────────────────────────────────┐
                  │      FixedPriceDocumentMerger (new)        │
                  │  seed from existing → apply touched fields │
                  │  → validate final pair (sale ≤ regular)     │
                  │  → atomic accept/revert per currency entry  │
                  └───────┬──────────────┬──────────────┬──────┘
                          │              │              │
             ┌────────────▼───┐ ┌────────▼────────┐ ┌───▼──────────────────┐
             │ ProductFixed    │ │ FixedPriceCatalog│ │ FixedPriceCsvImport  │
             │ PricesPanel     │ │ OperationsService │ │ (new, M25)           │
             │ (existing, M20) │ │ (existing, M24)   │ │ inserted_product_    │
             │ now delegates   │ │ now delegates      │ │ object → merge/save  │
             │ merge to shared │ │ merge to shared     │ │                       │
             │ primitive       │ │ primitive           │ │                       │
             └─────────────────┘ └────────────────────┘ └───────────┬───────────┘
                                                                     │
                                                        ┌────────────▼────────────┐
                                                        │ FixedPriceCsvIntegration │
                                                        │  (new, M25 — the one     │
                                                        │  WC-hook-aware adapter)  │
                                                        └────────────┬────────────┘
                                                                     │ registers, once, from Plugin bootstrap
                    ┌────────────────────────────────────────────────┼────────────────────────────────────────┐
                    │ export hooks                                   │ import hooks
                    ▼                                                 ▼
     woocommerce_product_export_column_names            woocommerce_product_import_pre_insert_product_object
     woocommerce_product_export_product_default_columns    → raw-meta resync-to-database-truth defense (§5)
     woocommerce_product_export_row_data                 woocommerce_csv_product_import_mapping_options
        → FixedPriceRepository::get() once per row,      woocommerce_csv_product_import_mapping_default_columns
          project all currency columns (§4)               woocommerce_product_import_inserted_product_object
                                                              → structured-column merge/save (§6)
```

`FixedPriceCsvIntegration` is the single adapter holding all WooCommerce-CSV
hook knowledge. `FixedPriceDocumentMerger` and `FixedPriceValidator` remain
WooCommerce-agnostic domain code, callable identically from the product
editor, M24's catalog service, and M25's importer.

---

## 3. Canonical CSV representation

One column pair per non-base currency currently present in
`CurrencyRegistry::get_currencies()` (enabled or disabled-but-configured):

| Internal id | Merchant-visible label (example: SEK) |
|---|---|
| `umc_fixed_regular_sek` | `UMC Fixed Regular Price (SEK)` |
| `umc_fixed_sale_sek` | `UMC Fixed Sale Price (SEK)` |

Never generated for the base currency. Regenerated from live configuration
on every export/import request — never cached across requests, so a
currency added, removed, disabled, or re-enabled between two runs is always
reflected correctly (§8).

Rejected alternative: a single serialized blob column
(`SEK:regular=1000;sale=900|EUR:…`). WooCommerce's exporter/importer model
is inherently one-scalar-value-per-column; a blob defeats auto-mapping by
header text, spreadsheet editability, and per-field clear granularity, none
of which structured columns give up.

---

## 4. Export

`FixedPriceCsvIntegration` registers:

- `woocommerce_product_export_column_names` — adds the full set of
  `umc_fixed_regular_{code}` / `umc_fixed_sale_{code}` id → label pairs for
  every currently non-base configured currency.
- `woocommerce_product_export_product_default_columns` — registers the same
  set, so merchants narrowing their export can individually pick/exclude a
  UMC column. WooCommerce's column picker is opt-out, not opt-in: leaving it
  empty (the common workflow) exports every column from the first hook
  regardless of the second — registering in both hooks does not create an
  opt-out surprise, it only adds an opt-in convenience on top of behavior
  that already includes UMC data by default, identical in kind to every
  native WC column (Weight, Dimensions, GTIN, …).
- `woocommerce_product_export_row_data` — for each row, calls
  `FixedPriceRepository::get( $product->get_id() )` **exactly once**, and
  projects every configured currency's `regular`/`sale` values from that one
  returned document into the corresponding columns. Never fetches per
  column.

Row rules:

- **Simple product**: from its own document.
- **Variation**: from its own document (its own post's `_umc_fixed_prices`
  — never inherited or computed from the parent).
- **Variable parent**: UMC columns are always blank, enforced by checking
  `$product->get_type() !== 'variable'` before ever calling
  `FixedPriceRepository::get()` for that row — a structural exclusion, not
  an incidental one.
- **Malformed/legacy stored data**: `FixedPriceDocument::from_storage()`'s
  existing graceful degradation (bad JSON → empty document) means a bad row
  exports blank UMC columns, never aborts the batch.
- Represents **authored** data only — reads exclusively through
  `FixedPriceRepository`; never touches `ProductPriceResolutionService`, so
  effective/converted pricing is never exported as if it were authored.

WooCommerce's formula-injection escaping (`WC_CSV_Exporter::escape_data()`)
applies generically to every column, including these — no independent
escaping is implemented, and the value domain (a plain non-negative decimal
or blank) makes injection structurally impossible regardless.

---

## 5. Import — raw-meta resync-to-database-truth defense

Registered at `woocommerce_product_import_pre_insert_product_object`, and
running on **every** product import through this plugin regardless of
whether M25's own structured columns are present:

```
$product_id = $object->get_id();
$db_value   = ( $product_id > 0 && metadata_exists( 'post', $product_id, '_umc_fixed_prices' ) )
    ? get_post_meta( $product_id, '_umc_fixed_prices', true )
    : null;

if ( null === $db_value ) {
    $object->delete_meta_data( '_umc_fixed_prices' );
} else {
    $object->update_meta_data( '_umc_fixed_prices', $db_value );
}
```

**Why this shape, precisely, and not an unconditional delete:**
`WC_Data::update_meta_data( $key, $value, $meta_id = 0 )` — the method
WooCommerce's own generic `meta:`-prefixed importer calls for a
`meta:_umc_fixed_prices` column — matches the *existing* `WC_Meta_Data` entry
by key (when no `$meta_id` is given) and **mutates its `value` property in
place**, keeping the entry's real, original database `meta_id`. It does not
add a duplicate. This means: for an existing product with a legitimate
document, if a malicious/hand-authored raw column is present, the object's
own in-memory record of the legitimate value is *already gone* — overwritten
— by the time this hook runs. An unconditional `delete_meta_data()` here
would therefore neutralize the attack but destroy a legitimate,
untouched-by-the-merchant document exactly as destructively, since the hook
cannot distinguish "this was mutated by an attack" from "this was never
touched" by inspecting `$object` alone.

The implemented fix instead re-reads the **current, independent database
state** for this exact meta key (`get_post_meta()`, bypassing `$object`'s own
possibly-already-corrupted in-memory cache entirely) and forces the
in-memory entry back to match it before `$object->save()` runs. Verified
against `WC_Data::save_meta_data()`: a null-valued entry with a real
`meta_id` triggers a genuine `data_store->delete_meta()` call (correct only
when the database truly holds nothing); a null-valued entry with no
`meta_id` (a malicious column added a fresh, never-persisted entry on a
brand-new product) is simply never written at all — neither deleted nor
added.

| Scenario | Outcome |
|---|---|
| Existing legitimate document; no raw column in the CSV | `$db_value` matches what was already in memory; the resync is a no-op; the document survives byte-identical |
| Existing legitimate document; malicious `meta:_umc_fixed_prices` column | The malicious in-memory mutation is overwritten back to the real database value before `save()`; the legitimate document is unaffected |
| Existing legitimate document; malicious raw column **and** valid M25 structured columns on the same row | The raw mutation is discarded by this hook; afterward, at `inserted_product_object` (§6), the merger reads the now-correctly-restored existing document and applies only the sanctioned structured-column changes |
| New product; malicious raw column only, no structured columns | No database value exists yet for this key; the untrusted in-memory value is never persisted |

This closes a real, empirically confirmed bypass: WooCommerce's importer
never calls `is_protected_meta()` anywhere in the product-import write path
(confirmed by direct read of the importer, `WC_Data`, and the product CPT
data store), so an underscore-prefixed key receives no special protection
from WooCommerce core on its own — and WooCommerce's own mapping
auto-selector **pre-selects** "Import as meta data" by default for a header
literally named `meta:_umc_fixed_prices`, requiring no manual remapping by
the merchant.

---

## 6. Import — structured columns

`FixedPriceCsvIntegration` also registers:

- `woocommerce_csv_product_import_mapping_options` /
  `woocommerce_csv_product_import_mapping_default_columns` — expose and
  auto-map the `umc_fixed_regular_{code}` / `umc_fixed_sale_{code}` columns
  by header text, identical in mechanism to WooCommerce's own native column
  mapping.
- `woocommerce_product_import_inserted_product_object` — the **only** place
  M25 calls `FixedPriceRepository`. Never at `pre_insert_product_object`:
  characterizing WooCommerce's importer showed a CSV row lacking an
  `id`/SKU-resolvable column can leave `$object->get_id()` at `0` at the
  pre-insert hook (an uncommon, non-round-trip case, since every
  WooCommerce-exported file includes an `id` column), whereas the
  inserted-object hook unconditionally fires only after `$object->save()`
  has already succeeded with a stable, real ID — for every
  create/update/simple/variation combination — and never fires at all if
  that save failed, so a bad UMC cell can never cause an otherwise-valid
  WooCommerce product row to be misreported as failed.

### Field semantics

| CSV state | Meaning |
|---|---|
| Column not mapped this session | Field entirely untouched |
| Mapped, cell genuinely blank | Explicit clear of that one field |
| Mapped, cell has a valid value | Set/overwrite |
| Mapped, cell has a non-blank invalid value | Field skipped, logged (never cleared, never coerced to zero) |

The raw CSV cell string is inspected for blankness (`'' === trim( $raw )`)
**before** calling `FixedPriceValidator::normalize_price()`, because that
method's `''` return value is otherwise ambiguous between "was blank" and
"failed validation" — collapsing them would recreate a sibling of the
non-numeric-input-becomes-zero defect this plugin's `e33e474` fix already
closed once, except here the failure mode would be an unintended clear
rather than an unintended zero.

### Sale ≤ regular atomicity

`FixedPriceDocumentMerger` (§7) validates the **final merged pair** for a
currency, not the individual incoming cell. A partial regular-only or
sale-only update that would make the resulting pair invalid — or a full
update supplying both at once — rejects the **entire currency entry**,
reverting it to its previous stored state (or absence), atomically. Logged
via `wc_get_logger()->warning( $message, [ 'source' => 'umc-csv-import' ] )`
with product ID, currency, field, and reason.

### Currency validity — current configuration is authoritative

`CurrencyRegistry` is rebuilt fresh from live `Settings`/
`get_woocommerce_currency()` on every request — never cached across
requests — so import-time validation always reflects import-time
configuration:

| State | Behavior |
|---|---|
| Store base currency | Never a column; if smuggled via the raw-meta route, blocked by §5; if via a hand-crafted structured column name, blocked by `FixedPriceDocument`'s existing multi-layer base strip |
| Enabled configured currency | Full read/write |
| Disabled-but-configured currency | Full read/write — the product editor's read-only presentation for disabled currencies is a UI nicety, not a data-layer rule; no enabled-state gate exists in the write path |
| Currency no longer configured (removed since export) | Rejected — field-level skip + log, never silently recreated |
| A currency that has become the store's base currency since export | Rejected by the same current-configuration-authoritative base defenses, as if it had always been base |

### No new persistence

`_umc_fixed_prices` remains the sole storage surface. No new meta key,
option, transient, or DB table. `Settings::SCHEMA_VERSION` stays 7,
`OrderSnapshot::SCHEMA_VERSION` stays 5, `PersistedKeys::INVENTORY_VERSION`
stays 10.

---

## 7. `FixedPriceDocumentMerger`

Extracted from `ProductFixedPricesPanel::persist_submission()`'s existing
algorithm, now the shared mutation authority for the product editor, M24's
catalog service, and M25's CSV importer:

1. Seed a working map from the existing document's currencies.
2. Apply only fields actually supplied/touched by the caller for a given
   currency (never falling back to a stale value for an omitted sub-field
   within a currency actively being written).
3. Normalize through `FixedPriceValidator::normalize_price()` (zero valid,
   negative/non-numeric rejected).
4. Validate the final merged pair via
   `FixedPriceValidator::sale_less_than_regular()`. On failure, reject the
   **entire currency entry atomically** and revert to its previous state —
   never a partial field write.
5. Rebuild via `FixedPriceDocument::from_array()` (which independently
   re-strips any base-currency entry as a second defense layer) and persist
   via `FixedPriceRepository::save()`.

**M24 hardening, deliberate and documented, not incidental**: prior to this
extraction, `FixedPriceCatalogOperationsService::merge_and_save()` called
`sale_less_than_regular()` nowhere — an inverted pair from decimal-rounding
edge cases in `seed()`'s FX conversion, while never observed in production
and not exercised by any existing test, would have been silently persisted.
After extraction, this can no longer happen. A dedicated M24 regression test
proves a seed scenario engineered to produce an inverted pair (via a test
double) is now safely rejected/reverted rather than silently persisted, and
the full pre-existing M24/M20 test suite passes unmodified otherwise.

---

## 8. Round-trip contract — semantic, not byte-level

WooCommerce may legitimately vary row ordering, unrelated columns, quoting,
and formatting between two export runs — none of that is a UMC concern.
Two separately-verified contracts:

- **Persistence round trip**: `canonical FixedPriceDocument → export →
  import/update → canonical FixedPriceDocument` must be an equivalent
  document (same currencies, same values). Where `FixedPriceRepository`'s
  serialization is already deterministic, byte-identical stored JSON may
  additionally be asserted.
- **UMC projection round trip**: `export A → import → export B` must
  produce equivalent UMC column values for matching rows — never a claim
  about the full CSV byte stream.

---

## 9. Performance / idempotency

| Condition | Repository interaction |
|---|---|
| No UMC structured fields mapped/present on the row | Zero |
| One or more mapped/present fields | At most one `get()` |
| After merge, result differs from what was read | At most one `save()` |
| All mapped fields invalid, or result equals what was read | No `save()` |

No extra read is introduced merely to decide whether to skip a write — the
no-op check compares the merged document against the single document
already read for the merge itself. Repository memoization
(`FixedPriceRepository`'s per-request cache) is confirmed safe across a
batch: `save()` updates the cache in place rather than merely invalidating
it, so a later row referencing the same product ID within one WooCommerce
import batch never observes a stale pre-save value.

---

## 10. Security / architecture guards

- FX exclusion: neither export nor import may reference `RateProvider`,
  `PriceConversionService`, or `DisplayPriceConverter`, make a live HTTP
  call, or touch order/reporting data — enforced by a standing guard test,
  mirroring `FixedPricingCatalogOperationsGuardTest`.
- Raw-meta resync-to-database-truth defense (§5) is a permanent, standing
  regression test, not a one-time characterization proof.
- Authorization is entirely WooCommerce core's: capability (`import`/
  `export`/`edit_products`), nonce, and file-upload validation all happen
  inside WC's own controller/Ajax endpoints before any UMC hook ever fires.
  M25 adds no parallel endpoint, capability, or nonce.

---

## 11. Explicit non-goals

REST write API for fixed prices; flat-markup bulk seeding; Quick Edit inline
fixed-price fields; scheduled bulk pricing; generic dynamic-pricing
compatibility claims; changes to `FixedPriceCatalogOperationsService`'s
seed/clear semantics beyond the shared-merger hardening in §7; changes to
`ProductFixedPricesPanel`'s UI beyond a discoverability callout linking to
WooCommerce's native Export/Import screens; compatibility claims for
third-party CSV/import tools.
