# ADR-0030: Fixed Pricing CSV Interchange

**Status:** Accepted (Milestone 25, target v0.24.0)

**Related:**
[`docs/architecture/fixed-pricing-csv-interchange.md`](../architecture/fixed-pricing-csv-interchange.md),
ADR-0025, ADR-0029

## Context

M24 (ADR-0029) explicitly deferred CSV import/export for fixed prices. The
only way to author a fixed price remains one product or variation at a time
through the WooCommerce product editor (M20), or a catalog-wide FX-derived
seed/clear (M24) — neither supports bulk, explicitly-authored interchange of
foreign-currency fixed prices via spreadsheet, which merchants with larger
catalogs or migration needs require.

M25 makes fixed foreign-currency prices practical to exchange in bulk by
extending WooCommerce's own product CSV export/import — not a parallel UMC
CSV subsystem — with structured, per-currency columns, while preserving
every M20–M24 monetary invariant unchanged.

Forensic investigation of WooCommerce's actual CSV importer/exporter source
(both the bundled ~10.9 checkout and the WC 8.2.3 GitHub tag, one patch below
this plugin's declared 8.2.5 floor) found: six stable, floor-safe extension
hooks that are the correct integration surface; a real, empirically
significant defect in WooCommerce's own generic custom-meta import mechanism
that can write directly to any underscore-prefixed post meta key — including
`_umc_fixed_prices` — bypassing every plugin validation layer, with WC's own
mapping UI pre-selecting that route by default for a column named
`meta:_umc_fixed_prices`; and that WooCommerce's export column picker is
opt-out, not opt-in, meaning any column a plugin registers is included in an
ordinary "export everything" run regardless of whether it is also registered
as an individually-selectable option.

## Decision

### Native WooCommerce integration, not a parallel CSV format

M25 adds columns to WooCommerce's own product CSV exporter/importer via six
existing extension hooks:
`woocommerce_product_export_column_names`,
`woocommerce_product_export_product_default_columns`,
`woocommerce_product_export_row_data`,
`woocommerce_csv_product_import_mapping_options`,
`woocommerce_csv_product_import_mapping_default_columns`,
`woocommerce_product_import_pre_insert_product_object`, and
`woocommerce_product_import_inserted_product_object`. Confirmed present,
identical in signature, at both the bundled ~10.9 checkout and the WC 8.2.3
tag. There is no separate UMC CSV admin page, no separate CSV file format,
and no separate authorization boundary — the feature rides entirely inside
WooCommerce's own already-authorized Products → Export / Import workflow.

### Canonical CSV representation — structured per-currency columns

One column pair per non-base configured currency (enabled or disabled):
`umc_fixed_regular_{code}` / `umc_fixed_sale_{code}` (internal id, lowercase
ISO code), labeled `UMC Fixed Regular Price (SEK)` / `UMC Fixed Sale Price (SEK)`.
Rejected: a single serialized blob column. WooCommerce's column model is
inherently scalar-per-column (one filter call returns one string value per
column per row); a blob would defeat spreadsheet editability, auto-mapping
by header text, and per-field clear granularity, none of which this design
gives up.

### Reused authorities — no parallel document format or validation

All reads and writes flow through the same three domain classes M20/M24
already established: `FixedPriceDocument` (`from_array()`/`from_storage()`/
`to_storage_json()`), `FixedPriceValidator::normalize_price()`, and
`FixedPriceRepository::get()/save()`. M25 introduces exactly one new shared
primitive, `FixedPriceDocumentMerger`, extracted from the mutation algorithm
`ProductFixedPricesPanel::persist_submission()` already implements, and now
used by all three authoring surfaces — the product editor, M24's catalog
operations service, and M25's CSV importer — so there is one mutation
authority, not three independent reimplementations.

Characterizing the two existing mutation paths (`ProductFixedPricesPanel`
and `FixedPriceCatalogOperationsService::merge_and_save()`) found they were
**not** semantically identical: the panel enforces
`FixedPriceValidator::sale_less_than_regular()` on the final merged pair;
the M24 service enforces it nowhere. `FixedPriceDocumentMerger` closes this
gap for all three callers. This is a deliberate, evidence-justified
hardening of M24 as a byproduct of extraction — it can only newly reject a
case no existing M24 test exercises (an inverted pair `seed()` never
produces in practice), never change behavior any current test asserts on.
A new M24 characterization test proves this explicitly.

### Export contract

Represents **authored** data only — read exclusively through
`FixedPriceRepository`, never through `ProductPriceResolutionService` (never
effective/converted pricing). Includes every non-base configured currency,
enabled or disabled-but-retained, so round-trip export/import cannot
silently drop disabled-currency data. Base currency never becomes a column.
Simple products and variations each project their own document; a variable
parent's UMC columns are always blank, enforced structurally (checking
product type before ever reading `FixedPriceRepository`), not incidentally.
Exactly one `FixedPriceRepository::get()` call per exported row, projecting
all currency columns from that single document.

