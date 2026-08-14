# Native Switcher Block (Milestone 23)

**Status:** Authoritative implementation specification for Milestone 23
(**v0.22.0**)

**ADR:** [ADR-0028](../adr/0028-native-switcher-block-rendering-surface.md)

**Branch:** `feature/m23-native-switcher-block`

---

## 1. Product objective

Add a first-class **Currency Switcher** Gutenberg block that renders through the
existing M17/M22 PHP switcher stack, enabling block-theme and Site Editor placement
without duplicating design or currency logic.

---

## 2. Baseline

| Item | Value |
|---|---|
| Prior release | M22 / **v0.21.0** |
| Settings schema | **7** (unchanged) |
| Order snapshot | **5** (unchanged) |
| PersistedKeys | **10** (unchanged) |
| DB migration | none |

---

## 3. Architecture

```text
Global Display settings
        ↓
SwitcherSettingsRepository
        ↓
SwitcherViewModelFactory
        ↓
SwitcherElementComposer (+ M22 presentation)
        ↓
SwitcherRenderer
        ↓
 ┌─────────────┬───────────────┬───────────────┬──────────────┐
 │ shortcode   │ floating side │ sticky footer │ native block │
 └─────────────┴───────────────┴───────────────┴──────────────┘
```

---

## 4. Block contract

| Item | Value |
|---|---|
| Name | `universal-multicurrency/currency-switcher` |
| API | 3 |
| Category | `widgets` |
| Rendering | PHP `render_callback` only |
| Attributes | none (supports: anchor, className, align) |

### Markup wrapper

Frontend output:

```html
<div class="wp-block-universal-multicurrency-currency-switcher …">
  <!-- existing SwitcherRenderer root .umc-switcher … -->
</div>
```

The public `.umc-switcher` contract remains on the inner renderer root.

---

## 5. Embedded placement override

Block instances call:

```php
$settings->with_placement( SwitcherSettings::PLACEMENT_MANUAL );
```

before view-model creation so `--floating-side` / `--floating-bottom` modifiers
do not apply to embedded instances.

---

## 6. Editor preview

| Concern | Rule |
|---|---|
| Mechanism | `ServerSideRender` → registered `render_callback` |
| Preview factory | `create_for_admin_preview()` |
| Currency state | Deterministic; no cookie/session writes |
| Geo / Visitor Location | Not run in editor |
| Fewer than 2 currencies | Sample EUR/SEK/USD preview (existing factory fallback) |

Inspector panel: link to Display settings; no mini-designer.

---

## 7. Asset loading

### Single authority: `SwitcherAssets`

| Path | Role |
|---|---|
| `SwitcherPresence` | Bounded proactive detection |
| `wp_enqueue_scripts` | Early enqueue when presence true |
| `SwitcherBlock::render()` | `ensure_enqueued()` backstop |
| Shortcode | `ensure_enqueued()` + late stylesheet fallback |

### Bounded presence (no unbounded FSE scan)

1. Automatic placement configured and eligible
2. Shortcode in current post content
3. Block in current post content (`has_block`)
4. Block in **current resolved template** content when `wp_get_current_template_id()`
   yields one template (single fetch, request-memoized)

**Not allowed:** querying all `wp_template` / `wp_template_part` rows, recursive
template-tree walks, or per-request full-site scans.

### block.json assets

- **`editorScript` only** — hand-written `editor.js` using WordPress globals
- **No** `style` / `viewScript` in block.json (avoids duplicate frontend enqueue)

---

## 8. Multi-instance behavior

Existing `SwitcherViewModelFactory` instance counter provides unique
`umc-switcher-trigger-{n}` / `umc-switcher-menu-{n}` IDs.

`switcher.js` binds per `.umc-switcher--dropdown` root.

M23 integration tests cover:

- two blocks
- block + shortcode
- block + floating
- block + sticky

---

## 9. Site Editor scope (Phase 1)

**In scope:** manual insertion in pages, headers, footers, Group, Columns.

**Out of scope:** automatic template injection, Navigation inner-block, WooCommerce
template overrides.

---

## 10. Caching

Server-rendered block HTML varies with request currency state. Same full-page
cache limitations as shortcode/floating/sticky. See deployment and geo cache
guidance. M23 does not add cache-control or fragment hydration.

---

## 11. Uninstall / deactivation

Block comment delimiters remain in merchant content. No content migration or
deletion. Frontend render callback absent after uninstall — normal WordPress
unknown-block behavior.

---

## 12. Work packages

| WP | Scope |
|---|---|
| WP0 | ADR-0028 + this spec + ROADMAP |
| WP1 | Surface characterization tests |
| WP2 | `SwitcherPresence` + `SwitcherAssets` integration |
| WP3 | `block.json`, `SwitcherBlock`, Plugin wiring |
| WP4 | `editor.js` + ServerSideRender |
| WP5 | FSE/template bounded detection + integration |
| WP6 | Multi-instance integration tests |
| WP7 | Store API regression + full matrix |
| WP8 | Manual editor acceptance checklist |
| WP9 | Security/performance/persistence guards |
| WP10 | Merchant/developer documentation |
| WP11 | v0.22.0 release preparation |

---

## 13. Manual editor acceptance checklist

When a WP dev environment is available (no deployment required):

- [ ] Insert block in page content; save; frontend shows switcher
- [ ] Insert in header template part; frontend header shows switcher
- [ ] Insert in footer template part
- [ ] Two blocks on one page — unique IDs, both functional
- [ ] Block + shortcode coexist
- [ ] Block + global floating/sticky coexist
- [ ] M22 icons on/off
- [ ] Mobile / RTL / keyboard operation
- [ ] Selection reload synchronizes across surfaces

---

## 14. Version

**0.22.0** · Settings schema **7** · PersistedKeys **10** · Order snapshot **5**.
