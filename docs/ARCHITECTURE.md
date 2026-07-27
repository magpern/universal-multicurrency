# Architecture

## Principle

Currency affects money only.

WooCommerce remains the single source of truth for inventory.

## Standalone independence

The plugin is fully standalone. Its only plugin dependency is WooCommerce
(`Requires Plugins: woocommerce`). It has no dependency or runtime coupling to
FOX / WOOCS / WooCommerce Currency Switcher or any helper plugin, and reads
none of their classes, functions, constants, options, cookies or sessions. All
persisted state lives in the plugin's own options (`umc_settings`,
`umc_rate_state`) plus permanent order and refund snapshot metadata. The
authoritative inventory of every key is [`docs/PERSISTED_DATA.md`](PERSISTED_DATA.md), enforced by
`PersistedKeys` and `PersistedKeysInventoryTest`. Merchant migration from
another currency switcher is documented in [`docs/MIGRATION.md`](MIGRATION.md)
(manual path only; no foreign import). See ADR-0003.

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
  deterministic. No other class multiplies or rounds money — `Settings::get_rate()`
  and `ManualRateProvider` return derived rate strings; `RateResolver` owns
  effective-rate arithmetic (ADR-0010).
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
| `Settings` | Sole owner of `umc_settings`. `defaults()`/`sanitize()` are pure and never throw; sanitize cleans or drops invalid input (invalid decimals fall back to 2, unusable rates are blanked while the row is kept). Constructible from in-memory data for testing. On first load from the option, {@see SettingsUpgrader} may migrate legacy schema version 0 stores to version 1, normalize through `sanitize()`, and persist only when the stored value changes. |
| `RateProvider` | Runtime read-side contract for `Converter` — synchronous, no I/O. `get_rate(base, target)` returns `'1'` for same-currency, a positive decimal string, or `null`. Automatic quotes reach this path through `Settings::get_rate()` / `RateResolver`; batch fetches use `ExchangeRateSource` instead (ADR-0010). |
| `ManualRateProvider` | The shipped `RateProvider` implementation. Delegates to `Settings::get_rate()`; performs no arithmetic. |
| `Converter` | `convert(amount, target)` and the pure static `apply_rate()` / `round_to_string()`. Rounds half-up to the target decimals; base target is a rate-1 no-op. See ADR-0002. |
| `CurrencyRegistry` | Assembles `Currency` objects from an injected base plus configured currencies. Base is always present and enabled; a same-code settings row never overrides the base identity. |

Exceptions live in `UMC\Exceptions` and all implement the marker interface
`UMC\Exceptions\Exception` for catch-all handling, while extending the most
fitting SPL type (`InvalidArgumentException` / `RuntimeException`).

### Settings schema upgrade (Milestones 7–8)

`Settings::SCHEMA_VERSION` is **2** and must not be bumped unless a genuine
settings shape change requires it. Both production migrations exist because the
stored shape actually changed; neither is an artificial bump.

Legacy stores (schema version **0**) persisted only a `currencies` array inside
`umc_settings`, with no explicit `schema_version` key. `SettingsUpgrader::migrate_0_to_1`
introduces `schema_version: 1` and copies the currency rows unchanged;
{@see Settings::sanitize()} then produces the canonical row shape.

`SettingsUpgrader::migrate_1_to_2` introduces the automatic-rate shape: the
per-currency `rate` key becomes `manual_rate`, and the global keys `rate_mode`,
`rate_provider`, `rate_update_interval`, and `rate_max_age_hours` gain defaults.
Upgraded stores stay in **manual** mode, so conversion output is unchanged across
the boundary — proven byte-for-byte by `tests/unit/SettingsMigrationFidelityTest.php`.
See [`MIGRATION.md`](MIGRATION.md) § Internal settings schema migrations.

`SettingsUpgrader` responsibilities:

- parse the stored schema version (missing/malformed → 0)
- reject unsupported **future** versions without persisting partial results
- apply migrations in ascending target-version order
- sanitize through `Settings::sanitize()`
- persist only when migration or normalization changes the stored option value
- remain idempotent (`v1 → v1` performs no migration and avoids writes when already canonical)

