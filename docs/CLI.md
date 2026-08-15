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

## Authorization

Both command groups trust WP-CLI's execution context entirely and perform no
`current_user_can()` checks — WP-CLI may run without a normal logged-in user
or with an explicit `--user`, and per-user capability gating would make
legitimate administrative CLI usage fail unpredictably. Access control for
CLI usage is the host's shell/SSH boundary.
