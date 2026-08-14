# Switcher customization guide

Merchant and developer reference for Universal Multicurrency storefront switcher
presentation (Milestone 17 / v0.16.0).

Authoritative architecture: [`docs/architecture/switcher-customization.md`](architecture/switcher-customization.md)
ADR: [`docs/adr/0022-switcher-customization-css-contract.md`](adr/0022-switcher-customization-css-contract.md)

---

## Structured Design (no CSS required)

In **WooCommerce → Settings → Multicurrency → Display**:

1. **Placement** — manual shortcode, floating side, floating bottom
2. **Content** — trigger vs menu visibility (code / symbol / name / icon), order, chevron
3. **Design** — preset, theme, size, shape, colors, spacing, motion, responsive bag
4. **Currency presentation icons** — optional bundled flags, size, shape, per-currency overrides (M22)
5. **Advanced** — Custom CSS (capability-gated)

Live admin preview updates **structured** controls only. Advanced Custom CSS
applies on the **storefront after save** — verify there, not in wp-admin.

---

## Presentation precedence

1. Plugin base styles
2. Selected preset (Default is a no-op relative to theme/size/shape)
3. Theme / size / shape
4. Structured overrides (CSS variables)
5. Responsive structured overrides
6. Advanced Custom CSS (last)

Changing preset does not silently clear overrides or Custom CSS.

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
.umc-switcher__menu
.umc-switcher__list
.umc-switcher__item
.umc-switcher__link
.umc-switcher__item.is-active
.umc-switcher__link[aria-current="true"]
```

Modifiers (settings-driven): `--dropdown`, `--horizontal-list`, `--manual`,
`--floating-side`, `--floating-bottom`, `--side-*`, `--align-*`, `--theme-*`,
`--size-*`, `--shape-*`, `--preset-*`, `--icon-size-*`, `--icon-shape-*`, `--hide-mobile`, `--hide-desktop`,
`--hide-name-on-mobile`, `--compact-on-mobile`.

The two responsive modifiers take effect below 768px only.

Optional hooks: `[data-umc-placement]`, `[data-umc-style]`.

### Internal (do not rely on)

`.umc-switcher--open`, `.umc-switcher--open-up`, `.umc-switcher--expanded`,
preview-only classes, instance element IDs.

Flags / `__option` / `--active` BEM modifiers are **not** part of this contract.

---

## Public CSS variables

Prefer `--umc-switcher-*` names (font, trigger, hover, open, menu, item,
selected, focus-ring, gap, transition, offsets, z-index).

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
global; shortcode attributes do not override design in M17.

---

## Multiple instances

Multiple shortcodes plus one automatic floating switcher may appear. Style rules
apply to all `.umc-switcher` roots unless you target `data-umc-placement` or
modifier classes.
