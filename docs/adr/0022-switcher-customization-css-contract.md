# ADR-0022: Switcher Customization & CSS Contract

**Status:** Accepted (Milestone 17, target v0.16.0)

**Related:** [`docs/architecture/switcher-customization.md`](../architecture/switcher-customization.md),
[`docs/SWITCHER_CUSTOMIZATION.md`](../SWITCHER_CUSTOMIZATION.md),
Display M1 / schema v3 switcher stack

## Context

Milestone 9 shipped a storefront currency switcher with placement, theme/size/
shape enums, content toggles (code/symbol/name), and an in-page admin preview.
Labels are plain text; there is no Custom CSS, no element ordering, and no
documented public CSS contract.

Merchants need structured presentation controls and an advanced CSS escape hatch
without turning Universal Multicurrency into a page builder, and without
changing currency-selection semantics.

## Decision

### One semantic DOM

All placements (shortcode, floating side, floating bottom) and admin preview
share one renderer. Presets are CSS modifier layers, not separate templates.

### Layered presentation

```text
base CSS → preset class → theme/size/shape → sparse overrides → responsive → Custom CSS
```

### Lossless schema 5→6 migration

- Always migrate `design.preset = default`.
- Copy legacy `appearance.theme|size|shape` into first-class `design.*` fields.
- `design.overrides = {}` on migration.
- Never map `shape=pill` → `preset=pill` during migration.
- `--preset-default` is a visual/token **no-op** relative to base + theme/size/shape.

### Structured overrides → CSS variables

Allowlisted token keys map to `--umc-switcher-*` custom properties. No generic
CSS compiler and no filesystem-generated stylesheets.

### Custom CSS (Model A)

- Merchants write full selectors (recommend `.umc-switcher` prefixes).
- Custom CSS is **not** technically scoped; the product must not claim isolation.
- Requires Display-save authority **and** `edit_css`.
- Server ignores unauthorized `custom_css` mutations and preserves stored CSS
  exactly (including forged POST replace/clear and omitted fields).
- Emitted storefront-only via `wp_add_inline_style( 'umc-switcher', … )` when the
  stylesheet is enqueued; never injected into ordinary wp-admin or the in-page
  preview.
- No iframe preview subsystem for Custom CSS.
- Reject NUL, raw `<`/`>`, breakout payloads, `@import`, **all** `url(...)`,
  `expression(`, `behavior:`, `-moz-binding`. Length-capped.
- Threat model: hard breakout prevention + best-effort denylist + trusted-author
  CSS behind `edit_css` — not a complete CSS security parser.

### PUBLIC vs INTERNAL CSS contract

**PUBLIC/STABLE:** `.umc-switcher`, `__trigger`, `__trigger-content`, `__code`,
`__symbol`, `__name`, `__chevron` (when enabled), `__menu`, `__list`, `__item`,
`__link`, settings-driven modifiers (placement/style/theme/size/shape/preset/
visibility), `.is-active`, `[aria-current="true"]`, `data-umc-placement`,
`data-umc-style`, documented `--umc-switcher-*` variables, and legacy `--umc-*`
aliases shipped in v0.15 for compatibility.

**INTERNAL/RUNTIME:** `--open`, `--open-up`, `--expanded`, preview classes /
`data-umc-preview-*` / `data-umc-display-*`, instance element IDs, JS plumbing.

**Do not introduce as public:** `__option`, `--active` BEM modifier, `__flag`.

### Responsive strategy

Small override bag only (not a full breakpoint designer).

### Accessibility

Preserve keyboard behavior, ARIA, focus-visible, semantic DOM order matching
visual order, non-empty trigger guardrail. No control to disable required focus
indication. Custom CSS may break a11y; document the risk.

### Flags deferred

No currency→country map, emoji/SVG flags, `__flag`, or flag tokens in M17.

### Chevron defaults

`content.show_chevron = false` for migrated **and** fresh schema-6 installs.
Explicit Content UI opt-in only.

## Consequences

- Settings schema bumps to **6**; PersistedKeys inventory stays **8**.
- Merchants with `edit_css` can restyle switchers powerfully; shop managers
  without `edit_css` retain structured Design controls only.
- Existing v0.15 appearance must not drift solely due to migration.
- Future icon/flag systems remain possible via extensible element ordering
  without pre-designing them now.
