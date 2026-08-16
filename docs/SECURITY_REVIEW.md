# Security review

Audit of Universal Multicurrency, established at Milestone 7 Commit 6 and
re-audited for the Milestone 8 automatic-rate surfaces (v0.8.0). This document
summarizes verified posture; **executable guards enforce the invariants** listed
here. See [`TEST_STRATEGY.md`](TEST_STRATEGY.md) § Milestone 7 security.

**Release gate:** zero unresolved **Critical** and **High** findings.

---

## Audited surfaces

| Surface | Primary files | Verification |
|---|---|---|
| Plugin bootstrap | `universal-multicurrency.php`, `Plugin.php` | PHPCS, guards, integration smoke |
| Activation / uninstall | `uninstall.php` | `UninstallPolicyTest`, `SecuritySourceGuardTest` |
| Admin settings save | `Admin/SettingsPage.php`, `Settings.php` | WooCommerce nonce + `manage_woocommerce`; `SettingsSanitizeTest` |
| Notice dismissal | `Diagnostics/NoticeDismissal.php` | Nonce + capability integration tests |
| Diagnostics / Site Health | `Diagnostics/*` | Capability gates; `DiagnosticsGuardTest` |
| Order admin meta box | `Admin/OrderCurrencyMetaBox.php` | Escaped output; WooCommerce order caps |
| Store API extension | `StoreApi/CartExtensionData.php` | Read-only data; no rate leakage; schema readonly |
| Cart / checkout hooks | `Integration/*`, `Cart/*` | Integration suites; storefront guards |
| Currency switching | `CurrencySwitcher.php`, `CurrencyContext.php` | Allow-list + `wp_safe_redirect`; behavioural tests |
| Cookies / session | `CurrencyContext.php` | ISO code normalization at boundary |
| Redirects | `CurrencySwitcher.php`, `NoticeDismissal.php` | Safe redirect guards |
| HPOS order/refund meta | `Order/*` | CRUD-only guards; no `$wpdb` |
| Passive detection | `Diagnostics/*` | ADR-0007; no foreign option reads |
| Build / release zip | `bin/build-zip.sh` | Source guard; dev-deps rejection |
| Manual rate update | `Admin/RateUpdateController.php` | `manage_woocommerce` + `check_admin_referer`; `RateUpdateControllerIntegrationTest` |
| Outbound provider HTTP | `Rates/Http/WordPressHttpTransport.php`, `Rates/Providers/FrankfurterRateSource.php` | `wp_safe_remote_get()` only, confined by `RatesPersistenceGuardTest` |
| Provider response handling | `Rates/Providers/FrankfurterRateSource.php` | Strict parsing + normalization; `FrankfurterRateSourceTest` |
| Rate persistence boundary | `Rates/ExchangeRateStore.php`, `Rates/RateUpdateState.php` | Single-writer guard; `ExchangeRateStoreTest`, `RatesPersistenceGuardTest` |
| Scheduled updates | `Rates/Scheduler.php` | Action Scheduler only; `SchedulerIntegrationTest` |
| Rate diagnostics | `Diagnostics/SiteHealthReport.php`, `Admin/RateFailureNotice.php` | Counter-only output; `SiteHealthRateIntegrationTest` |
| Geo Detection / Visitor Location admin | `Admin/Geo/*`, `Admin/GeoDetectionSettingsField.php` | `current_user_can( 'manage_options' )` gates on every panel render; `SecuritySourceGuardTest` whole-tree checks |
| Currency Decision Inspector (M15) | `Admin/DecisionInspector*` (under `SettingsPage.php`'s `decision_inspector` section) | Stateless, side-effect-free simulation; same admin-settings nonce/capability boundary as the rest of `SettingsPage.php` |
| Switcher Advanced Custom CSS (M17) | `Display/SwitcherCustomCss.php` | Pure, WordPress-free `sanitize()` seam invoked from `Settings::sanitize()`; storefront-only injection, gated `edit_css` admin field |
| WooCommerce transaction integrity (M18) | `Cart/*`, `Checkout/*`, `Integration/*` | `StorefrontGuardTest` structural invariants (no fee/stock/refund/order-status/Store-API callbacks outside the sanctioned seam) |
| Extension compatibility framework (M19) | `Compatibility/*` | Invariant I1 — only `src/Diagnostics/` may know a third-party plugin exists, only `DetectorManifest.php` may name one; `DiagnosticsBoundaryGuardTest` |
| Authoritative fixed pricing (M20) | `Pricing/FixedPriceRepository.php`, `Pricing/ProductPriceResolutionService.php` | Product-meta-only persistence via `update_post_meta`; `manage_woocommerce` product-editor capability boundary (WooCommerce-owned) |
| Multicurrency reporting (M21) | `Reporting/*` | Read-only over historical order facts; `ReportingArchitectureGuardTest`, `ReportingPerformanceGuardTest`; CSV formula-injection defense in `ReportingCsvRenderer::escape_csv_cell()` |
| Switcher presentation icons (M22) | `Display/CurrencyPresentationAssetRegistry.php` | Bundled SVGs only, registry-resolved (no arbitrary file/URL rendering); `CurrencyPresentationAssetRegistryTest` |
| Native switcher block (M23) | `blocks/`, block registration in `Display/*` | `M23ArchitectureGuardTest` — bounded presence detection, no duplicate registration |
| Fixed-pricing catalog operations (M24) | `Pricing/FixedPriceCatalogOperationsService.php`, `CLI/PricesCommand.php` | `manage_woocommerce` admin-screen capability + CSV/CLI authorization precedent; `FixedPricingCatalogOperationsGuardTest` |
| Fixed-pricing CSV interchange (M25) | `Pricing/FixedPriceCsvIntegration.php` | Rides WooCommerce's own authorized Products → Export/Import boundary (no separate auth surface); raw-meta resync-to-database-truth defense against WooCommerce's generic custom-meta import mechanism; `FixedPriceCsvIntegrationGuardTest` |

No custom REST routes, AJAX handlers, or runtime filesystem writes exist in `src/`.
`SecuritySourceGuardTest`'s checks (no direct SQL, no dangerous functions, no
debug output, request-superglobal confinement, no wildcard meta deletion, no
AJAX/REST registration) run over the **whole current `src/` tree** on every
CI run, not per-milestone — so every surface added since M8 (including all
rows above) is continuously covered by those invariants even where no
milestone-specific guard test exists.

