# Edge-control convention

Minimal documented interoperability for optional edge-docked visitor controls.
This is **not** a shared package, runtime discovery protocol, or collision engine.

Universal Multilingual implements the language control. Universal Multicurrency
implements the currency control independently. Neither plugin should read the
other's private attributes.

Copied from the released UML v1.12.0 contract and completed for UMC (ADR-0037).
UML remains a visual reference only; this file is UMC-owned.

## Root attributes

| Attribute | UML language | UMC currency |
|---|---|---|
| `data-um-edge-control` | `language` | `currency` |
| `data-um-edge` | `left` or `right` (physical) | `left` or `right` |
| `data-um-edge-slot` | `1` | `2` |
| `data-um-edge-priority` | `10` | `20` |

## Shared CSS variables (optional)

Plugins MAY set these on their own root. They are conventions, not a required runtime API.

| Variable | Suggested default | Purpose |
|---|---|---|
| `--um-edge-control-size` | `2.75rem` (~44px) | Minimum pointer target |
| `--um-edge-stack-gap` | `0.5rem` | Gap if two controls share an edge |
| `--um-edge-z-index` | `1000` | Below the WordPress admin bar |

Do **not** attempt to outrank the admin bar (`--wp-admin--admin-bar--height`).

## Same-edge stacking (UMC CSS only)

When another edge control occupies the same physical side, UMC slot 2 offsets
by one control-size plus one gap:

```css
calc(var(--um-edge-control-size, 2.75rem) + var(--um-edge-stack-gap, 0.5rem))
```

Detection is ancestor-rooted (`body:has(...)`). Direction:

- **top** — slot 2 moves down
- **bottom** — slot 2 moves up
- **middle** — slot 2 moves down (`translateY(calc(-50% + OFFSET))` on the root)

Browsers without `:has()` apply no slot offset. Possible overlap on the same
physical edge is an accepted degradation. There is no JS collision engine and
no UML runtime discovery.

## Private attributes (do not reuse)

- UML may use `data-aiml-*` and `.aiml-floating-selector*` privately.
- UMC may use `data-umc-*` and `--umc-switcher-*` privately.
