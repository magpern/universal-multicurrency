# Free Shipping Threshold Display API (v1.2.0)

**Status:** Authoritative specification for v1.2.0 (WP0 freeze; WP1 over-precision scope pending).

**ADR:** [`docs/adr/0034-free-shipping-threshold-display-api.md`](../adr/0034-free-shipping-threshold-display-api.md)

**Branch:** `feature/v1.2.0-free-shipping-threshold-display-api`

## Product objective

Let storefront consumers (for example announcement plugins) display the
WooCommerce free-shipping minimum in the shopper's active UMC currency without
duplicating conversion, rounding, active-currency selection, or formatting.

## Public PHP API

```php
umc_get_free_shipping_threshold_display( string $base_threshold ): ?array
```

### Success

```php
array(
	'formatted_html' => string,
	'amount'         => string,
	'currency_code'  => string,
)
```

### Consumer example

```php
if ( function_exists( 'umc_get_free_shipping_threshold_display' ) ) {
	$threshold = umc_get_free_shipping_threshold_display( '200.00' );

	if ( null !== $threshold ) {
		// "Free shipping on orders of {$threshold['formatted_html']} or more"
		echo wp_kses_post( $threshold['formatted_html'] );
	}
}
```

Consumers MUST treat `amount`, `currency_code`, and `formatted_html` as
authoritative. Consumers MUST NOT convert, re-round, look up rates, or
rebuild formatting.

## Shared architecture

```text
Checkout eligibility (ShippingConversion)
        │
        ▼
FreeShippingThresholdResolver ──► PriceConversionService ──► Converter
        ▲
Public display (FreeShippingThresholdDisplayService)
        ▲
umc_get_free_shipping_threshold_display()
```

One resolver instance is composed in `Plugin` on `woocommerce_init` and
injected into both eligibility and the display service.

## Availability

| Stage | Behavior |
|---|---|
| After plugin bootstrap | `function_exists( … ) === true` (`src/api.php` via Composer `autoload.files`) |
| Before display service bound | Function returns `null` |
| Non-convertible request | `null` (`CurrencyContext::is_convertible_request()`) |
| Valid convertible storefront call | Three-key array |

## Input / output rules

- Input: base-currency decimal string (WooCommerce-authored threshold).
- Input precision judged against **base** currency / WC base-threshold
  semantics, not target decimals.
- Foreign missing rate → `null` (no `get_rate()` `'1'` fabrication).
- Formatting via `wc_price( $amount, array( 'currency' => $code ) )`.
- Display-only: does not evaluate the current cart.

## Null matrix

| Condition | Result |
|---|---|
| Empty / non-numeric / negative | `null` |
| Non-convertible request | `null` |
| Unbound service | `null` |
| Missing foreign rate | `null` |
| Over-precision (base-authored) | **WP1 decides** (preference: global rejection) |
| Success | Three public keys only |

## WP1 hard gate (before WP2)

Characterize base vs foreign eligibility when `min_amount` has excess
fractional precision (e.g. `200.001`). Amend ADR-0034 with Option A (global
rejection) or Option B (base-active only) before implementing the resolver.

## Persistence

Unchanged from v1.1.1: Settings schema 7, OrderSnapshot 5, PersistedKeys
inventory 11, CacheState contract 1, no DB migration.

## Non-goals

No M27, no REST/AJAX, no shipping-setting writes, no USA dependency, no
production deploy.
