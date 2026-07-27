# ADR-0009 — Uninstall retention policy

## Status

Accepted (Milestone 7, v0.7.0 release candidate).

## Context

Universal Multicurrency persists data in several surfaces (see
[`docs/PERSISTED_DATA.md`](../PERSISTED_DATA.md) and `UMC\PersistedKeys`).
Merchants may uninstall the plugin while historical orders, refunds, and
support artefacts remain in the database.

Milestone 6 deliberately left `umc_dismissed_notices` user meta untouched on
uninstall (ADR-0007). Milestone 7 Commit 1 inventoried every key; Commit 2
formalizes the uninstall contract so future changes cannot broaden cleanup
accidentally.

## Decision

On plugin uninstall (`uninstall.php`), the plugin **must**:

1. **Delete plugin configuration only** — the `umc_settings` option
   (`Settings::OPTION` / `PersistedKeys::option_keys()`).
2. **Never delete order snapshot metadata** — all `_umc_*` keys written by
   `OrderSnapshot` (permanent commerce audit data; ADR-0004).
3. **Never delete refund snapshot metadata** — all `_umc_parent_*` keys written
   by `RefundSnapshot`.
4. **Never delete dismissal user metadata** — `umc_dismissed_notices`
   (`NoticeDismissal::META_KEY`) is preserved. Orphaned rows are harmless.

The uninstall handler **must not** call metadata deletion APIs (`delete_metadata`,
`delete_post_meta`, `delete_user_meta`), direct SQL (`$wpdb`), or any API that
would remove order, refund, or user meta. Session keys and cookies are not
WordPress uninstall targets (they expire through WooCommerce / the browser).

## Rationale

- **Commerce data is permanent.** Exchange-rate snapshots on orders and refunds
  are immutable audit records. Removing them would destroy historical context
  required for accounting, support, and ADR-0005 historical display.
- **Configuration is ephemeral.** `umc_settings` holds rates and enabled
  currencies for the live plugin only; it has no meaning without the plugin
  active.
- **Dismissal meta is cosmetic.** Per-user notice dismissals have no effect
  after uninstall and no security sensitivity; deleting them would require
  `delete_user_meta` and offer no merchant benefit (ADR-0007).

## Consequences

- `uninstall.php` remains a single `delete_option()` call.
- `PersistedKeys::uninstall_policy()` and `UninstallPolicyGuardTest` /
  `UninstallPolicyTest` enforce the contract in CI.
- Re-installing the plugin after uninstall starts from default settings; order
  snapshots and prior dismissal rows remain in the database.
- A future milestone that adds new persisted keys must update `PersistedKeys`,
  `docs/PERSISTED_DATA.md`, and these guards before shipping.

## Related

- ADR-0004 — order snapshot permanence
- ADR-0007 — dismissal meta introduction and M6 trade-off
- [`docs/PERSISTED_DATA.md`](../PERSISTED_DATA.md) — full inventory
