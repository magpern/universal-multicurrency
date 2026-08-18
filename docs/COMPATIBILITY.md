# Compatibility

This document is the single authoritative source for what Universal
Multicurrency supports: minimum versions, the CI matrix that exercises them,
which WooCommerce feature surfaces are covered where, and which third-party
currency switchers are detected as incompatible. `CompatibilityMatrixTest`
mechanically enforces agreement between this document, the plugin header,
`composer.json`, `phpcs.xml.dist`, `CLAUDE.md`, `.github/workflows/ci.yml`,
and `DetectorManifest::manifest()`.

Nothing in this document is aspirational. Every row cites the CI leg or test
that produced it; where no such evidence exists, the row is simply absent (see
§ How to read this document).

Third-party currency-switcher detection is advisory only: the plugin observes
passive presence evidence and never deactivates, modifies, or calls into
another plugin. See § Detection and § Known incompatible.

Reaching **v1.0.0** does not change this evidence model or promote any tier:
third-party extension compatibility, the CI matrix, and every label below are
carried forward exactly as earned through Milestone 25.

## How to read this document

### Labels

Every compatibility claim below uses one of six labels, applied per
**coordinate** (a specific version, or a specific version plus feature
surface) — never as a blanket claim about "the plugin" as a whole.

| Label | Meaning | What the project commits to |
|---|---|---|
| **Supported** | A named CI leg exercises this exact coordinate on every pull request, and is green. | Release-blocking; bug fixes at release priority. |
| **Works with** | Verified manually, or by a test that exists but does not run on every PR. | Best-effort triage and fixes; no CI gate; no promise against upstream drift. |
| **Untested** | No evidence either way. This is the default — it is never written into a row, and its absence from a table *is* the claim. | Reports accepted and triaged. |
| **Unsupported** | Outside the declared floor or ceiling. | Nothing. Activation is blocked by the plugin header, or the plugin stays inert. |
| **Incompatible** | A reproduced, named conflict this plugin cannot fix from its own side. | Detection and a warning only — never automatic remediation. |
| **Experimental** | Shipped, but with a deliberately unfrozen contract. | No backwards-compatibility promise. |

Third-party plugins can never be labelled *Supported* — they cannot run in
continuous integration. Their ceiling is *Works with*.

### Evidence tiers

Labels are derived from evidence, not asserted. In increasing strength:

- **Untested (E0)** — no evidence.
- **Works with (E1/E2)** — verified once manually, or by a test that exists in
  the repository but is not run on every pull request.
- **Tested / Supported (E3)** — a named, currently-configured CI leg exercises
  the exact coordinate on every pull request and is green. This document uses
  "Tested" and "Supported" interchangeably for this tier: *Tested* describes
  the evidence, *Supported* describes the commitment that evidence buys.
- **Incompatible (E-negative)** — a reproduced failure with a named cause.

### Ceiling / early-warning — not a compatibility label

The `ceiling` CI leg (§ The supported-version CI matrix) is a monitoring
mechanism, not a compatibility claim, and it does not fit the label table
above. It deliberately tracks the moving `latest` tag for WooCommerce rather
than a fixed version, so that a passing `ceiling` run means only "nothing has
broken against whatever WooCommerce currently publishes as latest" — which,
at any given time, may be a stable release or a pre-release build. The leg is
configured `continue-on-error`, so a `ceiling` failure never blocks a merge; it
exists purely to surface upstream drift early. **A green `ceiling` run
establishes nothing about production support for the WooCommerce version it
happened to test against** — that version is not pinned, is not announced, and
may not even be a final release. Production support claims in this document
come only from the `floor`, `current`, `mixed-php-floor` and `mixed-wp-floor`
legs, which pin exact, reproducible coordinates.

## Merchant-facing summary

If your store runs **PHP 8.1 or newer, WordPress 6.5 or newer, and WooCommerce
8.2 or newer**, every core feature of this plugin is supported: the currency
switcher, cart and checkout (both the classic flow and the Cart/Checkout
blocks), coupons, shipping, payment gateway filtering, High-Performance Order
Storage, and the permanent per-order currency snapshot.