Future migrations register in `SettingsUpgrader::production_migrations()` keyed by
the version they produce. Chaining is proven in unit tests via injected migration
maps (for example `0 → 1 → 2 → 3`) without exposing fake versions in production.

When the option is **absent**, `Settings::get()` returns defaults without writing.
When upgrade fails, in-memory callers receive defaults and the stored option is
left untouched.

## Storefront integration layer (Milestone 2)

Milestone 2 connects the domain core to WooCommerce for **runtime, display-only**
price conversion. Base product prices in the database are never written;
conversion happens only in `view`-context read filters. Stock, cart totals
persistence, orders, refunds, gateways, shipping, taxes and coupons are later
layers; fees are never converted.

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
- **Fees are not converted** (disabled; opt-in `umc_convert_fee` only). Order
  display, emails and refunds are Milestone 4; the Cart and Checkout blocks are
  Milestone 5.

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
| `Order\OrderCurrencyFormatting` | `OrderCurrencyContext` | Override `woocommerce_currency`, `_symbol`, `wc_price_args` decimals/separators when context active. Registered at priority 20, it overrides the M2 `CurrencyFormatting` (priority 10) result while a context is on the stack; M2 does not inspect the order context. |
| `Order\HistoricalOrderDisplay` | `OrderCurrencyContext` | Enter/exit brackets (prio 1/999 FILO) around order-details table, emails, resend, My-Account list. |
| `Order\OrderPayCurrencyLock` | `OrderCurrencyContext`, `Integration\GatewayCompatibility` | On `order-pay`/`pay_for_order`, load+verify order, enter context for the request; deregister the storefront gateway callback and filter the original gateway set with the explicit order currency. |
| `Order\RefundSnapshot` | `OrderSnapshotReader` | On `woocommerce_create_refund`, write-once `_umc_parent_transaction_currency` (falling back to the parent order currency for legacy parents) + `_umc_parent_rate_identity` (audit). |
| `Support\IsoCurrencyDecimals` | *(none)* | Pure ISO-4217 fallback map: 0-decimal (JPY, etc), 3-decimal (BHD, etc), default 2. |
| `Admin\OrderCurrencyMetaBox` | `OrderSnapshotReader`, `HistoricalFormattingResolver` | Read-only audit box (HPOS + legacy). Pure `view_model()` builder + escaped render. |

The snapshot schema includes `_umc_snapshot_version = 2` for M4 (M3 = v1). Legacy,
partial, malformed and future versions remain readable and refundable via the
fallback chain. See ADR-0005.

## Store API layer (Milestone 5)

The Cart and Checkout blocks are served by the same domain services as the
classic flow. `CurrencyContext::is_convertible_request()` now admits Store API
requests — they are storefront surfaces in everything but transport — while every
other REST namespace continues to report stored base values.

Opening that gate is most of the milestone: prices, coupons, core shipping, cart
recalculation and gateway availability all work over the Store API without new
code, because WooCommerce's schemas read prices through the same `view`-context
getters its templates use. `src/StoreApi` supplies only what WooCommerce's block
path does differently.

| Class | Deps | Responsibility |
|---|---|---|
| `StoreApi\CheckoutSnapshotAdapter` | `Order\OrderSnapshot` | Runs the snapshot writer at the Store API's equivalent of order creation, since `woocommerce_checkout_create_order` never fires for it. Owns the policy that lets an unpaid draft's snapshot follow a currency change, and nothing else — the metadata and the authority to write it stay with `OrderSnapshot`. |
| `StoreApi\OrderCurrencyLock` | `Order\OrderCurrencyContext`, `Integration\GatewayCompatibility` | The REST counterpart of `OrderPayCurrencyLock`, which hooks `template_redirect` and so never runs for an API request. Brackets the two order-scoped routes so a stored order is reported in its own currency and gateways are filtered by it. |
| `StoreApi\CartExtensionData` | `CurrencyContext` | Publishes currency state — the active code, the base code and the selectable codes — under the `umc` namespace on the cart endpoint. Carries no money, because amounts already reach clients through WooCommerce's own fields, and no exchange rate or cache identity, because those are implementation details rather than contract. |

Two supporting changes sit outside the namespace. `CurrencySwitcher` returns early
on REST requests, because answering an API call with a redirect would corrupt the
response and persisting a preference as a side effect of a read would surprise
API consumers. And both formatters filter `option_woocommerce_currency_pos`, the
one part of the money identity WooCommerce reads without a filter.

