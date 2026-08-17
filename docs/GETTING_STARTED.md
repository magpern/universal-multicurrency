# Getting started

A practical, merchant-facing walkthrough for installing and operating
Universal Multicurrency. It explains workflows, not internal architecture —
for how the plugin is built, see [`docs/ARCHITECTURE.md`](ARCHITECTURE.md)
and the [ADRs](adr/). All settings live under **WooCommerce → Settings →
Multicurrency** (`wp-admin/admin.php?page=wc-settings&tab=umc`), organized
into the sections referenced below.

## 1. Install

Requires WooCommerce active first (**PHP 8.1+**, **WordPress 6.5+**,
**WooCommerce 8.2+**, HPOS — see [`docs/COMPATIBILITY.md`](COMPATIBILITY.md)).
Upload the release ZIP through **Plugins → Add New → Upload Plugin**, or
extract it into `wp-content/plugins/`, then activate.

If you are moving off another currency switcher (FOX/WOOCS, WCML, CURCY,
YayCurrency, or similar), see [`docs/MIGRATION.md`](MIGRATION.md) first —
cut-over is manual by design; the plugin never reads another switcher's
configuration or auto-imports it.

## 2. Initial setup

On activation, no currency beyond your store's base currency is configured
— the storefront behaves exactly as before until you add one. Nothing is
shown to shoppers until you explicitly enable a currency (§3) and place a
switcher (§6).

## 3. Add currencies

**Section: Currencies.** Add a currency, set its display symbol/position/
decimal places, and enable it. A currency stays configurable but hidden
from shoppers until "Enabled" is checked — useful for preparing a currency
ahead of a launch.

## 4. Configure exchange rates

**Section: Exchange Rates.** Two modes, set per currency:

- **Manual** — enter a rate yourself; it never changes until you edit it.
- **Automatic** — the plugin fetches a live rate on a schedule (Frankfurter
  provider) and applies an optional merchant adjustment (e.g. +2%) on top.
  A rate that fails to fetch or comes back unchanged never overwrites the
  last known good rate.

The section shows each currency's rate age/health at a glance. A stale or
never-fetched automatic rate is flagged there and on **Site Health**.

## 5. Configure Visitor Location (optional)

**Section: Geo Detection.** Disabled by default. When enabled, an ordered
list of country/region rules routes first-time visitors to a currency
automatically. Manual shopper selection (§6) and a checkout currency lock
always take precedence over Visitor Location — it only ever supplies a
default, never overrides an explicit choice. See
[`docs/GEO_DETECTION.md`](GEO_DETECTION.md) for the full rule model and the
optional Universal Geo Context integration.

## 6. Configure the switcher

**Section: Display.** Place the switcher via shortcode
(`[universal_multicurrency_switcher]`), the bundled Gutenberg block
(**Universal Multicurrency → Currency Switcher** in the block inserter), or
a floating placement (side/bottom). Customize placement, trigger/menu
content (code, symbol, name, optional bundled currency icons), theme/size/
shape presets, and — for advanced needs — raw CSS via the gated Advanced
Custom CSS field. See
[`docs/SWITCHER_CUSTOMIZATION.md`](SWITCHER_CUSTOMIZATION.md) for the full
reference, including the CSS custom-property contract for theme
integration.

## 7. Fixed product pricing (optional)

By default, every price converts at runtime from your base-currency price
using the current exchange rate. For products where you want to author an
exact, deliberate price in a specific currency instead (e.g. psychological
pricing, marketplace-parity pricing) — **product editor → Multicurrency
Pricing** panel lets you enter a regular/sale price per enabled non-base
currency directly on that product or variation. A fixed price always wins
over FX conversion for that currency; leaving it blank falls back to
conversion.

For many products at once: **section: Fixed Pricing** — a coverage report
showing which products have fixed prices per currency, plus bounded bulk
**Seed** (converts each product's authored base price through the live rate,
once, into a fixed price) and **Clear** (removes fixed prices for a
currency) operation, with a preview step before anything is written. The
same operations are available via `wp umc prices list|seed|clear` — see
[`docs/CLI.md`](CLI.md).

## 8. Checkout behavior

**Section: Checkout.** Two entry modes:

- **Selected currency** — checkout continues in whatever currency the
  shopper was browsing in. If a chosen payment gateway doesn't support
  that currency, the plugin falls back to the store's base currency and
  shows the shopper a notice explaining why.
- **Store currency** — checkout always settles in the store's base
  currency, regardless of browsing currency; shoppers see a notice on the
  checkout page explaining the switch.

Every order permanently records which mode applied, the shopper's browsing
currency, the settled currency, and whether a fallback occurred — visible on
the order edit screen's **Currency & Exchange Rate** panel.

## 9. Reporting

**Section: Reporting.** Currency Performance (revenue and AOV per
currency), Pricing Source (fixed vs. converted), Currency Origin (manual
selection vs. Visitor Location), and Checkout Fallback reports, each backed
by immutable order facts in their **native transaction currency only** —
never a live or inverse FX recalculation of historical orders, so numbers
here never silently change when today's rates change. CSV export uses the
same models as the on-screen report.

## 10. CSV import/export

Bulk-edit fixed prices via WooCommerce's own **Products → Export/Import** —
no separate UMC CSV format. Exported files include one column pair per
enabled non-base currency (`UMC Fixed Regular Price (SEK)` / `UMC Fixed
Sale Price (SEK)`, etc.); edit those columns and re-import. Import is a
patch, not a full replace: a currency column absent from your import file
leaves that currency's existing fixed price untouched, and an explicitly
blank cell clears it. Leave both regular/sale blank for a currency to defer
to FX conversion instead.

## 11. Compatibility

**Section: Compatibility.** Passively detects other known currency
switchers and shows a graded warning if one is active alongside this
plugin — it never deactivates or modifies another plugin itself. This
section also lists third-party WooCommerce extension compatibility status
(Subscriptions, Product Add-Ons, Bundles, and others) with an honest
evidence tier per extension — see
[`docs/EXTENSION_INTEGRATION.md`](EXTENSION_INTEGRATION.md) for what each
tier actually means.

## 12. Diagnostics / troubleshooting

Two places to look first:

- **Section: Compatibility** also runs a local diagnostics scan
  (configuration, integration, theme, cache, environment) with a **Copy
  Report** action that redacts sensitive values — paste this when asking
  for support.
- **Tools → Site Health** includes rate-health, Visitor Location
  integration status, and version-support tests alongside WordPress's own
  checks.

## 13. Backup / upgrade / uninstall

- **Backup**: standard WordPress/WooCommerce backup practice covers this
  plugin — its data lives in normal options, post meta, and order meta.
- **Upgrade**: replace the plugin files (or update through
  Plugins → Updates) and activate; settings/currencies/rates/fixed prices/
  historical orders all carry forward automatically. No manual migration
  step is ever required for an in-place version upgrade.
- **Uninstall**: deleting the plugin removes only its configuration options
  (`umc_settings`, `umc_rate_state`, `umc_reporting_cache_gen`). Order
  currency/rate history, refund audit data, and fixed-price product data
  are permanent commerce records and are **never** deleted by uninstall —
  see [ADR-0009](adr/0009-uninstall-retention-policy.md) and
  [`docs/PERSISTED_DATA.md`](PERSISTED_DATA.md) for the complete inventory.
