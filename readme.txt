=== Universal Multicurrency for WooCommerce ===
Contributors: magpern
Tags: woocommerce, currency, multicurrency, exchange rates, money
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.21.0
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

= 0.21.0 =
* Switcher Currency Presentation (Milestone 22, Phase 1)
* Optional bundled presentation icons as a fourth content element (`icon`) on the existing M17 switcher
* Settings schema 6→7 with `display.presentation.*` and per-context `show_icon` (default off); no PersistedKeys or OrderSnapshot change
* Built-in presentation-region defaults (EUR→EU) with merchant overrides; registry-only bundled SVG assets
* Public CSS hooks: `.umc-switcher__icon`, `[data-umc-icon-type="flag"]`, `--umc-switcher-icon-size|radius|gap`

= 0.20.0 =
* Multicurrency Reporting & Analytics Foundation (Milestone 21, Phase 1)
* Admin Reporting section with date/status/currency filters, summary statistics, and read-only tables
* Native transaction-currency reports: Currency Performance (incl. AOV), Pricing Source, Currency Origin, Checkout Fallback, and rate provenance (table-only)
* Aggregate CSV export using the same immutable report models as the admin UI
* OrderSnapshot schema 4→5 with factual `_umc_currency_origin` persistence (`customer` | `visitor_location` only)
* PersistedKeys inventory 9→10; reporting cache transients (`umc_report_*`); no settings schema or DB migration

= 0.19.0 =
* Authoritative per-currency fixed product pricing (Milestone 20, Phase 1)
* Optional merchant-authored regular and sale prices per non-base foreign currency on simple products and variations
* WooCommerce native sale schedule gates fixed sale amounts; FX conversion remains fallback when fixed prices are absent
* Order line-item pricing provenance (`_umc_line_price_source`, `_umc_line_price_currency`)
* PersistedKeys inventory 8→9; no settings schema or order snapshot schema change

= 0.18.0 =
* Third-Party Extension Compatibility Framework (Milestone 19)
* Extension compatibility registry, evidence tiers (E0–E3), and Compatibility Center sub-labels
* Opt-in fee conversion via `umc_convert_fee` (`FeeConversion`); default pass-through preserved
* WooCommerce Subscriptions, Product Add-Ons, and Product Bundles adapters (Characterized — E2 simulated hooks)
* Subscriptions renewal browsing-currency isolation invariant; rate policy documented pending E3 validation
* Composite Products and Bookings: Not evaluated (M19 audit/deferral)
* No settings schema, PersistedKeys, or order snapshot schema change

= 0.17.0 =
* WooCommerce Compatibility & Transaction Integrity (Milestone 18)
* Free-shipping minimum order amounts convert base→active at eligibility time so thresholds match converted cart totals
* Evidence-linked WooCommerce core transaction integrity matrix (Classic, Blocks/Store API, admin, REST boundary)
* Expanded Classic ↔ Store API parity, cart currency/rate transitions, variation cache (currency and rate), and fee Known-limitation characterization
* No settings schema, PersistedKeys, or order snapshot schema change

= 0.16.0 =
* Switcher customization: Display screen reorganized into Placement, Content, Design, and Advanced sections
* Separate trigger and menu composition — choose the currency code, symbol, and name independently for the button and the list, with a configurable element order
* Six design presets (Default, Minimal, Pill, Compact, Borderless, Floating) layered under the existing theme, size, and shape settings, which continue to win over a preset
* Structured color, radius, control height, spacing, and font-weight overrides emitted as scoped CSS custom properties
* Motion setting for dropdown transitions; system reduced-motion preferences always win
* Mobile adjustments: hide the currency name and use compact spacing below 768px
* Advanced Custom CSS for the switcher, gated by the `edit_css` capability, sanitized against `@import`, `url(...)`, and script breakout payloads, and applied on the storefront only
* Settings schema v5→v6 converts the previous content and appearance settings without visual change

= 0.15.0 =
* Exchange rate operations & reliability: shared health model for admin, Site Health, Compatibility, and WP-CLI
* Presentation-only rate aging (50% of max age); stale rates remain usable for conversion
* Scheduler schedules when any currency has effective automatic mode (`has_automatic_targets`)
* Structured refresh failure taxonomy; hardened lock behaviour; Action Scheduler as next-run truth
* Exchange Rates admin operations UX and thin `wp umc rates` CLI
* Order snapshot schema 4 adds `_umc_rate_provider` and `_umc_rate_adjustment` provenance
* No settings schema change; no multi-provider failover; no live storefront provider HTTP

= 0.14.0 =
* Decision Inspector settings section explains why a shopper would use a currency (shopper selection, Visitor Location, checkout policy)
* Structured CurrencyResolutionResult via CurrencyResolver::evaluate() with truthful winning sources (explicit/session/cookie/base)
* Session provenance metadata (umc_currency_origin) distinguishes customer vs Visitor Location writes without affecting precedence
* Runtime/explanation parity via shared evaluators; GeoCurrencyDecisionService left unconsolidated where skip-reason labeling differs
* No settings schema change; safe in-place upgrade from 0.13.x

= 0.13.0 =
* Visitor Location hub reduced from seven panels to three: Overview, Currency Routing, Currency Simulation
* Overview redesigned as a merchant dashboard: integration health, detected country and resulting currency, and a needs-attention area, replacing the previous version-number display
* Currency Routing (formerly Detection + Settings) presents each rule as a condition/result/priority policy statement with inline validation
* Currency Simulation (formerly Geo Sandbox) replaces raw JSON output with a status-badge and trace presentation, and is aware of an active Universal Geo Context location simulation
* Retired Providers, Trusted Proxies, and Diagnostics panels now redirect to their replacement with a one-time notice; deep-link into Universal Geo Context instead of duplicating its screens
* Universal Geo Context availability centralized behind one check (`UgcIntegrationStatus`), correcting three call sites that previously skipped the API-compatibility check
* GeoContext (sandbox document) schema v2 removes unused reserved fields; older cached results are discarded safely
* No settings schema change; safe in-place upgrade from 0.12.x

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

= 0.17.0 =
WooCommerce Compatibility & Transaction Integrity. Free-shipping thresholds now convert with the cart. No settings schema change — safe upgrade from 0.16.x. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.16.0 =
Switcher customization release. Settings schema v5→v6 converts existing content and appearance settings automatically and preserves the current storefront appearance. Advanced Custom CSS additionally requires the `edit_css` capability. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.15.0 =
Exchange rate operations & reliability. Order snapshot schema 4 adds rate provenance metadata. No settings schema change — safe upgrade from 0.14.x. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

= 0.13.0 =
Visitor Location admin redesign (Overview, Currency Routing, Currency Simulation). No settings schema change — safe upgrade from 0.12.x. Requires WooCommerce 8.2+, PHP 8.1+, and HPOS.

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
