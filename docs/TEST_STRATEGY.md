# Test Strategy

Validate: - Conversion - Rounding - Products - Variations - Cart -
Checkout - Coupons - Taxes - Shipping - Orders - Refunds - Stock -
HPOS - Blocks

Three layers, all runnable via Docker (see `CLAUDE.local.md`):

- **Unit** (`tests/unit`, no WordPress) — pure domain + seam logic.
- **Integration** (`tests/integration`, WordPress + WooCommerce, **HPOS enabled**
  at bootstrap) — real cart/coupon/shipping/tax/order behaviour through the
  plugin's hooks.
- **Structural guards** (`tests/integration/StorefrontGuardTest.php`) —
  architecture invariants asserted against the source.

## Milestone 3 invariants under test

### Cart

Simple / sale / variable products, multiple quantities, base-currency no-op, and
the **no-double-conversion** invariant (a line total is `converted × qty`, never
`converted × qty × rate`). Recalculation fires when the rate identity changes.

### Coupons

Fixed cart / fixed product amounts converted once; percentage coupons operate on
already-converted totals (amount never converted); min/max spend thresholds
converted, asserted on both sides of the boundary.

### Shipping

Core `flat_rate` cost + taxes scaled by the rate; **non-core rates pass through
unchanged**; the shipping-package cache is isolated per currency+rate (signature
present under a non-base currency, absent under base).

### Taxes & rounding

Taxes computed natively (never converted); sum-of-parts reconciliation
`total = subtotal − discount + shipping + fees + tax` holds in the active
currency; JPY-class zero-decimal covered by M2.

### Gateways

Incompatible gateways hidden; gateways untouched by default.

### Orders (HPOS)

Snapshot written with the full `_umc_*` audit meta; write-once; a later store-rate
change does not alter a placed order; snapshot round-trips under HPOS.

### Structural guards

No plugin callbacks on fee/stock/refund/order-status/Store-API hooks; only the
seam uses `Converter`; no `$wpdb`/SQL in `src/`; no broad exception swallowing;
idempotent boot.

## Milestone 4 invariants under test

Exercised end to end through the real registered hooks under HPOS
(`HistoricalOrderDisplayTest`, `OrderPayCurrencyLockTest`, `RefundConversionTest`,
`LegacyOrderTest`), not just unit-level parsers.

### Historical order display

An EUR order rendered through the actual display brackets in a SEK session reports
EUR identity, symbol and 2 decimals, then reverts to SEK after the after-table
bracket; a 0-decimal JPY order reports 0 decimals in a 2-decimal session and
renders without a decimal separator; a currency with no live configuration falls
back to the ISO decimals. Stored totals are identical to creation, and a rate edit
or currency removal after order creation changes neither. Nested renders are LIFO;
repeated renders leave `depth() == 0` with no leaked context.

### Order-pay currency lock

Both endpoint forms resolve — standard `?order-pay=<id>` and legacy
`?pay_for_order=<id>`; missing/zero/malformed ids and the boolean `pay_for_order`
flag bail safely; the order key is validated and an order owned by another
customer is rejected; a paid order is not locked. Under lock the gateway filter
receives the **explicit** order currency: compatible gateways stay, incompatible
are removed, and a gateway supported by the order currency survives even though the
session filter would have removed it (the order-pay filter evaluates the original
gateway set). A disabled historical currency stays payable when a gateway supports
it; no compatible gateway yields an empty set plus a blocking notice. Totals and
order currency are never rewritten.

### Refunds

Full, partial and line-level refunds inherit the parent currency and store the
entered amount verbatim (no conversion); `_umc_parent_transaction_currency` and
`_umc_parent_rate_identity` are recorded and stable across reads; multiple partial
refunds reconcile without drift (0-dp JPY and 2-dp EUR); a rate edit or session
switch after creation changes nothing; a legacy parent falls back to its own order
currency for the audit currency.

### Legacy & malformed orders

Legacy (no snapshot), M3 v1, partial, malformed and future versions are each
classified correctly and remain readable and refundable in the stored currency
with unchanged totals. The order currency falls back to `$order->get_currency()`,
and the decimal fallback chain holds: stored → live config → ISO-4217 → 2.

### Structural guards

M4 services (`Order/*` + `Admin\OrderCurrencyMetaBox`) contain no `Converter` /
`PriceConversionService`, no live rate lookup (`get_rate`/`RateProvider`), no
`CurrencyContext` access, no session/cookie access, no post-meta API and no order/
refund total setters. `GatewayCompatibility` never inspects the order context. No
runtime `_umc_*` deletion; Store API hook registration stays inside
`src/StoreApi`; the historical-display enter/exit brackets are paired. Idempotent
boot; no leaks.

