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
