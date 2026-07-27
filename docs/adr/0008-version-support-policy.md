# ADR-0008: Version support policy

**Status:** Accepted (v0.6.0)

## Context

Each milestone adds surfaces that depend on WooCommerce, WordPress, and PHP
behaviour. Without a written policy, "supported" drifts into marketing language,
CI legs multiply without purpose, and floor raises happen by convenience rather
than evidence.

Milestone 6 introduces a five-leg integration matrix and
`docs/COMPATIBILITY.md` as the single authoritative source for every version
claim the project makes.

## Decisions

### 1. Four merchant-facing tiers; only CI-exercised earns "Supported"

Compatibility claims use six labels defined in `docs/COMPATIBILITY.md § Labels`.
Only coordinates exercised by a named, green CI leg on every pull request may be
labelled **Supported**. Manual verification earns **Works with** at best. Absence
of evidence is **Untested** — never written into a row.

*Consequences.* Third-party plugins can never be *Supported*. The `ceiling` leg is
early-warning monitoring, not a support claim — it tracks WooCommerce `latest`,
runs `continue-on-error`, and establishes nothing about production support for
whatever version it happened to test.

### 2. `docs/COMPATIBILITY.md` is authoritative; a test enforces it

Every version source — plugin header, `UMC_VERSION`, `composer.json`,
`phpcs.xml.dist`, `.github/workflows/ci.yml`, `CLAUDE.md`, and
`DetectorManifest::manifest()` — must agree with the machine-readable blocks in
`docs/COMPATIBILITY.md`. `CompatibilityMatrixTest` parses those blocks and fails
on drift.

*Consequences.* Version bumps change the header, `UMC_VERSION`, and
`docs/COMPATIBILITY.md` in the same commit. The README links to the doc without
repeating numbers, so it cannot become a seventh drift source.

### 3. Floors are raised on published triggers, never on convenience

A floor raise ships only in a minor release, is announced in
`docs/COMPATIBILITY.md § Planned floor changes` at least 90 days and one release
ahead, and moves every source atomically in the release that raises it.

*Consequences.* There is no runtime WordPress version guard beyond the plugin
header. WooCommerce below the declared floor receives a soft Site Health
*recommended* result, not a hard block.

### 4. Test the corners, not the cross-product

The full PHP × WordPress × WooCommerce cross-product is 27 combinations. CI runs
five integration legs — the lowest corner (`floor`), today's baseline (`current`),
two axis-isolation legs (`mixed-php-floor`, `mixed-wp-floor`), and a non-blocking
`ceiling` — so each failure is attributable.

*Consequences.* Unit tests additionally matrix PHP 8.1, 8.3, and 8.4 on every PR.
Integration bootstrap enables HPOS identically on every leg.

### 5. Floor-suite reduction is bounded — capability probes, counted exclusions

When WooCommerce itself lacks a surface at the floor, the plugin does not add
production workarounds. Tests for that surface are excluded only on legs where a
live REST route-table probe shows the route absent — never via
`version_compare( WC_VERSION, … )`.

At the WooCommerce 8.2 floor, eight `OrderRouteCurrencyTest` methods are tagged
`@group wc-order-route-unavailable` because the Store API `Order` and
`CheckoutOrder` routes are absent from the REST table. Structural guards assert
exactly eight such exclusions and zero `@group wc-shape` exclusions.

*Consequences.* The floor leg runs 307 of 315 integration tests; all other legs run
the full 315. Classic order-pay and order-confirmation remain fully covered at the
floor.

### 6. No runtime WordPress guard; soft WooCommerce classification only

Below-floor PHP or WordPress is already blocked by the plugin header on modern
WordPress. `VersionPolicy` classifies the running stack for Site Health and debug
output: `below_floor`, `at_floor`, `supported`, `above_tested`, or `unparseable`.
Above-tested WooCommerce yields *recommended*, not *critical*, because the header
`WC tested up to` is a soft ceiling.

*Consequences.* `SiteHealthReport` mirrors declared floors from the plugin header
and tested ceilings from constants aligned with `docs/COMPATIBILITY.md`. HPOS
disabled yields *critical* on the environment test.

## Related

- Machine-readable matrix: `docs/COMPATIBILITY.md`
- CI workflow: `.github/workflows/ci.yml`
- Drift enforcement: `tests/unit/CompatibilityMatrixTest.php`,
  `tests/unit/CiMatrixGuardTest.php`
