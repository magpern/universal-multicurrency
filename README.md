# Universal Multicurrency for WooCommerce

Unlimited currencies for WooCommerce, with manual rates or scheduled automatic
rates from an exchange-rate provider, plus optional authoritative fixed
per-currency pricing. Products and inventory stay in the store's base
currency; conversion happens at runtime on storefront, cart, checkout, and
Store API surfaces. Orders carry a permanent exchange-rate snapshot.

**Current release:** **v0.24.0** (plugin header and `UMC_VERSION`) — Fixed
Pricing CSV Interchange (ADR-0030). See [`docs/RELEASE_AUDIT.md`](docs/RELEASE_AUDIT.md)
for the release-closure record and [`docs/ROADMAP.md`](docs/ROADMAP.md) for
full milestone history.

## What it does

- **Currencies & rates** — unlimited currencies, manual or automatic
  (Frankfurter-provider) exchange rates, rate health/staleness monitoring,
  a thin `wp umc rates` CLI.
- **Visitor Location** — optional, disabled-by-default ordered
  country/region → currency routing, always subordinate to manual shopper
  selection and checkout currency locks.
- **Storefront switcher** — shortcode, widget, and native Gutenberg block,
  with structured presentation settings (placement, theme, size, shape,
  optional bundled currency icons) and optional Advanced CSS.
- **Checkout currency policy** — selected-currency or store-currency entry
  modes, causality-proven payment-gateway fallback, Classic and Checkout
  Blocks parity.
- **Authoritative fixed pricing** — optional merchant-authored per-currency
  regular/sale prices on simple products and variations, with FX conversion
  as the fallback; catalog-wide bulk seed/clear operations (dedicated admin
  screen + `wp umc prices` CLI); bulk CSV interchange through WooCommerce's
  own native product Export/Import.
- **Reporting** — admin reporting (Currency Performance, Pricing Source,
  Currency Origin, Checkout Fallback) reading immutable order facts in
  native transaction currency only — no live or inverse FX.
- **Compatibility** — passive detection of other currency switchers (never
  deactivates or modifies another plugin), a Compatibility Center admin
  surface, and an honest E0–E3 third-party extension evidence model.
- **Orders & history** — immutable per-order currency/rate snapshots;
  historical orders and refunds always display in their stored currency.

## Invariants

- WooCommerce owns inventory — never split stock by currency.
- Base prices stay in base currency; convert at display and transaction time.
- One conversion engine (`Converter` via `Integration\PriceConversionService`).
- Orders store immutable `_umc_*` snapshots; never deleted on uninstall.
- HPOS required; standalone — no FOX/WOOCS coupling (see ADR-0003, ADR-0007).
- Compatibility detection observes only; never deactivates another plugin.
- Merchant migration from another switcher is **manual only** — no foreign import
  (see `docs/MIGRATION.md`).
- Only the raw provider quote is persisted; the effective rate (quote plus
  merchant adjustment) is derived on read, never stored (ADR-0010).
- Rate operations (`umc_rate_state`) never share an option with money-bearing
  settings (`umc_settings`); a failed or unchanged fetch keeps the last known
  rate (ADR-0012).

## Install

Build the installable zip:

```bash
composer install --no-dev
bash bin/build-zip.sh
```

Produces `dist/universal-multicurrency-0.24.0.zip`. Upload and activate through
WordPress, or symlink the plugin directory into `wp-content/plugins/`.
WooCommerce must be active first. The release zip includes `readme.txt`,
production `src/`, `vendor/`, bundled presentation assets, block metadata, and
`languages/universal-multicurrency.pot` — never `tests/`, `node_modules/`, or
`.git/`.

New to the plugin? See [`docs/GETTING_STARTED.md`](docs/GETTING_STARTED.md)
for a merchant-facing walkthrough (currencies → rates → Visitor Location →
switcher → fixed pricing → checkout → reporting → CSV → uninstall) that does
not require reading any ADR.

## Development

```bash
composer install
composer phpcs
composer make-pot
composer make-pot:check
composer test:unit
composer test:integration   # needs MySQL + tests/bin/install-wp.sh
composer test:mutation      # Diagnostics scorer; needs PCOV
composer audit
composer release-audit      # release-blocking RC gate (see docs/RELEASE_AUDIT.md)
```

