# Switcher Customization & Presentation (Milestone 17)

**Status:** Authoritative implementation specification for Milestone 17
(**v0.16.0**).

**Branch:** `feature/m17-switcher-customization`

**ADR:** [ADR-0022](../adr/0022-switcher-customization-css-contract.md)

**Merchant / developer CSS guide:** [`docs/SWITCHER_CUSTOMIZATION.md`](../SWITCHER_CUSTOMIZATION.md)

This document materializes the externally approved M17 plan. Production
implementation must follow this specification. Working drafts under untracked
`docs/plans/` are not source of truth (`ReleaseAuditTest` forbids tracked
`docs/plans/`).

---

## 1. Product objective

Turn the existing storefront currency switcher into a layered presentation
system:

1. Base component CSS
2. Named preset class (`default` is a no-op)
3. First-class `theme` / `size` / `shape`
4. Sparse structured overrides → CSS custom properties
5. Small responsive override bag
6. Advanced Custom CSS (storefront only; capability-gated)

Currency-selection semantics are unchanged. M17 is presentation only.

---

## 2. Baseline

| Item | Value |
|---|---|
| Prior release | M16 / **v0.15.0** |
| Baseline commit | `b9e2f9c683af9a35ae7b05b6645c2f54446d21d0` |
| Settings schema | **5 → 6** |
| Persisted inventory | **8** (unchanged) |
| Order snapshot | **4** (unchanged) |
| DB migration | None |

---

## 3. Non-negotiable rules

1. One semantic renderer / DOM for all placements.
2. Presets are CSS layers, not duplicate templates.
3. Migration always sets `design.preset = default`.
4. Legacy `theme` / `size` / `shape` remain first-class; never collapsed into a
   named preset during migration.
5. `--preset-default` must be a visual/token no-op relative to base +
   theme/size/shape.
6. Flags are deferred (no `show_flag`, no country map, no `__flag`).
7. Chevron defaults **false** for migrated and fresh installs.
8. Custom CSS uses Model A full selectors and is **not** technically scoped.
9. Custom CSS requires Display-save authority **and** `edit_css`.
10. Unauthorized submissions preserve existing Custom CSS exactly.
11. Never inject merchant Custom CSS into ordinary wp-admin / in-page preview.
12. Reject all `url(...)` and `@import` in Custom CSS.
13. Emit Custom CSS only via `wp_add_inline_style( 'umc-switcher', … )` on
    storefront when the stylesheet is enqueued.
14. No filesystem CSS compiler, no iframe preview subsystem, no Custom JS/HTML.
15. No changes to Visitor Location, checkout policy, Decision Inspector, or
    order snapshot schema.

---

## 4. Settings schema 6 (`display`)

```text
enabled, placement, style, position.*, behavior.*, visibility.*
content.trigger.{show_code,show_symbol,show_name,order[]}
content.menu.{show_code,show_symbol,show_name,order[]}
content.show_chevron
design.preset
design.theme | design.size | design.shape
design.overrides{}
design.motion
responsive.*
custom_css
```

`appearance` is removed from normalized schema-6 output. One-release read alias:
if `design.theme|size|shape` absent and legacy `appearance.*` present, map into
design fields during `from_array`.

---

## 5. Migration `5 → 6`

```text
design.preset    = default
design.theme     = legacy appearance.theme
design.size      = legacy appearance.size
design.shape     = legacy appearance.shape
design.overrides = {}
design.motion    = subtle
content.trigger  = code/symbol from legacy; show_name = false
content.menu     = code/symbol/name from legacy
content.show_chevron = false
responsive       = defaults
custom_css       = ''
enabled/placement/style/position/behavior/visibility unchanged
```

27-cell theme×size×shape matrix must preserve independent enums and effective
cascade (shape wins radius over size when slight/pill).

---

## 6. Markup contract

Public elements: root, `__trigger`, `__trigger-content`, `__code`, `__symbol`,
`__name`, `__chevron` (when enabled), `__menu`, `__list`, `__item`, `__link`.

DOM order follows merchant `order[]`. No CSS `order`. No `__flag`, `__option`,
or `--active` BEM modifier.

---

## 7. Presentation precedence

```text
base switcher.css
→ .umc-switcher--preset-{id}
→ --theme / --size / --shape
→ sparse overrides → CSS variables
→ responsive utilities
→ Custom CSS (storefront last)
```

---

## 8. Custom CSS

| Concern | Rule |
|---|---|
| Capability | `manage_woocommerce` ∧ `edit_css` |
| Unauthorized POST | Ignore mutation; preserve stored CSS; save other fields |
| Preview | Never inject into wp-admin |
| Output | `wp_add_inline_style` after structured vars |
| Reject | NUL, `<>`, breakout, `@import`, all `url(...)`, `expression(`, `behavior:`, `-moz-binding` |
| Scope | Not enforced; recommend `.umc-switcher` prefixes |

---

## 9. Admin UX

Display sub-navigation: Placement | Content | Design | Advanced.

Live preview: structured controls only. Advanced CSS verified on storefront
after save.

---

## 10. Work packages

WP0 docs → WP1 characterization → WP2 schema → WP3 markup → WP4 tokens →
WP5 presets → WP6 design controls → WP7 responsive → WP8 Custom CSS →
WP9 admin UI → WP10 frontend → WP11 a11y → WP12 docs → WP13 release 0.16.0.

---

## 11. Version

**0.16.0** · Settings schema **6** · PersistedKeys **8** · Order snapshot **4**.
