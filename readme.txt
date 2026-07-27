=== Universal Multicurrency for WooCommerce ===
Contributors: magpern
Tags: woocommerce, currency, multicurrency, exchange rates, money
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unlimited currencies with manual or automatic exchange rates (Frankfurter). WooCommerce owns products and inventory; currency affects monetary values only.

== Description ==

Universal Multicurrency for WooCommerce lets merchants offer unlimited currencies using manually entered exchange rates or automatic Frankfurter-backed updates. Product and inventory data stay in the store base currency; prices convert at runtime on the storefront, cart, checkout, and WooCommerce Store API surfaces.

* Classic cart and checkout, Cart Block, and Checkout Block
* High-Performance Order Storage (HPOS) required and declared compatible
* Permanent per-order exchange-rate snapshots (`_umc_*` metadata)
* Historical orders and refunds display in their stored currency
* Passive detection of conflicting currency switchers (observation only; never auto-deactivates another plugin)

**Migration from another currency switcher:** manual cut-over only. This plugin does not import settings from FOX/WOOCS, WPML Multicurrency, or other switchers. Configure currencies and rates manually in WooCommerce → Settings → Multicurrency.

**Uninstall:** removes plugin settings (`umc_settings`, `umc_rate_state`) only. Order and refund snapshot metadata is preserved permanently.

== Installation ==

1. Upload the plugin zip through **Plugins → Add New → Upload**, or install via your deployment process.
2. Activate **Universal Multicurrency for WooCommerce** after WooCommerce is active.
3. Configure currencies under **WooCommerce → Settings → Multicurrency**.

== Frequently Asked Questions ==

= Does this plugin import settings from WOOCS or FOX? =

No. Automatic import from other currency switchers is intentionally unsupported. Configure currencies and rates manually.

= Will uninstalling delete my order currency history? =

No. Order snapshot metadata (`_umc_*`) and refund audit metadata remain on orders after uninstall.

= Can I run this alongside another currency switcher? =

Not for production traffic. Two runtime converters can double-convert prices. Deactivate the other switcher before relying on Universal Multicurrency.

= Are translations included? =

The plugin ships a POT template (`languages/universal-multicurrency.pot`) for translators. Bundled locale `.mo` files are not included in this release.

== Changelog ==

= 0.8.0 =
* Automatic exchange rates via Frankfurter (`ExchangeRateSource`, conditional HTTP caching)
* Settings schema v2: `manual_rate`, `provider_rate`, `merchant_adjustment`, global/per-currency rate modes
* Effective rates derived on read (`RateResolver`); operational state in `umc_rate_state`
* Action Scheduler recurring updates; manual update-now / update-all admin actions
* Site Health rate diagnostics; ADRs 0010–0013

= 0.7.0 =
* Release Candidate: persisted-data inventory and uninstall retention policy (ADR-0009)
* Settings upgrade framework (schema v1; sole production migration v0→v1)
* Manual merchant migration documentation (no foreign import; CSV format spec only)
* Translation readiness (canonical POT, drift guard, RTL audit documentation)
* Security audit and hardening (zero open Critical/High findings)
* Deterministic performance baselines and CI performance job
* Executable release audit (`composer release-audit`) and documentation synchronization

= 0.6.0 =
* Compatibility layer: passive conflict detection, admin notices, Site Health tests, and version support documentation
* Five-leg supported-version CI matrix
* Storefront conversion, classic cart/checkout, order snapshots, historical order display, refunds, and Store API / blocks parity (milestones 2–5)

== Upgrade Notice ==

= 0.8.0 =
Upgrades settings schema v1→v2 automatically (`rate` becomes `manual_rate`; default rate mode stays manual). Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.7.0 =
Release Candidate packaging and documentation only for most stores upgrading from 0.6.0 — no settings schema bump beyond the existing v0→v1 path. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.6.0 =
Requires WooCommerce 8.2 or newer and PHP 8.1 or newer. HPOS must be enabled.
