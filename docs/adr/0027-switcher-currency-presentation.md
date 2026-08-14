# ADR-0027: Switcher Currency Presentation

**Status:** Accepted (Milestone 22, target v0.21.0)

**Related:**
[`docs/architecture/switcher-currency-presentation.md`](../architecture/switcher-currency-presentation.md),
ADR-0022, ADR-0016, ADR-0019

## Context

M17 (v0.16.0) established a layered switcher presentation system with ordered
content elements `code`, `symbol`, and `name`. ADR-0022 explicitly deferred
flags and reserved extensible ordering for future presentation assets.

Merchants want optional visual cues (commonly flags) beside currency labels.
Flags are easily misread as geographic authority. Universal Multicurrency must
not conflate currency with country or affect routing.

## Decision

### Add `icon` as a fourth content element

M22 Phase 1 defines `icon` as a **bundled local presentation SVG** resolved
through an explicit registry. It is not a second symbol path and does not replace
M17 `symbol` rendering.

### Currency ≠ country

Presentation-region identifiers (e.g. `EU`, `SE`) are metadata for visual
display only. They must not affect Visitor Location, geo detection, checkout
currency resolution, analytics, or reporting. No reuse of geo routing
configuration.

### Visibility contract

Icon rendering requires **both**:

1. `show_icon === true` for the relevant context (trigger or menu)
2. `"icon"` present in that context's `order[]`

Neither condition alone renders an icon.

### Settings schema 7

Bump `Settings::SCHEMA_VERSION` from **6 → 7**. Add:

- `display.presentation.icon_overrides` (merchant overrides only)
- `display.presentation.icon_size` (`compact` | `standard` | `large`)
- `display.presentation.icon_shape` (`natural` | `square` | `circle`)
- `display.content.trigger.show_icon` (default `false`)
- `display.content.menu.show_icon` (default `false`)

**Do not** inject `icon` into existing `order[]` during migration.

`OrderSnapshot` schema, `PersistedKeys` inventory, and DB migrations are
unchanged.

### Built-in defaults (runtime only)

Non-persisted built-in currency → region suggestions:

SEK→SE, DKK→DK, NOK→NO, PLN→PL, GBP→GB, EUR→EU, USD→US, CHF→CH.

Fresh installs and upgrades share the same runtime defaults. Default visual
appearance remains unchanged because `show_icon` defaults to `false`.

### Asset registry

`CurrencyPresentationAssetRegistry` is the sole resolver for packaged assets.
Reject arbitrary region strings, paths, and URLs. Gracefully omit icons when no
effective mapping or asset exists.

### Accessibility

Icon-only triggers/menus are forbidden. Require at least `code` or `name`.
Decorative flags use `aria-hidden="true"`.

### Public CSS

Extend ADR-0022 with additive selectors (`.umc-switcher__icon`, etc.) and CSS
variables. Do not rename or remove M17 classes.

### Explicitly rejected in M22

- Custom uploads / Media Library / arbitrary URLs
- Remote icon CDN or API
- Gutenberg block or widget
- Visitor Location coupling
- Order/product meta persistence

## Consequences

- Merchants may opt in to presentation icons without breaking existing Custom CSS
- Upgrade path preserves semantic equivalence when icons remain disabled
- Registry and licensing documentation gate bundled SVG assets
- Future milestones may add custom media or blocks without revisiting M22 core