No JavaScript ships. Switching currency reloads the page, which makes every block
refetch on its own; an in-place switch would belong on `POST /cart/extensions`
under the same namespace. See ADR-0006.

## Diagnostics layer (Milestone 6)

Milestone 6 adds passive compatibility observation: detect other currency
switchers, grade evidence, and warn administrators — without deactivating,
modifying, or calling into another plugin, and without affecting monetary
behaviour anywhere. Detection is one-way observation from the host environment
outward through admin surfaces only. See ADR-0007 and ADR-0008.

| Class | Deps | Responsibility |
|---|---|---|
| `Diagnostics\Diagnostics` | — | Sub-composition root; the only Diagnostics class `Plugin.php` names |
| `Diagnostics\DetectorManifest` | — | Built-in detector data; the only file that may name a third-party product |
| `Diagnostics\DetectorRegistry` | — | Applies `umc_conflict_detectors`, sanitises, hydrates `Detector[]` |
| `Diagnostics\Detector` / `Signature` | — | Immutable value objects |
| `Diagnostics\SignatureKind` / `Confidence` | — | Kind weights and confidence thresholds |
| `Diagnostics\Finding` | — | Scored result: id, label, score, confidence, matched signatures |
| `Diagnostics\EnvironmentProbe` | — | Interface: evaluate signatures → evidence map |
| `Diagnostics\WordPressEnvironmentProbe` | — | The only file that reads WP registries and symbol tables |
| `Diagnostics\ConflictScorer` | — | Pure scoring over detectors + evidence |
| `Diagnostics\VersionPolicy` | — | Pure version-axis classification for Site Health |
| `Diagnostics\ConflictDetector` | `EnvironmentProbe`, `ConflictScorer`, `DetectorRegistry` | Probe → memo → score; supplies fingerprint |
| `Diagnostics\ConflictNotice` | `ConflictDetector`, `NoticeDismissal` | Dashboard/network notices + settings-tab field render |
| `Diagnostics\NoticeDismissal` | `ConflictDetector` | The only Diagnostics class that writes persistent state (user meta) |
| `Diagnostics\SiteHealthReport` | `ConflictDetector`, `VersionPolicy` | Site Health direct tests + debug section |

`Plugin.php` registers `Diagnostics` only when `is_admin()` and not during AJAX,
cron, or WP-CLI. Evaluation is lazy at `admin_notices`; request-scoped memoization
inside `ConflictDetector` is the only cache. `SettingsPage` prepends a field of
type `umc_conflict` to its settings array; Diagnostics registers the renderer —
no class reference crosses the Admin boundary.

### Invariants

1. **`src/Diagnostics/` is the only namespace permitted to know that third-party plugins exist.** Within it, `DetectorManifest.php` is the only file permitted to name one.
2. **No other subsystem may reference a detector class** — not by name, namespace, or string outside Diagnostics (except the `Plugin.php` seam).
3. **Conversion, pricing, Store API, snapshots, historical order services, and admin order services remain completely unaware that third-party plugins exist.** They are not passed findings and cannot behave differently because of one.
4. **Compatibility information flows only outward through the Diagnostics public surface** — rendered notices, Site Health tests, the debug section, and the `umc_conflict_*` view-model filters. There is no inward path.
5. **A merchant warning can never influence monetary behaviour.** No price, rate, cart total, coupon, shipping cost, tax, gateway availability, order, refund, or snapshot may differ because a conflict was detected, graded, rendered, or dismissed.
6. **Detection observes; it never acts.** No plugin is activated, deactivated, modified, or configured; no plugin option is written; no foreign data is read beyond passive existence checks.
7. **Detection is free everywhere it is not needed.** Zero hooks, zero probes, zero queries, and zero autoloaded Diagnostics classes on frontend, Store API, REST, AJAX, cron, and CLI requests.

## Release Candidate governance (Milestone 7)

The Release Candidate adds documentation, guards, and audit gates without
changing monetary behaviour. Authoritative records:

