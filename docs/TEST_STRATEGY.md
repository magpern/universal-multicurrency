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

## Deferred (later milestones)

Order display / emails / admin / account rendering, refunds and the order-pay
currency lock (Milestone 4). Cart & Checkout **Blocks** / Store API (dedicated
later milestone). Fee conversion (opt-in only).
