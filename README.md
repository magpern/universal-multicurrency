# Universal Multicurrency for WooCommerce

Unlimited currencies with manual exchange rates for WooCommerce. Products and
inventory stay in the store's base currency; conversion happens at runtime on
storefront, cart, checkout, and Store API surfaces. Orders carry a permanent
exchange-rate snapshot.

## Invariants

- WooCommerce owns inventory — never split stock by currency.
- Base prices stay in base currency; convert at display and transaction time.
- One conversion engine (`Converter` via `Integration\PriceConversionService`).
- Orders store immutable `_umc_*` snapshots; never deleted on uninstall.
- HPOS required; standalone — no FOX/WOOCS coupling (see ADR-0003, ADR-0007).
- Compatibility detection observes only; never deactivates another plugin.

## Install

Build the installable zip:

```bash
bin/build-zip.sh
```

Upload and activate through WordPress, or symlink the plugin directory into
`wp-content/plugins/`. WooCommerce must be active first.

## Development

```bash
composer install
composer phpcs
composer test:unit
composer test:integration   # needs MySQL + tests/bin/install-wp.sh
composer test:mutation      # Diagnostics scorer; needs PCOV
```

Docker command examples: `CLAUDE.local.md` (local, gitignored).

## Compatibility

See [`docs/COMPATIBILITY.md`](docs/COMPATIBILITY.md) for supported PHP,
WordPress, and WooCommerce versions, the CI matrix, and built-in conflict
detectors.

## Documentation

| Document | Contents |
|---|---|
| [`docs/PRODUCT_REQUIREMENTS.md`](docs/PRODUCT_REQUIREMENTS.md) | Goals and non-goals |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Layers, invariants, collaborators |
| [`docs/HOOKS.md`](docs/HOOKS.md) | Every WooCommerce hook registered |
| [`docs/COMPATIBILITY.md`](docs/COMPATIBILITY.md) | Version matrix and detector governance |
| [`docs/TEST_STRATEGY.md`](docs/TEST_STRATEGY.md) | Unit, integration, guards, mutation |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Per-milestone deploy and rollback record |
| [`docs/MIGRATION.md`](docs/MIGRATION.md) | Manual merchant cut-over from another switcher (no foreign import) |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | Milestone status |
| [`docs/adr/`](docs/adr/) | Architecture decision records |

## License

GPL-2.0-or-later — see [`LICENSE`](LICENSE).
