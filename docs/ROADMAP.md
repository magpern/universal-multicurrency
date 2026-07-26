# Roadmap

0.  Tooling & bootstrap
1.  Domain model
2.  Storefront conversion
3.  Cart & checkout — **classic** cart/coupons/core-shipping/taxes/gateways +
    immutable order snapshot at creation. Fees not converted (opt-in only).
4.  Orders & refunds (**v0.4.0**) — historical order currency context (display,
    emails, admin/account rendering, order-pay, refunds); immutable formatting;
    legacy order viewing.
5.  Compatibility — incl. admin conflict warning when another known
    multicurrency plugin (FOX/WOOCS, etc.) is active; never auto-deactivate.
6.  Release candidate

Cart & Checkout **Blocks** (Store API) is a dedicated milestone after Orders &
refunds; until then classic checkout is the only supported path.

Future: Auto rates, GeoIP, reporting, APIs.

The plugin is standalone and replaces FOX/WOOCS and the old MP helper; only
WooCommerce is a dependency (see docs/adr/0003).