There is one narrow, precisely-scoped exception: on WooCommerce versions at
the 8.2 floor specifically, viewing or paying for an *existing* order through
the block-based Store API (the "order confirmation" and "pay for order" REST
routes) is not covered by this plugin's own automated tests, because
WooCommerce itself does not register those routes on that version — the
classic (non-block) equivalent of the same screens is unaffected and fully
covered. See § The floor's Store API order-route exclusion for the technical
reason, and § WooCommerce feature surfaces for exactly which surfaces this
does and does not touch.

## Machine-readable summary

<!-- umc:versions:start -->
| Axis | Minimum supported | Recommended | Tested up to | CI-exercised | Label at minimum |
|---|---|---|---|---|---|
| PHP | 8.1 | 8.3 | 8.4 | 8.1, 8.3, 8.4 | Supported |
| WordPress | 6.5 | latest stable release | 7.0.2 (floats with `wp-phpunit`; not independently pinned) | 6.5.8, 7.0.2 | Supported |
| WooCommerce | 8.2 | current major (10.x) | 10.9.4 | 8.2.5, 10.9.4, latest (ceiling, non-blocking) | Supported |
<!-- umc:versions:end -->

## Version support

### PHP

- **Minimum supported: 8.1.** Verified by the `floor` and `mixed-php-floor` CI
  legs, and by `composer.json`'s `config.platform.php = 8.1.99` (dependency
  resolution is pinned as-if-8.1 on every leg, so no dependency the plugin
  installs can silently require a newer PHP).
- **Recommended: 8.3.** The version the `current` and `mixed-wp-floor` legs
  run, and the version most representative of an actively-maintained
  WordPress host today.
- **Tested up to: 8.4.** The `ceiling` leg's PHP version. PHPUnit 9.6 (this
  project's pinned test runner) proved fully compatible with PHP 8.4 in this
  milestone's verification — no PHPUnit-internal deprecations were observed.
- **CI-exercised:** 8.1 (`floor`, `mixed-php-floor`, and the unit job's PHP
  matrix), 8.3 (`current`, `mixed-wp-floor`, and the unit job), 8.4 (`ceiling`
  and the unit job).

### WordPress

