# Switcher customization guide

Merchant and developer reference for Universal Multicurrency storefront switcher
presentation: structured design and layout (M17), presentation icons (M22),
the native Gutenberg block (M23), and selector presentation presets (v1.3 /
ADR-0035).

Authoritative architecture: [`docs/architecture/switcher-customization.md`](architecture/switcher-customization.md),
[`docs/architecture/switcher-currency-presentation.md`](architecture/switcher-currency-presentation.md),
[`docs/architecture/native-switcher-block.md`](architecture/native-switcher-block.md),
[`docs/architecture/switcher-presentation-presets.md`](architecture/switcher-presentation-presets.md),
[`docs/architecture/switcher-uml-family-alignment.md`](architecture/switcher-uml-family-alignment.md)
ADRs: [`0022`](adr/0022-switcher-customization-css-contract.md),
[`0027`](adr/0027-switcher-currency-presentation.md),
[`0028`](adr/0028-native-switcher-block-rendering-surface.md),
[`0035`](adr/0035-switcher-presentation-presets.md),
[`0037`](adr/0037-switcher-uml-family-alignment.md)

---

## Structured Design (no CSS required)

In **WooCommerce → Settings → Multicurrency → Display**:

1. **Placement** — manual shortcode / native block, floating side, sticky footer
2. **Selector style** — Edge Pill / Floating Card / Minimal Icon / Tab / Classic Dropdown / Sticky Footer (filtered by placement)
3. **Content** — trigger vs menu visibility (code / symbol / name / icon), order, chevron
4. **Design** — theme (including Brand), size, shape (including Square), motion, colors, spacing; legacy token preset remains available under progressive disclosure
5. **Currency presentation icons** — optional bundled flags, size, shape, per-currency overrides (M22). EUR always uses the European Union flag and cannot be remapped. This is the top-level `display.presentation` icon subtree — not `design.presentation`.
6. **Mobile behaviour** (floating only) — Side selector / Bottom sheet / Compact sticky bar
7. **Advanced** — Custom CSS (capability-gated)

Live admin preview updates **structured** controls only, including Collapsed/Open
and Desktop/Mobile frames. Advanced Custom CSS applies on the **storefront after
save** — verify there, not in wp-admin.

---

## Presentation precedence

1. Plugin base styles
2. Layout modifiers (`--floating-side`, `--floating-bottom`, …)
3. Selector presentation geometry (`--presentation-edge-pill`, …)
4. Legacy preset token tweaks (`--preset-*` — still live under presentation)
5. Theme / size / shape
6. Structured overrides (CSS variables)
7. Responsive / mobile behaviour
8. Reduced motion / print
9. Advanced Custom CSS (last)

Choosing a new selector style does **not** clear `design.preset` or Custom CSS.
Legacy `--preset-*` classes remain on the root for merchant CSS compatibility.

---

## Public CSS selectors (stable)

```css
.umc-switcher
.umc-switcher__trigger
.umc-switcher__trigger-content
.umc-switcher__code
.umc-switcher__symbol
.umc-switcher__name
.umc-switcher__icon
.umc-switcher__icon img
[data-umc-icon-type="flag"]
.umc-switcher__chevron   /* only when enabled */
.umc-switcher__panel
.umc-switcher__close     /* sheet chrome; hidden until sheet mode */
.umc-switcher__sheet-title
.umc-switcher__menu
.umc-switcher__list
.umc-switcher__item
.umc-switcher__link
.umc-switcher__item.is-active
.umc-switcher__link[aria-current="true"]
```

Modifiers (settings-driven): `--dropdown`, `--horizontal-list`, `--manual`,
`--floating-side`, `--floating-bottom`, `--side-*`, `--align-*`,
`--presentation-*`, `--theme-*`, `--size-*`, `--shape-*`, `--preset-*`,
`--icon-size-*`, `--icon-shape-*`, `--mobile-retain`, `--mobile-bottom-sheet`,
`--mobile-sticky-compact`, `--hide-mobile`, `--hide-desktop`,
`--hide-name-on-mobile`, `--compact-on-mobile`, `--uml-family`.