| Topic | Document / gate |
|---|---|
| Standalone architecture (no foreign runtime coupling) | ADR-0003, `DiagnosticsBoundaryGuardTest`, `ReleaseAuditTest` |
| Passive conflict detection | ADR-0007, `DiagnosticsGuardTest` |
| Persisted-key inventory | [`PERSISTED_DATA.md`](PERSISTED_DATA.md), `PersistedKeys`, `PersistedKeysInventoryTest` |
| Uninstall retention | ADR-0009, `uninstall.php`, `UninstallPolicyGuardTest` |
| Manual merchant migration only | [`MIGRATION.md`](MIGRATION.md) — no foreign import, no RC CSV parser |
| Settings schema | `Settings::SCHEMA_VERSION === 2`; production migrations v0→v1 and v1→v2 via `SettingsUpgrader` |
| Translation readiness | [`TRANSLATION.md`](TRANSLATION.md), `languages/universal-multicurrency.pot`, `composer make-pot:check` |
| Security audit | [`SECURITY_REVIEW.md`](SECURITY_REVIEW.md) — zero open Critical/High; accepted M/L risks documented |
| Performance baselines | [`PERFORMANCE_BASELINES.md`](PERFORMANCE_BASELINES.md) — deterministic query/write ceilings only |
| Release audit | [`RELEASE_AUDIT.md`](RELEASE_AUDIT.md), `composer release-audit` — release-blocking repository gate |

`SettingsUpgrader` uses a single broad `catch ( \Throwable )` at the upgrade
boundary so corrupt stored options fail closed to defaults without persisting
partial migrations. `StorefrontGuardTest` allowlists only `SettingsUpgrader.php`
for that pattern.

## Exchange rate layer (Milestone 8)

Milestone 8 makes exchange rates fetchable from an external provider without
changing how conversion reads a rate. `Converter` and the `RateProvider` read
contract are unchanged; `Settings::get_rate()` derives the effective rate through
`RateResolver`. What is new is *where the inputs come from* and *what is allowed
to persist*.

Two rules shape the whole layer:

- **Derive, don't persist** (ADR-0010). Only the raw provider quote is stored.
  The **effective** rate — provider or manual quote plus the merchant
  adjustment — is recomputed by `RateResolver` on every read and is never
  written to any option. There is exactly one rate arithmetic definition.
- **Configuration and operations are separate options** (ADR-0012).
  `umc_settings` holds money-bearing merchant configuration.
  `umc_rate_state` holds volatile operational facts — last fetch time, last
  status, consecutive failures, bounded failure history, the update lock, and
  provider cache validators. A failed or unchanged fetch touches only the
  latter.

### Collaborators

| Class | Deps | Responsibility |
|---|---|---|
| `Rates\ExchangeRateSource` | — | Provider contract: `id()`, `label()`, capability probes, and `fetch( base, targets, ?previous )` returning one `RateFetchResult` for the whole batch. Distinct from `RateProvider`, which resolves rates for conversion at runtime. |
| `Rates\Providers\FrankfurterRateSource` | `Http\HttpTransport` | The shipped provider. One batch request per update; parses quotes through `Settings::normalize_rate()`; maps HTTP 304 to `RateFetchResult::not_modified()` and any other non-2xx or malformed body to a total failure. Injectable transport keeps every test offline. |
| `Rates\Http\HttpTransport` / `WordPressHttpTransport` | — | Narrow outbound seam over `wp_safe_remote_get()`, normalized into `HttpResponse` (status, lowercase headers, body, transport-error flag). |
| `Rates\RateQuote` | — | Immutable `base → target` quote as a decimal string. |
| `Rates\RateFetchResult` | `RateQuote`, `ProviderMetadata` | Immutable batch outcome: quotes, per-currency failures, metadata, fetch timestamp, and the mutually exclusive predicates `is_not_modified()`, `is_partial_failure()`, `is_total_failure()`. |
| `Rates\ProviderMetadata` | — | Immutable cache validators and provenance (schema version, provider id, quote date, `ETag`, `Last-Modified`). Stored in `umc_rate_state`, never in `umc_settings`. |
| `Rates\RateResolver` | — | Pure derivation of the effective rate from mode, manual rate, provider rate, and merchant adjustment. The only place adjustment arithmetic exists. |
| `Rates\RateConfiguration` | `RateUpdateInterval` | Immutable snapshot of the global rate settings, including `is_automatic_enabled()`. |
| `Rates\RateUpdateInterval` | — | Validated ISO-8601 recurrence (`PT6H`, `PT12H`, `P1D`, `P3D`, `P1W`) with a seconds accessor. |
| `Rates\RateUpdateState` | — | Sole sanitizer/defaults owner for `umc_rate_state`, plus the TTL-bounded update lock. |
| `Rates\ExchangeRateStore` | `Settings`, `RateUpdateState` | **The persistence boundary.** The only class that writes provider rates into `umc_settings` or writes `umc_rate_state`. Applies a `RateFetchResult`, exposes the automatic-currency set, operational status per currency, and lock acquire/release. |
| `Rates\RateUpdateService` | `ExchangeRateSource`, `ExchangeRateStore` | Orchestration only: acquire the lock, resolve targets, fetch **once**, hand the result to the store, fire `umc_rate_fetch_completed`, release the lock in `finally`. Holds no persistence logic. |
| `Rates\Scheduler` | `ExchangeRateStore`, `RateUpdateService` | Action Scheduler integration on hook `umc_run_rate_update` (ADR-0011). Reconciles the pending recurrence against `rate_update_interval` on `init` and on `umc_settings_saved`; unschedules entirely when automatic mode is off. |
| `Rates\RateStatusEvaluator` | `Settings`, `ExchangeRateStore` | Pure status derivation (`ok`, `stale`, `failed`, `never`) for admin badges and Site Health. |
| `Admin\RateUpdateController` | `RateUpdateService` | `admin_post_umc_update_rates`: capability + nonce, then one synchronous update, then a redirect carrying a flash notice. |

