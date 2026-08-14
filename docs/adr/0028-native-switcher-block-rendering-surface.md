# ADR-0028: Native Switcher Block & Rendering Surface Contract

**Status:** Accepted (Milestone 23, target v0.22.0)

**Related:**
[`docs/architecture/native-switcher-block.md`](../architecture/native-switcher-block.md),
ADR-0022, ADR-0027, ADR-0016

## Context

M17 established one semantic `SwitcherRenderer` shared by shortcode and automatic
floating/sticky placement. M22 added optional presentation icons on the same
composer/renderer stack. ADR-0027 explicitly deferred a Gutenberg block to M23+.

Block themes and the Site Editor expect native blocks. Shortcodes remain valid
but are not first-class in template editing workflows.

## Decision

### Block identity (public contract)

- **Name:** `universal-multicurrency/currency-switcher`
- **Type:** dynamic server-rendered block (`render_callback`)
- **API version:** 3
- **Category:** `widgets` (stable on WordPress 6.5 floor)

### One switcher engine

The block is a **rendering surface only**. It delegates to:

- `SwitcherViewModelFactory`
- `SwitcherElementComposer` (via factory)
- `SwitcherRenderer`
- `CurrencyContext` / `CurrencySwitcher` for selection URLs and state

No second renderer, selection engine, icon resolver, or currency persistence.

### Global vs instance settings

**Global Display settings** (`umc_settings.display.*`) remain the sole design
authority: content order, icons, presets, colors, responsive rules, Custom CSS.

**Block instance:** WordPress supports only (`anchor`, `className`, `align`).
No block color/typography/spacing supports. Inspector copy links merchants to
WooCommerce → Settings → Multicurrency → Display.

### Embedded/manual placement

Block instances render as **embedded/manual** surfaces. Each instance uses
`SwitcherSettings::with_placement( PLACEMENT_MANUAL )` so global floating/sticky
modifiers do not apply to inline block markup. Global automatic placement
remains independent when configured.

### Editor preview

- **Mechanism:** core `ServerSideRender` / block REST renderer
- **Authority:** `SwitcherViewModelFactory::create_for_admin_preview()`
- **No** shopper cookies, session mutation, Visitor Location, or checkout simulation

### Asset loading (single authority)

`SwitcherAssets` remains the sole enqueue service for storefront CSS/JS and
Custom CSS inline attachment.

- **block.json declares `editorScript` only** — no `style` / `viewScript` handles
  that would create a second frontend enqueue path
- **Proactive detection:** bounded `SwitcherPresence` (post content, current
  resolved template when available, automatic placement, shortcode)
- **Correctness backstop:** `SwitcherBlock::render()` → `SwitcherAssets::ensure_enqueued()`
- **Late stylesheet fallback preserved** for missed early enqueue

FSE/template presence detection must **not** scan all templates or perform
unbounded DB queries per request.

### Persistence

No Settings schema bump. No OrderSnapshot or PersistedKeys change. No DB
migration. Block comment delimiters in post/template content are WordPress-owned,
not UMC option/meta inventory.

### Explicitly rejected in M23

- Elementor widget
- Classic `WP_Widget`
- Navigation inner-block / custom link type
- Block color/typography designer
- Custom Media Library icons
- Node/npm/webpack toolchain
- Custom public write REST endpoint
- Static saved switcher markup
- Interactivity API client-side currency engine

## Consequences

- Block-theme merchants can place the switcher in templates without shortcodes
- Existing shortcode, floating, and sticky surfaces unchanged
- Multiple switchers on one page remain supported with unique instance IDs
- Full-page cache limitations unchanged from existing switcher surfaces
- Future milestones may add custom media icons (M24) without revisiting M23 core
