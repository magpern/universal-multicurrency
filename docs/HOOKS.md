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

- the request is convertible — front-end page views, front-end AJAX and the
  **Store API** (which backs the Cart and Checkout blocks); not admin screens,
  other REST namespaces, cron or WP-CLI (`CurrencyContext::is_convertible_request()`,
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
| `woocommerce_init` | — | Builds the request graph, runs the switch handler, registers conversion filters, and wires the Display switcher stack once the WC session is available. |
| `woocommerce_get_settings_pages` | `($pages)` | Adds the "Multicurrency" settings tab (`Admin\SettingsPage`), instantiated only when WooCommerce builds its settings. |
| `woocommerce_settings_tabs_array`, `woocommerce_settings_{id}`, `woocommerce_settings_save_{id}` | — | Wired by the `WC_Settings_Page` base class for tab registration, output and save. |
| `woocommerce_admin_field_umc_currencies` | `($field)` | Renders the custom currencies table field. |
| `woocommerce_admin_field_umc_display` | `($field)` | Renders the Display settings cards and live preview shell (`Admin\DisplaySettingsField`). |

The switch itself is a `?currency=<code>` request handled by
`CurrencySwitcher` on `woocommerce_init`: it validates the code against the
selectable allow-list, persists to the WC session, optionally to a 30-day guest
cookie when remember-selection is enabled (otherwise any existing remembered
cookie is expired), and `wp_safe_redirect`s to the same URL without the parameter.

## Display M1 — storefront currency switcher

Registered on `woocommerce_init` alongside the conversion stack. Presentation
only — currency resolution, validation, and switching remain in
`CurrencyContext` / `CurrencySwitcher`.

| Hook | Args | Prio | Owner | Why |
|---|---|---|---|---|
| `add_shortcode('universal_multicurrency_switcher')` | — | — | `Display\SwitcherShortcode` | Primary manual switcher shortcode. |
| `add_shortcode('umc_switcher')` | — | — | `Display\SwitcherShortcode` | Legacy alias for the same renderer. |
| `wp_enqueue_scripts` | — | 5 | `Display\SwitcherAssets::register_assets()` | Registers switcher CSS/JS handles (does not enqueue globally). |
| `wp_enqueue_scripts` | — | 15 | `Display\SwitcherAssets::maybe_enqueue_from_post_content()` | Enqueues assets when post content contains a switcher shortcode. |
| `wp_enqueue_scripts` | — | 20 | `Display\SwitcherAssets::maybe_enqueue_automatic()` | Enqueues assets when automatic floating placement is enabled. |
| `language_attributes` | `($output)` | 10 | `Display\SwitcherAssets::append_no_js_class()` | Adds the `no-js` document class for progressive dropdown enhancement. |
| `wp_footer` | — | 20 | `Display\AutomaticSwitcherPlacement::maybe_render()` | Renders the automatic floating switcher once per request when enabled. |

Assets may also print a late `<link>` fallback when a shortcode renders after
styles were already printed. Guard tests allow `Display\SwitcherAssets` as the
sole storefront asset registrar besides admin assets.

## Milestone 3 — cart, checkout & order currency

Milestone 3 makes the whole classic transaction authoritative in the selected
currency. It reuses the M2 seam (`Integration\PriceConversionService`) as the
**single** product-price converter and adds conversion only for the monetary
inputs M2 never touched — fixed coupon amounts, coupon spend thresholds and core
shipping costs — plus gateway currency compatibility and the immutable order
snapshot. **Taxes are never converted**: WooCommerce computes them natively on
already-converted amounts. See `docs/architecture/transaction-flow.md` for the
end-to-end flow and the double-conversion proof, and ADR-0004 for the model.

All storefront hooks below register on `woocommerce_init` and gate on
`is_convertible_request() && ! is_base_active()` unless noted.

### Cart recalculation (`Cart\CartRecalculation`)

| Hook | Args | Prio | Why |
|---|---|---|---|
| `woocommerce_cart_loaded_from_session` | `($cart)` | 20 | Recompute totals when the cart's stored rate identity no longer matches the active one (currency switch, rate edit, stale/cross-tab session). Keyed on `code:rate`, so a changed rate also triggers it. |

### Coupons (`Integration\CouponConversion`)

| Hook | Args | Prio | Why |
|---|---|---|---|
| `woocommerce_coupon_get_amount` | `($amount, $coupon)` | 10 | Convert fixed cart / fixed product amounts base→active once. Percentage coupons are left untouched — they operate on already-converted totals. |
| `woocommerce_coupon_get_minimum_amount` | `($amount, $coupon)` | 10 | Convert the min-spend threshold so the comparison against the converted subtotal is apples-to-apples. |
| `woocommerce_coupon_get_maximum_amount` | `($amount, $coupon)` | 10 | Convert the max-spend threshold, as above. |

### Shipping — core methods only (`Integration\ShippingConversion`)

| Hook | Args | Prio | Why |
|---|---|---|---|
| `woocommerce_package_rates` | `($rates, $package)` | 90 | Convert cost + per-class taxes for **core** methods (`flat_rate`, `free_shipping`, `local_pickup`) base→active by the same rate; non-core / third-party rates pass through unchanged (assumed already in the transaction currency). |
| `woocommerce_cart_shipping_packages` | `($packages)` | 10 | Inject the rate identity into each package so WooCommerce's `shipping_for_package_*` cache is keyed per currency+rate and self-invalidates on a switch or rate edit. |
| `woocommerce_shipping_free_shipping_is_available` | `($available, $package, $method)` | 10 | Re-evaluate free-shipping eligibility using a **converted** `min_amount` (base→active) so the threshold is compared in the same currency as the cart. Request-scoped only — does not persist settings or permanently mutate `$method->min_amount`. |

### Fees — opt-in only (`Integration\FeeConversion`)

| Hook | Args | Prio | Why |
|---|---|---|---|
| `woocommerce_cart_calculate_fees` | `()` | 99 | After fees are added, convert only those that opt in via `umc_convert_fee`. Default pass-through. |

### Extension monetary boundaries (Milestone 18)

| Concern | Contract |
|---|---|
| Base currency | WooCommerce store currency; authored product/coupon/shipping amounts live here |
| Active shopper currency | Resolved for convertible requests; drives conversion seam |
| Effective checkout currency | M11 checkout policy may settle/fallback before order creation |
| Order-owned currency | Authoritative after order creation (order-pay, emails, account, admin historical) |
| Fixed monetary inputs | Convert once via `PriceConversionService` |
| Free-shipping `min_amount` | Base-authored; converted at eligibility evaluation (M18) |
| Fees | **Pass-through by default** — opt in per fee via `umc_convert_fee` (wired M19); amounts convert once when opted in |
| Third-party shipping | Pass-through unless `umc_convert_shipping_rate` opts in |

See [`docs/architecture/woocommerce-transaction-integrity.md`](architecture/woocommerce-transaction-integrity.md) and ADR-0023.

### Gateways (`Integration\GatewayCompatibility`)

| Hook | Args | Prio | Why |
|---|---|---|---|
| `woocommerce_available_payment_gateways` | `($gateways)` | 10 | Storefront callback: remove gateways that do not support the **session** currency (via the shared `filter_gateways_for_currency()` engine, given an explicit code); if none remain, add one explanatory checkout notice. Never rewrites a gateway's amount/currency. On the order-pay endpoint this callback is deregistered by `OrderPayCurrencyLock` (see M4). |

### Order snapshot (`Order\OrderSnapshot`)

| Hook | Args | Prio | Why |
|---|---|---|---|
| `woocommerce_checkout_create_order` | `($order, $data)` | 10 | Write the immutable `_umc_*` currency/rate snapshot via `WC_Order` CRUD (HPOS-safe), once. WooCommerce already stores the order currency and active-currency totals natively; this adds the audit trail. |

Metadata keys written (permanent order data; never removed on uninstall):
`_umc_base_currency`, `_umc_transaction_currency`, `_umc_exchange_rate`,
`_umc_rate_timestamp`, `_umc_rate_source`, `_umc_plugin_version`,
`_umc_rate_identity`. Milestone 4 adds two more at creation:
`_umc_snapshot_version` (`2`) and `_umc_transaction_decimals`. Refunds carry
`_umc_parent_transaction_currency` and `_umc_parent_rate_identity` (see M4).

## Milestone 4 — historical order behaviour & refunds

Milestone 4 ensures once an order exists, its stored WooCommerce order currency
and immutable `_umc_*` snapshot are authoritative for every later operation — the
order never changes appearance, totals, gateway currency, or formatting due to
session currency changes, rate edits, disabled currencies, or base-currency
changes. Historical services read stored values in the order currency, format
them correctly, and never reconvert.

### Order-scoped currency context and display (`Order\*`)

| Hook | Args | Prio | Owner | Activation | Teardown |
|---|---|---|---|---|---|
| `woocommerce_order_details_before_order_table` | `($order)` | 1 | `HistoricalOrderDisplay` | thank-you + view-order | paired `after` (FILO @999) |
| `woocommerce_order_details_after_order_table` | `($order)` | 999 | `HistoricalOrderDisplay` | thank-you + view-order | pops context |
| `woocommerce_email_before_order_table` | `($order,$admin,$plain,$email)` | 1 | `HistoricalOrderDisplay` | email render/preview | paired `after` (FILO @999) |
| `woocommerce_email_after_order_table` | `($order,$admin,$plain,$email)` | 999 | `HistoricalOrderDisplay` | email render/preview | pops context |
| `woocommerce_before_resend_order_emails` | `($order,$type)` | 1 | `HistoricalOrderDisplay` | admin email resend | paired `after` (FILO @999) |
| `woocommerce_after_resend_order_email` | `($order,$type)` | 999 | `HistoricalOrderDisplay` | admin email resend | pops context |
| `woocommerce_my_account_my_orders_column_order-total` | `($order)` | 10 | `HistoricalOrderDisplay` | My-Account list cell | owned `run()` via try/finally |
| `woocommerce_currency` | `($code)` | 20 | `OrderCurrencyFormatting` | context active | fires only under context |
| `woocommerce_currency_symbol` | `($symbol,$code)` | 20 | `OrderCurrencyFormatting` | context active | — |
| `wc_price_args` | `($args)` | 20 | `OrderCurrencyFormatting` | context active OR explicit `currency` arg | stateless; registered after M2 `CurrencyFormatting` (prio 10) so it overrides the session formatting while a context is on the stack |

While an order context is on the stack, `OrderCurrencyFormatting` (priority 20)
overrides the M2 `CurrencyFormatting` (default priority 10) result. M2 does not
inspect the order context; the two are mutually exclusive by construction (M2
only rewrites formatting on a convertible non-base storefront request).

### Order-pay currency lock (`Order\OrderPayCurrencyLock`)

| Hook | Args | Prio | Owner | Purpose |
|---|---|---|---|---|
| `template_redirect` | `()` | 10 | `OrderPayCurrencyLock` | Detect the `order-pay` / `pay_for_order` endpoint; load+verify order; enter the currency context for the request. |
| `woocommerce_available_payment_gateways` | `($gateways)` | 10 | `OrderPayCurrencyLock` | Filter gateways for the locked order currency (explicit currency, not session). |

On lock, `OrderPayCurrencyLock` **deregisters** the storefront `GatewayCompatibility`
callback (the shared instance) and registers its own at the vacated priority 10,
so the order-currency filter evaluates the **original** gateway set. Filtering is
deterministic and never depends on a later filter repairing an earlier
session-based result. Both endpoint forms are supported: the standard
`?order-pay=<id>&key=…` and the legacy `?pay_for_order=<id>&key=…`.

### Refund audit metadata (`Order\RefundSnapshot`)

| Hook | Args | Prio | Owner | Activation | Teardown |
|---|---|---|---|---|---|
| `woocommerce_create_refund` | `($refund,$args)` | 10 | `RefundSnapshot` | any refund creation | write-once audit meta; no save hook |

### Admin audit meta box (`Admin\OrderCurrencyMetaBox`)

| Hook | Args | Prio | Owner | Purpose |
|---|---|---|---|---|
| `add_meta_boxes_{wc_get_page_screen_id('shop-order')}` | `($post_or_order)` | 10 | `OrderCurrencyMetaBox` | Read-only audit box (HPOS). |
| `add_meta_boxes_shop_order` | `($post_or_order)` | 10 | `OrderCurrencyMetaBox` | Read-only audit box (legacy). |

## Deliberately NOT hooked (out of scope)

Fees are **pass-through by default** (M19 wires opt-in conversion via `FeeConversion`
and `umc_convert_fee`). Arbitrary fees remain untouched unless an integration
opts in. No stock hooks, ever. Order-status hooks carry no callback either.

Milestone 6 adds: no `deactivate_plugins` or related deactivation APIs; no
frontend conflict notice; no JavaScript or other frontend assets for diagnostics.

Display M1 registers conditional storefront CSS/JS for the currency switcher
only (`Display\SwitcherAssets`). The Cart and Checkout blocks are still served
entirely by server-side conversion; currency switching reloads the page via
`?currency=`, which makes block data refetch on its own.

Guard tests (`StorefrontGuardTest`, `StoreApiHooksStructureTest`,
`DiagnosticsGuardTest`) assert that no plugin-origin callbacks land on the fee,
stock or order-status hooks; that Store API registration stays inside
`src/StoreApi`; that only the seam uses the `Converter`; that only the snapshot
writers stage order metadata; that nothing stamps the order currency; that Store
API code raises no session notices; that only the Store API adapter saves an
order; that no frontend assets are registered; that no `$wpdb`/SQL is used; that
no broad exception is swallowed; and that Diagnostics stays inside
`src/Diagnostics/`, never reaches the money path or storefront, never
auto-deactivates plugins, and registers only the seven admin hooks listed in
the Milestone 6 section.

## Milestone 5 — Store API and Blocks

| Hook | Type | Owner | Why |
| --- | --- | --- | --- |
| `option_woocommerce_currency_pos` | filter (10) | `Integration\CurrencyFormatting` | The Store API derives `currency_prefix`/`currency_suffix` from a raw read of this option — the only part of the money identity WooCommerce does not already filter. |
| `option_woocommerce_currency_pos` | filter (20) | `Order\OrderCurrencyFormatting` | Same, for an order render, layered above the session formatter like the other four order filters. |
| `woocommerce_store_api_checkout_update_order_meta` | action (10) | `StoreApi\CheckoutSnapshotAdapter` | Store API checkout never fires `woocommerce_checkout_create_order`, so this is where a block order's snapshot is staged. WooCommerce saves afterwards. |
| `woocommerce_store_api_cart_update_order_from_request` | action (10) | `StoreApi\CheckoutSnapshotAdapter` | A draft order is re-synced from the cart on every mutating cart request, restamping its currency. Realigns the snapshot while the order is unpaid. Fires after WooCommerce's own save, so this callback saves. |
| `rest_request_before_callbacks` / `rest_request_after_callbacks` | filter (10) | `StoreApi\OrderCurrencyLock` | Brackets `/order/{id}` and `/checkout/{id}` so a stored order is reported in its own currency, and gateways are filtered by it rather than by the session. |
| `woocommerce_store_api_register_endpoint_data` | API call | `StoreApi\CartExtensionData` | Publishes currency state — active code, base code, selectable codes — under the `umc` namespace on the cart endpoint. No amounts and no exchange rate. |

## Milestone 6 — compatibility and diagnostics

Registered only when `is_admin()` and not during AJAX, cron, or WP-CLI.
Evaluation is lazy at `admin_notices` (or the first Site Health callback that
needs findings). No WooCommerce storefront, Store API, or REST hooks.

| Hook | Prio | Owner | Why |
|---|---|---|---|
| `admin_notices` | 10 | `Diagnostics\ConflictNotice::render()` | Dashboard conflict notice when findings match screen + confidence rules. |
| `network_admin_notices` | 10 | `Diagnostics\ConflictNotice::render_network()` | Multisite network-admin variant with network-administrator guidance. |
| `deactivated_plugin` | 10 | `Diagnostics\ConflictNotice::suppress()` | Suppresses one residual notice on the deactivation confirmation screen (PHP cannot undeclare classes until the next request). |
| `admin_init` | 10 | `Diagnostics\NoticeDismissal::maybe_dismiss()` | Nonce'd GET dismiss handler; redirects with query args stripped on success. |
| `site_status_tests` | 10 | `Diagnostics\SiteHealthReport::tests()` | Registers direct tests for conflicts and environment (gated on `activate_plugins`). |
| `debug_information` | 10 | `Diagnostics\SiteHealthReport::debug()` | Adds the `universal-multicurrency` debug section (gated on `activate_plugins`). |
| `woocommerce_admin_field_umc_conflict` | 10 | `Diagnostics\ConflictNotice::render_settings_field()` | Long-form evidence list on the Multicurrency settings tab; never dismissible. |

## Milestone 8 — automatic exchange rates

Rate services are wired at the composition root for every request type; only the
admin surfaces are gated on `is_admin()`. Nothing here touches the storefront
money path — conversion still reads `umc_settings` through `RateResolver`.

### Scheduling (`Rates\Scheduler`)

| Hook | Args | Prio | Owner | Why |
|---|---|---|---|---|
| `umc_run_rate_update` | — | 10 | `Scheduler::run()` | Action Scheduler callback for one recurring update. This is the plugin's own scheduled hook (`Scheduler::HOOK`); Action Scheduler owns the queue, WP-Cron is not used. |
| `init` | — | 20 | `Scheduler::ensure_scheduled_on_init()` | Reconciles the recurring schedule with `rate_update_interval`, unless `umc_settings_saved` already ran this request. |
| `umc_settings_saved` | — | 10 | `Scheduler::ensure_scheduled()` | Reschedules immediately when the merchant changes the interval or leaves automatic mode. |

`ensure_scheduled()` compares the pending action's `get_recurrence()` against the
configured interval and only unschedules/reschedules on a mismatch, on duplicate
pending actions, or when automatic mode is off. Covered by
`tests/integration/Rates/SchedulerIntegrationTest.php`.

### Manual update request (`Admin\RateUpdateController`)

| Hook | Args | Prio | Owner | Why |
|---|---|---|---|---|
| `admin_post_umc_update_rates` | — | 10 | `RateUpdateController::handle()` | Synchronous merchant-triggered refresh. Requires `manage_woocommerce` and `check_admin_referer( 'umc_update_rates' )`; answers with `wp_safe_redirect()` back to the settings tab carrying `umc_msg` / `umc_typ`. Registered only when `is_admin()`. |

Links are rendered by `Admin\ExchangeRateSettingsField` (`scope=all`) and
`Admin\CurrencyTableField` (`scope=single&code=XXX`), both nonced with
`umc_update_rates`. Covered by
`tests/integration/Rates/RateUpdateControllerIntegrationTest.php`.

### Admin surfaces (`Admin\*`)

| Hook | Args | Prio | Owner | Why |
|---|---|---|---|---|
| `woocommerce_admin_field_umc_exchange_rates` | `($field)` | 10 | `Admin\ExchangeRateSettingsField::render()` | Global rate mode, provider, interval, max age, and the "update now" control. |
| `admin_notices` | — | 10 | `Admin\SettingsPage::maybe_render_notice()` | Renders the flash notice carried back by the controller redirect. |
| `admin_notices` | — | 10 | `Admin\RateFailureNotice::render()` | Warns when an automatic currency has a recorded fetch failure. Registered only when `is_admin()`. |

### Site Health (`Diagnostics\SiteHealthReport`)

| Hook | Args | Prio | Owner | Why |
|---|---|---|---|---|
| `site_status_tests` | `($tests)` | 10 | `SiteHealthReport::tests()` | Adds the `umc_rate_health` and `umc_geo_configuration` direct tests alongside the M6 conflict and environment tests (gated on `activate_plugins`). |
| `debug_information` | `($info)` | 10 | `SiteHealthReport::debug()` | Adds `stale_automatic_rates` and `oldest_automatic_rate_age` to the existing debug section. Counters only — never a rate value, provider URL, or error string. |

`umc_rate_health` reports **last-known operational state only**; it never issues
an HTTP request. Severity: `critical` when automatic mode is enabled with no
recurring action scheduled, when any automatic currency has a recorded failure,
or when three or more are stale; `recommended` for one or two stale currencies;
otherwise `good`. Covered by
`tests/integration/Diagnostics/SiteHealthRateIntegrationTest.php`.

## Filters and actions the plugin provides

| Filter / Action | Args | Since | Purpose |
|---|---|---|---|
| `umc_is_request_convertible` (filter) | `($convertible)` | 0.2.0 | Override whether the current request converts prices. |
| `umc_currency_signature` (filter) | `($signature, $code, $rate)` | 0.3.0 | Override the rate identity (`code:rate`) used for cache isolation. |
| `umc_coupon_amount_is_base` (filter) | `($is_base, $coupon)` | 0.3.0 | Return false to declare a coupon already priced in the active currency (skips conversion). |
| `umc_convert_shipping_rate` (filter) | `($convert, $rate, $package)` | 0.3.0 | Override per rate whether to convert; defaults true for core methods, false otherwise. |
| `umc_convert_fee` (filter) | `($should, $fee)` | 0.3.0 | Opt-in fee conversion. Default false — only base-authored fees that return true are converted once (M19 `FeeConversion`). |
| `umc_should_convert_product_price` (filter) | `($should)` | 0.18.0 | Extension adapters may return false during subscription renewal context to prevent browsing currency from altering renewal-owned amounts. |
| `umc_use_fixed_product_price` (filter) | `($use_fixed, $product)` | 0.19.0 | Return false to force FX conversion even when fixed prices exist for the active currency. |
| `umc_fixed_prices_saved` (action) | `($product_id, $document)` | 0.19.0 | Fires after fixed prices are saved — from the product editor, the Fixed Pricing admin screen, or `wp umc prices seed\|clear` (0.23.0). |
| `umc_convert_product_addon_price` (filter) | `($convert, $price)` | 0.18.0 | Opt-in for Product Add-Ons flat/quantity raw prices. Default true when adapter active. |
| `umc_convert_bundled_item_price` (filter) | `($convert, $price)` | 0.18.0 | Opt-in for Product Bundles bundled item prices. Default true when adapter active. |
| `umc_extension_compatibilities` (filter) | `($definitions)` | 0.18.0 | Append third-party extension compatibility definitions to the M19 registry. |
| `umc_gateway_supported_currencies` (filter) | `($codes, $gateway)` | 0.3.0 | Declare a gateway's supported currencies; null = all. |
| `umc_order_snapshot_meta` (filter) | `($meta, $order, $context)` | 0.3.0 | Filter the order snapshot metadata before it is written. |
| `umc_cart_recalculated` (action) | `($current, $previous)` | 0.3.0 | Fires after the cart is recalculated for a new rate identity. |
| `umc_gateway_hidden` (action) | `($id, $active)` | 0.3.0 | Fires when a gateway is hidden for currency incompatibility. |
| `umc_order_snapshot_created` (action) | `($order, $meta)` | 0.3.0 | Fires after the order snapshot is staged on the order. |
| `umc_order_currency_context_entered` (action) | `($order)` | 0.4.0 | Fires when an order currency context is entered (historical render / order-pay). |
| `umc_order_currency_context_exited` (action) | `()` | 0.4.0 | Fires when an order currency context is exited. |
| `umc_order_pay_locked_currency` (action) | `($currency, $order)` | 0.4.0 | Fires when the order-pay endpoint locks a specific order's currency. |
| `umc_refund_snapshot_created` (action) | `($refund, $meta, $snapshot)` | 0.4.0 | Fires after refund audit metadata is staged on a refund. |
| `umc_order_audit_view_model` (filter) | `($view, $snapshot, $order)` | 0.4.0 | Filter the order currency audit meta-box view model. |
| `umc_order_snapshot_refreshed` (action) | `($order, $previous, $meta)` | 0.5.0 | Fires when an unpaid Store API draft's snapshot is rewritten for a new currency or rate. |
| `umc_conflict_detectors` (filter) | `($detectors)` | 0.6.0 | Append runtime detector rows (data-only arrays); output passes through `DetectorRegistry::sanitize()`. Built-ins come from `DetectorManifest`. |
| `umc_conflict_notice_view_model` (filter) | `($view, $findings, $screen_id, $fingerprint)` | 0.6.0 | Filter the dashboard/network admin notice view model before render. |
| `umc_conflict_settings_view_model` (filter) | `($view, $findings, $can_activate_plugins)` | 0.6.0 | Filter the Multicurrency settings-tab conflict panel view model before render. |
| `umc_settings_saved` (action) | `()` | 0.8.0 | Fires after `Admin\SettingsPage` persists the Multicurrency tab. `Scheduler` listens to reconcile the recurring update. |
| `umc_exchange_rate_sources` (filter) | `(ExchangeRateSource[] $sources)` | 0.8.0 | Register additional `UMC\Rates\ExchangeRateSource` implementations. `Plugin::resolve_rate_source()` selects the one whose `id()` matches `rate_provider`, falling back to Frankfurter. |
| `umc_rate_fetch_completed` (action) | `(RateFetchResult $result)` | 0.8.0 | Fires after every fetch attempt — success, partial failure, total failure, and `not_modified` — once the store has persisted the outcome. |
| `umc_geo_country_resolved` (action) | `(CountryContext $context)` | 0.11.0 | Fires after country context is resolved for geo routing. |
| `umc_geo_currency_decided` (action) | `($currency, GeoRuleEvaluationResult $result, CountryContext $country)` | 0.11.0 | Fires after geo routing selects a currency on the storefront. |

### Test-only extension seams (E2 evidence)

These hooks are fired only by UMC-owned test-double plugins or integration tests.
They must not be used to claim real-plugin compatibility.

| Hook | Args | Since | Role |
|---|---|---|---|
| `umc_test_extension_subscriptions_renewal_start` (action) | `($currency_or_subscription)` | 0.18.0 | Simulates subscription renewal context entry for E2 tests. |
| `umc_test_extension_subscriptions_renewal_end` (action) | `()` | 0.18.0 | Simulates subscription renewal context exit for E2 tests. |
| `umc_test_extension_product_addons_price_raw` (filter) | `($price)` | 0.18.0 | Simulates Product Add-Ons raw price seam for E2 tests. |
| `umc_test_extension_bundled_item_price` (filter) | `($price)` | 0.18.0 | Simulates Product Bundles item price seam for E2 tests. |

`umc_convert_fee` is wired in Milestone 19 via `Integration\FeeConversion` — default
pass-through; integrations opt in per fee.

Every entry in this table is verified against `src/` by
`tests/unit/HooksDocumentationSyncTest.php`: each documented `umc_*` hook must
exist in production code, and every `umc_*` hook fired or filtered in `src/`
must appear here.