---

## Milestone 8 — automatic rate providers

### Authorization on the manual update path

`admin_post_umc_update_rates` is the only merchant-triggered write in this
milestone. `RateUpdateController::handle()` enforces, in order:

1. `current_user_can( 'manage_woocommerce' )`, otherwise `wp_die()` — the check
   runs **before** nonce verification and before any provider call, so an
   unauthorized request never reaches the network or the store;
2. `check_admin_referer( 'umc_update_rates' )`;
3. `sanitize_text_field( wp_unslash( … ) )` on `scope` and `code`, with `code`
   upper-cased; an unknown code simply resolves to no automatic target.

The response is a `wp_safe_redirect()` back to
`admin.php?page=wc-settings&tab=umc` with a `rawurlencode`d flash message. Both
entry points that render the link (`ExchangeRateSettingsField`,
`CurrencyTableField`) nonce it with `wp_nonce_url( …, 'umc_update_rates' )`.

Verified behaviourally: an unauthorized request performs zero provider calls,
zero `umc_settings` writes, and leaves operational state at `never`; a request
without a valid nonce is rejected the same way
(`tests/integration/Rates/RateUpdateControllerIntegrationTest.php`).

### Outbound HTTP

| Control | Status |
|---|---|
| Request function | `wp_safe_remote_get()` only — SSRF protections and the `http_request_host_is_external` policy apply |
| Confinement | `Rates/Http/WordPressHttpTransport.php` is the only production class that performs an outbound rate request |
| Endpoint | Hard-coded `https://api.frankfurter.dev/v1/latest`; not merchant-configurable, so no stored URL can be poisoned |
| Query construction | `rawurlencode()` on the base code and on the comma-joined, upper-cased, de-duplicated target list |
| Timeout | 15 s, floored at 1 s |
| Credentials | None — Frankfurter is unauthenticated. No API key field exists, so no secret is collected, persisted, logged, or rendered |
| Transport errors | Collapsed to `HttpResponse( 0, [], '', true )`; the `WP_Error` message is never surfaced or stored |