M5 adds five more: only the snapshot writers stage order metadata; nothing stamps
the order currency; Store API code raises no session notices; only the Store API
adapter saves an order; no frontend assets are registered. Each was verified to
fail when violated, not merely to pass today.

M6 adds Diagnostics guards (`tests/integration/DiagnosticsGuardTest.php`):
`src/Diagnostics/` stays inert to conversion and rate reading; only
`NoticeDismissal.php` reads request input or persists user meta; only
`WordPressEnvironmentProbe.php` reads `active_plugins` / `active_sitewide_plugins`;
registry probes stay in the probe; detection types never leak outside
`src/Diagnostics/` except the `Plugin.php` seam; third-party identifiers stay in
`DetectorManifest.php`; no auto-deactivation; no Store API / asset hooks; and
detection never writes `umc_settings` or mutates `active_plugins`. The admin hook
surface is exactly:

- `admin_notices`
- `network_admin_notices`
- `deactivated_plugin`
- `admin_init`
- `site_status_tests`
- `debug_information`
- `woocommerce_admin_field_umc_conflict`

Performance assertions use `$wpdb->num_queries` deltas only — never wall-clock timing.
Each guard was verified to fail when violated, not merely to pass today.

## Milestone 6 invariants under test

Exercised through unit tests under `tests/unit/Diagnostics/`, integration tests
under `tests/integration/Diagnostics/`, `DiagnosticsGuardTest`, and
`CompatibilityMatrixTest`.

### Conflict detection and scoring

Built-in detectors match install-wp.sh fixtures; third-party rows via
`umc_conflict_detectors` pass through the same sanitiser. `ConflictScorer`
property tests cover monotonicity, threshold boundaries, and deterministic
ordering. `class_exists( …, false )` is asserted at source level (G8b).
False-positive controls: weak evidence alone caps at LOW; `plugin_path` alone
reaches HIGH.

### Notice dismissal

Nonce'd GET dismiss persists fingerprint in user meta; cap (20) and expiry (180
days) enforced in `NoticeDismissal::sanitize_storage()`. Dismissal is per user;
fingerprint change re-surfaces the notice. HIGH on `plugins.php` and the
settings tab is non-dismissible. Redirect strips query args. Shop managers see
settings-tab copy without deactivation instructions.

### Site Health

Conflict test maps confidence → `good` / `recommended` / `critical`.
Environment test classifies PHP, WordPress, and WooCommerce axes via
`VersionPolicy`; HPOS disabled → critical. Debug section gated on
`activate_plugins`; emits detected findings only, never the full manifest.

### Diagnostics structural guards

`DiagnosticsGuardTest` and `DiagnosticsBoundaryGuardTest` enforce I1–I7:
Diagnostics inert on storefront and Store API; no `$wpdb`/SQL in Diagnostics;
no foreign option reads; no deactivation APIs; detection types confined to
`src/Diagnostics/`; third-party names only in `DetectorManifest.php`; exactly
seven admin hooks; no mutation of `umc_settings` or `active_plugins`.

### Version matrix drift

`CompatibilityMatrixTest` binds the plugin header, `UMC_VERSION`,
`composer.json`, `phpcs.xml.dist`, CI workflow, `CLAUDE.md`, and
`DetectorManifest` to `docs/COMPATIBILITY.md`. `CiMatrixGuardTest` forbids
`version_compare( WC_VERSION, … )` for test gating. `OrderRouteGroupGuardTest`
asserts exactly eight `@group wc-order-route-unavailable` tests.

## Version matrix

Five integration legs exercise the corners of the supported box plus axis
isolation (`floor`, `current`, `mixed-php-floor`, `mixed-wp-floor`, `ceiling`).
See `docs/COMPATIBILITY.md` and ADR-0008.

**Floor policy:** the `floor` leg excludes the eight Store API order-route tests
tagged `wc-order-route-unavailable` when a live REST route-table probe shows
`Order` / `CheckoutOrder` routes absent — never via WooCommerce version compare.
Every other leg runs the full suite.

**Capability-probe-not-version-compare:** structural guards assert no
`version_compare( WC_VERSION, … )` under `tests/` for CI gating.

**No wall-clock assertions:** performance checks use query-count deltas only.

**Infection scope:** unit suite only; `src/Diagnostics/` with presentation,
registry, manifest, and VO files excluded; thresholds MSI 85% / covered 95%
on `ConflictScorer` and `VersionPolicy` only.

## Mutation testing (Diagnostics scorer)

Infection runs over `src/Diagnostics/` with the **unit suite only** — integration
tests re-bootstrap WordPress on every mutant and are excluded. The job is scoped
to scoring logic (`ConflictScorer.php`, `VersionPolicy.php`): presentation layers,
registry hydration, manifest data, and value objects are excluded because mutating
string literals or integration-only surfaces does not test label correctness.

