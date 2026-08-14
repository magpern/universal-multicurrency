# Switcher Currency Presentation (Milestone 22)

**Status:** Authoritative implementation specification for Milestone 22
(**v0.21.0**).

**Branch:** `feature/m22-switcher-currency-presentation`

**ADR:** [ADR-0027](../adr/0027-switcher-currency-presentation.md)

**Builds on:** [M17 switcher customization](switcher-customization.md) (ADR-0022)

This document materializes the approved M22 plan. M17 remains authoritative for
switcher renderer architecture, content ordering, presets, responsive styling,
Custom CSS, and the stable public CSS contract. M22 adds one optional semantic
content element: **`icon`**.

---

## 1. Product objective

Add optional bundled presentation flags/images to the existing M17 switcher
without redesigning it. Icons are **visual presentation only** — they do not
affect currency routing, Visitor Location, checkout, or reporting.

---

## 2. Baseline (post-M21)

| Item | Value |
|---|---|
| Prior release | M21 / **v0.20.0** |
| Baseline commit | `1dc87c8b0a64493eb1b3f554609a2f92631f02a1` |
| Settings schema | **6 → 7** |
| Persisted inventory | **10** (unchanged) |
| Order snapshot | **5** (unchanged) |
| DB migration | None |

---

## 3. Non-negotiable rules

1. **`icon` is a bundled local presentation image** — not a symbol renderer,
   not an upload, not a remote URL, not emoji, not geo routing.
2. **`symbol` remains the only currency-symbol path** (M17 unchanged).
3. **Both required to render:** `show_icon === true` **and** `"icon" ∈ order[]`.
4. **Migration defaults:** `show_icon = false` for trigger and menu; **do not**
   inject `icon` into existing `order[]`.
5. **No icon-only mode:** at least `code` or `name` must remain for accessible
   currency identity.
6. **Currency ≠ country:** mappings are **presentation-region identifiers** only.
7. **Built-in defaults are not persisted** — merchants store overrides only.
8. **Disabled-currency override retention** (M20 pattern): overrides survive
   currency disable/re-enable.
9. **Registry-only asset resolution** — no path traversal, no arbitrary regions.
10. **One semantic renderer** for shortcode, floating, sticky, and admin preview.
11. **No Gutenberg block, no Media Library icons, no CDN.**

---

## 4. Settings schema 7 (`display.presentation` + content)

```text
presentation.icon_overrides = { [currency_code]: presentation_region_id }
presentation.icon_size = compact | standard | large
presentation.icon_shape = natural | square | circle

content.trigger.show_icon = false   (default)
content.menu.show_icon = false      (default)
```

Existing `content.*.order[]`, design, behavior, visibility, responsive, and
Custom CSS are unchanged by migration defaults.

---

## 5. Visibility + order composition

| Condition | Icon renders? |
|---|---|
| `show_icon = false` | Never |
| `show_icon = true`, `icon` absent from `order[]` | Never |
| Both true | May render when effective mapping + asset exist |

Admin UI synchronizes toggle and order on save. Normalization repairs
inconsistent legacy/manual settings without implicit placement outside `order[]`.

---

## 6. Built-in presentation defaults (non-persisted)

| Currency | Default region |
|---|---|
| SEK | SE |
| DKK | DK |
| NOK | NO |
| PLN | PL |
| GBP | GB |
| EUR | EU |
| USD | US |
| CHF | CH |

Effective mapping: `merchant override ?? built-in default ?? none`.

EUR defaults to **EU**, not a member-state flag.

---

## 7. Asset registry

`CurrencyPresentationAssetRegistry` maps allowed presentation-region identifiers
to packaged SVG files under `assets/icons/presentation/`. Only registry entries
resolve. Unknown mappings gracefully omit the icon (no broken image, no API).

Asset provenance and license: [`docs/assets/PRESENTATION_FLAGS.md`](../assets/PRESENTATION_FLAGS.md).

---

## 8. Public CSS contract (extensions only)

New stable selectors:

- `.umc-switcher__icon`
- `.umc-switcher__icon img`
- `[data-umc-icon-type="flag"]`

New CSS custom properties:

- `--umc-switcher-icon-size`
- `--umc-switcher-icon-radius`
- `--umc-switcher-icon-gap`

M17 selectors and variables remain stable.

---

## 9. Icon shape semantics

| Shape | Behavior |
|---|---|
| `natural` | Preserve native flag aspect ratio |
| `square` | Square container, `object-fit: cover` |
| `circle` | Square crop + full border radius |

Flags must not be stretched.

---

## 10. Accessibility

Icons are decorative when shown alongside `code` or `name`. Use
`aria-hidden="true"` on images. Do not rely on flag alt text as sole identity.

---

## 11. Preview anti-drift

PHP emits canonical switcher markup via `SwitcherRenderer`. JavaScript may toggle
classes, attributes, text, and icon visibility/src on that structure. JavaScript
must not rebuild a parallel switcher DOM tree.

---

## 12. Upgrade invariant

Sites upgrading from pre-M22 settings render the same switcher by default because
icons are off and `order[]` is untouched.

---

## 13. Out of scope (M23+)

- Custom Media Library icons
- Gutenberg block / widget
- Per-breakpoint icon sizing
- Visitor Location coupling
- Product/order persistence changes
