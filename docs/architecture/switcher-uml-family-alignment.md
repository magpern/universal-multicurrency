# Switcher UML-family alignment

**Status:** Authoritative implementation specification for the existing
selector stack's visual alignment with Universal Multilingual v1.12.0.

**ADR:** [ADR-0037](../adr/0037-switcher-uml-family-alignment.md)

**Builds on:** [Switcher presentation presets (ADR-0035)](switcher-presentation-presets.md),
[Currency presentation (ADR-0027)](switcher-currency-presentation.md),
[Switcher customization (ADR-0022)](switcher-customization.md)

Working drafts under untracked `docs/plans/` are not source of truth
(`ReleaseAuditTest` forbids tracked `docs/plans/`).

---

## 1. Product objective

Refactor the **existing** Universal Multicurrency visitor selector so UML-family
floating presentations (`edge_pill`, `minimal_icon`, `tab`) share the visual
and interaction family of UML v1.12.0's Floating Language Selector, without
duplicating the selector, importing UML runtime assets, or changing currency
authority.

---

## 2. Baseline

| Item | Value |
|---|---|
| Plugin version | **1.2.1** (unchanged; no release) |
| Settings schema | **8** (unchanged) |
| Persisted inventory | **12** (unchanged) |
| Order snapshot | **5** (unchanged) |
| CacheState | Unchanged — presentation not in `state_hash` |
| DB migration | None |
| UML visual reference | tag **v1.12.0** (`e1d80bc05da0395cc7e58422bb91ce4c52d49207`) |

---

## 3. Non-negotiable rules

1. One semantic renderer / DOM. Do not add a second floating selector.
2. Resolver precedence remains `explicit > session > cookie > user_preferred > base`.
3. Switching remains `?currency=` + `CurrencySwitcher::maybe_switch()`.
4. No UML CSS/JS/PHP import; no `.aiml-*`, `data-aiml-*`, `--aiml-fs-*`.
5. Family collapsed trigger is flag + ISO code (never flag-only).
6. EUR → EU is authoritative and non-overridable at presentation time.
7. Flags are not geo / IP / locale.
8. Saved `mobile_behavior` is never rewritten server-side.
9. Schema 8 / inventory 12 / no DB migration.
10. Custom CSS Model A remains last in the cascade.

---

## 4. Architecture (unchanged chain)

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

Family behaviour is a **renderer / CSS / JS presentation layer** over this
chain. `is_uml_family_floating()` is true only for `placement=floating_side`
plus `edge_pill|minimal_icon|tab`.

---

## 5. Presentation mapping

| UML v1.12.0 | UMC enum | Notes |
|---|---|---|
| `edge_pill` | `edge_pill` | Same name |
| `minimal` | `minimal_icon` | Do not rename |
| `tab` | `tab` | Additive enum |

Legacy `floating_card`, `classic_dropdown`, and `sticky_footer` stay.

---

## 6. Flag policy

`CurrencyPresentationResolver` applies merchant overrides except EUR, which
always uses `REGION_EU`. Built-in issuer mappings ship with bundled SVGs.
Unknown and listed supranational codes render **code only**.

See [`docs/assets/PRESENTATION_FLAGS.md`](../assets/PRESENTATION_FLAGS.md).

---

## 7. Edge convention and same-edge stacking

UMC copy: [`docs/EDGE_CONTROL_CONVENTION.md`](../EDGE_CONTROL_CONVENTION.md).

Slot 2 offset, applied only when another `data-um-edge-control` occupies the
same physical side:

```text
offset = (slot - 1) × (control-size + gap)
       = 1 × (2.75rem + 0.5rem)
       = 3.25rem
```

Implemented as:

```css
calc(var(--um-edge-control-size, 2.75rem) + var(--um-edge-stack-gap, 0.5rem))
```

Directions: **top down**, **bottom up**, **middle down**. Root middle
position keeps `top: 50%` and adjusts `translateY(calc(-50% + OFFSET))`.
Panel open/close transform stays on the panel child.

Browsers without `:has()`: no slot offset; possible same-edge overlap is an
accepted degradation. No JS collision engine.

---

## 8. Mobile behaviour

| Situation | Result |
|---|---|
| Saved `mobile_behavior` present | Unchanged through sanitize/load/save |
| Absent key, family presentation | `retain` |
| New Floating in admin | `edge_pill`, `edge_offset=0`, `retain` |
| Presentation change to family this session | `retain` only if mobile field is not dirty |
| Explicit `bottom_sheet` / `sticky_compact` | Fully supported |

Sheet/dialog behaviour follows the saved mobile value, not the presentation
family.

---

## 9. Out of scope

- CurrencyResolver / session / cookie / geo / pricing / FX changes
- Cache key redesign
- UML runtime dependency
- Production (`biopentra.eu`) and release/tag
- Schema or persisted-inventory bump
