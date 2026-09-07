# Switcher Presentation Presets (v1.3.0)

**Status:** Authoritative implementation specification for **v1.3.0** —
planning freeze. Production implementation must follow this document and
[ADR-0035](../adr/0035-switcher-presentation-presets.md).

**Branch:** `feature/display-edge-pill-selector`

**ADR:** [ADR-0035](../adr/0035-switcher-presentation-presets.md)

**Builds on:** [Switcher customization (ADR-0022)](switcher-customization.md),
[Currency presentation (ADR-0027)](switcher-currency-presentation.md),
[Native switcher block (ADR-0028)](native-switcher-block.md)

Working drafts under untracked `docs/plans/` are not source of truth
(`ReleaseAuditTest` forbids tracked `docs/plans/`).

---

## 1. Product objective

Redesign the floating currency selector into curated presentation presets
(Edge Pill recommended for new Floating setups) while preserving one shared
renderer/settings stack and the canonical `?currency=` switching flow.

---

## 2. Baseline

| Item | Value |
|---|---|
| Prior release | **v1.2.1** |
| Baseline commit | `be18f2d629caec5dfd45d2b459fe31cfb4178b8a` (`origin/main`) |
| Settings schema | **7 → 8** |
| Persisted inventory | Unchanged |
| Order snapshot | Unchanged (schema 5) |
| CacheState | Unchanged — presentation not in `state_hash` |
| DB migration | None (settings option rewrite only) |

---

## 3. Non-negotiable rules

1. One semantic renderer / DOM for shortcode, floating, sticky, block, and preview.
2. Presentation presets are CSS + one JS controller — not separate templates.
3. Migration **never** auto-converts existing floating stores to Edge Pill.
4. Legacy `design.preset` remains sanitized and emitted; token cascade stays live under presentation geometry.
5. New keys live under `design.presentation` — never confuse with `display.presentation` (icons).
6. `sticky_compact` is floating mobile behaviour only; never changes `placement`.
7. Disclosure for collapsed / popover / Edge Pill expand; listbox roles forbidden.
8. Sheet mode uses the dialog contract in §7 — no native `<dialog>` / `showModal()`.
9. No AJAX/REST switch, no nonce on `?currency=`, no SPA refresh.
10. No FOX/WOOCS code, names, markup, assets, or settings.
11. Shortcode continues to inherit global placement; block forces manual via `for_embedded_surface()`.
12. Custom CSS Model A (ADR-0022) unchanged; no iframe Custom CSS preview.
13. `prefers-reduced-motion: reduce` always wins over merchant motion.
14. No-JS: trigger hidden, static currency link list remains usable.

---

## 4. Architecture

```text
umc_settings.display.*  (SwitcherSettings, schema 8)
        ↓
SwitcherSettingsRepository
        ↓
SwitcherViewModelFactory → SwitcherOptionFactory
        ↓                    → SwitcherElementComposer
        ↓                    → SwitcherLabelFormatter
SwitcherViewModel
        ↓
SwitcherRenderer  (one semantic DOM)
        ↓
 shortcode | AutomaticSwitcherPlacement | SwitcherBlock | admin preview
        ↓
SwitcherAssets (umc-switcher CSS/JS)
        ↓
<a href="?currency=CODE"> → CurrencySwitcher::maybe_switch()
```

### Class ownership

| Concern | Class |
|---|---|
| Settings / sanitize / coercion | `SwitcherSettings` |
| Option orchestration | `SwitcherOptionFactory` (existing — do not add another factory) |
| Part selection / Minimal Icon fallback | `SwitcherElementComposer` |
| Accessible names | `SwitcherLabelFormatter` |
| Markup | `SwitcherRenderer` |
| Storefront behaviour | `assets/js/switcher.js` (one IIFE controller) |

---

## 5. Settings schema 8 (`display`)

### New / changed keys

