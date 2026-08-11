# WP-CLI

Universal Multicurrency registers a thin exchange-rate operations command group
when WP-CLI is available. Commands wrap the same services used by admin refresh,
Action Scheduler, Site Health, and Compatibility — they never call provider HTTP
from the storefront path.

## Commands

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

## Notes

- Refresh preserves last-known rates on failure (same contract as admin / AS).
- `--currency` limits refresh to one enabled automatic currency code.
- No interactive prompts and no provider credential handling (Frankfurter is public).
