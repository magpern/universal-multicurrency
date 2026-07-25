# ADR-0003 — Standalone plugin, no FOX/WOOCS coupling

## Status

Accepted.

## Context

An earlier helper plugin ("MP WOOCS Browse Currency") was built as an extension
of FOX / WOOCS (WooCommerce Currency Switcher) and depended on it. This new
plugin is intended to **replace** both FOX/WOOCS and that helper, not extend
them.

## Decision

- The plugin is fully standalone. Its only plugin dependency is WooCommerce; the
  header declares exactly `Requires Plugins: woocommerce`.
- No dependency or runtime coupling to FOX, WOOCS, WooCommerce Currency
  Switcher, or the old MP helper — none of their classes, functions, constants,
  options, cookies or sessions are read or written.
- All persisted state is the plugin's own (`umc_settings`, and permanent order
  snapshot meta in later milestones).
- Code from the old MP plugin is not imported; any capability it provided is
  reviewed and rewritten as independent functionality before inclusion.

## Consequences

- **Future requirement:** when another known multicurrency plugin (e.g.
  FOX/WOOCS) is active alongside this one, show a dismissible administrative
  conflict warning. The plugin must **never** automatically deactivate another
  plugin. (Scheduled for the Compatibility milestone; see ROADMAP.)
- The provisional name "Universal Multicurrency" may change before commercial
  release. New user-facing branding strings are kept to the minimum required
  (plugin header, unavoidable notices) until the final name is chosen; the
  domain layer introduces none.
