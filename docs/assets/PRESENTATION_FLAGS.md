# Presentation flag assets (M22)

Bundled SVG files under `assets/icons/presentation/` supply optional switcher
presentation icons. They are **not** geographic authority — see
[ADR-0027](../adr/0027-switcher-currency-presentation.md).

## Source and authorship

| Item | Detail |
|---|---|
| **Source** | Original simplified vexillological SVG geometry authored for Universal Multicurrency v0.21.0 |
| **License** | GPL-2.0-or-later (same as the plugin) |
| **Modification** | Created directly for this plugin; geometry simplified for small display sizes |
| **Attribution** | No third-party attribution required |
| **Redistribution** | Permitted under plugin license; shipped inside plugin ZIP releases |

## Design notes

- Path/shape-only SVG (no scripts, no external references, no event handlers)
- Intended for `<img src="local-plugin-url">` rendering (not inline SVG at runtime)
- `EU` represents the European Union presentation region for EUR — not a member state
- Region identifiers: `AU`, `BR`, `CA`, `CH`, `CN`, `CZ`, `DK`, `EU`, `GB`, `HK`, `IN`, `JP`, `KR`, `MX`, `NO`, `NZ`, `PL`, `SE`, `SG`, `US`, `ZA`

EUR always uses the European Union presentation region. A stored
`icon_overrides['EUR']` value is retained for backward-data preservation and
is ignored at presentation time (ADR-0037).

Supranational / ambiguous ISO codes (`XOF`, `XAF`, `XCD`, `XPF`, `XDR`, `XAU`,
`XAG`, `XPT`, `XPD`) have no default flag.

## Security review checklist

Each packaged SVG is inspected for:

- No `<script>` elements
- No `foreignObject`
- No remote `xlink:href` / `href` references
- No event handler attributes
- No embedded executable metadata required at runtime

## Registry

Only identifiers registered in `UMC\Display\CurrencyPresentationAssetRegistry`
resolve to files. Arbitrary two-letter strings are rejected even if a file exists
on disk.
