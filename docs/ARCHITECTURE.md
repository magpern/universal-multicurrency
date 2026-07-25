# Architecture

## Principle

Currency affects money only.

WooCommerce remains the single source of truth for inventory.

## Standalone independence

The plugin is fully standalone. Its only plugin dependency is WooCommerce
(`Requires Plugins: woocommerce`). It has no dependency or runtime coupling to
FOX / WOOCS / WooCommerce Currency Switcher or any helper plugin, and reads
none of their classes, functions, constants, options, cookies or sessions. All
persisted state lives in the plugin's own `umc_settings` option (plus permanent
order snapshot meta in later milestones). See ADR-0003.

## Layers

-   Domain
-   Money
-   Exchange Rates
-   WooCommerce Integration
-   Admin
-   Frontend
-   Diagnostics

## Domain layer (Milestone 1)

The domain core is pure PHP with no WordPress or WooCommerce dependency, so it
is unit-testable without a bootstrap. It never registers hooks.

### Invariants

- **`Currency` never stores an exchange rate.** A `Currency` is identity and
  display formatting only (code, symbol, position, decimals, enabled). Rates
  are configuration held in `Settings` and resolved through a `RateProvider`.
- **`Converter` is stateless and owns all monetary arithmetic.** It holds only
  its collaborators, keeps no mutable state, caches nothing, and is fully
  deterministic. No other class multiplies or rounds money — `Settings` and
  `ManualRateProvider` only store and return rate strings.
- **The base currency lives only in the WooCommerce `woocommerce_currency`
  option** and is never duplicated into `umc_settings`. The domain layer does
  not read that option itself: `CurrencyRegistry` receives the base `Currency`
  by injection, so the option is read once at the composition seam.
- **A missing or unusable rate fails explicitly.** A converted value is never
  produced from an absent, zero, negative or non-numeric rate.

### Contracts

| Class | Contract |
|---|---|
| `Currency` | Immutable value object. Code validated as `^[A-Z]{3}$` (format only, not ISO-4217 membership — WooCommerce allows custom codes). Decimals 0–4. Position one of `left`, `right`, `left_space`, `right_space`. |
| `Settings` | Sole owner of `umc_settings`. `defaults()`/`sanitize()` are pure and never throw; sanitize cleans or drops invalid input (invalid decimals fall back to 2, unusable rates are blanked while the row is kept). Constructible from in-memory data for testing. |
| `RateProvider` | The only rate abstraction (an implementation seam for future automatic rates). `get_rate(base, target)` returns `'1'` for same-currency, a positive decimal string, or `null`. |
| `ManualRateProvider` | Reads admin-entered rates from `Settings`; performs no arithmetic. |
| `Converter` | `convert(amount, target)` and the pure static `apply_rate()` / `round_to_string()`. Rounds half-up to the target decimals; base target is a rate-1 no-op. See ADR-0002. |
| `CurrencyRegistry` | Assembles `Currency` objects from an injected base plus configured currencies. Base is always present and enabled; a same-code settings row never overrides the base identity. |

Exceptions live in `UMC\Exceptions` and all implement the marker interface
`UMC\Exceptions\Exception` for catch-all handling, while extending the most
fitting SPL type (`InvalidArgumentException` / `RuntimeException`).

## Storefront integration layer (Milestone 2)

Milestone 2 connects the domain core to WooCommerce for **runtime, display-only**
price conversion. Base product prices in the database are never written;
conversion happens only in `view`-context read filters. Stock, cart totals
persistence, orders, refunds, gateways, shipping, taxes, coupons, fees and
REST/Store API remain untouched (later milestones).

Request flow and collaborators:

- `CurrencyResolver` — pure priority resolution (explicit → session → cookie →
  base) against the selectable allow-list.
- `CurrencySwitcher` — validates a `?currency=` request, persists to the WC
  session + a 30-day cookie, and safe-redirects without the parameter.
- `CurrencyContext` — request-scoped facade: resolves the active `Currency`,
  computes the base→active rate once, builds the selectable set (enabled **and**
  rated, plus base), and decides `is_convertible_request()`. Memoized.