Docker command examples: `CLAUDE.local.md` (local, gitignored).

Browser release-acceptance suite (Playwright, targets an authorized DEV
WordPress + WooCommerce environment only — never production):
[`tests/e2e/README.md`](tests/e2e/README.md).

## Compatibility

Requires **PHP 8.1+**, **WordPress 6.5+**, and **WooCommerce 8.2+** (HPOS). See
[`docs/COMPATIBILITY.md`](docs/COMPATIBILITY.md) for the supported-version matrix,
CI legs, and passive conflict detectors.

## Migration and uninstall

- **Migration:** manual cut-over only — deactivate the old switcher, configure UMC
  manually; no automatic import from FOX/WOOCS or other switchers.
- **Uninstall:** deletes plugin configuration options (`umc_settings`,
  `umc_rate_state`, `umc_reporting_cache_gen`) only; `_umc_*` order meta,
  `_umc_parent_*` refund meta, `_umc_fixed_prices` product meta, and
  `umc_dismissed_notices` user meta are preserved (ADR-0009).

## Changelog

Highlights only — see [`readme.txt`](readme.txt) for the complete
release-by-release changelog.

### 0.24.0 — Fixed Pricing CSV Interchange

Bulk, explicitly-authored interchange of per-currency fixed prices through
WooCommerce's own native product CSV export/import (structured
`umc_fixed_regular_{code}`/`umc_fixed_sale_{code}` columns, no second CSV
format), with a shared `FixedPriceDocumentMerger` mutation authority and a
resync-to-database-truth defense against WooCommerce's own generic
custom-meta import mechanism. No settings schema change — see
[ADR-0030](docs/adr/0030-fixed-pricing-csv-interchange.md).

### 0.23.0 — Fixed Pricing Catalog Operations

Catalog-wide fixed-price coverage visibility and bounded bulk seed/clear
operations, a dedicated Fixed Pricing admin screen, a passive Products-list
coverage column, and a symmetric `wp umc prices list|seed|clear` CLI. No
settings schema change — see [ADR-0029](docs/adr/0029-fixed-pricing-catalog-operations.md).

### 0.20.0–0.22.0 — Reporting, presentation icons, native block

Multicurrency Reporting (native-transaction-currency-only admin reporting,
OrderSnapshot schema 5), optional bundled switcher presentation icons
(Settings schema 7), and a native Gutenberg switcher block
(`universal-multicurrency/currency-switcher`). See
[ADR-0026](docs/adr/0026-multicurrency-reporting-truth-contract.md),
[ADR-0027](docs/adr/0027-switcher-currency-presentation.md),
[ADR-0028](docs/adr/0028-native-switcher-block-rendering-surface.md).

### 0.19.0 — Authoritative Per-Currency Product Pricing

Optional merchant-authored regular/sale prices per non-base currency on
simple products and variations, with FX conversion as fallback. No settings
schema change; `PersistedKeys` 8 → 9. See
[ADR-0025](docs/adr/0025-authoritative-fixed-product-pricing.md).

### 0.17.0–0.18.0 — WooCommerce & third-party extension compatibility

Transaction-integrity hardening against WooCommerce core semantics (tax/
shipping/coupon/threshold correctness, Classic/Blocks/Store API parity), then
a third-party extension compatibility framework with an honest E0–E3 evidence
model applied to priority integrations. See
[ADR-0023](docs/adr/0023-woocommerce-transaction-integrity-contract.md),
[ADR-0024](docs/adr/0024-third-party-extension-compatibility-contract.md).

### 0.16.0 — Switcher Customization

Structured switcher settings plus optional Advanced Custom CSS — one semantic
DOM for every placement, CSS-layer presets instead of a second renderer.
Settings schema 5 → 6. See [`docs/SWITCHER_CUSTOMIZATION.md`](docs/SWITCHER_CUSTOMIZATION.md).

### 0.14.0–0.15.0 — Currency explainability & exchange-rate operations

Structured currency-decision provenance and a stateless Decision Inspector;
then a rate-health model, aging presentation, scheduler correctness, and a
thin `wp umc rates` CLI. No settings schema change.

### 0.13.0 — Visitor Location boundary alignment

