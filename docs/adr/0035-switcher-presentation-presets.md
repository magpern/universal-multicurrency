# ADR-0035 — Switcher Presentation Presets (Edge Pill / Floating Redesign)

## Status

Accepted (post-1.0 feature release, target **v1.3.0**). Planning freeze for
implementation on `feature/display-edge-pill-selector`.

## Relationship to prior ADRs

ADR-0031 §6 remains honored: this is **not** M27 and does not reopen the
closed M0–M26 roadmap. It is a focused feature release identified only as
**v1.3.0**, tracked under `# Post-1.0 releases` in `docs/ROADMAP.md`.

This ADR **extends** ADR-0022 (one semantic DOM, CSS layers, Custom CSS Model A),
ADR-0027 (currency presentation icons), and ADR-0028 (native block as a
rendering surface). It does **not** change currency switching, pricing,
checkout policy, Visitor Location, OrderSnapshot, PersistedKeys, or CacheState
hashing (ADR-0032).

ADR-0003 remains absolute: no FOX/WOOCS code, terminology, markup, assets,
settings, or class names.

## Context

Milestone 9/17/22/23 delivered a shared storefront currency switcher with
placement, content composition, legacy `design.preset` token skins, icons, and
a Gutenberg block. Floating placement is functional but visually vanilla: a
generic dropdown inset from the viewport edge with the same interaction as
manual/block surfaces.

Merchants need a premium, modern floating selector (Edge Pill and curated
alternatives) without a second renderer, a free-form visual designer, or any
change to `?currency=` switching semantics.

## Decision

### 1. One shared presentation stack

Keep:

```text
SwitcherSettings → SwitcherViewModelFactory → SwitcherRenderer
  → shortcode | AutomaticSwitcherPlacement | SwitcherBlock | admin preview
  → SwitcherAssets (umc-switcher CSS/JS)
  → <a href="?currency=CODE"> → CurrencySwitcher::maybe_switch()
```

Do **not** add a floating-only renderer, template pack, REST/AJAX switch
endpoint, nonce on `?currency=`, or SPA price refresh.

### 2. New interaction layer: `design.presentation`

New enum under **`design.presentation`** (never the top-level
`display.presentation` icon subtree from ADR-0027):

| Value | Role |
|---|---|
| `edge_pill` | Recommended floating default for **new** Floating selections |
| `floating_card` | Rounded floating trigger + popover |
| `minimal_icon` | Compact circular / rounded-square trigger |
| `classic_dropdown` | Current dropdown; migration target for existing floating stores |
| `sticky_footer` | Paired with `placement=sticky_footer` only |

Placement remains the surface (`manual` | `floating_side` | `sticky_footer`).
Presentation is the look/interaction. Sticky Footer stays a **placement**, not
a floating skin. Stored `sticky_footer` and public CSS/data hook
`floating-bottom` are unchanged.

### 3. Legacy `design.preset` stays live

`design.preset` (`default|minimal|pill|compact|borderless|floating`) remains
sanitized and emitted as `.umc-switcher--preset-*`. Presentation owns
**geometry and open behaviour**; legacy presets own **token styling**. Choosing
a new presentation must not clear or remap `design.preset`. Cascade:

```text
base → elements → layout → presentation → legacy preset
  → theme / size / shape → overrides → responsive → Custom CSS
```

### 4. Schema 7 → 8 (visually neutral migration)

Bump `Settings::SCHEMA_VERSION` to **8**. Migration maps existing floating
stores to `classic_dropdown` / `floating_card` / `minimal_icon` / sticky
`sticky_footer` presentation — **never** auto-converts to Edge Pill.
Edge Pill is admin-preselected only when a merchant newly selects Floating
while still on `classic_dropdown` under the frozen conditions.

Additional schema keys: `responsive.mobile_behavior`
(`retain|bottom_sheet|sticky_compact`), theme `brand`, motion aliases
(`subtle`→`standard`, `none`→`off`), shape `square`.

`sticky_compact` is a **floating-mode mobile strategy only**. It must never
change `placement`, automatic-render hooks, or create a second automatic
selector.

OrderSnapshot and PersistedKeys are unchanged. CacheState `state_hash` must
not include presentation settings.

### 5. Bottom-sheet dialog contract

Sheet mode (Edge Pill mobile, explicit `bottom_sheet`, Floating Card /
Minimal Icon horizontal-fit promotion, sticky compact sheet) uses a real
dialog pattern on `div.umc-switcher__panel` while open:

- `role="dialog"`, `aria-modal="true"`, `aria-labelledby` → visible title
- Visible `<button type="button" class="umc-switcher__close">`
- Focus trap = that panel’s Close button + currency links only
- Backdrop is pointer-only (`aria-hidden`, never focusable)
- Escape / backdrop close restores focus to **this** instance’s trigger
- No native `<dialog>` / `showModal()`
- Decorative divider only — no drag handle, grab cursor, or swipe-to-dismiss

Collapsed, popover, and Edge Pill expand remain **disclosure** (not listbox).
`role="listbox"` / `role="option"` remain forbidden.

### 6. Multiple instances

A page may contain shortcode/block **and** the automatic selector. Opening one
switcher closes other open non-sheet switchers; only one sheet at a time; IDs
are unique per render; a sheet trap never includes another instance.

### 7. Minimal Icon and Floating Card rules

- Minimal Icon visible face: unambiguous symbol, else ISO code; flag only when
  `CurrencyPresentationResolver` returns a URL. Never blank or icon-only
  identity (ADR-0027).
- Accessible trigger label always: `Currency: {name}, {code}`.
- Floating Card persists `mobile_behavior=retain`; if the open menu cannot fit
  horizontally, promote that open to the same bottom sheet. Vertical flip is
  **not** a solution to horizontal overflow.

### 8. Admin IA

Display settings remain the single design authority. Progressive disclosure,
Admin Design System cards, in-page live preview with Collapsed/Open and
Desktop/Mobile (no iframe Custom CSS preview — ADR-0022). Shortcode
inheritance of global placement is unchanged; the block continues to force
manual via `for_embedded_surface()`.

## Consequences

- Settings schema **7 → 8**; PersistedKeys and OrderSnapshot unchanged.
- Existing floating stores keep current appearance until the merchant opts into
  a new presentation.
- Public CSS gains `data-umc-presentation`, `--presentation-*`, and sheet
  chrome; legacy `--preset-*` remain public and live.
- Implementation follows
  [`docs/architecture/switcher-presentation-presets.md`](../architecture/switcher-presentation-presets.md).

## Explicit non-goals

Second renderer; AJAX/REST switching; drag-and-drop positioning; per-device
independent designs; arbitrary Design CSS fields; Media Library icons; native
`<dialog>`; FOX/WOOCS coupling; CacheState hash changes; OrderSnapshot /
PersistedKeys bumps; M27.
