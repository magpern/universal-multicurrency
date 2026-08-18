# WP-CLI

Universal Multicurrency registers thin CLI command groups when WP-CLI is
available. Commands wrap the same services used by their admin equivalents —
they never call provider HTTP from the storefront path, and neither group
performs a `current_user_can()` authorization check: WP-CLI execution is
treated as trusted administrative/system access (see § Authorization).

## Exchange-rate commands

```bash
wp umc rates status
wp umc rates refresh [--currency=<code>]
wp umc rates list
```

| Command | Service | Exit code |
|---|---|---|
| `status` | `RateHealthService` | Always `0` (unhealthy state is reported in the table) |
| `refresh` | `RateUpdateService` | `0` on complete success, partial success, not modified, or no automatic targets; `1` on total failure or when an update lock is held |
| `list` | Settings + `RateStatusEvaluator` | Always `0` |

- Refresh preserves last-known rates on failure (same contract as admin / AS).
- `--currency` limits refresh to one enabled automatic currency code.
- No interactive prompts and no provider credential handling (Frankfurter is public).

## Fixed-price catalog operations (M24)

```bash
wp umc prices list  [--currency=<code>] [--status=fixed|partial|fx]
wp umc prices seed  --currency=<code> [--product=<id>|--all] [--dry-run]
wp umc prices clear --currency=<code> [--product=<id>|--all] [--dry-run]
```

| Command | Service | Exit code |
|---|---|---|
| `list` | `FixedPriceCatalogQuery` | `0` on success; `1` on invalid `--currency`/`--status` |
| `seed` | `FixedPriceCatalogOperationsService` | `0` on completion (including partial skips); `1` on invalid arguments or no rate available for the target currency |
| `clear` | `FixedPriceCatalogOperationsService` | `0` on completion; `1` on invalid arguments |

- `--currency` rejects the store base currency and any code not configured
  in UMC.
- `--product` and `--all` are mutually exclusive; exactly one is required for
  `seed`/`clear`.
- `--dry-run` runs the identical computation/classification path and performs
  **zero** `FixedPriceRepository::save()` writes; its reported outcome
  represents exactly what the real run would do.
- Processes in batches of 100–250 products/variations per cycle (unlike the
  admin screen, the CLI is not bounded in total catalog size — that scope cap
  is admin-only).
- Seeding converts each product's/variation's **authored** native
  regular/sale price (never `get_price()`, never the base amount unchanged)
  using **one** FX rate resolved at the start of the invocation — every batch
  of a single `seed --all` uses that same rate. See
  [`docs/architecture/fixed-pricing-catalog-operations.md`](architecture/fixed-pricing-catalog-operations.md).
- Both `seed` and `clear` are idempotent — rerunning after an interrupted
  invocation reprocesses already-handled products harmlessly.
- Shares its orchestration service with the dedicated Fixed Pricing admin
  screen — no second seed/clear implementation.

## External cache-state commands (v1.1.0)

```bash
wp umc cache-state status [--format=<table|json>]
wp umc cache-state acknowledge <hash>
```

| Command | Service | Exit code |
|---|---|---|
| `status` | `CacheStateService` | Always `0` on successful execution — `reconciliation_required: true` in the output is a normal, successful result, **not** a command failure. Any non-zero exit means the state is unknown/unavailable. |
| `acknowledge` | `CacheStateService` | `0` on match; `1` on a stale, unknown, or malformed hash (nothing is written) |

There is no `check` subcommand — `status --format=json` already is the
machine-readable check interface.

**Contract.** `status --format=json` prints one flat JSON object
(`contract_version`, `state_hash`, `acknowledged_hash`,
`monitoring_enrolled`, `reconciliation_required`, `base_currency`,
`currencies`, `geo_enabled`, `acknowledged_at`, `rates_last_updated_at`).
Field names/types are additive-only within one `contract_version`; removing
or retyping a field requires a `CONTRACT_VERSION` bump. `state_hash` is
opaque — external infrastructure must compare it byte-for-byte and never
attempt to recompute it.

**External command failure contract.** Success is exit `0` with the
documented JSON object emitted, regardless of `reconciliation_required`'s
value. Any non-zero exit — command not found, bootstrap failure, PHP fatal,
DB failure — means the cache state is unknown/unavailable, never "reconciled"
and never "the plugin is inactive." External infrastructure must fail closed
on unknown state; if it needs to distinguish "plugin not active" from "status
call failed," it must check WordPress's own plugin-activation state through
its own means.

**Acknowledgement is a claim, not a verification.** It records that an
external operator or tool *claims* successful reconciliation for the exact
state hash submitted. It does not confirm nginx, a CDN, Varnish, or any proxy
runtime was updated, reloaded, or is serving correctly — the plugin has no
visibility into that runtime, and hash equality alone cannot prove it (see
the ABA limitation below).

**Normative operator transaction** — the whole mitigation for the ABA
limitation, and the only way to run this safely:

```
1. wp umc cache-state status --format=json      # read current hash
2. reconcile the external cache to that state    # infrastructure-side
3. validate / reload / accept on the infra side
4. wp umc cache-state status --format=json      # re-read — never reuse step 1's value
5. wp umc cache-state acknowledge <hash from step 4>
```

Never acknowledge the hash captured before reconciliation started. If the
configuration changed between steps 1 and 4, reconcile again against the new
value.

**ABA limitation (documented, not solved in code).** Acknowledge A →
configuration changes to B → external cache is reconciled to B but
acknowledgement is skipped → configuration changes back to A. `state_hash`
now equals `acknowledged_hash` again even though the external cache may
still be configured for B. Hash equality proves the current configuration
matches the last claimed reconciliation — it cannot prove external runtime
state. The mitigation is the transaction procedure above, not a code fix;
see [ADR-0032](adr/0032-external-cache-state-readiness.md) SS5 and
[`docs/architecture/external-cache-state-readiness.md`](architecture/external-cache-state-readiness.md).

**Boundaries.** This command group never SSHes anywhere, never writes nginx
or any proxy config, never reloads or purges an external cache, never holds
cache-server credentials, and performs no outbound HTTP. All control flows
from infrastructure to the plugin (`acknowledge`), never the reverse.

## Authorization

All three command groups trust WP-CLI's execution context entirely and
perform no `current_user_can()` checks — WP-CLI may run without a normal
logged-in user or with an explicit `--user`, and per-user capability gating
would make legitimate administrative CLI usage fail unpredictably. Access
control for CLI usage is the host's shell/SSH boundary.
