# Visitor Location — administrator guide

Visitor Location determines a visitor's country and turns it into a currency
decision. **Universal Geo Context owns the country fact; this plugin owns the
currency policy** — see [ADR-0018](adr/0018-visitor-location-boundary-alignment.md).

Configure under **WooCommerce → Settings → Multicurrency → Visitor Location**
(`section=geo_detection`), which has three panels: **Overview**,
**Currency Routing**, and **Currency Simulation**.

## Overview

A merchant dashboard, not a diagnostics page. It answers four questions:

1. Is Visitor Location connected? (an integration-health card — "Universal
   Geo Context connected", "Simulation inactive", "Status healthy" — never a
   version number unless the installed API is incompatible)
2. Which country is currently detected? (for your own admin session —
   storefront visitors are resolved individually)
3. Which currency would that produce? (a preview of your Currency Routing
   policy against that detected country)
4. Is anything wrong? (a "Needs attention" section that only appears when it
   has something to say — missing rules, no provider available, an active
   Universal Geo Context simulation)

Provider, source, confidence, and version detail live behind a collapsed
**Technical details** section — useful for support conversations, not the
first thing a merchant needs to see.

## Currency Routing

The ordered policy that turns a detected country into a currency. Rules are
checked **from top to bottom**; the first rule matching the visitor's
country determines the currency. Each rule reads as a condition → result
statement ("When the visitor is in Sweden, use SEK.") with its priority
number and, when relevant, an inline warning (for example, when its
currency is no longer enabled or has no exchange rate).

This panel also holds automatic-detection enable/mode, the technical
fallback currency, the WooCommerce fallback toggle, and checkout behaviour —
everything that used to be a separate Settings panel now lives here, since
all of it is currency-routing policy.

### Canonical example

| Order | Rule | Currency |
|---|---|---|
| 1 | Sweden | SEK |
| 2 | Denmark | DKK |
| 3 | Norway | NOK |
| 4 | Poland | PLN |
| 5 | United Kingdom | GBP |
| 6 | European Union | EUR |
| 7 | Other countries | EUR |

Expected results:

- Sweden → SEK (rule 1)
- Poland → PLN when the Poland rule is **above** the European Union rule
- Germany → EUR through the European Union rule
- China → EUR through Other countries
- Poland → EUR when the European Union rule is **above** the Poland rule (first
  match wins — the Poland rule is shadowed)

Place **specific country overrides above broader regions**. **Other countries**
should normally be last. Use **Add recommended European rules** to append
preset rows for enabled currencies only — currencies are never enabled
automatically.

### Manual selection always wins

Explicit `?currency=` selection, a valid session currency, and a valid
remembered cookie all take precedence over automatic detection. Visitor
Location never traps the customer in a detected currency.

### Other countries vs technical fallback

- **Other countries** handles valid countries unmatched by earlier rules.
- **Technical fallback** handles missing or invalid country context and safety
  cases when no rule produces a selectable currency. Defaults to store base
  currency.

### Checkout lock

Once checkout has started, currency is locked to keep totals consistent
throughout the order (Milestone 11). Optional billing/shipping re-evaluation
applies only **before** lock when enabled.

### Detection modes

| Mode | Behaviour |
|---|---|
| First eligible visit | Apply once when no higher-priority currency exists |
| Once per session | Apply once per WooCommerce session |
| Until manually selected | Geo may set initial currency; switcher selection suppresses geo |

Automatic detection is **disabled by default** on new installs and on
upgrade until an administrator enables it.

### Country sources

- **Universal Geo Context** (optional, recommended): supplies the country
  when installed and running a compatible public API (feature-detected;
  there is no minimum version — see ADR-0018). Provider configuration,
  trusted proxies, and detection diagnostics are managed there, not in this
  plugin.
- **WooCommerce fallback**: checkout billing/shipping country and optional
  WooCommerce geolocation, used when Universal Geo Context is unavailable.
  No visitor IP addresses are stored or shown.

## Currency Simulation

