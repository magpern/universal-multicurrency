# WooCommerce hooks used

Every WooCommerce hook this plugin registers, with its signature and rationale.
Kept in sync as milestones add hooks. All hooks were verified against the
WooCommerce source the plugin targets (10.9.x).

The plugin also declares two feature compatibilities on `before_woocommerce_init`
(`custom_order_tables`, `cart_checkout_blocks`) — those are declarations, not
behaviour hooks, and are covered in the bootstrap file.

## Gating

Every storefront filter below is registered unconditionally on `woocommerce_init`
(so the variation-price cache hash, which embeds attached-callback signatures,
stays stable across request types) and each callback returns the value unchanged
unless **both**:

- the request is convertible — front-end page views and front-end AJAX only; not
  admin screens, REST, Store API, cron or WP-CLI (`CurrencyContext::is_convertible_request()`,
  filterable via `umc_is_request_convertible`), and
- the active currency is **not** the base currency.

## Milestone 2 — storefront price conversion

### Price amounts (`Integration\PriceHooks`)

| Hook | Args | Why |
|---|---|---|
| `woocommerce_product_get_price` | `($value, $product)` | View-context displayed price for simple/standard products. |
| `woocommerce_product_get_regular_price` | `($value, $product)` | Regular price; converted with the same rate so the sale comparison holds. |
| `woocommerce_product_get_sale_price` | `($value, $product)` | Sale price; an empty string is passed through untouched (never coerced to 0). |
| `woocommerce_product_variation_get_price` | `($value, $variation)` | Single-variation display (variations use the `woocommerce_product_variation_get_` prefix). |
| `woocommerce_product_variation_get_regular_price` | `($value, $variation)` | As above. |
| `woocommerce_product_variation_get_sale_price` | `($value, $variation)` | As above. |
| `woocommerce_variation_prices_price` | `($price, $variation, $product)` | Bulk path behind variable-product price ranges, sorting, add-to-cart form. |
| `woocommerce_variation_prices_regular_price` | `($price, $variation, $product)` | As above. |
| `woocommerce_variation_prices_sale_price` | `($price, $variation, $product)` | As above. |
| `woocommerce_get_variation_prices_hash` | `($hash, $product, $for_display)` | Appends `[active_code, rate]` so the `wc_var_prices_{id}` transient never crosses currencies and self-invalidates when a rate changes. **Critical for correctness.** |

All amounts are routed through `Integration\PriceConversionService`, which owns
the empty/non-numeric passthrough and the base no-op, then applies the rate via
`Converter::apply_rate()`. There is no broad exception handling: the service is
exception-safe on valid display input, and programming errors must surface.

### Currency identity & formatting (`Integration\CurrencyFormatting`)

| Hook | Args | Why |
|---|---|---|
| `woocommerce_currency` | `($code)` | Reports the active currency code so `get_woocommerce_currency()` (and everything downstream) follows the selection. |
| `woocommerce_currency_symbol` | `($symbol, $code)` | Returns the active currency's configured custom symbol, when set (only for the active code). |
| `wc_get_price_decimals` | `($decimals)` | Per-currency decimals (e.g. 0 for JPY); drives `wc_price()` precision. |
| `wc_price_args` | `($args)` | Sets `price_format` to the active currency's symbol position. |

Per-currency thousand/decimal **separators** are intentionally not filtered: they
are not part of the Milestone 2 settings schema, so WooCommerce's store defaults
apply.

### Presentation & lifecycle

| Hook | Args | Why |
|---|---|---|
| `woocommerce_init` | — | Builds the request graph, runs the switch handler, and registers the filters above once the WC session is available. |
| `woocommerce_get_settings_pages` | `($pages)` | Adds the "Multicurrency" settings tab (`Admin\SettingsPage`), instantiated only when WooCommerce builds its settings. |
| `woocommerce_settings_tabs_array`, `woocommerce_settings_{id}`, `woocommerce_settings_save_{id}` | — | Wired by the `WC_Settings_Page` base class for tab registration, output and save. |
| `woocommerce_admin_field_umc_currencies` | `($field)` | Renders the custom currencies table field. |
| `add_shortcode('umc_switcher')` | — | The currency switcher (`Frontend\Switcher::render()`), reusable by future block/Elementor wrappers. |

The switch itself is a `?currency=<code>` request handled by
`CurrencySwitcher` on `woocommerce_init`: it validates the code against the
selectable allow-list, persists to the WC session + a 30-day cookie
(`wc_setcookie`), and `wp_safe_redirect`s to the same URL without the parameter.

## Deliberately NOT hooked (out of scope through Milestone 2)

No cart totals, coupon, shipping, tax, fee, checkout, order, refund, gateway,
stock, or Store API/Blocks hooks. Because catalog conversion uses WooCommerce's
own view-context price getters, the cart naturally reflects converted unit
prices; however cart-level rounding, coupons, shipping, taxes and order
persistence are addressed in Milestone 3 and must not be considered final in
Milestone 2. A guard test (`StorefrontGuardTest`) asserts no plugin-origin
callbacks land on those hooks.

## Filters the plugin provides

| Filter | Args | Purpose |
|---|---|---|
| `umc_is_request_convertible` | `($convertible)` | Override whether the current request converts prices. |
