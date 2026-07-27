# Universal Multicurrency for WooCommerce

Unlimited currencies with manual exchange rates for WooCommerce. Products and
inventory stay in the store's base currency; conversion happens at runtime on
storefront, cart, checkout, and Store API surfaces. Orders carry a permanent
exchange-rate snapshot.

**Current release candidate:** **v0.7.0** (plugin header and `UMC_VERSION`).
Milestone 7 is complete in this repository. Git tag `v0.7.0` and GitHub release
publication remain pending explicit approval after review.

## Invariants

- WooCommerce owns inventory — never split stock by currency.
- Base prices stay in base currency; convert at display and transaction time.
- One conversion engine (`Converter` via `Integration\PriceConversionService`).
- Orders store immutable `_umc_*` snapshots; never deleted on uninstall.
- HPOS required; standalone — no FOX/WOOCS coupling (see ADR-0003, ADR-0007).
- Compatibility detection observes only; never deactivates another plugin.
- Merchant migration from another switcher is **manual only** — no foreign import
  (see `docs/MIGRATION.md`).

## Install

Build the installable zip:

```bash
composer install --no-dev
bash bin/build-zip.sh
```

Produces `dist/universal-multicurrency-0.7.0.zip`. Upload and activate through
WordPress, or symlink the plugin directory into `wp-content/plugins/`.
WooCommerce must be active first. The release zip includes `readme.txt`,
production `src/`, `vendor/`, and `languages/universal-multicurrency.pot`.

## Development

```bash
composer install
composer phpcs
composer make-pot
composer make-pot:check
composer test:unit
composer test:integration   # needs MySQL + tests/bin/install-wp.sh
composer test:mutation      # Diagnostics scorer; needs PCOV
composer audit
composer release-audit      # release-blocking RC gate (see docs/RELEASE_AUDIT.md)
```

Docker command examples: `CLAUDE.local.md` (local, gitignored).

## Compatibility

Requires **PHP 8.1+**, **WordPress 6.5+**, and **WooCommerce 8.2+** (HPOS). See
[`docs/COMPATIBILITY.md`](docs/COMPATIBILITY.md) for the supported-version matrix,
CI legs, and passive conflict detectors.

## Migration and uninstall

- **Migration:** manual cut-over only — deactivate the old switcher, configure UMC
  manually; no automatic import from FOX/WOOCS or other switchers.
- **Uninstall:** deletes `umc_settings` only; `_umc_*` order meta,
  `_umc_parent_*` refund meta, and `umc_dismissed_notices` user meta are preserved
  (ADR-0009).

## Changelog

### 0.7.0 — Release Candidate

Persisted-data inventory, uninstall policy, settings upgrade framework (schema v1),
manual migration documentation, translation readiness, security audit, deterministic
performance baselines, executable release audit, and documentation synchronization.
See [`docs/RELEASE_AUDIT.md`](docs/RELEASE_AUDIT.md) for the closure record.

### 0.6.0

Compatibility and diagnostics milestone — passive conflict detection, Site Health,
five-leg CI matrix, and `docs/COMPATIBILITY.md`.

## Documentation

| Document | Contents |
|---|---|
| [`readme.txt`](readme.txt) | WordPress.org–oriented plugin readme (Stable tag 0.7.0) |
| [`docs/PRODUCT_REQUIREMENTS.md`](docs/PRODUCT_REQUIREMENTS.md) | Goals and non-goals |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Layers, invariants, collaborators, RC governance |
| [`docs/HOOKS.md`](docs/HOOKS.md) | Every WooCommerce hook registered |
| [`docs/COMPATIBILITY.md`](docs/COMPATIBILITY.md) | Version matrix and detector governance |
| [`docs/TEST_STRATEGY.md`](docs/TEST_STRATEGY.md) | Unit, integration, guards, mutation, release audit |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Per-milestone deploy record and contributor commands |
| [`docs/MIGRATION.md`](docs/MIGRATION.md) | Manual merchant cut-over (no foreign import) |
| [`docs/PERSISTED_DATA.md`](docs/PERSISTED_DATA.md) | Persisted-key inventory and uninstall contract |
| [`docs/TRANSLATION.md`](docs/TRANSLATION.md) | Text domain, POT workflow, JS/RTL translation status |
| [`docs/SECURITY_REVIEW.md`](docs/SECURITY_REVIEW.md) | M7 security audit record and accepted residual risks |
| [`docs/PERFORMANCE_BASELINES.md`](docs/PERFORMANCE_BASELINES.md) | Deterministic performance ceilings |
| [`docs/RELEASE_AUDIT.md`](docs/RELEASE_AUDIT.md) | Executable release-blocking audit record |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | Milestone status (Milestone 7 complete at v0.7.0 RC) |
| [`docs/adr/`](docs/adr/) | Architecture decision records |

## License

GPL-2.0-or-later — declared in the plugin header and `composer.json`.