A **read-only what-if** over the currency decision pipeline: given a
simulated country and shopper state, which rule matches, what currency is
suggested, and what would the shopper actually see. It never changes the
storefront, your session, cart, or active currency, and it implements no
second detection or simulation engine — this is the same evaluator that
runs in production, invoked safely.

This is distinct from **simulating a location in Universal Geo Context**,
which changes *your own admin browsing session's* live detected country.
When Universal Geo Context is simulating, Currency Simulation shows a
banner with a one-click way to load that same country here, and a link to
manage the Universal Geo Context simulation.

Quick-pick presets (SE, NO, DK, FI, DE, GB, US) and recently used countries
speed up repeated tests. The result shows the effective currency, the
matched rule (or the reason routing was skipped — an explicit currency,
session, cookie, checkout lock, or automatic detection being disabled), and
the full evaluation trace; the raw document is available in a collapsed
"Technical details" block for support. The legacy **Test detection** action
redirects here.

For a full shopper + Visitor Location + checkout explanation, use
**WooCommerce → Settings → Multicurrency → Decision Inspector** (Milestone 15).
Currency Simulation remains the geo-focused what-if tool; Decision Inspector
composes the same evaluators without duplicating them. See
[`docs/architecture/currency-decision-explainability.md`](architecture/currency-decision-explainability.md).

## Admin route changes (M14)

The Visitor Location hub previously had seven panels. Provider status,
Trusted Proxies, and Diagnostics were always either read-only stubs or a
placeholder pointing at Universal Geo Context; that content was removed in
favor of a direct deep link, and the standalone Settings panel folded into
Currency Routing. Bookmarked URLs for the four retired panels
(`geo_panel=providers|proxies|diagnostics|settings`) redirect automatically
with a one-time notice, and will keep doing so for at least two minor
releases. See [ADR-0018](adr/0018-visitor-location-boundary-alignment.md)
for the full rationale.

## Caching and first-visit detection

Visitor Location determines a visitor's country only when WordPress and PHP
execute. If your store uses **full-page caching** (via a caching plugin, CDN,
edge cache, or reverse proxy), a cached page served to a first-time visitor
*before* the detection code runs will show a currency computed for a *different*
geography.

### Merchant configuration

If you enable first-visit Visitor Location detection, you should ensure your
cache does not serve the same page to visitors from different countries without
re-evaluating:

- **Exclude landing pages from full-page cache** — mark your home page, category
  archives, and product listing pages as non-cacheable, or set a very short TTL
  (cache lifetime). First-visit detection runs earliest on these pages.
- **Configure cache to vary on the currency cookie** — if your caching plugin
  (e.g. WP Super Cache, LiteSpeed Cache, WP Rocket) supports cache-key variation,
  add the `umc_currency` cookie as a vary dimension. After Visitor Location
  applies a currency once, the cookie persists it for 30 days, and your cache
  respects the cookie automatically.
- **Use session-aware cache** — some CDNs and reverse proxies (Cloudflare,
  Varnish, Nginx) can be configured to check WooCommerce session cookies and
  bypass cache for new sessions, serving fresh HTML instead of a cached page.

If you do not configure cache behavior and a first-time visitor from Sweden
lands on a cached page that was built for a Brazil visitor, they will see
prices in BRL until they manually select a currency or reload the page.

### For developers

The `umc_geo_currency_decided` action fires **after** a geo currency is selected
and **before** it is persisted. This is the appropriate hook to add cache-busting
logic (send `Cache-Control: no-cache`, `Vary` headers, or clear relevant cache
keys) if you need Visitor Location to bypass your cache entirely.

## Related documentation

- [ADR-0018](adr/0018-visitor-location-boundary-alignment.md) — the
  Universal Geo Context boundary and what changed in M14
- [ADR-0016](adr/0016-geo-detection-ordered-routing.md) — the routing engine
- [`PERSISTED_DATA.md`](PERSISTED_DATA.md)
- [`COMPATIBILITY.md`](COMPATIBILITY.md)
