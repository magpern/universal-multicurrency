# ADR-0006: Store API and Blocks parity

**Status:** Accepted (v0.5.0)

## Context

WooCommerce ships two checkout experiences. The classic cart and checkout render
server-side templates; the Cart and Checkout blocks render in the browser and get
everything through the Store API. M2 to M4 made the plugin authoritative for the
classic path only, and `CurrencyContext::is_convertible_request()` treated every
REST request as non-convertible — the Store API included.

That left a plugin whose bootstrap declared `cart_checkout_blocks` compatibility
while behaving as if the blocks did not exist. A shopper who selected SEK saw
base-currency prices in a block cart, and an order placed through the Checkout
block carried no `_umc_*` snapshot at all, so it was classified as legacy: the
treatment reserved for orders that predate the plugin.

The Store API is also a natural place to get multicurrency wrong. It has its own
schemas, its own money encoding, and its own order-creation path that never
touches `WC_Checkout`. Reimplementing conversion at that layer would have been
the obvious route and the wrong one.

## Decisions

### 1. One conversion engine

Store API support introduces no new conversion. All arithmetic stays in
`Converter`, reachable only through `Integration\PriceConversionService`.

This is possible because WooCommerce's own schemas read prices the same way its
templates do: cart item and product prices reach the response through
`wc_get_price_including_tax()` / `wc_get_price_excluding_tax()`, which read
`$product->get_price()` in `view` context. The existing product price filters
therefore apply to Store API responses unchanged.

*Consequences.* Most of this milestone is test code. Opening the gate made
prices, coupons, core shipping, cart recalculation and gateway filtering work
over the Store API with no new production code, and a guard confines Store API
registration to `src/StoreApi`.

*Alternatives considered.* Overriding money fields through `ExtendSchema`. This
would have duplicated every amount and re-implemented rounding at the transport
layer, giving two formulas to keep in agreement.

### 2. The Store API is an adapter, not a domain layer

`src/StoreApi` decides *when* domain services run and nothing else. It contains
no formulas, no formatting rules, and no metadata definitions.

*Consequences.* Adapters stay small and replaceable, and the classic flow is
untouched by them. The one place this shows is the checkout snapshot: the adapter
supplies timing and a refresh policy, while `Order\OrderSnapshot` remains the
sole writer for both flows.

*Alternatives considered.* A flow-neutral checkout service both paths delegate
to. WooCommerce owns those two flows and structures them differently; an
abstraction over them would have been speculation.

### 3. Server-side authority

No JavaScript ships. Monetary values are computed and formatted on the server,
and the response is the single source of truth.

*Consequences.* No build toolchain, no Node in CI, and no second place where an
amount could be formatted or, worse, recalculated. The cost is that switching
currency reloads the page rather than updating in place.

*Alternatives considered.* Client-side display overrides through
`registerCheckoutFilters`. Rejected: a second presentation authority, and it
would have made the browser a participant in monetary correctness.

### 4. Currency-state transport

The resolution contract from M2 is unchanged — explicit `?currency=`, then the
WooCommerce session, then the cookie, then base — and applies to Store API
requests as-is. REST requests never *write* that state.

*Consequences.* Cart routes inherit the session currency, including for clients
using a Cart-Token. `/products`, which WooCommerce serves without a session,
resolves from the cookie or an explicit argument. A `?currency=` argument on a
REST request affects that one response and persists nothing, which gives headless
clients per-request selection for free. Answering an API call with a redirect, as
the storefront switcher does, would have corrupted the response.

*Alternatives considered.* Carrying currency inside the Cart-Token or an
extension payload. Rejected: a second state channel to keep consistent with the
first.

### 5. Snapshot lifecycle

The snapshot is written when the Store API materialises its checkout order, and
may be rewritten only while that order is unpaid.

Store API checkout reuses a draft order across payment retries, and every
mutating cart request re-syncs it from the cart — restamping its currency and
totals. Without a refresh, a shopper who changed currency mid-retry would leave a
persisted order whose snapshot described a currency the order no longer had.

*Consequences.* A persisted order never contradicts its own snapshot. Refreshing
requires `created_via` of `store-api`, a status of `checkout-draft`, `pending` or
`failed`, and no payment date; `on-hold` is excluded because the gateway has
acknowledged intent. From payment onward the snapshot is permanent, which is what
M4's historical display and refunds depend on. The refresh hook fires after
WooCommerce has already saved, so that one callback saves the order itself — the
sole exception to staging, pinned by a guard.

*Alternatives considered.* Strict write-once even for drafts, which permits a
persisted contradiction; and refreshing only at the next checkout POST, which
leaves the contradiction in the database for abandoned retries.

### 6. Cache strategy

Currency identity is the `code:rate` signature, and it already keys every cache
the plugin influences: the session cart marker, the shipping package hash, and
the variation-price transient. Store API support added no new cache and no new
key.

*Consequences.* A rate correction invalidates all three at once, so a cart, its
shipping rates and its product prices move together. Cart routes are sent with
`Cache-Control: no-store` by WooCommerce. `/products` is not, so its responses
vary by cookie without saying so — an HTTP-layer concern documented for
deployment rather than something PHP can fix.

*Alternatives considered.* Bumping a global cache version on every switch.
Rejected as needlessly destructive across currencies.

### 7. Classic and Blocks parity, and where extensions belong

Parity is asserted, not assumed: one scenario runs through both flows and the
totals, gateway availability and snapshot metadata must match.

Third-party extensions integrate through the published `umc_*` filters, never by
adding conversion points. Amounts an extension authors in base currency flow
through the single product-price conversion wherever WooCommerce exposes them as
prices; anything else — subscriptions, deposits, bookings, memberships, vendor
payouts — needs its own adapter and its own ADR.

*Consequences.* Behaviour for unsupported extensions is defined rather than
undefined: their amounts are left as authored. Third-party shipping methods are
not converted unless a host opts in per rate, because the plugin cannot know
which currency a method was priced in.

*Alternatives considered.* Best-effort conversion of unrecognised amounts.
Rejected: it cannot be reconciled with proving that nothing converts twice.