Deterministic execution uses a single thread (`composer test:mutation` →
`infection --threads=1`). Thresholds enforced in `infection.json5`:

- **MSI:** 85%
- **Covered Code MSI:** 95%

CI runs the mutation job on PHP 8.3 with PCOV when `src/Diagnostics/**`,
`tests/unit/Diagnostics/**`, or `infection.json5` changes (`dorny/paths-filter`).
Unrelated pull requests skip the job.

## Store API and Blocks (Milestone 5)

`tests/integration/StoreApi/` drives real `/wc/store/v1` routes through
`rest_do_request()`. The shared `StoreApiTestCase` owns the details that make
those tests mean anything, each of which had to be discovered:

- **Request identity.** `WC::is_rest_api_request()` and
  `WC::is_store_api_request()` read `$_SERVER['REQUEST_URI']` and return false
  when it is empty, while `rest_do_request()` sets nothing. Without simulating
  it, a test exercises the storefront path while appearing to test the Store API.
  The URI is set before any currency context is built, since the
  convertible-request decision is memoized.
- **Fresh routes per request.** WooCommerce instantiates each route once at
  `rest_api_init`, and the checkout route remembers the order it last handled.
  The REST server is discarded between requests, which also makes a checkout
  retry genuinely reload its draft from the session.
- **Per-request cart hydration.** Session loading is guarded by `did_action()`,
  so within one process only the first cart load fires
  `woocommerce_cart_loaded_from_session`. Switching currency re-hydrates the cart
  so recalculation is actually exercised.
- **Session and notice hygiene.** Plugin session keys, the Store API draft-order
  id and WooCommerce notices are cleared between tests. WooCommerce turns session
  error notices into Store API cart errors, so a notice left behind by one test
  fails the next one's requests.

Coverage: the conversion gate (Store API in, other REST namespaces out,
including a route parsed by WordPress rather than matched anywhere in the URI); the
session-less products route including zero-decimal and rounding-sensitive
amounts; the cart lifecycle with repeated reads pinning single conversion;
coupons by type and threshold; real shipping zones including cache isolation
across a switch; taxes derived from converted amounts; gateway availability and
notice suppression; currency switching with an existing cart, including
round-trips that would expose compounding; snapshots on block orders with the
unpaid-draft refresh window and post-payment permanence; stored orders reported
in their own currency; and a reconciliation suite running one scenario through
both flows.

## Deferred (later milestones)

Fee conversion (opt-in only). A no-reload in-place currency switch, which would
require shipping JavaScript.

## Milestone 7 — translation readiness (Release Candidate)

### Guards

- **`tests/unit/TranslationReadinessTest.php`** — canonical text domain (`universal-multicurrency`), plugin header agreement, `load_plugin_textdomain()` registration, representative POT msgids, exclusion of internal identifiers as msgids, no shipped JavaScript files, translation documentation contract, and **`composer make-pot:check`** drift protection (regenerate-and-diff via `bin/make-pot.sh`).
- **PHPCS `WordPress.WP.I18n`** — wrong or missing text domain in PHP source.
- **CI `pot` job** — runs `composer make-pot:check` on every pull request.

POT headers strip wall-clock fields (`POT-Creation-Date`, `PO-Revision-Date`, `X-Generator`) so diffs are deterministic. See [`docs/TRANSLATION.md`](TRANSLATION.md) for contributor workflow and the RTL audit (documentation only — no mandatory RTL CSS fixes in RC).

## Milestone 7 — security audit (Release Candidate)

### Guards

- **`tests/unit/SecuritySourceGuardTest.php`** — static invariants: no `$wpdb`, safe redirects only, no dangerous functions or debug output, request superglobal boundaries, confined `get_option()` / `get_user_meta()`, no runtime filesystem writes, no custom AJAX/REST, uninstall contract, release zip exclusions, currency input normalization and settings URL hardening markers.
- **`tests/integration/SecurityBehaviourTest.php`** — poisoned cookie/session/query handling, dismissal fingerprint mismatch, conflict-notice settings URL open-redirect rejection.
- Existing **`StorefrontGuardTest`**, **`DiagnosticsGuardTest`**, **`UninstallPolicyTest`**, and PHPCS enforce carry-forward boundaries.

Audit record: [`docs/SECURITY_REVIEW.md`](SECURITY_REVIEW.md). **Zero unresolved Critical/High findings** at Commit 6.

## Milestone 7 — performance baselines (Release Candidate)

Established **after** Commit 6 security stabilization so ceilings reflect final execution paths.

### Guards

