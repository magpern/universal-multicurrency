=== Universal Multicurrency for WooCommerce ===
Contributors: magpern
Tags: woocommerce, currency, multicurrency, exchange rates, money
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.12.1
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

= 0.12.1 =
* Fix Add currency redirect blocked by WooCommerce settings unsaved-changes guard on the Multicurrency overview

= 0.12.0 =
* Geo Detection admin hub with Overview, Detection, Geo Sandbox, Providers, Trusted Proxies, Diagnostics, and Settings panels
* GeoContext document (schema v1) for structured sandbox simulation and future provider-chain work
* Geo Sandbox with quick-pick presets, recent countries, and JSON trace output via admin-post
* Panel-aware saves: Detection updates rules only; Settings updates operational options only
* Legacy simulation action redirects to Geo Sandbox

= 0.11.0 =
* Geo Detection settings section with ordered first-match country/region routing
* Settings schema v5 with safe v4→v5 migration (Geo Detection disabled by default)
* Optional Universal Geo Context integration and WooCommerce geolocation fallback
* Recommended European rules action, simulation tool, and Site Health geo test
* Manual shopper selection and checkout currency lock precedence preserved

= 0.10.0 =
* Checkout currency policy settings (`selected` or store currency at checkout entry)
* Causality-proven payment-gateway fallback with informational customer notices
* Classic and Checkout Blocks parity, including Blocks notice delivery via Store API extension data
* Order snapshot v3 checkout metadata
* Settings schema v4 with safe v3→v4 migration (defaults preserve v0.9.x checkout behaviour)

= 0.9.1 =
* Compatibility diagnostics center with local scans, support report, and Copy Report action
* Fix false-positive Compatibility warnings for base currency and empty symbol overrides
* Fix single-currency Update now actions incorrectly marking other automatic currencies as failed
* No settings schema change; safe in-place upgrade from 0.9.0

= 0.9.0 =
* Display settings configurator with visual placement and style controls
* Floating Side and Floating Bottom positioning with edge and offset controls
* Manual shortcode helper with copy action for manual placement
* Live responsive preview with desktop and mobile viewport modes
* Sticky Display save bar with unsaved-change indicator
* Accessible native controls, segmented appearance options, and keyboard-friendly dropdown behavior
* Inactive placement panel values preserved when switching placement modes
* Storefront currency switcher renderer and assets (dropdown and horizontal list)
* No settings schema change; safe in-place upgrade from 0.8.x

= 0.8.1 =
* Fixes recurring rate-update scheduling when the configured interval changes
* Refreshes rate timestamps when merchants edit manual rate, adjustment, or per-currency rate mode inputs
* Corrects plugin header description to reflect manual and automatic exchange-rate support
* No settings schema change; safe in-place upgrade from 0.8.0

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

= 0.10.0 =
Checkout currency policy release. Settings schema v3→v4 adds checkout defaults that preserve selected-currency checkout behaviour. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.9.1 =
Compatibility diagnostics and rate-update fixes. No settings schema change — safe upgrade from 0.9.0. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.9.0 =
Display configurator release. No settings schema change — safe upgrade from 0.8.x. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.8.1 =
Maintenance release. No settings schema change — safe upgrade from 0.8.0. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.8.0 =
Upgrades settings schema v1→v2 automatically (`rate` becomes `manual_rate`; default rate mode stays manual). Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.7.0 =
Release Candidate packaging and documentation only for most stores upgrading from 0.6.0 — no settings schema bump beyond the existing v0→v1 path. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.6.0 =
Requires WooCommerce 8.2 or newer and PHP 8.1 or newer. HPOS must be enabled.
