# Compatibility

This document is the intended single authoritative source for what Universal
Multicurrency supports: minimum versions, the CI matrix that exercises them, and
which WooCommerce feature surfaces are covered where. "Intended," because the
mechanical enforcement of that authority — a test proving the plugin header,
`composer.json`, `phpcs.xml.dist` and `CLAUDE.md` all agree with the tables
below — is introduced in a later commit within this milestone (§ Changing this
document). Until then, treat every claim here as evidence-backed but not yet
drift-proof.

Nothing in this document is aspirational. Every row cites the CI leg or test
that produced it; where no such evidence exists, the row is simply absent (see
§ How to read this document).

_A section on third-party currency-switcher detection — known compatible,
known incompatible, and how conflicts are reported — is intentionally absent
from this revision. It is added once the detector exists, later in this
milestone._

## How to read this document

### Labels

Every compatibility claim below uses one of six labels, applied per
**coordinate** (a specific version, or a specific version plus feature
surface) — never as a blanket claim about "the plugin" as a whole.

| Label | Meaning | What the project commits to |
|---|---|---|
| **Supported** | A named CI leg exercises this exact coordinate on every pull request, and is green. | Release-blocking; bug fixes at release priority. |
| **Works with** | Verified manually, or by a test that exists but does not run on every PR. | Best-effort triage and fixes; no CI gate; no promise against upstream drift. |
| **Untested** | No evidence either way. This is the default — it is never written into a row, and its absence from a table *is* the claim. | Reports accepted and triaged. |
| **Unsupported** | Outside the declared floor or ceiling. | Nothing. Activation is blocked by the plugin header, or the plugin stays inert. |
| **Incompatible** | A reproduced, named conflict this plugin cannot fix from its own side. | Detection and a warning only — never automatic remediation. |
| **Experimental** | Shipped, but with a deliberately unfrozen contract. | No backwards-compatibility promise. |

Third-party plugins can never be labelled *Supported* — they cannot run in
continuous integration. Their ceiling is *Works with*.

### Evidence tiers

Labels are derived from evidence, not asserted. In increasing strength:

- **Untested (E0)** — no evidence.
- **Works with (E1/E2)** — verified once manually, or by a test that exists in
  the repository but is not run on every pull request.
- **Tested / Supported (E3)** — a named, currently-configured CI leg exercises
  the exact coordinate on every pull request and is green. This document uses
  "Tested" and "Supported" interchangeably for this tier: *Tested* describes
  the evidence, *Supported* describes the commitment that evidence buys.
- **Incompatible (E-negative)** — a reproduced failure with a named cause.

### Ceiling / early-warning — not a compatibility label

The `ceiling` CI leg (§ The supported-version CI matrix) is a monitoring
mechanism, not a compatibility claim, and it does not fit the label table
above. It deliberately tracks the moving `latest` tag for WooCommerce rather
than a fixed version, so that a passing `ceiling` run means only "nothing has
broken against whatever WooCommerce currently publishes as latest" — which,
at any given time, may be a stable release or a pre-release build. The leg is
configured `continue-on-error`, so a `ceiling` failure never blocks a merge; it
exists purely to surface upstream drift early. **A green `ceiling` run
establishes nothing about production support for the WooCommerce version it
happened to test against** — that version is not pinned, is not announced, and
may not even be a final release. Production support claims in this document
come only from the `floor`, `current`, `mixed-php-floor` and `mixed-wp-floor`
legs, which pin exact, reproducible coordinates.

## Merchant-facing summary

If your store runs **PHP 8.1 or newer, WordPress 6.5 or newer, and WooCommerce
8.2 or newer**, every core feature of this plugin is supported: the currency
switcher, cart and checkout (both the classic flow and the Cart/Checkout
blocks), coupons, shipping, payment gateway filtering, High-Performance Order
Storage, and the permanent per-order currency snapshot.

There is one narrow, precisely-scoped exception: on WooCommerce versions at
the 8.2 floor specifically, viewing or paying for an *existing* order through
the block-based Store API (the "order confirmation" and "pay for order" REST
routes) is not covered by this plugin's own automated tests, because
WooCommerce itself does not register those routes on that version — the
classic (non-block) equivalent of the same screens is unaffected and fully
covered. See § The floor's Store API order-route exclusion for the technical
reason, and § WooCommerce feature surfaces for exactly which surfaces this
does and does not touch.

## Machine-readable summary

<!-- umc:versions:start -->
| Axis | Minimum supported | Recommended | Tested up to | CI-exercised | Label at minimum |
|---|---|---|---|---|---|
| PHP | 8.1 | 8.3 | 8.4 | 8.1, 8.3, 8.4 | Supported |
| WordPress | 6.5 | latest stable release | 7.0.2 (floats with `wp-phpunit`; not independently pinned) | 6.5.8, 7.0.2 | Supported |
| WooCommerce | 8.2 | current major (10.x) | 10.9.4 | 8.2.5, 10.9.4, latest (ceiling, non-blocking) | Supported |
<!-- umc:versions:end -->

