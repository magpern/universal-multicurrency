# ADR-0025: Authoritative Fixed Product Pricing

**Status:** Accepted (Milestone 20, target v0.19.0)

**Related:**
[`docs/architecture/authoritative-fixed-product-pricing.md`](../architecture/authoritative-fixed-product-pricing.md),
ADR-0001, ADR-0002, ADR-0023, ADR-0024

## Context

Through v0.18.0 Universal Multicurrency converts base-authored WooCommerce product
prices at runtime (ADR-0001). Serious multicurrency stores also need optional
merchant-authored prices per foreign currency without duplicating inventory or
creating a second monetary engine.

M19 established extension compatibility boundaries. M20 must extend the single
product-price seam without weakening transaction integrity or evidence semantics.

## Decision

### Three mutually exclusive pricing paths

| Condition | Authoritative source |
|---|---|
| Active currency = store base | WooCommerce native regular/sale fields |
| Active non-base currency + applicable fixed price | UMC `_umc_fixed_prices` meta |
| Active non-base currency + no applicable fixed price | Existing UMC FX conversion (ADR-0002) |

**FIXED OR CONVERTED — never FIXED THEN CONVERTED.**

A resolved fixed amount must never pass through `PriceConversionService`.

### Base currency exclusion (hard invariant)

`_umc_fixed_prices` must not contain an effective override for the store base
currency. Defense in depth:

- Admin UI shows non-base currencies only
- Save validator rejects/strips base currency entries
- Runtime resolver ignores base currency entries in persisted documents
- Diagnostics may warn on malformed persisted base entries

### Sale scheduling

M20 does **not** introduce per-currency sale schedules.

WooCommerce remains authoritative for **whether the product is on sale**
(including scheduled sales via `_sale_price_dates_from` / `_to`).

UMC supplies the active-currency amount:

- When WC considers the product on sale **and** a fixed sale amount exists for
  the active foreign currency → use fixed sale
- When WC considers the product on sale **and** fixed sale is absent → convert
  the base sale price (existing path)
- When WC sale is inactive → use fixed regular when present, else convert base
  regular

Example (base EUR, active SEK):

| Period | SEK price |
|---|---|
| Before WC sale start | 1,100 (fixed regular) |
| During WC sale window | 880 (fixed sale) |
| After WC sale end | 1,100 (fixed regular) |

### Disabled currency retention

Disabling a currency in UMC settings **must not** delete authored fixed prices.

- Persist all valid foreign-currency entries on save
- Ignore disabled-currency entries at runtime
- Re-enabling restores prior values without re-entry
- Merchant may explicitly clear per currency

`FixedPriceDocument` must not destructively normalize stored entries against the
currently enabled currency list on read.

### Pricing pipeline and extension boundary

```text
WooCommerce authored base product price
        ↓
UMC fixed-price resolution (foreign currencies only)
        ↓
if fixed applies:
    authoritative active-currency product price
else:
    existing UMC base→active conversion
        ↓
WooCommerce downstream calculations
        ↓
optional post-UMC third-party price modifiers
```

M20 does **not** claim generic dynamic-pricing extension compatibility. Plugins
that modify prices after UMC hooks remain governed by the M19 compatibility
boundary (ADR-0024).

Extensions converting **base-authored** add-on/bundle prices on their own seams
are unchanged.

### Persistence

| Key | Location | Uninstall |
|---|---|---|
| `_umc_fixed_prices` | Product + variation post meta | Deleted with product (WC) |
| `_umc_line_price_source` | Order line item meta | Preserved (audit) |
| `_umc_line_price_currency` | Order line item meta | Preserved (audit) |

- No Settings schema bump
- No order snapshot schema bump (remains 4)
- `PersistedKeys` inventory 8 → 9

### Scope (Phase 1)

**In:** simple products, variations, admin product editor, storefront/cart/checkout,
Store API parity, variation cache identity, line-item provenance.

**Out:** bulk edit, import/export, REST write APIs, add-on/bundle/subscription
renewal fixed pricing, Composite/Bookings, reporting UI, WP-CLI pricing audit,
generic dynamic-pricing compatibility claims.

## Consequences

- ADR-0001 remains valid for base currency and conversion fallback
- Merchants gain foreign-currency price control without FX volatility on fixed SKUs
- M21 reporting can consume line-item provenance
- `PriceHooks` must characterize hook priority before modification (WP1)
- Variation price hash must include fixed-price fingerprint and WC sale-state token
