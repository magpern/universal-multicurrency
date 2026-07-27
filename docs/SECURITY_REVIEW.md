# Security review — Milestone 7 Release Candidate

Audit of Universal Multicurrency at Commit 6 (security). This document
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

No custom REST routes, AJAX handlers, or runtime filesystem writes exist in `src/`.

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

### Low — accepted

| ID | Surface | Disposition |
|---|---|---|
| L1 | Exception messages in pure domain classes | Developer diagnostics only; never echoed (PHPCS exclusion) |
| L2 | Evidence needles in admin UI | Matched identifiers only; never foreign option values (ADR-0007) |

### Informational

| ID | Note |
|---|---|
| I1 | `composer audit` run in CI validation — no known advisories on production deps (`php >=8.1` only) |
| I2 | Release ZIP copies `src/`, `vendor/`, `languages/`, plugin bootstrap — excludes `tests/`, `docs/`, plans |

---

## Authorization and nonce results

| Action | Capability | Nonce | Test coverage |
|---|---|---|---|
| WooCommerce settings save | `manage_woocommerce` (WC core) | `woocommerce-settings` (WC core) | `SettingsOptionTest` |
| Notice dismissal | `activate_plugins` / `manage_network_plugins` | `check_admin_referer( 'umc_dismiss_' . $fingerprint )` | `NoticeDismissalIntegrationTest`, `SecurityBehaviourTest` |
| Dashboard / plugins notices | `activate_plugins` | n/a (read) | `ConflictNoticeIntegrationTest` |
| Site Health / debug | `activate_plugins` | n/a (read) | `SiteHealthIntegrationTest` |
| Currency switch | none (storefront) | none (by design, M1) | `RequestGateTest`, `SecurityBehaviourTest` |

Unauthorized dismissal attempts do not write user meta (`SecurityBehaviourTest`).

---

## Sanitization and validation

- Request input confined to five boundary classes (`SecuritySourceGuardTest`).
- Settings sanitized via `Settings::sanitize()` (schema v1, bounded decimals/rates).
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
- No wildcard metadata deletion; uninstall deletes `umc_settings` only (ADR-0009).

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
| Medium (accepted) | 3 |
| Low (accepted) | 2 |

---

## Document control

| Item | Value |
|---|---|
| Milestone | 7 Release Candidate — Commit 6 |
| Related ADRs | 0003, 0007, 0009 |
| Next audit trigger | Any new admin mutation, request handler, or persistence surface |