- **`tests/integration/PerformanceBaselineTest.php`** (`@group performance`) — settings read/write ceilings, currency-resolution write freedom, plugin init idempotency, diagnostics query delta, storefront/cart/Store API read-only behaviour, order/refund snapshot counts, uninstall delete contract, hook registration uniqueness.
- **`tests/unit/PerformanceBaselineTest.php`** — in-memory `Settings` idempotency and upgrader persist-once semantics.
- **`tests/unit/PerformanceGuardTest.php`** — forbids transients/object-cache calls in `src/`; verifies baseline documentation and ceiling constants.
- Existing **`SettingsUpgradeIntegrationTest`**, **`DiagnosticsGuardTest`**, and **`StorefrontGuardTest`** carry forward overlapping invariants.

Baseline record: [`docs/PERFORMANCE_BASELINES.md`](PERFORMANCE_BASELINES.md).

**No wall-clock assertions:** performance checks use query-count and write-count deltas only.

## Milestone 7 — release audit (Release Candidate)

### Guards

- **`composer release-audit`** / **`bin/release-audit.sh`** — PHPCS, unit release-audit guards, security/performance/persisted-data subsets, POT drift, `composer audit`, production ZIP build + `ReleaseZipInspector`.
- **`tests/unit/ReleaseAuditTest.php`** (`@group release-audit`) — repository hygiene, foreign-coupling scan, metadata consistency, settings schema, security/performance document gates, CI job presence, ZIP inspection when `UMC_RELEASE_ZIP` is set.
- **CI `release-audit` job** — runs the canonical command on every pull request.

Audit record: [`docs/RELEASE_AUDIT.md`](RELEASE_AUDIT.md). **Zero unresolved release blockers** required before merge/tag/release approval.

## Milestone 7 — documentation synchronization (Release Candidate)

### Guards

- **`tests/unit/DocumentationSyncTest.php`** (`@group documentation`, `@group release-audit`) — required doc files, `readme.txt` header/metadata vs plugin header, manual migration and uninstall statements, ROADMAP Milestone 7 closure, forbidden premature tag/release claims, documented composer commands, relative link resolution, architecture/security cross-references, `ReleaseZipInspector` requires `readme.txt`.
- **`tests/unit/MigrationDocumentationTest.php`** — migration playbook structure and cross-links.
- **`tests/unit/ReleaseAuditTest.php`** — overlapping metadata and hygiene guards.

Merchant readme: [`readme.txt`](../readme.txt). Developer readme: [`README.md`](../README.md).

## Milestone 7 — v0.7.0 RC finalization (Commit 10)

### Guards

- **`DocumentationSyncTest`** — canonical version, changelog entries, milestone closure state, no pending-commit language, packaged ZIP metadata when `UMC_RELEASE_ZIP` is set.
- **`ReleaseAuditTest`**, **`ReleaseZipInspector`**, **`composer release-audit`** — full RB1–RB15 gate.

## Milestone 8 — automatic exchange rates (v0.8.0)

Provider HTTP is **never** live in tests: `Rates\Http\HttpTransport` is injected,
and `UMC\Tests\Support\FakeHttpTransport` returns canned `HttpResponse` objects
including the 304 path.

### Unit

- **`tests/unit/Rates/`** — provider parsing and capability probes
  (`FrankfurterRateSourceTest`), fetch-result predicates, quote and metadata
  value objects, interval validation, effective-rate derivation
  (`RateResolverTest`), merchant-adjustment policy, and store write ordering
  (`ExchangeRateStoreTest`).
- **`tests/unit/Rates/RateUpdateNotModifiedWriteCeilingTest.php`** — the
  WordPress-free half of `CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES`, counting
  option writes through `UMC\Tests\Support\OptionWriteMetrics`.
- **`tests/unit/SettingsMigrationFidelityTest.php`** — v1 → v2 conversion output
  is byte-identical.
- **`tests/unit/HooksDocumentationSyncTest.php`** — every `umc_*` hook in `src/`
  is documented, and every documented hook exists.

### Integration

- **`tests/integration/Rates/SchedulerIntegrationTest.php`** — Action Scheduler
  recurrence reconciliation, duplicate collapse, unscheduling on manual mode.
- **`tests/integration/Rates/RateUpdateControllerIntegrationTest.php`** — the
  `admin_post_umc_update_rates` round trip: success, 304, provider failure,
  unauthorized, and unsigned requests.
- **`tests/integration/Diagnostics/SiteHealthRateIntegrationTest.php`** — the
  registered `umc_rate_health` test across healthy, never-fetched, unscheduled,
  stale, escalated-stale, failed, and manual-mode states, plus redaction.
- **`tests/integration/CurrencyTableFieldRateTimestampTest.php`** — merchant-edit
  timestamp semantics.

### Guards

- **`tests/unit/Rates/RatesPersistenceGuardTest.php`** — single writer for
  `umc_rate_state`, confined option reads inside `src/Rates/`, all outbound HTTP
  inside `WordPressHttpTransport` using only `wp_safe_remote_get()`, and a
  scheduler that never names a provider.