### Provider response handling

Nothing from the response body reaches persistence unvalidated:

- Non-2xx (other than 304) and non-JSON or `rates`-less bodies produce a total
  failure with the fixed code `provider_unavailable` or `invalid_response`.
- Each quote passes through `Settings::normalize_rate()`, which admits only a
  finite positive decimal string; anything else is dropped as a per-currency
  failure rather than persisted.
- Currencies the merchant did not request are ignored — the store writes only
  codes it asked for.
- `ETag` and `Last-Modified` are capped at 200 characters before storage
  (`HEADER_MAX_LENGTH`), bounding what an upstream can push into an option.
- A total failure or an HTTP 304 never overwrites a known-good rate; only
  operational counters move.

### Lock behaviour

`RateUpdateState` holds a TTL-bounded lock (`LOCK_TTL_SECONDS = 120`) in
`umc_rate_state`. `RateUpdateService::update()` acquires it, and releases it in a
`finally` block, so a fatal fetch cannot strand it; a concurrent caller receives
`UpdateInProgressException`, which the controller renders as a "try again
shortly" notice. The lock is a concurrency guard, **not** an authorization
control — capability and nonce checks stand on their own ahead of it.

### Diagnostic redaction

`umc_rate_health` and the two debug fields report **derived counts and states**
only: how many automatic currencies are stale, the oldest age in whole hours,
and whether a failure or a missing schedule exists. They never emit a rate
value, a merchant adjustment, the provider URL, an `ETag`, a `Last-Modified`
value, or a stored error code. Persisted `last_error` values are a closed
vocabulary of internal codes (`provider_unavailable`, `invalid_response`,
`not_returned_by_provider`) — no upstream text is stored in the first place.
`RateFailureNotice` prints currency codes only, escaped with `esc_html()`, and
returns early without `manage_woocommerce`. Site Health surfaces are gated on
`activate_plugins`.

Asserted in `tests/integration/Diagnostics/SiteHealthRateIntegrationTest.php`,
which fails if a provider rate, an error token, the provider host, or an `etag`
string appears anywhere in the encoded test result or debug fields.

### Secret persistence

None. The shipped provider requires no credential, `umc_settings` has no key or
token field, and `umc_rate_state` stores only timestamps, statuses, internal
error codes, bounded cache validators, and the lock row. Confirmed against the
inventory in [`PERSISTED_DATA.md`](PERSISTED_DATA.md). A future authenticated
provider would be a **new** audit trigger.

---

## Findings by severity

### Critical — none (resolved: 0 open)

No open critical findings at RC audit completion.

### High — none (resolved: 0 open)

| ID | Surface | Scenario | Resolution | Proof |
|---|---|---|---|---|
| H1 (fixed) | Cookie/session/query input | Malformed currency strings could reach resolver before allow-list | Added `CurrencyContext::normalize_currency_code()` at read boundary | `SecurityBehaviourTest`, `SecuritySourceGuardTest` |
| H2 (fixed) | Conflict notice link | `umc_conflict_notice_view_model` could supply external `settings_url` | `ConflictNotice::settings_admin_url()` rejects non-`admin.php` paths | `SecurityBehaviourTest`, source guard |

### Medium — accepted / documented

| ID | Surface | Scenario | Disposition | Proof |
|---|---|---|---|---|
| M1 | Currency switch GET | No CSRF nonce on `?currency=` | **Accepted.** Idempotent display preference only; allow-list validated; no privilege change | ADR-0003 pattern; `RequestGateTest` |
| M2 | Guest cookie | Not HttpOnly (WooCommerce `wc_setcookie`) | **Accepted.** Client-readable currency code required for guest persistence; value is non-sensitive ISO code only | `CurrencySwitcher::persist()` review |
| M3 | Filter hooks | Public filters can alter view models | **Accepted.** Site-owner trust boundary; settings URL now hardened (H2) | Documented in HOOKS.md |
| M4 | `umc_exchange_rate_sources` | Another plugin can register an `ExchangeRateSource` and become the active provider when `rate_provider` matches its `id()` | **Accepted.** Site-owner trust boundary, identical to WooCommerce's own gateway/shipping registration model. Quotes from any source still pass `Settings::normalize_rate()` and are written only through `ExchangeRateStore`. | `Plugin::resolve_rate_source()`; `RatesPersistenceGuardTest` |

