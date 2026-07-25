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

### Historical order display

EUR/JPY orders in different session currencies show correct decimals + symbol;
totals identical to creation. After a rate edit / currency disable / base-currency
change, the order's formatting and totals remain unchanged. Admin order (HPOS +
legacy) shows the audit meta box with snapshot version, decimals, symbol, position.

### Order-pay currency lock

Order-pay endpoint locks the order currency regardless of session. Gateway filter
receives the order currency explicitly; incompatible gateways hidden; if no
compatible gateway exists and the currency is disabled, a blocking error shows
and the order remains payable via the ISO fallback. Order totals never change;
payment request currency equals order currency.

### Refunds

Full, partial, line, shipping, tax, manual, and multiple refunds all store/display
in the parent order currency; no conversion; parent − refunds = remaining
(reconciliation with zero-decimal JPY and two-decimal EUR). `_umc_parent_*`
metadata written once and correct.

### Legacy & malformed orders

Pre-M3 (no snapshot), M3 v1 (no version key), partial, malformed, and future
versions all remain viewable and refundable. Decimal fallback chain applies:
stored → config → ISO → 2.

### Context lifecycle

Nested + repeated renders leave `OrderCurrencyContext::depth() == 0`. Formatting
reverts to session after the context exits. The `run()` method restores even if
the wrapped render throws (exception safety via try/finally).

### Structural guards

M4 services (`Order/*` + `Admin\OrderCurrencyMetaBox`) contain no `Converter` /
`PriceConversionService` / `CurrencyContext` rate/active access / session/cookie /
total-setters. `_umc_*` metadata never deleted. Refund hook released;
bracket pairing intact. Idempotent boot; no leaks.

## Deferred (later milestones)

Cart & Checkout **Blocks** / Store API (dedicated later milestone). Fee conversion
(opt-in only).