| Key | Allowed | Notes |
|---|---|---|
| `design.presentation` | `edge_pill`, `floating_card`, `minimal_icon`, `classic_dropdown`, `sticky_footer` | Coerced to a value legal for `placement` |
| `design.preset` | existing six | Deprecated as selector-style UI; **live token layer** |
| `design.theme` | `automatic`, `light`, `dark`, `brand` | UI: Site theme / Light / Dark / Brand |
| `design.motion` | `standard`, `reduced`, `off` | Read aliases: `subtle`→`standard`, `none`→`off` |
| `design.shape` | `slight`, `rounded`, `pill`, `square` | Hidden for Edge Pill geometry |
| `responsive.mobile_behavior` | `retain`, `bottom_sheet`, `sticky_compact` | Ignored unless `placement=floating_side` |

### Placement × presentation matrix

| Placement | Allowed presentations |
|---|---|
| `manual` | `classic_dropdown` (+ `style=horizontal_list`) |
| `floating_side` | `edge_pill`, `floating_card`, `minimal_icon`, `classic_dropdown` |
| `sticky_footer` | `sticky_footer` only |

### Save-time coercion

- Manual + floating-only presentation → `classic_dropdown`
- Sticky footer → `presentation=sticky_footer`, `style=dropdown`
- Floating + `sticky_footer` presentation → `edge_pill`
- Automatic placement → `style=dropdown` (unchanged)

### Migration 7 → 8 (visually neutral)

```text
design.presentation =
  sticky_footer     if placement === sticky_footer
  floating_card     if placement === floating_side AND preset === floating
  minimal_icon      if placement === floating_side AND preset === minimal
  classic_dropdown  otherwise

responsive.mobile_behavior =
  bottom_sheet if presentation in (edge_pill, minimal_icon)
  retain       otherwise

design.motion: subtle → standard, none → off
design.preset, content.*, position.*, visibility.*, custom_css, presentation.icon_*: unchanged
```

**New Floating selection (admin JS):** if merchant selects Floating while
presentation is still `classic_dropdown`, set `edge_pill`, `edge_offset=0`,
`mobile_behavior=bottom_sheet`.

**Canonical regression:** a real schema-7 floating fixture (custom preset,
offsets, content order, custom CSS) upgraded to 8 must render equivalent
public markup/classes/CSS variables except additive presentation/dialog chrome,
and must keep `.umc-switcher--preset-{original}`.

---

## 6. Markup contract (additive)

Public elements from ADR-0022/0027 remain. Dropdown markup gains:

```html
<div class="umc-switcher … umc-switcher--presentation-{id} umc-switcher--preset-{legacy}"
     data-umc-placement="…" data-umc-style="…"
     data-umc-presentation="…" data-umc-mobile-behavior="…">
  <button type="button" class="umc-switcher__trigger"
          id="umc-switcher-trigger-N"
          aria-expanded="false"
          aria-controls="umc-switcher-panel-N"
          aria-label="Currency: Swedish krona, SEK">
    <span class="umc-switcher__trigger-content">…</span>
  </button>
  <div class="umc-switcher__backdrop" hidden aria-hidden="true"></div>
  <div class="umc-switcher__panel" id="umc-switcher-panel-N">
    <h2 class="umc-switcher__sheet-title" id="umc-switcher-title-N" hidden>…</h2>
    <button type="button" class="umc-switcher__close" hidden>…</button>
    <span class="umc-switcher__sheet-divider" hidden aria-hidden="true"></span>
    <ul class="umc-switcher__menu" id="umc-switcher-menu-N" hidden>…</ul>
  </div>
</div>
```

- `data-umc-presentation` is **PUBLIC**.
- `__backdrop` and `__sheet-divider` are **INTERNAL**.
- `__close` / `__panel` / `__sheet-title` are documented sheet chrome.
- Collapsed HTML must **not** carry `role="dialog"`.
- Do not introduce `__flag`, `--active`, listbox roles, or native `<dialog>`.

---

## 7. Bottom-sheet dialog contract

While sheet mode is active on **this** instance’s `__panel`:

1. Set `role="dialog"`, `aria-modal="true"`, `aria-labelledby` → `__sheet-title`.
2. Show visible Close button with accessible name.
3. Move focus into the dialog (Close first).
4. Trap Tab among Close + this panel’s currency links only.
5. Backdrop: visible, `aria-hidden="true"`, no tabindex; pointer closes.
6. Escape closes; restore focus to this trigger; strip dialog attributes.
7. Decorative divider only — no drag affordance.

### Multiple instances

- Opening a non-sheet switcher closes other open non-sheet switchers.
- Opening a sheet closes every other open switcher. One sheet at a time.
- Unique trigger / panel / menu / title ids per render instance.
- Sheet trap never includes another instance’s controls.

---

## 8. Presentation behaviour summary

| Presentation | Closed | Open | Mobile |
|---|---|---|---|
| Edge Pill | Flush edge tab | Expand inward | Default bottom sheet |
| Floating Card | Inset floating control | Popover; flip vertical if needed | Persist `retain`; **horizontal overflow → sheet** |
| Minimal Icon | Compact control | Popover / sheet as Card | Default bottom sheet |
| Classic Dropdown | Current dropdown | Current popover | Retain unless merchant opts in |
| Sticky Footer | Viewport-bottom bar | Expand up / sheet on narrow | Always bottom |

### Minimal Icon visible face

1. Flag only if icon URL resolves.
2. Else unambiguous symbol (not in duplicate-symbol map).
3. Else ISO code.
4. Accessible label always `Currency: {name}, {code}`.

---

## 9. CSS cascade

```text
base tokens → elements → layout (floating-side / floating-bottom)
→ presentation (geometry, sheet, flush tab)
→ legacy preset (token tweaks — still live)
→ theme / size / shape → overrides → responsive / mobile_behavior
→ reduced-motion → print → Custom CSS
```

Z-index: Classic floating keeps `9990` for visual neutrality. New
presentations use lower curated tokens (floating ~1000, sticky ~40, sheet
backdrop/panel ~1090/1100). Breakpoint remains **768px**.

Strengthen `.umc-switcher button` / `a` resets so theme button CSS cannot
restyle the control. Do not use `all: revert` on the root.

---

## 10. JavaScript controller

One IIFE (`umc-switcher`), no globals, no build step.

- Strategies: `expand` | `popover` | `sheet`
- Horizontal-fit check for Floating Card / Minimal Icon with `retain`
- Single delegated document click listener
- Honour `prefers-reduced-motion` and merchant motion
- Preview roots: do not fight admin Collapsed/Open; keep `#` links inert
- No currency-switch logic in JS

---

## 11. Admin Display IA

Subnav: Placement → Appearance → Design → Advanced.

Progressive disclosure by placement/presentation. Live in-page preview with
Collapsed/Open and Desktop/Mobile (mobile frame must apply sheet class
explicitly — admin is not a real 768px viewport). Floating→Edge Pill
defaulting in admin JS only. Preserve inactive position values on save.
English UI strings localized via PHP / `umcDisplayPreview` — no hard-coded
English in admin JS.

---

## 12. Work packages

| WP | Scope |
|---|---|
| WP0 | This ADR + architecture + ROADMAP (docs only) |
| WP1 | Schema 8 + migration + settings tests |
| WP2 | ViewModel / renderer / composer / labels |
| WP3 | Presentation CSS |
| WP4 | Storefront JS controller |
| WP5 | Admin Display + preview |
| WP6 | Surface regression |
| WP7 | Merchant CSS guide + POT |
| WP8 | Full gates + Playwright acceptance |

---

## 13. Explicit non-goals

Second renderer; AJAX/REST switching; drag-and-drop positioning; per-device
designs; arbitrary Design CSS fields; Media Library icons; native `<dialog>`;
FOX/WOOCS coupling; CacheState hash changes; OrderSnapshot / PersistedKeys
bumps; M27; `docs/plans/` in the repository.