Visitor Location hub reduced from seven panels to three (Overview, Currency
Routing, Currency Simulation); Overview redesigned as a merchant dashboard;
Currency Routing absorbs the former Settings panel and presents rules as
policy statements. No settings schema change — see [ADR-0018](docs/adr/0018-visitor-location-boundary-alignment.md).

### 0.11.0 — Geo Detection

Ordered first-match country/region currency routing, settings schema v5,
optional Universal Geo Context integration with WooCommerce geolocation
fallback. See [`docs/GEO_DETECTION.md`](docs/GEO_DETECTION.md).

### 0.10.0 — Checkout currency policy

Checkout currency policy (`selected` or store currency at checkout entry),
causality-proven payment-gateway fallback, Classic and Checkout Blocks
parity, settings schema v4.

### 0.9.0–0.9.1 — Display Configurator & compatibility diagnostics

Commercial-grade Display settings workspace with visual placement/style
controls and live responsive preview; a read-only Compatibility diagnostics
center with grouped findings and redacted support-report export.

### 0.8.0 — Automatic exchange rates

Provider abstraction with the Frankfurter source, Action Scheduler recurring
updates, conditional HTTP caching, per-currency merchant adjustments,
settings schema v2. Existing stores upgrade in manual mode with
byte-identical conversion output.

### 0.7.0 — Release Candidate

Persisted-data inventory, uninstall policy, settings upgrade framework (schema v1),
manual migration documentation, translation readiness, security audit, deterministic
performance baselines, executable release audit, and documentation synchronization.
See [`docs/RELEASE_AUDIT.md`](docs/RELEASE_AUDIT.md) for the closure record.

### 0.6.0

Compatibility and diagnostics milestone — passive conflict detection, Site Health,
five-leg CI matrix, and `docs/COMPATIBILITY.md`.

## Documentation

| Document | Contents |
|---|---|
| [`readme.txt`](readme.txt) | WordPress.org–oriented plugin readme (Stable tag 0.24.0) |
| [`docs/GETTING_STARTED.md`](docs/GETTING_STARTED.md) | Merchant onboarding walkthrough |
| [`docs/PRODUCT_REQUIREMENTS.md`](docs/PRODUCT_REQUIREMENTS.md) | Goals and non-goals |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Layers, invariants, collaborators, exchange-rate layer |
| [`docs/HOOKS.md`](docs/HOOKS.md) | Every hook registered, and every extension point provided |
| [`docs/EXTENSION_INTEGRATION.md`](docs/EXTENSION_INTEGRATION.md) | Third-party extension compatibility statuses |
| [`docs/COMPATIBILITY.md`](docs/COMPATIBILITY.md) | Version matrix and detector governance |
| [`docs/TEST_STRATEGY.md`](docs/TEST_STRATEGY.md) | Unit, integration, guards, mutation, release audit |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Per-milestone deploy record and contributor commands |
| [`docs/MIGRATION.md`](docs/MIGRATION.md) | Manual merchant cut-over (no foreign import) |
| [`docs/PERSISTED_DATA.md`](docs/PERSISTED_DATA.md) | Persisted-key inventory and uninstall contract |
| [`docs/TRANSLATION.md`](docs/TRANSLATION.md) | Text domain, POT workflow, JS/RTL translation status |
| [`docs/SECURITY_REVIEW.md`](docs/SECURITY_REVIEW.md) | Security audit record and accepted residual risks |
| [`docs/PERFORMANCE_BASELINES.md`](docs/PERFORMANCE_BASELINES.md) | Deterministic performance ceilings |
| [`docs/RELEASE_AUDIT.md`](docs/RELEASE_AUDIT.md) | Executable release-blocking audit record |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | Milestone status |
| [`docs/GEO_DETECTION.md`](docs/GEO_DETECTION.md) | Visitor Location administrator guide |
| [`docs/SWITCHER_CUSTOMIZATION.md`](docs/SWITCHER_CUSTOMIZATION.md) | Switcher presentation & Advanced CSS guide |
| [`docs/CLI.md`](docs/CLI.md) | `wp umc rates`/`wp umc prices` CLI reference |
| [`docs/adr/`](docs/adr/) | Architecture decision records |

## License

GPL-2.0-or-later — declared in the plugin header and `composer.json`.
