# ADR-0005: Historical order currency context

**Status:** Accepted (v0.4.0)

## Context

Once an order is created, its stored WooCommerce order currency and immutable
`_umc_*` snapshot are the authoritative record of how that transaction was priced
and paid. Later changes to the storefront — a session currency switch, an
exchange rate edit, a currency disablement, a base currency change — must never
alter the order's stored totals, display format, gateway currency, or decimal
precision.

M3 records the snapshot at order creation time. M4 must ensure that every later
operation (display, refund, order-pay retry, admin viewing) reads the snapshot
and formats stored values correctly in the order's currency, never reconverting
a persisted total. The challenge is that WooCommerce's formatting filters
(`wc_price_args`) receive decimals and symbol-position from global options tied
to the **session** currency (M2's `CurrencyFormatting`), not the order currency.

## Decision

1. **Persist decimals only, not presentation formatting.** The snapshot stores
   `_umc_transaction_decimals` (numeric precision; immutable). Symbol, position,
   and separators are **presentation** — they may change via localization or
   merchant config, and we want orders to reflect later updates to those choices.
   Precedence: stored decimals → current config → ISO-4217 fallback → 2.

2. **Separate snapshot reading from formatting fallback.** `OrderSnapshotReader`
   reads and validates `_umc_*` keys with zero dependencies (no `Settings`,
   `CurrencyRegistry`, session, rates). `HistoricalFormattingResolver` owns the
   decimals/symbol/position fallback chain and accesses `CurrencyRegistry` and
   `IsoCurrencyDecimals`. The dependency graph is explicit: Reader → Snapshot →
   Resolver → ResolvedFormatting.

3. **Add explicit snapshot schema versioning.** Store `_umc_snapshot_version = 2`
   for M4; M3 snapshots are classified as v1 (no version key, but M3 keys present).
   Legacy orders (no snapshot) remain viewable and refundable. Malformed/partial/
   future versions are readable via the fallback chain.

4. **Request-scoped LIFO order-currency context stack.** `OrderCurrencyContext`
   enters an order (reads snapshot, resolves formatting once, pushes to stack),
   and exits (pops, reverts). Formatting overrides are active only while the
   context has a current frame. `OrderCurrencyFormatting` registers at priority
   20, after the M2 `CurrencyFormatting` (priority 10), and deterministically
   overrides the M2 result while a context is on the stack. M2 does **not**
   inspect the order context — the two are mutually exclusive by construction
   (M2 only rewrites formatting on a convertible non-base storefront request),
   so a priority-ordered override is chosen over injecting the order context into
   the M2 formatter. Owned paths use `run(order, callable)` with `try/finally` to
   restore on success and error; template-based paths use FILO hook priorities
   (enter@1, exit@999).

5. **Order-pay gateway filtering receives the order currency explicitly, and is
   deterministic.** `OrderPayCurrencyLock` enters the context and, on lock,
   **deregisters** the storefront `GatewayCompatibility` callback (a single shared
   instance) and registers its own at the vacated priority 10, calling
   `GatewayCompatibility::filter_gateways_for_currency($gateways, $order->get_currency())`.
   The order-currency filter therefore evaluates the **original** gateway set: a
   gateway unsupported in the session currency but supported by the order currency
   is never permanently discarded by a session pre-filter, and the result never
   depends on a later filter repairing an earlier one. The generic
   `GatewayCompatibility` inspects no session/cookie/order context — it acts only
   on the explicit currency it is given.

6. **Refund currency inherits parent order currency.** WooCommerce creates refunds
   in the parent currency natively. M4's `RefundSnapshot` writes audit metadata
   (`_umc_parent_transaction_currency`, `_umc_parent_rate_identity`) from the
   parent snapshot, with no amount read/written and no conversion service invoked.
   For a legacy parent with no snapshot, `_umc_parent_transaction_currency` falls
   back to the parent order's own currency (consistent with the admin audit box),
   so the audit trail is always populated; the rate identity has no such fallback.

7. **No exchange-rate service on historical paths.** Order rendering, order-pay,
   refund creation, and audit display must not reference `Converter`,
   `PriceConversionService`, `CurrencyContext::get_rate()`, `RateProvider`, live
   rate lookup, or the session active currency. Structural guards enforce this.

## Consequences

- **Orders store the exact decimals they were created with.** A JPY order (0
  decimals) created in v0.4.0 will display and refund with 0 decimals forever,
  even if JPY is disabled or the currency config changes. Legacy (v1) orders fall
  back to the decimal chain.
- **Presentation (symbol, position, separators) always reflects the live config.**
  An order created in 2024 with the £ symbol will reflect changes to that symbol
  made in 2025 (if the currency is enabled in config). Decimals remain fixed per
  order; this is the intended trade-off between immutability (totals, precision)
  and user experience (merchant can fix a symbol typo).
- **Third-party templates/emails that format amounts without `wc_price()` are
  out of scope.** The context only affects WooCommerce's native hooks and renders.
- **Store API / Blocks order rendering remain deferred.** M4 covers classic
  templates only.
- **Disabled/removed currency order remain payable.** Order-pay availability is
  judged on `umc_gateway_supported_currencies` vs the stored ISO code, never on
  whether the currency is selectable. An order in a sunset currency can still
  reach checkout.
- **Rate changes, session switches, and currency disablement are transparent to
  historical orders.** No side effects, no reconciliation, no invoice rewrites.
  Audit trails in `_umc_*` meta are permanent.