UML-family floating presentations (`edge_pill`, `minimal_icon`, `tab` on
`floating_side`) use a 781px / 782px visibility boundary, flush-edge docking,
and `data-um-edge-*` attributes. See
[`docs/EDGE_CONTROL_CONVENTION.md`](EDGE_CONTROL_CONVENTION.md) and
[ADR-0037](adr/0037-switcher-uml-family-alignment.md).
Classic, manual, and block surfaces keep the existing 767px / 768px bag.

Public data hooks: `[data-umc-placement]`, `[data-umc-style]`,
`[data-umc-presentation]`, `[data-umc-mobile-behavior]`.

### Internal (do not rely on)

`.umc-switcher--open`, `.umc-switcher--open-up`, `.umc-switcher--sheet`,
`.umc-switcher--mobile-sheet`, `.umc-switcher--expanded`,
`.umc-switcher__backdrop`, `.umc-switcher__sheet-divider`,
preview-only classes, instance element IDs.

Flags / `__option` / `--active` BEM modifiers are **not** part of this contract.

---

## Public CSS variables

Prefer `--umc-switcher-*` names (font, trigger, hover, open, menu, item,
selected, focus-ring, gap, transition, offsets, z-index, panel-width, accent).

Legacy aliases from v0.15 (`--umc-surface`, `--umc-text`, `--umc-border`,
`--umc-hover`, `--umc-selected-bg`, `--umc-focus-ring`, `--umc-radius`,
`--umc-control-height`, `--umc-spacing`, offset/z-index vars) remain mapped for
compatibility.

---

## Advanced Custom CSS

Requires WordPress `edit_css` **and** permission to save Multicurrency Display
settings. Without `edit_css`, the field is locked and the server **preserves**
any previously stored CSS when other Display settings are saved.

Custom CSS is **not** automatically scoped to the switcher. Prefer selectors
under `.umc-switcher` so you do not restyle the rest of the site.

Rejected: `@import`, any `url(...)`, backslash escape sequences (for example
`\2014`), `expression(`, `behavior:`, `-moz-binding`, raw `<` / `>`, NUL, and
style/script breakout payloads. A rejected save keeps the previously stored
Custom CSS and shows an admin error notice. A rejected submission is
discarded whole and your last saved CSS is kept.

Custom CSS is printed after the plugin stylesheet with
`wp_add_inline_style( 'umc-switcher', … )`, on the storefront only, while that
stylesheet is enqueued. A shortcode that renders after the page has already
printed its styles falls back to a plain stylesheet link and omits Custom CSS on
that request; place such switchers in normal content or use automatic placement.

Example:

```css
.umc-switcher__trigger {
	border-radius: 999px;
	transition: transform 150ms ease;
}

.umc-switcher__trigger:hover {
	transform: translateY(-2px);
}

.umc-switcher__link:hover {
	background: #f3f3f3;
}

@media (max-width: 767px) {
	.umc-switcher__name {
		display: none;
	}
}
```

Accessibility: Custom CSS can remove focus styles or shrink targets. Keep
`:focus-visible` visible.

---

## Shortcode

`[universal_multicurrency_switcher]` (alias `[umc_switcher]`). Presentation is
global; shortcode attributes do not override design in M17. Shortcode instances
continue to inherit the store’s global Display settings (placement included).

---

## Native block (M23)

Block name: `universal-multicurrency/currency-switcher` (Category: **Widgets**).

Insert from the block inserter in pages, posts, or block-theme template parts.
**All visual design** is configured under **WooCommerce → Settings → Multicurrency →
Display** — the block has no local color/typography/icon designer.

Embedded block instances always render as **manual/inline** surfaces even when
global automatic floating/sticky placement is enabled, so a page may show both
an inline block and the global floating/sticky switcher.

Multiple switchers on one page (block + shortcode + automatic) share the same
shopper currency state and use unique instance IDs for accessibility.

---

## Multiple instances

Multiple shortcodes plus one automatic floating switcher may appear. Style rules
apply to all `.umc-switcher` roots unless you target `data-umc-placement`,
`data-umc-presentation`, or modifier classes. Opening one non-sheet switcher
closes other open non-sheet switchers; only one sheet dialog may be open at a
time.