### Low — accepted

| ID | Surface | Disposition |
|---|---|---|
| L1 | Exception messages in pure domain classes | Developer diagnostics only; never echoed (PHPCS exclusion) |
| L2 | Evidence needles in admin UI | Matched identifiers only; never foreign option values (ADR-0007) |

### Informational

| ID | Note |
|---|---|
| I1 | `composer audit` run in CI validation — no known advisories on production deps (`php >=8.1` only) |
| I2 | Release ZIP copies `src/`, `vendor/`, `languages/`, `readme.txt`, plugin bootstrap — excludes `tests/`, `docs/`, plans |

---

## Authorization and nonce results

| Action | Capability | Nonce | Test coverage |
|---|---|---|---|
| WooCommerce settings save | `manage_woocommerce` (WC core) | `woocommerce-settings` (WC core) | `SettingsOptionTest` |
| Notice dismissal | `activate_plugins` / `manage_network_plugins` | `check_admin_referer( 'umc_dismiss_' . $fingerprint )` | `NoticeDismissalIntegrationTest`, `SecurityBehaviourTest` |
| Dashboard / plugins notices | `activate_plugins` | n/a (read) | `ConflictNoticeIntegrationTest` |
| Site Health / debug | `activate_plugins` | n/a (read) | `SiteHealthReportIntegrationTest`, `SiteHealthRateIntegrationTest` |
| Currency switch | none (storefront) | none (by design, M1) | `RequestGateTest`, `SecurityBehaviourTest` |
| Manual rate update | `manage_woocommerce` | `check_admin_referer( 'umc_update_rates' )` | `RateUpdateControllerIntegrationTest` |
| Rate failure notice | `manage_woocommerce` | n/a (read) | `RateFailureNotice::render()` early return |
| Scheduled rate update | n/a (Action Scheduler context) | n/a | `SchedulerIntegrationTest` |

Unauthorized dismissal attempts do not write user meta (`SecurityBehaviourTest`).
Unauthorized or unsigned rate-update attempts perform no provider call and no
option write (`RateUpdateControllerIntegrationTest`).

---

## Sanitization and validation

- Request input confined to the boundary classes allowlisted by `SecuritySourceGuardTest` (which includes `Admin/RateUpdateController.php`).
- Settings sanitized via `Settings::sanitize()` (schema v2; bounded decimals, rates, merchant adjustment clamped to ±50%, interval restricted to a fixed ISO-8601 set, max age 1–720 hours, provider restricted to the known identifier).
- Operational state sanitized via `RateUpdateState::sanitize()` (bounded timestamps, closed status vocabulary, bounded failure history, capped cache validators).
- Currency candidates normalized to `/^[A-Z]{3}$/` before resolution.
- Notice dismissal fingerprints validated with `/^[a-f0-9]{16}$/` and `hash_equals()`.
- Order-pay order IDs use `absint()`; order keys sanitized with `sanitize_text_field()`.

---

## Output escaping

- Admin tables, notices, Site Health, and order meta box use `esc_html`, `esc_attr`, `esc_url` at output boundaries.
- Translated placeholders use `printf` + `esc_html__` with substituted values escaped (`ConflictNotice`, `GatewayCompatibility`).
- PHPCS `WordPress.WP.I18n` and `WordPress.Security.EscapeOutput` clean on production paths.

---

## SQL / HPOS

- No `$wpdb` in `src/` (`StorefrontGuardTest`, `SecuritySourceGuardTest`).
- Order/refund data via `WC_Order` CRUD only (`StorefrontGuardTest` M4 guards).
- No wildcard metadata deletion; uninstall deletes configuration options
  (`umc_settings`, `umc_rate_state`) only (ADR-0009, extended by ADR-0012).