Columns are registered in both the column-names hook and the
default-columns hook. WooCommerce's "which columns to export" picker is
opt-out (leaving it empty exports every registered column); registering in
both hooks means UMC data is included in an ordinary "export everything" run
by default — matching every other WC column's behavior — while still being
individually selectable for merchants who want to narrow their export.

### Import contract — conservative, field-level, WC-native semantics

For each mapped column: unmapped → untouched (session-wide, decided once at
the mapping step); mapped and genuinely blank → explicit clear of that one
field; mapped with a valid value → set; mapped with a non-blank but invalid
value → the field is skipped and logged, **never** cleared and **never**
coerced to zero. This requires inspecting the raw cell string for blankness
*before* calling `FixedPriceValidator::normalize_price()`, because that
function's `''` return value is otherwise ambiguous between "was blank" and
"failed validation" — collapsing them would either silently clear on a typo
or silently zero on the same, either being exactly the class of defect this
plugin's own `e33e474` fix already closed once for the product editor.

`FixedPriceDocumentMerger` seeds from the existing document, applies only
fields actually mapped and present on the row, then validates the **final
merged pair** for that currency (not the individual incoming cell). An
invalid result (`sale > regular`, from either a partial or full update)
rejects the entire currency entry atomically, reverting to its previous
stored state — matching the product editor's existing, tested behavior
exactly, never a partial write of just one field.

Currency validity at import time is governed entirely by the current
`CurrencyRegistry`, never by whatever configuration existed at export time:
a currency no longer configured is rejected (skip + log), never silently
recreated; a currency that has become the store's base currency since
export is rejected by the same base-currency defenses that would reject it
if it had always been base, because `CurrencyRegistry` is rebuilt fresh from
live settings every request.

Persistence for M25's own structured columns happens **exclusively** at
`woocommerce_product_import_inserted_product_object` — never at
`pre_insert_product_object` — because a CSV row lacking an `id`/SKU-resolvable
column (an uncommon but real, non-round-trip case) can leave the product
object with ID `0` at the pre-insert hook; the inserted-object hook is the
only one unconditionally guaranteed, for every create/update/simple/variation
combination, to fire after `$object->save()` has already succeeded with a
stable, real ID — and it never fires at all if that save failed, so a bad
UMC cell can never cause WooCommerce's own product write to be misreported.

### Raw-meta resync-to-database-truth defense

WooCommerce's generic `meta:`-prefixed CSV column mechanism writes directly
to arbitrary post meta, including underscore-prefixed keys, with no
protection check anywhere in the import pipeline (`is_protected_meta()` is
never invoked in this code path). A CSV author who knows the storage key
`_umc_fixed_prices` — already public in this plugin's own documentation —
can smuggle an unvalidated raw JSON document past every domain-layer defense
this plugin has, via a header (`meta:_umc_fixed_prices`) that WooCommerce's
own mapping auto-selector pre-selects by default, requiring no manual
remapping.

The defense registered at `woocommerce_product_import_pre_insert_product_object`
is **not** an unconditional delete. Direct inspection of `WC_Data::update_meta_data()`
showed the generic importer's write mutates the *existing* `WC_Meta_Data`
entry's value in place (matched by key, keeping its real database
`meta_id`) rather than adding a duplicate — meaning the object's in-memory
record of a pre-existing legitimate document is already overwritten by the
time this hook runs, and an unconditional delete would destroy that
legitimate document exactly as destructively as the attack it defends
against. The correct, implemented behavior instead **resyncs** the object's
in-memory `_umc_fixed_prices` entry to an independently, freshly read
database value (`get_post_meta()`, bypassing the object's own possibly-corrupted
in-memory cache entirely) before `$object->save()` persists anything: if a
legitimate value currently exists in the database, the in-memory entry is
restored to exactly that value, discarding any importer-authored mutation;
if none exists, the entry is removed so nothing is ever persisted from an
untrusted raw blob. This is unconditional and provably non-destructive in
every case — an existing legitimate document survives a normal import
byte-identical whether or not a malicious raw column was present, a new
product's raw-only column never persists, and when both a malicious raw
column and valid M25 structured columns are present on the same row, the
raw blob is discarded here while the structured columns are applied
correctly, afterward, at `inserted_product_object`, to the now-correctly-restored
existing document.

