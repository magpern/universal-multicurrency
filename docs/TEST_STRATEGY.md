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
runtime `_umc_*` deletion; no Store API / Blocks hook registration in `src`; the
historical-display enter/exit brackets are paired. Idempotent boot; no leaks.

## Deferred (later milestones)

Cart & Checkout **Blocks** / Store API (dedicated later milestone). Fee conversion
(opt-in only).
