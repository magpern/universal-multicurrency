# Geo Detection — administrator guide

Geo routing rules are checked **from top to bottom**. The first rule matching
the visitor's country determines the currency.

Configure under **WooCommerce → Settings → Multicurrency → Geo Detection**
(`section=geo_detection`).

## Canonical example

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
should normally be last.

## Manual selection always wins

Explicit `?currency=` selection, a valid session currency, and a valid
remembered cookie all take precedence over Geo Detection. Geo Detection never
traps the customer in a detected currency.

## Other countries vs technical fallback

- **Other countries** handles valid countries unmatched by earlier rules.
- **Technical fallback** handles missing or invalid country context and safety
  cases when no rule produces a selectable currency. Defaults to store base
  currency.

## Checkout lock

Once checkout has started, currency is locked to keep totals consistent
throughout the order (Milestone 11). Optional billing/shipping re-evaluation
applies only **before** lock when enabled.

## Detection modes

| Mode | Behaviour |
|---|---|
| First eligible visit | Apply once when no higher-priority currency exists |
| Once per session | Apply once per WooCommerce session |
| Until manually selected | Geo may set initial currency; switcher selection suppresses geo |

Geo Detection is **disabled by default** on new installs and on upgrade to
schema v5 until an administrator enables it.

## Country providers

- **Universal Geo Context** (optional): supplies country when installed and
  compatible; UMC remains fully functional without it.
- **WooCommerce fallback**: checkout billing/shipping country and optional
  WooCommerce geolocation. No visitor IP addresses are stored or shown.

Use **Add recommended European rules** to append preset rows for enabled
currencies only. Currencies are never enabled automatically.

## Simulation

The **Geo Sandbox** panel runs a read-only simulation via
`admin_post_umc_geo_sandbox_run`. It builds a **GeoContext** document, evaluates
routing, and displays structured JSON output. It does not alter session, cookies,
cart, or active currency.

Quick-pick presets (SE, NO, DK, FI, DE, GB, US) and recently used countries
speed up repeated tests. The legacy **Test detection** action redirects to Geo
Sandbox.

## Admin hub panels

Geo Detection uses secondary navigation (`geo_panel` query argument):

| Panel | Purpose |
|---|---|
| Overview | Status, provider summary, quick links |
| Detection | Geographic routing rules |
| Geo Sandbox | GeoContext simulation |
| Providers | Read-only provider status (editable in a future release) |
| Trusted Proxies | Universal Geo Context boundary |
| Diagnostics | Geo counts and diagnostic links |
| Settings | Enable/mode, fallback, checkout behaviour |

Saving from **Detection** updates rules only. Saving from **Settings** updates
operational options only.

## Related documentation

- [ADR-0016](adr/0016-geo-detection-ordered-routing.md)
- [`PERSISTED_DATA.md`](PERSISTED_DATA.md)
- [`COMPATIBILITY.md`](COMPATIBILITY.md)
