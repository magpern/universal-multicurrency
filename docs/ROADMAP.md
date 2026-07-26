# Roadmap

0.  Tooling & bootstrap
1.  Domain model
2.  Storefront conversion
3.  Cart & checkout — **classic** cart/coupons/core-shipping/taxes/gateways +
    immutable order snapshot at creation. Fees not converted (opt-in only).
4.  Orders & refunds (**v0.4.0**) — historical order currency context (display,
    emails, admin/account rendering, order-pay, refunds); immutable formatting;
    legacy order viewing.
5.  Cart & Checkout **Blocks** (**v0.5.0**) — Store API parity: converted
    prices, coupons, core shipping, taxes and gateway availability in block
    carts and checkouts; snapshots on block orders; stored orders reported in
    their own currency. Server-side only — switching reloads the page.
6.  Compatibility — incl. admin conflict warning when another known
    multicurrency plugin (FOX/WOOCS, etc.) is active; never auto-deactivate.
    Also the minimum-supported-version matrix (PHP 8.1 / WP 6.5 / WC 8.2).
7.  Release candidate

Future: Auto rates, GeoIP, reporting, APIs.

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
