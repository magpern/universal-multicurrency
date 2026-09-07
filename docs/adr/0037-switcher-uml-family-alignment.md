# ADR-0037 — Switcher UML-family visual alignment

## Status

Accepted (presentation refactor over the existing selector stack; no release).

## Relationship to prior ADRs

This ADR extends ADR-0035 (selector presentation presets), ADR-0027 (currency
presentation icons), and ADR-0022 (one semantic DOM / Custom CSS Model A).
It does **not** change currency switching, pricing, checkout policy, Visitor
Location, OrderSnapshot, PersistedKeys, CacheState hashing (ADR-0032), or
authenticated preferred currency (ADR-0036).

ADR-0003 remains absolute: no FOX/WOOCS coupling.

## Context

Universal Multilingual v1.12.0 shipped a Floating Language Selector with a
documented edge-control convention. Universal Multicurrency already has a
working visitor currency selector (`SwitcherSettings` → ViewModel →
`SwitcherRenderer` → `?currency=`). Merchants want the floating currency
control to sit in the same visual family as the language control without a
second renderer or a runtime dependency on UML.

## Decision

### 1. STATE A — refactor, do not duplicate

Keep the existing authority chain:

```text
SwitcherSettings → SwitcherViewModelFactory → SwitcherRenderer
  → shortcode | AutomaticSwitcherPlacement | SwitcherBlock | admin preview
  → SwitcherAssets (umc-switcher CSS/JS)
  → <a href="?currency=CODE"> → CurrencySwitcher::maybe_switch()
```

Do not add a parallel floating selector, UML CSS/JS/PHP import, `.aiml-*`
classes, `data-aiml-*`, `--aiml-fs-*`, or UML asset handles.

### 2. Frozen visual reference

Released UML **v1.12.0** (`e1d80bc05da0395cc7e58422bb91ce4c52d49207`) is the
frozen visual family reference. UMC independently implements matching tokens
where appropriate. UML remains read-only.

### 3. UML-family vs legacy presentations

UML-family floating presentations (require `placement=floating_side`):

- `edge_pill`
- `minimal_icon` (UML `minimal` mapped here; the enum is not renamed)
- `tab` (additive)

Legacy / non-family:

- `floating_card`
- `classic_dropdown`
- `sticky_footer`
- manual / block classic surfaces

Classic, manual, and block UI are not globally restyled into the dark floating
family.

### 4. Collapsed family trigger

Family floating trigger presentation is **flag + ISO currency code** at render
time. Merchant `content.trigger.*` remains stored and remains valid for
classic/manual/block. Missing or unmapped assets fall back to **code only**.
Accessible names continue through `SwitcherLabelFormatter::format_accessible_name()`.

### 5. Flags are presentation only

`CurrencyPresentationResolver` and `CurrencyPresentationAssetRegistry` remain
the only flag authority. Flags are not derived from geo, IP, billing/shipping
country, or locale.

EUR always resolves to the European Union region (`EU`). Merchant
`icon_overrides['EUR']` is ignored at presentation time, is not offered in
admin, and is not applied from POST. A previously stored EUR override may
remain in the option for backward-data preservation and is explained in admin
as unused.

Supranational / ambiguous codes (XOF, XAF, XCD, XPF, XDR, XAU, XAG, XPT, XPD)
have no default flag.

### 6. Accessibility and progressive enhancement

Disclosure semantics (`button` + `aria-expanded` + `aria-controls`) and real
`?currency=` links. Current item uses `aria-current="true"`, never `page`.
No `listbox`. No-JS keeps links operable. After JS init the family root sets
`data-umc-enhanced="1"`. JS must not `preventDefault` currency links and must
not switch currency via AJAX/REST.

### 7. Mobile behaviour compatibility

Never rewrite an existing saved `mobile_behavior`. Absent-key default for
family presentations is `retain`. New Floating admin defaulting
(`applyFloatingDefaults`) initializes Edge Pill, `edge_offset=0`, and
`retain`. Changing presentation to a family value in the same admin session
sets `retain` only when the mobile control has not already been edited
(client dirty flag). Explicit `bottom_sheet` / `sticky_compact` remain fully
supported.

### 8. Edge-control convention

Family floating roots emit:

- `data-um-edge-control="currency"`
- `data-um-edge="left|right"`
- `data-um-edge-slot="2"`
- `data-um-edge-priority="20"`

Shared convention variables: `--um-edge-control-size`, `--um-edge-stack-gap`,
`--um-edge-z-index`. Same-edge stacking uses ancestor-rooted `body:has(...)`
and offset `(slot-1) × (control-size + gap)` applied in UMC CSS only. Top
moves down, bottom moves up, middle moves down. Browsers without `:has()` get
no slot offset (documented degradation). No JS collision engine.

### 9. Persistence freeze

Settings schema remains **8**. Persisted inventory remains **12**.
OrderSnapshot remains **5**. No DB migration. `tab` is an additive enum value
inside the existing `design.presentation` key. `center` sanitizes to stored
`middle`.

## Consequences

UMC owns its CSS/JS/PHP. UML and UMC can occupy the same physical edge without
a shared runtime package. Currency authority, cache signatures, and saved
selector keys stay unchanged.
