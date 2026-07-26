# Store API request lifecycle

Reference notes for the Cart & Checkout Blocks milestone. Every claim here was
verified against WooCommerce source; file references use WooCommerce 10.9.4,
and the hook surface was diffed against 11.0.0-beta.2 with no differences in
the areas below.

## Why the Store API is not a "frontend" request

`WC::is_request( 'frontend' )` excludes REST requests, and WooCommerce only
calls `wc_load_cart()` for frontend requests. On a `/wc/store/v1/*` request
`WC()->session` and `WC()->cart` are therefore **null** during `init` and
`wp_loaded`.

Cart-bearing routes initialise the session themselves, inside the route:

```
AbstractCartRoute::get_response()      Routes/V1/AbstractCartRoute.php:113
  → load_cart_session()                Routes/V1/AbstractCartRoute.php:171
    → CartController::load_cart()      Utilities/CartController.php:30
      → wc_load_cart()                 initialises session + cart
      → $cart->get_cart()              fires woocommerce_load_cart_from_session
                                       then woocommerce_cart_loaded_from_session
```

Consequences for this plugin:

- `Cart\CartRecalculation` (on `woocommerce_cart_loaded_from_session`) **does**
  run for cart routes, once the conversion gate allows it.
- Routes extending `AbstractRoute` — notably `/products` — load **no session at
  all**. Currency there resolves from the explicit query argument or the cookie;
  `CurrencyContext::read_session()` already degrades to `null` without a
  session, so no special handling is required.

## Request identity

WooCommerce offers three ways to recognise its own API requests, and they are not
equally precise.

| Predicate | Source | Match |
| --- | --- | --- |
| `WC::is_rest_api_request()` | `includes/class-woocommerce.php:607` | the REST prefix **anywhere** in `$_SERVER['REQUEST_URI']` |
| `WC::is_store_api_request()` | `includes/class-woocommerce.php:628` | `<rest-prefix>/wc/store/` **anywhere** in the request URI |
| `Authentication::is_request_to_store_api()` | `src/StoreApi/Authentication.php` | `/wc/store/` **anchored** at the start of `$GLOBALS['wp']->query_vars['rest_route']` |

The first two are substring tests, so a query argument that happens to contain a
Store API path — a redirect target, a search term — matches. That is harmless for
the decisions WooCommerce makes with them, but it is not harmless for a
conversion boundary: it would let an admin REST call be read as a Store API
request and return converted prices.

`CurrencyContext::is_store_api_request()` therefore uses the third form, the
anchored parsed route, and falls back to the URI test only when no parsed route
exists. The anchored form also removes the need to feature-detect
`WC::is_store_api_request()`, which **does not exist in WooCommerce 8.2**, this
plugin's minimum supported version.

`rest_do_request()` sets neither a request URI nor a parsed route. Integration
tests must simulate them or they exercise the storefront path while appearing to
test the Store API; the shared harness
(`tests/integration/StoreApi/StoreApiTestCase.php`) owns that simulation and
exposes both forms.

## Money serialization

All Store API money passes through two schema helpers:

| Helper | Source | Behaviour |
| --- | --- | --- |
| `AbstractSchema::prepare_money_response()` | `Schemas/V1/AbstractSchema.php:397` | float → minor-unit integer string via `MoneyFormatter` |
| `AbstractSchema::prepare_currency_response()` | `Schemas/V1/AbstractSchema.php:383` | merges the `currency_*` identity via `CurrencyFormatter` |

`CurrencyFormatter::format()` (`Formatters/CurrencyFormatter.php:17-50`) builds
the identity from filtered core functions — `get_woocommerce_currency()`,
`get_woocommerce_currency_symbol()`, `wc_get_price_decimals()`,
`wc_get_price_decimal_separator()`, `wc_get_price_thousand_separator()` — with
one exception: the symbol position comes from a **raw**
`get_option( 'woocommerce_currency_pos' )` read at line 18. That option is the
only leg of the money identity not covered by an existing WooCommerce filter,
which is why this plugin filters `option_woocommerce_currency_pos`.

Cart item and product prices reach the schemas through
`wc_get_price_including_tax()` / `wc_get_price_excluding_tax()`, which read
`$product->get_price()` in `view` context — so the plugin's existing product
price filters apply to Store API responses unchanged.

## Checkout and order creation

The Store API does **not** use `WC_Checkout`; `woocommerce_checkout_create_order`
never fires for it. Orders are built by `Utilities/OrderController.php:42`
(`create_order_from_cart()`), which sets status `checkout-draft`, `created_via`
`store-api`, and stamps the currency from `get_woocommerce_currency()` at
`Utilities/OrderController.php:114` — a filtered value, so the order carries the
transaction currency once the gate is open.

Hooks used by this milestone:

| Hook | Source | `@since` | Save semantics |
| --- | --- | --- | --- |
| `woocommerce_store_api_checkout_update_order_meta` | `Routes/V1/Checkout.php:834` | 7.2.0 | fires inside `create_or_update_draft_order()`; WooCommerce saves the order afterwards (`update_status( 'pending' )`, plus the optimistic save in `Routes/V1/Checkout.php:511` on failure). Callbacks must **stage** meta, not save. |
| `woocommerce_store_api_cart_update_order_from_request` | `Routes/V1/AbstractCartRoute.php:262` | 7.2.0 | fires in `cart_updated()` **after** `update_order_from_cart()` has already saved the draft. Callbacks that change meta here must save the order themselves. |

Both are comfortably below the WooCommerce 8.2 floor, so no feature detection is
needed for either.

Other lifecycle hooks, recorded for completeness: `..._checkout_order_created`
(`Checkout.php:791`, `@since 10.8.0`, fires once when the draft is first
materialised), `..._checkout_order_processed` (`Checkout.php:675` and
`CheckoutOrder.php:158`, `@since 7.2.0`, after stock reservation and before
payment), `..._checkout_update_order_from_request`
(`Utilities/CheckoutTrait.php:247`).

The draft order is reused across payment retries. `cart_updated()` re-runs
`update_order_from_cart()` on every mutating cart request while a valid draft
exists, which re-stamps the order currency and totals — the reason the snapshot
adapter refreshes unpaid drafts rather than treating the first write as final.

## Payment methods

`CartSchema.php:386` serializes payment methods as a plain pluck over
`WC()->payment_gateways->get_available_payment_gateways()`, so the core
`woocommerce_available_payment_gateways` filter fully governs what the Cart and
Checkout blocks offer. No Store API specific gateway hook exists or is needed.

## Order routes

`GET /wc/store/v1/order/<id>` (`Routes/V1/Order.php:62`) and
`POST /wc/store/v1/checkout/<id>` (`Routes/V1/CheckoutOrder.php:52`) serialize
stored order amounts, but their currency identity still comes from
`CurrencyFormatter` — that is, from the **session** currency, not the order's.
Order-scope formatting has to be established for these routes explicitly; the
classic equivalent (`Order\OrderPayCurrencyLock`) hooks `template_redirect`,
which never fires for REST.

## Caching

Cart routes send `Cache-Control: no-store` (`AbstractCartRoute.php:158`).
`/products` sends `Last-Modified` and no `no-store`, so its responses are
cacheable by intermediaries even though the body varies with the selected
currency — an HTTP-layer concern documented in `docs/DEPLOYMENT.md` rather than
something the plugin can fix in PHP.