---

## Settings upgrade boundary

`SettingsUpgrader::upgrade()` wraps migration work in `catch ( \Throwable )` so
corrupt or unexpected stored options fail closed: callers receive defaults,
`should_persist()` stays false, and partial migrations are never written. This is
intentional at the persistence boundary — not a blanket exception-swallowing
pattern. `StorefrontGuardTest` allowlists **only** `SettingsUpgrader.php` for
broad `catch ( Throwable | Exception )` probes elsewhere in `src/`.

---

## REST / Store API / hooks

- Store API extension is read-only (`CartExtensionData`); no update callback.
- Conversion gate anchored to parsed REST route, not substring URI (`RequestGateTest`).
- No `register_rest_route` or `wp_ajax_*` in `src/`.
- Diagnostics never hooks storefront money path (`DiagnosticsGuardTest` G1–G7).

---

## Cookie and session security

| Control | Status |
|---|---|
| Allow-listed selectable currencies | Yes — resolver + switcher |
| Malformed cookie/session/query rejected | Yes — `normalize_currency_code()` |
| Sensitive data in cookie | No — ISO code only |
| REST switch side effects | Blocked — `CurrencySwitcher` early return |
| Signature treated as crypto auth | No — cart signature is cache identity only |

---

## Dependency and build audit

| Item | Result |
|---|---|
| Production Composer deps | `php >=8.1` only |
| Dev deps in release zip | Blocked by `build-zip.sh` phpunit check |
| Tests/docs in zip script | Not copied (guard) |
| GitHub Actions | Standard checkout/setup-php; no secret echo |

---

## Executable guards added (Commit 6)

| Guard | Scope |
|---|---|
| `tests/unit/SecuritySourceGuardTest.php` | SQL, redirects, dangerous functions, debug output, request boundaries, options, filesystem, AJAX/REST, uninstall, build script, hardening markers |
| `tests/integration/SecurityBehaviourTest.php` | Poisoned cookie/session, malformed query, wrong dismissal fingerprint, external settings URL rejection |

### Executable guards added for Milestone 8

| Guard | Scope |
|---|---|
| `tests/unit/Rates/RatesPersistenceGuardTest.php` | Only `RateUpdateState` writes `umc_rate_state`; only `RateUpdateState`/`ExchangeRateStore` read options inside `src/Rates/`; **all** outbound HTTP is confined to `WordPressHttpTransport`, which may use only `wp_safe_remote_get()`; the scheduler never names a provider |
| `tests/integration/Rates/RateUpdateControllerIntegrationTest.php` | Capability and nonce enforcement on `admin_post_umc_update_rates`; no provider call and no option write on rejection |
| `tests/integration/Diagnostics/SiteHealthRateIntegrationTest.php` | Rate values, error tokens, provider host, and cache validators absent from every diagnostic surface |

Existing guards retained: `StorefrontGuardTest`, `DiagnosticsGuardTest`, `UninstallPolicyTest`, PHPCS.

---

## Residual accepted risks

See Medium/Low tables above. None expose authorization boundaries, order integrity, merchant funds, or sensitive configuration without site-owner action.

---

## Confirmation

**Zero unresolved Critical or High findings** at Commit 6 completion.

| Severity | Open |
|---|---|
| Critical | 0 |
| High | 0 |
| Medium (accepted) | 4 |
| Low (accepted) | 2 |

The Milestone 8 re-audit opened **no** Critical or High finding. It added one
accepted Medium (M4, the provider-registration filter) and the surface
documentation above.

---

## Document control

| Item | Value |
|---|---|
| Milestone | 7 Release Candidate — Commit 6; re-audited for Milestone 8 (v0.8.0); narrative re-audited through Milestone 25 (v0.24.0) for Milestone 26 (v1.0.0) — audited-surfaces table extended, no new finding, no change to the Milestone 8 findings below |
| Related ADRs | 0003, 0007, 0009, 0010–0013, 0019, 0022, 0024, 0026, 0030, 0031 |
| Next audit trigger | Any new admin mutation, request handler, persistence surface, or an authenticated rate provider |