This guard runs on **every** product import through this plugin, not only
ones using M25's own structured columns — it is a blanket architectural
guard, not an opt-in — because the vulnerable native WooCommerce mechanism
it defends against exists independently of M25's own columns.

### Warning/error delivery — zero new persistence

No native WooCommerce extension point exists to append a third-party
column's validation warnings to the import results screen; the only working
technique to force a row into WC's own "failed" bucket requires throwing
from `inserted_product_object`, which fires *after* `$object->save()` has
already succeeded — using it would make WooCommerce misreport a genuinely
successful product write as failed, with no rollback. Rejected as an
anti-pattern. Field-level validation failures are instead skipped (never
cleared, never coerced) and logged via `wc_get_logger()->warning(...)` on a
dedicated `umc-csv-import` channel — WooCommerce's own existing, already-precedented
(export side) log store, requiring no new option, transient, meta key, or
DB table.

### Performance and idempotency

Export: exactly one `FixedPriceRepository::get()` per row regardless of
currency count. Import: zero repository interaction when no UMC structured
field is mapped/present on a row; at most one existing-document read once
anything is mapped; a save only when the merged result differs from what
was read — never an extra read merely to decide whether to skip a write, and
never a second write on a repeated, unchanged import.

### FX exclusion

CSV import/export is authoring and presentation of already-authored data,
never conversion. Neither direction may invoke `RateProvider`,
`PriceConversionService`, or `DisplayPriceConverter`, make a live HTTP call,
or read/mutate order or reporting data — enforced by a standing architecture
guard test, the same discipline `FixedPricingCatalogOperationsGuardTest`
already applies to M24.

### Explicit non-goals

REST write API for fixed prices; flat-markup bulk seeding; Quick Edit inline
fixed-price fields; scheduled bulk pricing; generic dynamic-pricing
compatibility claims; any change to `FixedPriceCatalogOperationsService`'s
seed/clear semantics beyond the shared-merger hardening above; any change to
`ProductFixedPricesPanel`'s UI beyond a discoverability callout; claims of
compatibility with third-party CSV/import tools (WP All Import, Product CSV
Import Suite, or similar) — only WooCommerce's own native core CSV
integration, at this plugin's declared floor and current CI legs, is
claimed.

### Persistence

No `Settings::SCHEMA_VERSION` bump (remains **7**). No `OrderSnapshot::SCHEMA_VERSION`
bump (remains **5**). No `PersistedKeys` inventory bump (remains **10** — no
new option, transient, meta key, or DB table; `_umc_fixed_prices` remains
the sole persisted surface, reused exactly as-is).

## Consequences

- Every M20/M24 monetary invariant (fixed-vs-converted exclusivity, base
  exclusion, disabled-currency retention, WC-authoritative sale scheduling,
  variation-native isolation) is preserved exactly — M25 changes only *how*
  fixed prices can be authored in bulk, never *what* a resolved price means.
- `FixedPriceDocumentMerger` becomes the single mutation authority for all
  three authoring surfaces, closing a validation gap M24 shipped with
  (missing `sale_less_than_regular()` enforcement) as a direct, deliberate
  byproduct of the extraction required for M25.
- WooCommerce's own generic custom-meta CSV mechanism is confirmed to be
  able to write to any plugin's underscore-prefixed product meta with no
  protection anywhere in core — a finding with implications beyond this
  plugin, addressed here only for `_umc_fixed_prices`.
- CSV import/export round-trip fidelity for fixed prices becomes a primary,
  tested acceptance criterion (semantic — canonical document and UMC-column-projection
  equivalence — never whole-file byte equality, which WooCommerce does not
  guarantee and this plugin does not own).
- REST pricing API, flat-markup seeding, and Quick Edit remain candidate
  future milestones, unaffected by this decision.