### Write paths

```
Scheduler::run()  ─┐
                   ├─> RateUpdateService::update()
RateUpdateController::handle() ─┘        │
                                         ├─ ExchangeRateStore::try_acquire_lock()   → umc_rate_state
                                         ├─ ExchangeRateSource::fetch()             → no writes
                                         ├─ ExchangeRateStore::apply_fetch_result()
                                         │     ├─ success/partial → umc_settings (provider_rate, rate_updated_at)
                                         │     │                  + umc_rate_state
                                         │     ├─ not_modified    → umc_rate_state ONLY
                                         │     └─ total failure   → umc_rate_state ONLY
                                         └─ ExchangeRateStore::release_lock()       → umc_rate_state
```

Money-bearing settings are written **before** operational state, so a failure
while recording operational facts can never lose a rate the provider returned
(`ExchangeRateStoreTest`).

### Conditional HTTP

`ExchangeRateStore` hands the previous batch's `ProviderMetadata` back to the
provider, which sends `If-None-Match` / `If-Modified-Since`. A **304** means
nothing money-bearing changed, so the 304 path writes operational state only and
performs zero `umc_settings` writes — enforced by
`CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES` (see
[`PERFORMANCE_BASELINES.md`](PERFORMANCE_BASELINES.md)). A provider that ignores
conditional headers simply never returns 304; there is no new failure mode
(ADR-0013).

### Invariants

1. **`ExchangeRateStore` is the only writer** of `umc_rate_state` and the only
   writer of provider rates into `umc_settings`. Providers, the service, the
   scheduler, and the admin controller never call `Settings::save()`.
2. **Effective rates are never persisted.** Only `manual_rate`, `provider_rate`,
   and `merchant_adjustment` are stored; `RateResolver` derives the rest.
3. **One provider call per update.** The lock is TTL-bounded and released in a
   `finally`, so a fatal fetch cannot strand it.
4. **A failed or unchanged fetch never destroys a known-good rate.** Last-known
   values stay in `umc_settings`; only status and failure counters move.
5. **Diagnostics read last-known state.** `umc_rate_health` and the debug
   counters issue no HTTP request and expose counts only — never a rate value,
   provider URL, cache validator, or provider error string.
6. **The storefront money path is unaware of providers.** Conversion reads
   `Settings`; no storefront request fetches, schedules, or writes rates.

Plugin version is **0.8.0**. Milestone 8 shipped and its post-release review is
closed; see [`RELEASE_AUDIT.md`](RELEASE_AUDIT.md) and [`ROADMAP.md`](ROADMAP.md).
