# ADR-0007: Passive conflict detection

**Status:** Accepted (v0.6.0)

## Context

ADR-0003 commits the plugin to standalone operation: no runtime coupling to FOX,
WOOCS, or any other currency switcher. Milestone 6 must still warn merchants when
another switcher is active, because two runtime converters silently multiply rates
and corrupt cart and order totals permanently.

That creates an apparent contradiction: ADR-0003 forbids reading "their classes,
functions, constants…" while a detector checks for exactly those symbols. The
distinction is load-bearing and must be written down before the first needle ships.

## Decisions

### 1. Observe the host environment; never call into it

Detection uses only passive probes against WordPress's own registries and PHP's
symbol tables:

- `active_plugins` / `active_sitewide_plugins` membership
- `class_exists( $name, false )` — never with autoload enabled
- `defined()` — never `constant()`
- `function_exists()` for registered functions
- `isset( $wp_filter[ $tag ] )` — never `apply_filters()`
- registered shortcodes

An **existence check against WordPress's registries** is observation of the host
environment. WordPress publishes these registries so plugins can understand where
they run; reading them creates no dependency, because behaviour is identical
whether or not the symbol exists.

**Dereferencing a foreign symbol** — instantiating its classes, calling its
functions, reading its options, cookies, sessions, or rates for any purpose beyond
reporting its presence — is coupling. ADR-0003 forbids the second and always
permitted the first.

*Consequences.* `src/Diagnostics/WordPressEnvironmentProbe.php` is the only file
that touches WP registries. Guards G2, G4, and G8 enforce the closed probe set at
source level. Third-party filter callbacks that throw propagate deliberately; garbage
input is dropped by the total sanitiser.

*Alternatives considered.* Reading another plugin's options or selected currency to
"confirm" a conflict was rejected — foreign schema, T3 inertness failure, and
forgeable input.

### 2. Grade evidence; let confidence decide the surface

Each detector carries weighted signatures. `ConflictScorer` sums matched weights
(integer arithmetic only), maps the score to HIGH (≥60), MEDIUM (≥30), or LOW
(≥10), and drops findings below 10 entirely. Thresholds are frozen in this ADR.

*Consequences.* HIGH requires dispositive evidence (typically `plugin_path` alone
at 60 points). Weak kinds (`hook`, `shortcode`) cap at LOW. Screen visibility and
Site Health severity derive from confidence, not from a boolean "conflict yes/no".

*Alternatives considered.* Count-of-signatures thresholds treat unequal evidence as
equal and cliff-edge off when symbols rename; rejected.

### 3. Never deactivate, never modify, never remediate

The plugin warns only. It never calls `deactivate_plugins`, never writes another
plugin's options, and never changes monetary behaviour because a conflict was
detected, graded, rendered, or dismissed.

*Consequences.* Notices link to `plugins.php` and the Multicurrency settings tab;
they do not offer a deactivate button. Guard G4b forbids deactivation APIs
plugin-wide.

### 4. Detection is admin-only, offline, and free elsewhere

`Diagnostics` registers only when `is_admin()` and not during AJAX, cron, or
WP-CLI. Evaluation is lazy at `admin_notices`, after every plugin has registered
its hooks. There is no HTTP, no transient cache, and zero cost on the storefront,
Store API, other REST, or CLI.

*Consequences.* Frontend, Store API, and cron carry exactly zero Diagnostics hooks
and zero autoloaded Diagnostics classes. Request-scoped memoization inside
`ConflictDetector` is the only cache; there is nothing to invalidate.

*Alternatives considered.* Transient caching was rejected — it is an option write,
slower than recomputation on typical hosts, and introduces a large invalidation
surface for no gain.

### 5. Dismissal is per-user state keyed to the conflict fingerprint

Dashboard notices may be dismissed via a nonce'd GET link handled on `admin_init`.
Dismissals persist in user meta (`umc_dismissed_notices`), capped at 20 entries
with 180-day expiry. The fingerprint hashes the sorted finding ids plus the plugin
major.minor version so a new minor may surface warnings again.

HIGH conflicts on `plugins.php` and the Multicurrency settings tab are never
dismissible. The settings-tab panel is always visible when findings exist.
Dismissal is per user — other administrators still see the warning.

*Consequences.* This is the first non-order persisted data outside `umc_settings`.
It is deliberately not removed on uninstall (see ADR-0009). Orphaned
dismissal rows keyed to old fingerprints are harmless.

### 6. Diagnostics is the only namespace that knows third-party plugins exist

Invariants I1–I7 (see `docs/ARCHITECTURE.md`) keep compatibility observation
separate from the money path:

- **I3 (knowledge):** conversion, Store API, snapshots, and historical order
  services must not ask whether a conflict exists.
- **I5 (effect):** even with knowledge, no price, rate, cart total, coupon,
  shipping cost, tax, gateway, order, refund, or snapshot may differ because a
  conflict was detected.

The T3 inertness rule (a probe must not change behaviour whether the symbol exists)
is I5 at probe level; I5 is T3 at subsystem level. Weakening one without the other
would be visible.

*Consequences.* `Plugin.php` is the sole inward seam — it instantiates
`Diagnostics` behind the admin gate. `SettingsPage` emits the field type string
`umc_conflict`; Diagnostics registers the renderer. Compatibility information
flows outward through rendered notices, Site Health tests, the debug section, and
the `umc_conflict_*` view-model filters — never inward.

### 7. Naming another vendor's plugin is a governed act

Built-in detectors live only in `DetectorManifest.php`. Adding, demoting, or
removing one follows the checklist in `docs/COMPATIBILITY.md § Detection`.
Every needle must be verified against distributed source before it ships; false
positives are fixed by removing needles, never by raising thresholds.

Sites may append runtime rows via `umc_conflict_detectors`; those rows pass through
the same sanitiser and are never merged into the manifest automatically.

*Consequences.* The manifest and `docs/COMPATIBILITY.md § Known incompatible`
must agree bi-directionally; `CompatibilityMatrixTest` enforces this on every PR.

## Related

- Amends the forward reference in ADR-0003 — observation is not the coupling ADR-0003
  forbids.
- Executable form of these decisions: `tests/integration/DiagnosticsGuardTest.php`,
  `tests/unit/DiagnosticsBoundaryGuardTest.php`, and Infection over
  `ConflictScorer` / `VersionPolicy`.
