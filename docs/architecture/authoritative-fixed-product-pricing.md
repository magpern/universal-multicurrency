# Authoritative Fixed Product Pricing — M20 architecture

**Milestone:** 20 · **Target version:** 0.19.0 · **Baseline:** origin/main
`2af284772b48834974b2f6748d5e878cf9e40715` (M19 / v0.18.0 closure)

**ADR:** [`docs/adr/0025-authoritative-fixed-product-pricing.md`](../adr/0025-authoritative-fixed-product-pricing.md)

---

## 1. Runtime contract

```text
Base currency           → WooCommerce native regular/sale fields
Foreign + fixed price   → merchant-authored _umc_fixed_prices amount
Foreign + no fixed      → existing PriceConversionService FX path
```

Fixed OR converted. Never fixed then converted.

---

## 2. Fixed-price document (`_umc_fixed_prices`)

JSON document stored on simple products and variations.

```json
{
  "schema_version": 1,
  "currencies": {
    "SEK": { "regular": "1100", "sale": "880" },
    "GBP": { "regular": "79", "sale": "" }
  }
}
```

| Rule | Detail |
|---|---|
| `schema_version` | Always `1` in M20 |
| Currency keys | Uppercase ISO codes configured in UMC |
| Base currency | **Must not appear** — rejected on save, ignored at runtime |
| Empty `sale` | Valid; sale fallback converts base sale when WC sale active |
| Absent currency key | Conversion fallback for that currency |
| Disabled currency | **Retained** in document; **ignored** at runtime |
| Sparse | Omit empty documents entirely (delete meta) |

Decimals: WooCommerce-compatible decimal strings; normalized on save.

---

## 3. Sale-state gating

`ProductSaleStateResolver` delegates to WooCommerce native sale semantics
(`WC_Product::is_on_sale()` including scheduled dates).

| WC sale state | Fixed regular authored | Fixed sale authored | Result |
|---|---|---|---|
| Inactive | yes | — | Fixed regular |
| Inactive | no | — | Convert base regular |
| Active | — | yes | Fixed sale |
| Active | — | no | Convert base sale |
| Active | yes | no | Convert base sale (fixed regular not used as active price) |

Variation products use the **variation's** sale state.

---

## 4. Pricing pipeline

```text
WC getter returns base-authored value
        ↓
PriceHooks (priority 10, unchanged unless characterization proves otherwise)
        ↓
should_convert()? (false when base active or umc_should_convert_product_price false)
        ↓
ProductPriceResolutionService
   ├─ fixed path → return active-currency amount; record provenance=fixed
   └─ converted path → PriceConversionService.convert(value); provenance=converted
        ↓
WC downstream (tax, cart, coupons)
        ↓
Post-UMC extensions (M19 boundary — no generic guarantee)
```

### Hook characterization baseline (pre-M20)

Current [`PriceHooks`](../../src/Integration/PriceHooks.php):

| Filter | Priority | Accepted args |
|---|---|---|
| `woocommerce_product_get_*` | 10 | 2 (`$price`, `$product`) — M20 uses both |
| `woocommerce_product_variation_get_*` | 10 | 2 |
| `woocommerce_variation_prices_*` | 10 | 3 (`$price`, `$variation`, `$parent`) |
| `woocommerce_get_variation_prices_hash` | 10 | 1 |

M20 extends hash with: active currency, rate, fixed-document fingerprint, sale-state token.

---

## 5. Domain components

| Class | Responsibility |
|---|---|
| `FixedPriceDocument` | Immutable normalized document |
| `FixedCurrencyPrice` | Regular + optional sale for one currency |
| `FixedPriceValidator` | Decimals, base exclusion, malformed payload rejection |
| `FixedPriceRepository` | Meta read/write; request memoization |
| `ProductSaleStateResolver` | WC `is_on_sale()` wrapper |
| `ProductPriceResolution` | Value object: amount, source, currency, field |
| `ProductPriceResolutionService` | Fixed-vs-converted decision |
| `ProductPriceProvenanceRegistry` | Request-scoped product→source map for checkout |
| `LineItemPriceProvenance` | Writes line-item meta at order creation |

---

## 6. Line-item provenance

Written once at order line creation (classic + Store API).

| Meta key | Values |
|---|---|
| `_umc_line_price_source` | `fixed` \| `converted` |
| `_umc_line_price_currency` | ISO code of transaction currency at resolution |

Reflects the path used when the line item was priced — not inferred from current
product settings.

Order snapshot schema **4** unchanged.

---

## 7. Variation cache identity

`woocommerce_get_variation_prices_hash` components (when converting):

1. Active currency code
2. Active FX rate (conversion fallback only — still included for cache safety)
3. Fixed-price document fingerprint (`md5` of canonical JSON or document hash)
4. Sale-state token (`on_sale` / `not_on_sale` + schedule boundary fingerprint)

---

## 8. Admin UX

Panel: **Multicurrency prices** on product editor.

- **Non-base currencies only** — no second base editor
- Enabled currencies: editable regular + optional sale
- Disabled currencies: read-only/inactive section showing retained values
- Blank field = automatic conversion for that amount type
- Copy explains WC sale schedule governs all fixed sale amounts

Capabilities: standard product edit caps via WooCommerce save lifecycle.

---

## 9. Explicit non-goals

See ADR-0025. No bulk/import/REST writes, no CLI audit, no add-on/bundle/renewal
fixed pricing, no reporting UI, no dynamic-pricing compatibility claims.

---

## 10. Persistence versions (M20)

| Contract | Version |
|---|---|
| `Settings::SCHEMA_VERSION` | 6 (unchanged) |
| `PersistedKeys::INVENTORY_VERSION` | 9 |
| `OrderSnapshot::SCHEMA_VERSION` | 4 (unchanged) |

---

## 11. Work packages

WP0 docs → WP1 characterization → WP2 domain → WP3 persistence → WP4 resolution →
WP5 simple integration → WP6 variations + cache → WP7 admin → WP8 diagnostics →
WP9 cart/checkout/provenance → WP10 regression/docs → WP11 release prep.