- **Minimum supported: 6.5.** Verified by the `floor` and `mixed-wp-floor` CI
  legs, which pin `wp-phpunit/wp-phpunit` to the `6.5.*` series (resolved to
  6.5.8 during this milestone's verification) and download matching WordPress
  core. `phpcs.xml.dist`'s `minimum_wp_version` also declares 6.5.
- **Recommended: the latest stable WordPress release.**
- **Tested up to: 7.0.2** at the time of this milestone's verification. This
  number is **not an independent pin** — it is whatever
  `wp-phpunit/wp-phpunit` resolves to from `composer.json`'s
  `"^6.5 || ^7.0"` constraint when `composer.lock` was last updated, and it
  will move the next time that lockfile changes. Treat it as "the version
  most recently proven to work," not as a ceiling.
- **CI-exercised:** 6.5.8 (`floor`, `mixed-wp-floor`), 7.0.2 (`current`,
  `mixed-php-floor`, `ceiling` — via the unpinned/"auto" resolution).

### WooCommerce

- **Minimum supported: 8.2.** This floor is derived, not arbitrary: High-
  Performance Order Storage (a hard requirement of this plugin) reached
  general availability in WooCommerce 8.2. Verified by the `floor` CI leg
  against WooCommerce 8.2.5 — the latest patch release in the 8.2 series.
- **Recommended: the current major version line (10.x).**
- **Tested up to: 10.9.4**, pinned explicitly in CI (`current`,
  `mixed-php-floor`, `mixed-wp-floor` legs) because the Store API test suite
  asserts response shapes, and an unpinned "latest" would resolve to
  pre-release builds whose changes would surface as CI failures unrelated to
  the plugin.
- **`ceiling` observation (non-blocking, not a support claim):** the `ceiling`
  leg's `latest` resolution was observed at **11.0.0-beta.2** — a pre-release
  build — during this milestone's verification, and passed cleanly. This is
  exactly the scenario § Ceiling / early-warning describes: useful early
  signal, no production commitment.
- **CI-exercised:** 8.2.5 (`floor`), 10.9.4 (`current`, `mixed-php-floor`,
  `mixed-wp-floor`), `latest` — floating, currently 11.0.0-beta.2 (`ceiling`,
  non-blocking).

### Planned floor changes

None currently planned. A floor raise, when proposed, would ship only in a
minor release, be announced here at least 90 days and one release ahead of
the release that raises it, and change the plugin header, `composer.json`,
`phpcs.xml.dist`, `.github/workflows/ci.yml` and this document atomically —
the same commit, every source moving together.

## The supported-version CI matrix

Full cross-product of the three axes above is 27 combinations. This plugin
tests **the corners of the supported box, plus the two coordinates that
isolate each axis** — five integration legs, each independently attributable
if it fails:

| Leg | PHP | WordPress | WooCommerce | Why this coordinate exists |
|---|---|---|---|---|
| `floor` | 8.1 | 6.5.8 | 8.2.5 | The lowest supported corner — the only leg that substantiates the declared floors together. |
| `current` | 8.3 | 7.0.2 | 10.9.4 | Today's baseline coordinate; the authority for Store API response-shape assertions. |
| `mixed-php-floor` | 8.1 | 7.0.2 | 10.9.4 | Isolates the PHP axis: floor PHP against otherwise-current WordPress/WooCommerce. |
| `mixed-wp-floor` | 8.3 | 6.5.8 | 10.9.4 | Isolates the WordPress axis: floor WordPress against otherwise-current PHP/WooCommerce. Without this leg, a `floor` failure could not be attributed to the PHP axis or the WordPress axis. |
| `ceiling` | 8.4 | 7.0.2 | latest (floating; observed 11.0.0-beta.2) | Early warning on upstream drift. Non-blocking — see § Ceiling / early-warning. |

All five ran cleanly in this milestone's verification: `floor` at 307 of 315
tests (see § The floor's Store API order-route exclusion for the other 8);
every other leg at the full 315.

## WooCommerce feature surfaces

| Surface | Status | Since | Evidence |
|---|---|---|---|
| High-Performance Order Storage (custom order tables) | Supported | 0.3.0 | `ci:current`, `ci:floor` — the integration bootstrap enables HPOS identically on every leg |
| Legacy CPT order storage | Works with | 0.4.0 | `LegacyOrderTest` — read and refund only; not CI-exercised on every PR |
| Classic cart and checkout | Supported | 0.3.0 | `ci:current`, `ci:floor` |
| Cart Block / Checkout Block | Supported | 0.5.0 | `ci:current` |
| Store API: cart, checkout creation, products, coupons, shipping, gateway filtering | Supported | 0.5.0 | `ci:current`, `ci:floor` |
| Store API: order-confirmation and pay-for-order routes (`Order`, `CheckoutOrder`) | **Untested at the floor** — see § The floor's Store API order-route exclusion; **Supported at current** | 0.5.0 | `ci:current`; excluded at the floor via `@group wc-order-route-unavailable` |
| Order-pay and order-confirmation through the **classic** (non-block) flow | Supported | 0.4.0 | `ci:current`, `ci:floor` — unaffected by the Store API route gap above |
| Product price-filter block | Works with (base currency only) | 0.5.0 | Known limitation carried from Milestone 5 |

## WooCommerce transaction integrity (Milestone 18)

Evidence-linked commerce matrix. Labels:

- **Supported** — automated evidence on blocking CI
- **Characterized** — covered by tests but not a full combinatorial claim
- **Known limitation** — intentional boundary
- **Out of scope** — deferred (typically M19)

| Feature | Classic | Blocks/Store API | Admin | Status | Test evidence |
|---|---|---|---|---|---|
| Simple / sale / variation prices | ✓ | ✓ | base/stored | Supported | `StorefrontConversionTest`, `ProductsRouteConversionTest` |
| Variation price hash (code **and** rate) | ✓ | ✓ | — | Supported | `StorefrontConversionTest::test_variation_prices_hash_includes_currency_and_rate` |
| Grouped / external product display | ✓ | — | — | Characterized | `StorefrontConversionTest` smoke |
| Fixed + percentage coupons; min/max spend | ✓ | ✓ | — | Supported | `CouponConversionTest`, `CartRouteConversionTest` |
| Core shipping cost conversion | ✓ | ✓ | — | Supported | `ShippingConversionTest`, `ShippingRateParityTest` |
| Free-shipping `min_amount` threshold | ✓ | ✓ | — | Supported | `FreeShippingThresholdTest`, `FreeShippingThresholdParityTest`, `ClassicStoreApiParityTest` |
| Cart currency / rate transition | ✓ | ✓ | — | Supported | `CartConversionTest`, `CartCurrencyTransitionTest`, `CartCurrencySwitchTest` |
| Tax on converted amounts (WC owns tax) | ✓ | ✓ | — | Supported | `TaxReconciliationTest`, `ClassicStoreApiParityTest` |
| Classic ↔ Store API totals parity | ✓ | ✓ | — | Supported | `ClassicStoreApiParityTest` |
| Order snapshot schema 4 / HPOS CRUD | ✓ | ✓ | meta box | Supported | `TransactionOrderTest`, `CheckoutSnapshotTest` |
| Refunds use parent order context | — | — | ✓ | Supported | `RefundConversionTest` |
| Order-pay / thank-you / emails historical | ✓ | ✓ | — | Supported | `OrderPayCurrencyLockTest`, `HistoricalOrderDisplayTest` |
| `/wc/store/` shopper conversion | — | ✓ | — | Supported | Store API suite, `RestCurrencyBoundaryTest` |
| `/wc/v3` admin REST non-conversion | — | — | ✓ | Supported | `RestCurrencyBoundaryTest` |
| Cart fees auto-converted | — | — | — | Known limitation | Default pass-through; opt-in via `umc_convert_fee` (`FeeConversionTest`, `FeeBoundaryTest`) |
| Third-party shipping | — | — | — | Characterized | Pass-through + opt-in `umc_convert_shipping_rate` (`ShippingConversionTest`) |
| Third-party extensions | — | — | Compatibility Center | Characterized (E2) | See § Third-party WooCommerce extensions (Milestone 19) |

Authoritative invariants: [`docs/architecture/woocommerce-transaction-integrity.md`](architecture/woocommerce-transaction-integrity.md), ADR-0023.

## Third-party WooCommerce extensions (Milestone 19)

Evidence-linked extension matrix. **Integrated** requires E3 real-extension
validation only. E1/E2 cap at **Characterized** with explicit sub-labels.

| Extension | Status line | Evidence | Surfaces |
|---|---|---|---|
| WooCommerce Subscriptions | Characterized — simulated extension hooks | E2 | Initial checkout (normal UMC seam); renewal browsing-currency isolation only |
| Product Add-Ons | Characterized — simulated extension hooks | E2 | UMC generic add-on price seam (real extension hook unverified at E2) |
| Product Bundles | Characterized — simulated extension hooks | E2 | UMC generic bundled-item price seam (real extension hook unverified at E2) |
| Composite Products | Not evaluated | E0 | Investigation deferred; Bundles chosen as bounded M19 integration |
| WooCommerce Bookings | Not evaluated | E0 | M19 audit-only |

Authoritative spec: [`docs/architecture/extension-compatibility.md`](architecture/extension-compatibility.md), ADR-0024, [`docs/EXTENSION_INTEGRATION.md`](EXTENSION_INTEGRATION.md).

### Milestone 20 — fixed product pricing (v0.19.0)

M20 adds optional merchant-authored fixed regular/sale prices per **non-base**
foreign currency on simple products and variations. FX conversion remains the
fallback when no applicable fixed price exists.

| Topic | M20 posture |
|---|---|
| WooCommerce native base prices | Supported — sole authority for store base currency |
| Fixed product/variation prices | Supported — M20 core scope |
| Post-UMC third-party price modifiers | Governed by M19 extension boundary; no generic dynamic-pricing claim |
| M19 extension evidence tiers | Unchanged — M20 does not promote any extension |

Authoritative spec: [`docs/architecture/authoritative-fixed-product-pricing.md`](architecture/authoritative-fixed-product-pricing.md), ADR-0025.

### Milestone 25 — fixed pricing CSV interchange (v0.24.0)

M25 extends WooCommerce's native product CSV export/import with structured
per-currency fixed-price columns via six WooCommerce extension hooks:
`woocommerce_product_export_column_names`,
`woocommerce_product_export_product_default_columns`,
`woocommerce_product_export_row_data`,
`woocommerce_csv_product_import_mapping_options`,
`woocommerce_csv_product_import_mapping_default_columns`,
`woocommerce_product_import_pre_insert_product_object`, and
`woocommerce_product_import_inserted_product_object`. Confirmed present with
identical signatures at both this plugin's declared WooCommerce floor
(sourced from the WC 8.2.3 tag — one patch below the pinned 8.2.5 floor
coordinate) and the pinned `current` leg (10.9.4).

| Topic | M25 posture |
|---|---|
| WooCommerce native product CSV export/import | Supported — the sole interchange mechanism; no second UMC CSV format or admin page |
| Six extension hooks above | Supported at floor and current; confirmed present, unchanged in signature, at both coordinates |
| WooCommerce's generic `meta:`-prefixed custom-meta import mechanism | Defended against, not relied upon — see § Raw-meta resync-to-database-truth defense below |
| Third-party CSV/import tools (WP All Import, Product CSV Import Suite, or similar) | Not evaluated — no compatibility claim |

**Raw-meta resync-to-database-truth defense**: WooCommerce's own generic
`meta:`-prefixed CSV import column mechanism writes directly to arbitrary
post meta, including underscore-prefixed keys, with no `is_protected_meta()`
check anywhere in the product-import write path — confirmed by direct source
read and by a real, passing integration test
(`tests/integration/Csv/WooCommerceCsvImporterNativeBehaviorTest.php`) that a
hand-authored `meta:_umc_fixed_prices` CSV column can otherwise write
arbitrary, unvalidated content to that key, and that WooCommerce's own
mapping auto-selector pre-selects this route by default. M25 registers an
unconditional guard on `woocommerce_product_import_pre_insert_product_object`
that resyncs the importing object's in-memory fixed-price meta entry to an
independently, freshly read database value — never an unconditional delete,
since WooCommerce's generic importer mutates an *existing* meta entry's value
in place rather than adding a duplicate, and a blind delete would destroy a
legitimate existing document exactly as destructively as the attack it
defends against. See ADR-0030 § Raw-meta resync-to-database-truth defense and
[`docs/architecture/fixed-pricing-csv-interchange.md`](architecture/fixed-pricing-csv-interchange.md)
§5 for the full algorithm and evidence.

No `Settings::SCHEMA_VERSION`, `OrderSnapshot::SCHEMA_VERSION`, or
`PersistedKeys::INVENTORY_VERSION` change. No new persisted option,
transient, or meta key.

Authoritative spec: [`docs/architecture/fixed-pricing-csv-interchange.md`](architecture/fixed-pricing-csv-interchange.md), ADR-0030.

## The floor's Store API order-route exclusion

At the WooCommerce floor (8.2.x), WooCommerce's own Store API `RoutesController`
registers the `Order` and `CheckoutOrder` routes only when an internal
experimental-build flag is set — true only for the standalone WooCommerce
Blocks feature-plugin build, never for a standard WordPress.org WooCommerce
install. On that version, `/wc/store/v1/order/{id}` and
`/wc/store/v1/checkout/{id}` are simply not present in the REST route table.
**This document does not claim the exact WooCommerce version at which those
routes became unconditional** — that boundary was observed at two points
(absent at 8.2.5, present unconditionally at 10.9.4) and was never bisected in
between.

The 8 tests in `OrderRouteCurrencyTest` that dispatch a real request through
those routes are tagged `@group wc-order-route-unavailable` and excluded only
on the `floor` CI leg (`composer test:integration -- --exclude-group
wc-order-route-unavailable`). Three properties of this exclusion are
deliberate:

- **Capability-based, not version-based.** The tests are gated by a live probe
  of the registered REST route table (`rest_get_server()->get_routes()`),
  never by `version_compare( WC_VERSION, … )`. A structural guard
  (`tests/unit/CiMatrixGuardTest.php`) asserts no such version comparison
  exists anywhere under `tests/`.
- **Not a production workaround.** No code in `src/` changed to accommodate
  this gap. The plugin's own Store API order-currency lock
  (`src/StoreApi/OrderCurrencyLock.php`) hooks generic REST dispatch filters
  that simply never fire for a route WordPress never matched — it is
  correctly inert on WooCommerce 8.2, not broken.
- **Exactly bounded.** A structural guard
  (`tests/unit/CiMatrixGuardTest.php` and
  `tests/unit/OrderRouteGroupGuardTest.php`) asserts the exclusion covers
  exactly these 8 tests, confined to this one file, and that no other
  exclusion group is used anywhere in the CI configuration. **No `wc-shape`
  exclusion currently exists** — that group name is reserved for a genuine
  Store API response-*shape* incompatibility, which this is not, and none has
  been recorded.

## Third-party plugins

Universal Multicurrency is standalone (see ADR-0003). No third-party
currency-switcher plugin is *Supported* — the ceiling for any such plugin is
*Works with* at best. The built-in detectors below are labelled *Incompatible*
because two independent runtime price converters cannot coexist safely.

### Known incompatible

<!-- umc:incompatible:start -->
| Plugin | Slug | Category | Failure mode | Detected as | Resolution |
|---|---|---|---|---|---|
| FOX - Currency Switcher Professional for WooCommerce | `woocs` | Runtime currency conversion | Both plugins convert the same WooCommerce price getters; hook order determines which rate wins, so catalogue, cart, and saved order totals can disagree | `DetectorManifest` id `woocs` | Deactivate the conflicting switcher before relying on Universal Multicurrency |
| CURCY - Multi Currency for WooCommerce | `curcy` | Runtime currency conversion | Same double-conversion failure mode as other runtime switchers | `DetectorManifest` id `curcy` | Deactivate the conflicting switcher before relying on Universal Multicurrency |
| WPML Multilingual & Multicurrency for WooCommerce | `wcml` | Runtime currency conversion | WPML's multicurrency layer converts prices independently of Universal Multicurrency | `DetectorManifest` id `wcml` | Deactivate WPML multicurrency or Universal Multicurrency — only one converter may be authoritative |
| YayCurrency | `yaycurrency` | Runtime currency conversion | Same double-conversion failure mode as other runtime switchers | `DetectorManifest` id `yaycurrency` | Deactivate the conflicting switcher before relying on Universal Multicurrency |
<!-- umc:incompatible:end -->

Every slug in the table above must exist as a built-in detector id in
`DetectorManifest::manifest()`, and vice versa. `CompatibilityMatrixTest`
asserts that bi-directional agreement on every pull request.

### Reported, unverified

No third-party reports have been reproduced and admitted under the detector
governance checklist yet.

## Migrating from another currency switcher

Universal Multicurrency does **not** import, read, or migrate configuration from
any other currency switcher. That is a deliberate architectural constraint
(ADR-0003, ADR-0007), not a missing feature.

| Supported | Unsupported |
|---|---|
| Manual cut-over using [`MIGRATION.md`](MIGRATION.md) | Automatic import from foreign plugin options or databases |
| Deactivate old switcher, configure UMC manually | Running two runtime converters together |
| Passive HIGH/MEDIUM conflict detection | UMC reading foreign sessions, cookies, or rates |
| Historical WooCommerce orders/refunds unchanged | Mapping FOX/WOOCS/WPML export formats in core |
| `_umc_*` order meta preserved on uninstall (ADR-0009) | Admin CSV import in the Release Candidate |

The full checklist, deployment sequence, rollback notes, FAQ, and the optional
future **UMC-native CSV format specification** (spec only — no parser in RC) live
in [`MIGRATION.md`](MIGRATION.md).

## Detection

Diagnostics observes the host environment using passive evidence only:
active plugin paths, declared classes (`class_exists( $name, false )`),
defined constants (`defined()`, never `constant()`), registered functions,
shortcodes, and hooks (`isset( $wp_filter[ $tag ] )`, never
`apply_filters()`). It never reads another plugin's options, cookies,
sessions, rates, or selected currency. See ADR-0007.

Built-in detectors live in `DetectorManifest.php` — the only file permitted
to name a third-party product. Sites may add runtime rows through the
`umc_conflict_detectors` filter; those rows pass through the same sanitiser
as the built-ins and are never merged into the manifest automatically.

### Detector lifecycle governance

`DetectorManifest` is a governance surface: it is where the project asserts,
in public and in shipped code, that another vendor's plugin conflicts with
this one. Those assertions need an owner and a process.

#### Ownership

| Asset | Owner | Change vehicle |
|---|---|---|
| Built-in detector rows | The plugin maintainer, exclusively | A PR that satisfies the admission checklist below |
| Weight schedule (`SignatureKind::DEFAULT_WEIGHTS`) and thresholds (`Confidence::THRESHOLD_*`) | The plugin maintainer | A PR that also updates the scoring sanity table in ADR-0007 if the reasoning changes |
| Third-party detector rows | The site or plugin adding them, at runtime | `umc_conflict_detectors`. Never merged into the manifest |
| The `Incompatible` label for a plugin | The plugin maintainer | `docs/COMPATIBILITY.md § Known incompatible`, atomically with the detector row — the drift test asserts bi-directional agreement |

The filter exists so a site can describe its own environment without waiting
for a release; the manifest exists so the project can make a claim it is
prepared to defend. Merging a filter-supplied detector into the manifest is a
maintainer decision requiring the full checklist, never a courtesy.

#### When a detector may be added

All five must hold. A report alone is never sufficient.

1. **A reproduction exists** — a named failure mode observed with both plugins
   active, recorded in § Known incompatible with the versions of both plugins
   and the date.
2. **The conflict is structural, not configurable** — the two plugins cannot
   coexist correctly under any settings. A plugin that merely can be
   misconfigured into a conflict belongs in § Reported, unverified, not in the
   manifest.
3. **Every needle is verified against that plugin's actual distributed source**,
   at a recorded version — not inferred from documentation or another
   detector's naming pattern.
4. **MQ1 and MQ2 both hold** — at least one `plugin_path` signature, and ≥
   MEDIUM reachable from symbol evidence alone.
5. **MQ3 holds against the whole manifest** — no needle collides with an
   existing detector.

#### When a detector may be removed or demoted

| Trigger | Action |
|---|---|
| Upstream ships a verified fix | Demote to *Works with* in the doc. Keep the detector row, annotated with the fix version; narrow to the affected version range if signatures permit |
| Target plugin withdrawn or abandoned | Remove the row and doc entry in the same commit; record in release notes |
| A needle produces false positives | Remove that needle, not the detector — then re-check MQ1/MQ2. If they no longer hold, remove the detector entirely |
| A needle no longer matches any shipped version | Remove it as dead weight at the next touch |

A detector is never removed merely because it has stopped firing in the wild.

#### How weights and thresholds are reviewed

- **Per-kind default weights** change only if the general evidential argument in
  ADR-0007 changes. Guard G6 makes plugin-specific tuning impossible.
- **Per-signature overrides** (clamped 1..60) are the correct instrument for one
  unusually distinctive or weak needle. Overrides must carry an inline comment and
  must not move a detector across a confidence threshold on their own.
- **Thresholds** (60/30/10) are frozen by ADR-0007. Changing them requires an ADR
  amendment and a re-run of the scoring sanity table.

#### How false positives are handled

A false positive is a priority defect — more urgent than a false negative.

1. Reproduce and record the exact evidence set (`Finding::matched()`).
2. Add a **failing unit test** against `ConflictScorer` before changing anything.
3. Fix by **removing or narrowing the offending needle** — never by raising a
   threshold.
4. Re-verify MQ1–MQ3 for the affected detector.
5. If the fix lowers confidence for a real conflict, say so in the release notes.

#### Required with every detector change

| Artefact | Required when |
|---|---|
| Unit tests — new or corrected evidence combination plus negative control | Always |
| Integration test — new probe path not already covered by fixture tests | New evidence *kind* only |
| Runtime evidence — verified source reference in the commit message | Always |
| `docs/COMPATIBILITY.md` row — added, demoted, or removed in the same commit | Always |
| Release-note entry | Whenever merchant-visible behaviour changes |

### Notice dismissal

Dashboard conflict notices may be dismissed per user via a nonce'd GET link on
`admin_init` (`?umc-dismiss=<fingerprint>`). Dismissals persist in user meta
`umc_dismissed_notices` (max 20 entries, 180-day expiry). The fingerprint
includes the plugin major.minor version.

HIGH conflicts on `plugins.php` and the Multicurrency settings tab (`tab=umc`)
are never dismissible. MEDIUM and LOW follow screen scoping in
`ConflictNotice::should_show_on_screen()`. Shop managers without
`activate_plugins` see the settings-tab warning without deactivation
instructions. Dismissal does not affect Site Health — tests recompute from live
state. User meta is not removed on uninstall (documented trade-off).

### Site Health

When the viewer has `activate_plugins`, Universal Multicurrency registers two
direct Site Health tests and a debug section:

| Test id | Label | Mapping |
|---|---|---|
| `umc_conflicts` | Currency switcher conflicts | No findings → `good`; LOW → `good` with hedged copy; MEDIUM → `recommended`; HIGH → `critical` |
| `umc_environment` | Universal Multicurrency environment | Below declared floor on any axis → `critical`; above tested or unparseable → `recommended`; HPOS disabled → `critical`; otherwise `good` |

The debug section (`universal-multicurrency`) lists detected findings and
environment axis classifications. It never emits the full manifest or unmatched
needles. Both callbacks return empty when the viewer lacks `activate_plugins`.

See ADR-0008 for version classification rules.

## Environment requirements

High-Performance Order Storage is enabled identically on every CI leg (see
`tests/integration/bootstrap.php`) and is therefore Supported at every
coordinate in the matrix above. Multisite, WP-CLI, and headless (REST-only,
no-theme) usage are not exercised by any CI leg in this milestone — every
integration run reports "Running as single site" — and carry no compatibility
claim in either direction.

## Visitor Location (v0.11.0; boundary aligned in M14/v0.13.0)

Visitor Location (Currency Routing + Currency Simulation) reuses the existing
`umc_currency` session/cookie persistence. Full-page caching may affect
first-visit currency display when automatic detection is enabled — verify
with the Compatibility diagnostics tab.

**Universal Geo Context integration:** optional, feature-detected. This
plugin consumes only Universal Geo Context's public API — the six global
functions in its `src/api.php` — gated by `function_exists()` and
`universal_geo_api_version() >= 1`. There is no minimum Universal Geo
Context *version*: any release exposing public API v1 is compatible. When
Universal Geo Context is absent, outdated, or reports an incompatible API
version, this plugin falls back to WooCommerce checkout country /
geolocation and remains fully functional — see ADR-0018. Provider
configuration, trusted proxies, and detection diagnostics are managed
entirely in Universal Geo Context; this plugin's Visitor Location hub
(Overview, Currency Routing, Currency Simulation) never duplicates them.

Another multicurrency plugin performing geo-based switching is reported
through the existing passive conflict detection architecture when
detectable.

## External cache-state readiness (v1.1.0)

The Compatibility → Cache category gains one deterministic result reporting
whether this installation's cache-critical configuration (base currency, the
enabled-∧-rated currency set, geo-routing enablement) matches what an
external full-page cache was last told to reconcile against:

| State | id | severity |
|---|---|---|
| Never acknowledged | `cache.state_not_enrolled` | `INFO` |
| Acknowledged and matching | `cache.state_reconciled` | `INFO` |
| Acknowledged but the configuration has changed since | `cache.state_reconciliation_required` | `WARNING` |

This is entirely separate from the existing object/page-cache detection
above (`cache.object_dropin`, `cache.plugin.*`, `cache.none_detected`,
`cache.edge_note`) — it does not detect or classify a cache technology, it
reports the plugin's own opt-in reconciliation contract for external
infrastructure that keys on this plugin's semantic state. See
[ADR-0032](adr/0032-external-cache-state-readiness.md),
[`docs/architecture/external-cache-state-readiness.md`](architecture/external-cache-state-readiness.md),
and [`docs/CLI.md`](CLI.md) for the full contract, the `wp umc cache-state`
commands, and the acknowledgement transaction. The plugin never controls,
purges, reloads, or holds credentials for any external cache.

## Changing this document

This document is the single authoritative source for every version claim and
every built-in incompatible-detector slug the project makes.
`tests/unit/CompatibilityMatrixTest.php` parses the machine-readable blocks
above and fails if the plugin header, `composer.json`, `phpcs.xml.dist`,
`.github/workflows/ci.yml`, `CLAUDE.md`, or `DetectorManifest::manifest()`
disagree with it. Adding or removing a built-in detector requires updating §
Known incompatible in the same commit.
