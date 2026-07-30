# ADR-0016 — Geo Detection ordered routing

## Status

Accepted (Milestone 12, target v0.11.0).

## Context

Merchants need automatic storefront currency selection based on the visitor's
country without replacing manual customer choice, checkout currency locks, or
order-owned currency context. Geo Detection must remain optional, disabled by
default on upgrade, and safe when Universal Geo Context (UGC) is absent.

## Decision

### First-match-wins routing

- Rules are stored as an **ordered array**; array order is evaluation order.
- The evaluator walks rules top to bottom and **stops at the first match** whose
  currency is selectable.
- Supported rule types: **country** (ISO alpha-2 exact match), **region**
  (preset membership), **other** (catch-all for valid countries that reach it).
- Only one **other** rule is allowed and it must be last.

### Region presets (registry version 1)

Embedded, version-controlled membership — no network dependency during
storefront requests:

| ID | Label | Notes |
|---|---|---|
| `eu` | European Union | 27 member states post-Brexit |
| `eurozone` | Eurozone | EU members using EUR |
| `eea` | European Economic Area | EU + Iceland, Liechtenstein, Norway |

Semantic checks enforced in unit tests:

- Germany ∈ EU and Eurozone
- Poland ∈ EU, ∉ Eurozone
- Sweden ∈ EU, ∉ Eurozone
- Denmark ∈ EU, ∉ Eurozone
- Norway ∈ EEA, ∉ EU
- United Kingdom ∉ EU, Eurozone, or EEA

Broader continent presets are **deferred** beyond v0.11.0.

### Other countries vs technical fallback

- **Other countries** matches any valid country that reaches the rule without
  matching earlier rules.
- **Technical fallback** is a separate configured currency used when country
  context is missing/invalid or no rule yields a selectable currency. Defaults
  to store base currency.

### Shopper precedence

Geo Detection is a policy-gated candidate **below** explicit, session, and
cookie shopper selection:

1. Order-owned currency context
2. Explicit `?currency=` selection
3. Valid WooCommerce session currency
4. Valid currency cookie
5. Eligible Geo Detection result
6. Technical fallback currency
7. Store base currency

Manual switcher use sets `umc_manual_currency` in session and suppresses
subsequent geo application for `until_manual` mode.

### Country context providers

- **Universal Geo Context (optional):** feature-detected public API only
  (`function_exists`); no import of undocumented UGC classes.
- **WooCommerce fallback:** checkout billing/shipping country when valid, else
  WooCommerce geolocation at most once per eligible request when enabled.
- Geolocation is skipped when a higher-priority valid currency already exists,
  and for admin, cron, WP-CLI, non-Store REST, order-pay, and order-received
  routes.
- **No visitor IP addresses** are persisted or displayed.

### Checkout lock boundary

- Default `geo.checkout.lock_on_entry = true` preserves Milestone 11 behaviour.
- Optional billing/shipping re-evaluation applies **only before** checkout
  currency lock.
- Order-pay, payment retry, REST-created orders, and admin-created orders never
  use Geo Detection.

### Settings schema

- `umc_settings` schema **v5** adds a `geo` subtree, disabled by default with
  empty rules on v4→v5 migration (zero storefront behaviour change until an
  administrator enables and configures routing).

### Detection modes (v0.11.0)

- `first_visit` — apply once when no higher-priority currency exists
- `session` — apply once per WooCommerce session
- `until_manual` — geo may establish initial currency; manual switcher suppresses geo

`country_change` mode is **deferred**.

## Consequences

- Admin UI uses move up/down (accessible reordering); drag-and-drop is optional
  progressive enhancement only.
- Recommended European rules are an explicit admin action; currencies and Geo
  Detection are never enabled silently.
- Simulation uses `admin_post_umc_geo_simulate` (read-only; no REST/AJAX).
- Region registry version bumps require merchant review when membership changes.

## Related documentation

- [`docs/GEO_DETECTION.md`](../GEO_DETECTION.md) — administrator guide
- [`docs/PERSISTED_DATA.md`](../PERSISTED_DATA.md) — session keys
- [`docs/HOOKS.md`](../HOOKS.md) — `umc_geo_*` extension points
