# CLAUDE.md

## Core invariants

1.  WooCommerce owns inventory.
2.  Never split stock by currency.
3.  Base prices stay in base currency.
4.  Convert prices at runtime.
5.  Orders store exchange-rate snapshots permanently.
6.  HPOS required.
7.  One approved milestone at a time.
8.  Tests accompany every feature.

## Code rules

- **Generic product only.** No site names, client names, hosting domains, or
  any deployment-specific branding in committed files — code, comments, docs,
  tests, workflows, composer metadata, commit content. The plugin must work on
  any WooCommerce site and be publishable as-is. Check before every commit.
- **Fully self-contained repo.** This directory is its own git repository
  (GitHub: `magpern/universal-multicurrency`), independent of whatever tree it
  happens to be checked out in. Never reference paths outside the repo from
  committed code, and never commit this project's files into any surrounding
  repository.
- Naming: namespace `UMC\`, prefix `umc_`, textdomain `universal-multicurrency`.
- Minimum versions: PHP 8.1, WordPress 6.5, WooCommerce 8.2.
- Order data only through `WC_Order` CRUD (HPOS-safe) — never post meta or
  direct SQL. Never hook stock filters or write stock meta, not even as
  pass-throughs.
- `uninstall.php` removes settings only; order snapshot meta (`_umc_*`) is
  permanent order data and is never deleted.
- No secrets in this repo, ever.

## Workflow

- Checks: `composer phpcs`, `composer test:unit`, `composer test:integration`
  (integration needs MySQL and `tests/bin/install-wp.sh`; see
  `.github/workflows/ci.yml` for the reference setup).
- Machine-specific dev-environment notes belong in `CLAUDE.local.md`
  (gitignored) — never in this file.
- Release: bump the `Version:` plugin header, tag `vX.Y.Z` matching it, push
  the tag. The Release workflow builds and publishes the installable zip.