- `Integration\PriceConversionService` — the single conversion seam
  (empty/non-numeric passthrough + base no-op, then `Converter::apply_rate()`).
  All integration points (M2 price hooks and later cart/coupon/shipping) go
  through it.
- `Integration\PriceHooks` / `Integration\CurrencyFormatting` — thin
  view-context filters delegating to the seam and reporting the active currency's
  identity/formatting. Attached unconditionally, gated per request.
- `Frontend\Switcher` — one reusable `render()` behind `[umc_switcher]`; future
  block/Elementor wrappers reuse it.
- `Admin\SettingsPage` / `Admin\CurrencyTableField` — a WooCommerce settings tab
  whose currencies table persists through `Settings::save()` (M1 sanitizer).

The base `Currency` is built at the composition root (`Plugin::init()`) from
`woocommerce_currency` and WooCommerce's price options, then injected into
`CurrencyRegistry` — the read Milestone 1 deferred.

Every hook is catalogued in `docs/HOOKS.md`. Runtime conversion and rounding are
governed by ADR-0002.

## Transaction layer (Milestone 3)

Milestone 3 makes the **classic** cart and checkout authoritative in the selected
currency and records an immutable order-time rate snapshot. It reuses the M2 seam
as the single product-price converter and adds conversion only for the monetary
inputs M2 never touched. The end-to-end flow and the double-conversion proof live
in `docs/architecture/transaction-flow.md`; the model is governed by ADR-0004.

- **Unit-price-authoritative conversion.** M2's `view`-context getters remain the
  only product-price converter; WooCommerce's native totals engine computes line
  totals, discounts, shipping, fees and taxes from the converted unit prices. The
  cart stores product references, never prices, so every recalculation reconverts
  from base — a converted value is never reused as input, and `set_price()` is
  never called.
- **Taxes are never converted** — WooCommerce computes them natively; tax rates are
  currency-agnostic percentages.
- Collaborators (all consuming `Integration\PriceConversionService`):
  - `Cart\CartRecalculation` — recomputes totals when the rate identity changes.
  - `Integration\CouponConversion` — fixed amounts + min/max thresholds base→active.
  - `Integration\ShippingConversion` — **core methods only** cost/tax conversion +
    per-currency shipping-cache isolation; `umc_convert_shipping_rate` opt-out.
  - `Integration\GatewayCompatibility` — hides gateways incompatible with the
    active currency.
  - `Order\OrderSnapshot` — writes the write-once `_umc_*` snapshot via `WC_Order`
    CRUD (HPOS-safe) at order creation.
- **Rate identity** — `CurrencyContext::get_currency_signature()` (`code:rate`)
  keys every monetary cache so they self-invalidate on a switch **or** rate edit.
- **Fees are not converted** (disabled; opt-in `umc_convert_fee` only). **Blocks /
  Store API** and order display / emails / refunds are later milestones; classic
  checkout is the only supported path and Blocks compatibility is not claimed.

## Order & display layer (Milestone 4)

Milestone 4 ensures once an order exists, its stored WooCommerce order currency
and immutable `_umc_*` snapshot are authoritative for every later operation — the
order never changes appearance, totals, gateway currency, or formatting due to
session currency changes, rate edits, disabled currencies, or base-currency
changes. The historical services layer reads stored values in the order currency,
formats them correctly via a fallback chain (stored decimals → config → ISO-4217),
and never reconverts a persisted total.

### Invariants

1. **Stored totals authoritative** — never multiplied by any rate at render/pay/refund.
2. **Order currency overrides session currency** while an order is rendered, paid, or refunded.
3. **Refund currency == parent order currency**, always.
4. **No exchange-rate service on any historical/refund path** — no `Converter`,
   `PriceConversionService`, `RateProvider`, or `CurrencyContext` rate/active lookup.
5. **Context cannot leak** — every enter is paired with an exit via `try/finally`
   (owned paths) or strict FILO hook priorities; after render, formatting reverts.