## Version support

### PHP

- **Minimum supported: 8.1.** Verified by the `floor` and `mixed-php-floor` CI
  legs, and by `composer.json`'s `config.platform.php = 8.1.99` (dependency
  resolution is pinned as-if-8.1 on every leg, so no dependency the plugin
  installs can silently require a newer PHP).
- **Recommended: 8.3.** The version the `current` and `mixed-wp-floor` legs
  run, and the version most representative of an actively-maintained
  WordPress host today.
- **Tested up to: 8.4.** The `ceiling` leg's PHP version. PHPUnit 9.6 (this
  project's pinned test runner) proved fully compatible with PHP 8.4 in this
  milestone's verification — no PHPUnit-internal deprecations were observed.
- **CI-exercised:** 8.1 (`floor`, `mixed-php-floor`, and the unit job's PHP
  matrix), 8.3 (`current`, `mixed-wp-floor`, and the unit job), 8.4 (`ceiling`
  and the unit job).

### WordPress

- **Minimum supported: 6.5.** Verified by the `floor` and `mixed-wp-floor` CI
  legs, which pin `wp-phpunit/wp-phpunit` to the `6.5.*` series (resolved to
  6.5.8 during this milestone's verification) and download matching WordPress
  core. `phpcs.xml.dist`'s `minimum_wp_version` also declares 6.5.
- **Recommended: the latest stable WordPress release.**
- **Tested up to: 7.0.2** at the time of this milestone's verification. This
  number is **not an independent pin** — it is whatever
  `wp-phpunit/wp-phpunit` resolves to from `composer.json`'s
  `"^6.5 || ^7.0"` constraint when `composer.lock` was last updated, and it
  will move the next time that lockfile changes. Treat it as "the version
  most recently proven to work," not as a ceiling.
- **CI-exercised:** 6.5.8 (`floor`, `mixed-wp-floor`), 7.0.2 (`current`,
  `mixed-php-floor`, `ceiling` — via the unpinned/"auto" resolution).

### WooCommerce

- **Minimum supported: 8.2.** This floor is derived, not arbitrary: High-
  Performance Order Storage (a hard requirement of this plugin) reached
  general availability in WooCommerce 8.2. Verified by the `floor` CI leg
  against WooCommerce 8.2.5 — the latest patch release in the 8.2 series.
- **Recommended: the current major version line (10.x).**
- **Tested up to: 10.9.4**, pinned explicitly in CI (`current`,
  `mixed-php-floor`, `mixed-wp-floor` legs) because the Store API test suite
  asserts response shapes, and an unpinned "latest" would resolve to
  pre-release builds whose changes would surface as CI failures unrelated to
  the plugin.
- **`ceiling` observation (non-blocking, not a support claim):** the `ceiling`
  leg's `latest` resolution was observed at **11.0.0-beta.2** — a pre-release
  build — during this milestone's verification, and passed cleanly. This is
  exactly the scenario § Ceiling / early-warning describes: useful early
  signal, no production commitment.
- **CI-exercised:** 8.2.5 (`floor`), 10.9.4 (`current`, `mixed-php-floor`,
  `mixed-wp-floor`), `latest` — floating, currently 11.0.0-beta.2 (`ceiling`,
  non-blocking).

### Planned floor changes

None currently planned. A floor raise, when proposed, would ship only in a
minor release, be announced here at least 90 days and one release ahead of
the release that raises it, and change the plugin header, `composer.json`,
`phpcs.xml.dist`, `.github/workflows/ci.yml` and this document atomically —
the same commit, every source moving together.

## The supported-version CI matrix

Full cross-product of the three axes above is 27 combinations. This plugin
tests **the corners of the supported box, plus the two coordinates that
isolate each axis** — five integration legs, each independently attributable
if it fails:

| Leg | PHP | WordPress | WooCommerce | Why this coordinate exists |
|---|---|---|---|---|
| `floor` | 8.1 | 6.5.8 | 8.2.5 | The lowest supported corner — the only leg that substantiates the declared floors together. |
| `current` | 8.3 | 7.0.2 | 10.9.4 | Today's baseline coordinate; the authority for Store API response-shape assertions. |
| `mixed-php-floor` | 8.1 | 7.0.2 | 10.9.4 | Isolates the PHP axis: floor PHP against otherwise-current WordPress/WooCommerce. |
| `mixed-wp-floor` | 8.3 | 6.5.8 | 10.9.4 | Isolates the WordPress axis: floor WordPress against otherwise-current PHP/WooCommerce. Without this leg, a `floor` failure could not be attributed to the PHP axis or the WordPress axis. |
| `ceiling` | 8.4 | 7.0.2 | latest (floating; observed 11.0.0-beta.2) | Early warning on upstream drift. Non-blocking — see § Ceiling / early-warning. |

All five ran cleanly in this milestone's verification: `floor` at 230 of 238
tests (see § The floor's Store API order-route exclusion for the other 8);
every other leg at the full 238.

## WooCommerce feature surfaces

| Surface | Status | Since | Evidence |
|---|---|---|---|
| High-Performance Order Storage (custom order tables) | Supported | 0.3.0 | `ci:current`, `ci:floor` — the integration bootstrap enables HPOS identically on every leg |
| Legacy CPT order storage | Works with | 0.4.0 | `LegacyOrderTest` — read and refund only; not CI-exercised on every PR |
| Classic cart and checkout | Supported | 0.3.0 | `ci:current`, `ci:floor` |
| Cart Block / Checkout Block | Supported | 0.5.0 | `ci:current` |
| Store API: cart, checkout creation, products, coupons, shipping, gateway filtering | Supported | 0.5.0 | `ci:current`, `ci:floor` |
| Store API: order-confirmation and pay-for-order routes (`Order`, `CheckoutOrder`) | **Untested at the floor** — see § The floor's Store API order-route exclusion; **Supported at current** | 0.5.0 | `ci:current`; excluded at the floor via `@group wc-order-route-unavailable` |
| Order-pay and order-confirmation through the **classic** (non-block) flow | Supported | 0.4.0 | `ci:current`, `ci:floor` — unaffected by the Store API route gap above |
| Product price-filter block | Works with (base currency only) | 0.5.0 | Known limitation carried from Milestone 5 |

## The floor's Store API order-route exclusion

At the WooCommerce floor (8.2.x), WooCommerce's own Store API `RoutesController`
registers the `Order` and `CheckoutOrder` routes only when an internal
experimental-build flag is set — true only for the standalone WooCommerce
Blocks feature-plugin build, never for a standard WordPress.org WooCommerce
install. On that version, `/wc/store/v1/order/{id}` and
`/wc/store/v1/checkout/{id}` are simply not present in the REST route table.
**This document does not claim the exact WooCommerce version at which those
routes became unconditional** — that boundary was observed at two points
(absent at 8.2.5, present unconditionally at 10.9.4) and was never bisected in
between.

The 8 tests in `OrderRouteCurrencyTest` that dispatch a real request through
those routes are tagged `@group wc-order-route-unavailable` and excluded only
on the `floor` CI leg (`composer test:integration -- --exclude-group
wc-order-route-unavailable`). Three properties of this exclusion are
deliberate:

- **Capability-based, not version-based.** The tests are gated by a live probe
  of the registered REST route table (`rest_get_server()->get_routes()`),
  never by `version_compare( WC_VERSION, … )`. A structural guard
  (`tests/unit/CiMatrixGuardTest.php`) asserts no such version comparison
  exists anywhere under `tests/`.
- **Not a production workaround.** No code in `src/` changed to accommodate
  this gap. The plugin's own Store API order-currency lock
  (`src/StoreApi/OrderCurrencyLock.php`) hooks generic REST dispatch filters
  that simply never fire for a route WordPress never matched — it is
  correctly inert on WooCommerce 8.2, not broken.
- **Exactly bounded.** A structural guard
  (`tests/unit/CiMatrixGuardTest.php` and
  `tests/unit/OrderRouteGroupGuardTest.php`) asserts the exclusion covers
  exactly these 8 tests, confined to this one file, and that no other
  exclusion group is used anywhere in the CI configuration. **No `wc-shape`
  exclusion currently exists** — that group name is reserved for a genuine
  Store API response-*shape* incompatibility, which this is not, and none has
  been recorded.

## Environment requirements

High-Performance Order Storage is enabled identically on every CI leg (see
`tests/integration/bootstrap.php`) and is therefore Supported at every
coordinate in the matrix above. Multisite, WP-CLI, and headless (REST-only,
no-theme) usage are not exercised by any CI leg in this milestone — every
integration run reports "Running as single site" — and carry no compatibility
claim in either direction.

## Changing this document

This document is intended to become the single authoritative source for
every version claim the project makes. As of this commit, that authority is
declared but not yet mechanically enforced: a later commit in this milestone
introduces a unit test that parses the plugin header, `composer.json`,
`phpcs.xml.dist`, `.github/workflows/ci.yml` and `CLAUDE.md`, and fails if any
of them disagrees with the tables above. Until that test exists, a version
literal changed in one of those files without a matching update here will not
be caught automatically — treat this document as the intended source of
truth today, and as the enforced one once that test lands.
