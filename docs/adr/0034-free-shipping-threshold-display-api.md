# ADR-0034 — Free Shipping Threshold Display API (v1.2.0)

## Status

Accepted (post-1.0 feature release, target **v1.2.0**).

**WP1 over-precision scope:** **LOCKED — Option A (global rejection).**
See §12.

## Relationship to ADR-0031 / ADR-0032 / ADR-0033

ADR-0031 §6 remains honored: this is **not** M27 and does not reopen the
closed M0–M26 roadmap. It is a focused feature release identified only as
**v1.2.0**, tracked under `# Post-1.0 releases` in `docs/ROADMAP.md`.

ADR-0032 (`CacheState` contract v1) and ADR-0033 (variable price-range
identity) are **unchanged**.

ADR-0023 (transaction integrity / free-shipping `min_amount` conversion)
remains the eligibility contract; this ADR adds a **shared resolver** and a
**public display facade** that must not drift from that eligibility path.

## Context

Storefront consumers (for example announcement plugins) need to display the
WooCommerce free-shipping minimum in the shopper's active UMC currency.
Without a UMC-owned API, consumers are forced to multiply rates, round, and
format independently — guaranteeing drift from checkout eligibility.

Today eligibility converts base-authored `min_amount` at evaluation time via
`ShippingConversion` → `PriceConversionService::convert_amount()` →
`Converter::apply_rate()`. There is no public PHP function surface.

## Decision — frozen public contract

### 1. Purpose

Provide a documented public PHP API that returns the free-shipping threshold
in the currently active UMC currency using the **same** threshold resolution
checkout uses. Display-only: the API does **not** answer whether the current
cart qualifies.

### 2. Public PHP facade

```php
umc_get_free_shipping_threshold_display( string $base_threshold ): ?array
```

Feature-detectable with `function_exists()` after plugin bootstrap.

### 3. Successful return shape (exact keys)

```php
array(
	'formatted_html' => string, // wc_price() HTML for amount + currency
	'amount'         => string, // canonical decimal string from shared resolver
	'currency_code'  => string, // active (or base) currency code
)
```

No other keys. Consumers must treat all three as authoritative.

### 4. Shared monetary authority

```text
ShippingConversion ──────────────┐
                                 ▼
                    FreeShippingThresholdResolver
                                 ▲
FreeShippingThresholdDisplayService
                                 │
umc_get_free_shipping_threshold_display()
```

Foreign resolution uses only `PriceConversionService::convert_amount()` →
existing `Converter::apply_rate()`. No second conversion algorithm.

### 5. Request context (convertible only)

If `! CurrencyContext::is_convertible_request()` → `null`.

Includes wp-admin, cron, non-Store REST, and WP-CLI per existing UMC
semantics. A normal storefront request with **base** currency active still
returns the three-key base-currency result.

### 6. Input

`$base_threshold` is a decimal string authored in the **WooCommerce store
base currency**.

Input precision is judged against the **base currency** and WooCommerce's
actual base-threshold semantics. Target-currency decimals belong exclusively
to the existing conversion path. Do not reject a valid base value merely
because the active target has fewer decimals (e.g. EUR `200.50` with active
JPY).

### 7. Failure / `null` cases

| Condition | Result |
|---|---|
| Empty input | `null` |
| Non-numeric input | `null` |
| Negative input | `null` |
| Non-convertible request | `null` |
| Before Plugin binds the display service (`woocommerce_init`) | `null` |
| Foreign active currency with missing exchange rate | `null` |
| Over-precision base input (fractional digits beyond base currency decimals) | `null` for **every** active currency (**Option A**, WP1) |
| Success | Exact three-key array |

Missing foreign rate must **not** use `CurrencyContext::get_rate()`'s `'1'`
fallback to fabricate a foreign-labelled result.

### 8. Lifecycle / availability

- Composer registers `src/api.php` via `autoload.files` (new mechanism;
  repository was PSR-4 only).
- After plugin bootstrap, `function_exists( 'umc_get_free_shipping_threshold_display' )`
  is true.
- The function returns `null` until the Plugin-bound display service is ready.
- Operational success additionally requires a convertible request and valid
  input.

The facade must **not** reconstruct Settings, CurrencyRegistry, rates,
CurrencyContext, Converter, or PriceConversionService.

### 9. Formatting

`formatted_html` is produced via `wc_price( $amount, array( 'currency' => $code ) )`
so WooCommerce/UMC formatting filters apply. Do not concatenate symbols
manually. Do not parse HTML to recover `amount`.

### 10. Display-only / consumer rule

Consumers MUST NOT independently convert, multiply by rates, re-round,
determine target decimals, substitute currency codes, or reconstruct the
threshold. They SHOULD render `formatted_html` directly.

### 11. Non-goals (hard)

- No M27.
- No REST/AJAX endpoint.
- No shipping-setting mutation or persistence of converted thresholds.
- No Settings / OrderSnapshot / PersistedKeys / CacheState / DB migration
  changes.
- No Visitor Location, checkout policy, reporting, or fixed-price domain
  changes.
- No USA (universal-site-announcements) runtime dependency or USA classes
  in UMC.
- No production deployment as part of this release process.

### 12. Over-precision scope — WP1 LOCKED (Option A)

**Evidence** (`FreeShippingThresholdPrecisionCharacterizationTest`, store
decimals = 2, threshold `200.001`):

| Context | Observation |
|---|---|
| Base active | Native WC compares cart against **raw** `200.001`. Cart at display-rounded `200.00` does **not** qualify. |
| `wc_price( '200.001' )` | Displays as `200.00` (third fractional digit hidden). |
| Foreign active | `Converter::apply_rate( '200.001', rate, target_decimals )` yields an exact target-decimal threshold that eligibility uses. |
| Valid base `200.50` + active JPY (0dp) | Accepted; target rounding remains Converter’s job. |

**Selected rule — Option A (global rejection):**

A base-authored `$base_threshold` whose fractional digit count exceeds the
**base currency’s** decimals makes
`umc_get_free_shipping_threshold_display()` return `null` for **every**
active currency (base and foreign).

**Rationale:** The input itself is not a truthful, consistently representable
WooCommerce threshold across currencies. Global rejection gives consumers one
predictable contract and prevents announcing `200.00` when base checkout
requires `200.001`.

**Rejected — Option B (base-active only):** Would allow foreign display of a
converted/rounded threshold from an over-precise base input. Rejected because
the same merchant-authored string would succeed in one currency and fail in
another, and the base display lie remains possible if a consumer only checks
foreign paths.

**Implementation:** count fractional digits in the input decimal string
against `CurrencyContext` / registry base currency decimals. Do not judge
input precision against the **active** (target) currency.

## Consequences

- First supported public PHP function surface for UMC.
- Eligibility refactor must wire `ShippingConversion` through the shared
  resolver without changing `requires` / `ignore_discounts` / coupon
  free-shipping semantics.
- Standing architecture guards must prevent a second threshold calculator.
- Release **1.2.0**; persistence inventory unchanged from v1.1.1.