6. **HPOS-only access** — `WC_Order`/`WC_Order_Refund` CRUD; no `$wpdb`, post-meta API, or table SQL.
7. **Snapshot permanent & additive** — M3 keys never rewritten; M4 only adds `_umc_*` keys; no `_umc_*` deletion.
8. **Legacy orders viewable & refundable** — missing snapshot never blocks read/refund.

### Architecture

A new **order-scoped request state stack** that reads and resolves currency formatting
once on entry, then caches the formatting for the request:

```
WC_Order
  → OrderSnapshotReader        (CRUD read + validate + classify; NO Settings/registry/session)
  → OrderCurrencySnapshot       (immutable VO; schema_version, stored_decimals, audit fields)
  → HistoricalFormattingResolver (decimals/symbol/position fallback; uses CurrencyRegistry + IsoCurrencyDecimals)
  → ResolvedOrderCurrencyFormatting (immutable: code, decimals, symbol, position)
       ↓
  OrderCurrencyContext (stack of ResolvedOrderCurrencyFormatting, LIFO)
       ├─ OrderCurrencyFormatting  (override globals under context; M2 CurrencyFormatting stands down)
       ├─ HistoricalOrderDisplay   (enter/exit brackets around render zones)
       ├─ OrderPayCurrencyLock     (order-pay: enter context; gateway filtering with explicit order currency)
       └─ Admin\OrderCurrencyMetaBox (read-only audit; direct reader + resolver use)
  RefundSnapshot (reader only — writes _umc_parent_* audit meta)
```

### Collaborators

| Class | Deps | Responsibility |
|---|---|---|
| `Order\OrderSnapshotReader` | *(none)* | CRUD-read `_umc_*` metadata; validate/normalize; classify via `_umc_snapshot_version`. No Settings, registry, session, or rates. |
| `Order\OrderCurrencySnapshot` | — | Immutable VO: accessors + classification flags (`has_snapshot`, `is_legacy`, `is_partial`, `is_malformed`, `is_future`). |
| `Order\HistoricalFormattingResolver` | `CurrencyRegistry`, `Support\IsoCurrencyDecimals` | Decimals fallback: stored → config → ISO-4217 → 2. Symbol/position from live config (presentation-only). |
| `Order\ResolvedOrderCurrencyFormatting` | — | Immutable VO: `code()`, `decimals()`, `symbol()`, `position()`. |
| `Order\OrderCurrencyContext` | `OrderSnapshotReader`, `HistoricalFormattingResolver` | Request-scoped LIFO stack. `enter(order)`, `exit()`, `run(order, callable)`, `is_active()`, `depth()`, `current_code()`. |
| `Order\OrderCurrencyFormatting` | `OrderCurrencyContext` | Override `woocommerce_currency`, `_symbol`, `wc_price_args` decimals/separators when context active. M2 `CurrencyFormatting` gates on `is_active()`. |
| `Order\HistoricalOrderDisplay` | `OrderCurrencyContext` | Enter/exit brackets (prio 1/999 FILO) around order-details table, emails, resend, My-Account list. |
| `Order\OrderPayCurrencyLock` | `OrderCurrencyContext`, `Integration\GatewayCompatibility` | On `order-pay`, load+verify order, enter context for request, filter gateways with explicit order currency. |
| `Order\RefundSnapshot` | `OrderSnapshotReader` | On `woocommerce_create_refund`, write-once `_umc_parent_transaction_currency` + `_umc_parent_rate_identity` (audit). |
| `Support\IsoCurrencyDecimals` | *(none)* | Pure ISO-4217 fallback map: 0-decimal (JPY, etc), 3-decimal (BHD, etc), default 2. |
| `Admin\OrderCurrencyMetaBox` | `OrderSnapshotReader`, `HistoricalFormattingResolver` | Read-only audit box (HPOS + legacy). Pure `view_model()` builder + escaped render. |

The snapshot schema includes `_umc_snapshot_version = 2` for M4 (M3 = v1). Legacy,
partial, malformed and future versions remain readable and refundable via the
fallback chain. See ADR-0005.
