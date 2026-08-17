# Release acceptance — browser suite

Exercises real WooCommerce admin/storefront UI (never a PHP-level shortcut)
against disposable, uniquely-prefixed fixture products on an **authorized DEV
WordPress + WooCommerce environment only**. Two generations of specs live
here, both release-blocking for their respective milestone:

- `specs/m25-fixed-pricing-csv.spec.ts` (M25, ADR-0030) — the Products →
  Export/Import admin UI, fixed-price CSV interchange.
- `specs/v1-core-purchase-journey.spec.ts`, `specs/v1-blocks-journey.spec.ts`,
  `specs/v1-fixed-pricing-journey.spec.ts` (M26 v1.0 readiness) — three small,
  bounded smoke journeys proving what PHP integration tests structurally
  cannot: manual currency selection through a real Classic checkout and a
  real order (journey A); the same shopping flow through the WooCommerce
  Cart/Checkout **Blocks** (journey F); and an authoritative fixed price
  displaying and settling at its exact authored amount through simple and
  variable products (journeys C+D). See each spec file's header comment for
  what it proves and — for the Blocks journey — an explicit, documented scope
  boundary (order *placement* is deliberately not asserted there; see that
  file).

This is a minimal, self-contained, plugin-specific suite — not a general
frontend/E2E testing platform for the repository. It never bundles into the
production plugin ZIP (`bin/build-zip.sh` does not include `tests/`).

## What the M25 suite proves

All 19 mandatory scenarios from the frozen acceptance matrix, plus the raw
protected-meta bypass defense (the single highest-risk item) in all four
required scenarios, plus a dedicated new-product raw-meta case:

| Spec | Test(s) in `specs/m25-fixed-pricing-csv.spec.ts` |
|---|---|
| Export column discovery / base exclusion | TEST 1, TEST 2 |
| Enabled / disabled-configured / blank-field export | TEST 3, TEST 4, TEST 5 |
| Variable parent blank / variation isolation | TEST 6, TEST 7 |
| Default vs. narrowed column selection | TEST 8, TEST 9 |
| Import auto-mapping | TEST 10 |
| Raw-meta resync-to-database-truth defense, scenarios A–D | TEST 11a–11d |
| Structured update / explicit clear / patch semantics | TEST 12, TEST 13, TEST 14 |
| Variation update isolation / partial-column import | TEST 15, TEST 16 |
| Sale>regular atomic rejection, no false WC row failure | TEST 17 |
| Storefront fixed price (not re-converted) / FX fallback | TEST 18, TEST 19 |

## Safety model

- **Production-host guard** (`fixtures/production-guard.ts`): the suite
  refuses to run against any hostname not on an explicit allowlist
  (`UMC_E2E_ALLOWED_HOSTS`, required — there is no shipped default host, so
  every caller must name their own DEV site). This is a hard stop that throws
  before any mutation, not a warning — there is no way to disable it, and an
  unset/invalid target URL, or an unset allowlist, also refuses to run rather
  than falling back to any default.
- **Disposable fixtures only**: every fixture product's SKU is prefixed
  `m25e2e-<run-id>-`. `fixtures/cleanup-fixtures.php` deletes only products
  matching that exact prefix — it never touches any other product, and
  double-checks the prefix match itself (not just the initial `LIKE` query)
  before deleting.
- **Credentials**: never committed. Set `UMC_E2E_ADMIN_USER`/
  `UMC_E2E_ADMIN_PASSWORD` as environment variables (or a gitignored `.env`
  you source yourself — see `.env.example`). Not printed to any log by the
  suite itself.
- **No production deployment**: this suite only ever interacts with the
  target site's public HTTPS admin/storefront, exactly like a real browser.
  It never touches Docker, WP-CLI, or the filesystem directly — fixture
  setup/cleanup (which do need WP-CLI) run separately, on the host, via
  `run-acceptance.sh`, never from inside the Playwright container.

## Prerequisites

No Node.js is required on the host — everything runs via the official
`mcr.microsoft.com/playwright:v1.55.1-noble` Docker image (pinned; bundles a
patched Playwright — see "Dependency note" below). You do need:

- Docker, with access to the target site's `wpcli` Compose service (profile
  `tools`).
- The target site's docker-compose project directory (`WP_COMPOSE_DIR`).

## Running

```bash
cd tests/e2e
UMC_E2E_BASE_URL=https://your-dev-site.example \
UMC_E2E_ADMIN_USER=... \
UMC_E2E_ADMIN_PASSWORD=... \
UMC_E2E_ALLOWED_HOSTS=your-dev-site.example \
WP_COMPOSE_DIR=/path/to/wordpress-compose-project \
  bash run-acceptance.sh
```

With no extra arguments this creates **both** fixture sets (WP-CLI, via
`fixtures/setup-fixtures.php` for M25 and `fixtures/setup-v1-fixtures.php`
for the v1.0 journeys — the latter also provisions two disposable pages
carrying WooCommerce's own canonical Cart/Checkout block scaffold, never the
merchant's real cart/checkout pages), runs all four release-blocking specs
(Docker Playwright), then removes every fixture (WP-CLI, via the matching
`cleanup-*.php` scripts) — always, even on failure (`trap ... EXIT`). Pass
`--keep-fixtures` to skip cleanup for post-mortem debugging, or one or more
spec paths (relative to `specs/`) to run a subset, e.g.:

```bash
bash run-acceptance.sh specs/v1-core-purchase-journey.spec.ts
```

`run-single.sh "<grep pattern>"` runs a filtered subset against fresh
fixtures and always keeps them afterward — useful for debugging one
scenario, but **not** representative of a full run: `test.describe.serial`
means later tests assume earlier ones already ran (e.g. TEST 14 asserts a
DKK value TEST 11c set) — a partial grep will show those as failing for
that reason alone, not a real regression. Always confirm a real finding
against the full suite before treating it as a defect.

## Fixture lifecycle

`fixtures/setup-fixtures.php` creates, via the plugin's own
`FixedPriceRepository`/`FixedPriceDocument` (the same persistence path the
product editor and CSV import use, not hand-written meta):

- One simple product (SKU `m25e2e-<run>-simple`): native regular/sale (100/80
  EUR, so WooCommerce's own on-sale state is genuinely active), authored SEK
  regular+sale, authored USD (disabled-but-configured) regular-only, PLN
  deliberately left unauthored (FX-fallback case).
- One variable product (SKU `m25e2e-<run>-variable`) with two variations
  (`-var-a`, `-var-b`), each with its own distinct authored SEK regular
  price.

Authored amounts are deliberately **not** a multiple of any configured
exchange rate, so a storefront assertion that the displayed price equals the
authored figure is unambiguous proof the fixed value was used — a
coincidentally-matching FX-converted figure could not produce false
confidence.

`fixtures/setup-v1-fixtures.php` additionally creates, for the three v1.0
journey specs: a converted-price simple product (SKU `v1e2e-<run>-converted`,
no authored fixed price); a fixed-price simple product (SKU
`v1e2e-<run>-fixed-simple`, authored SEK regular); a variable product (SKU
`v1e2e-<run>-variable`) with one fixed-priced variation and one
FX-converted variation; and two **disposable pages** carrying WooCommerce's
own canonical Cart/Checkout block scaffold (`WC_Install::get_cart_block_content()`/
`get_checkout_block_content()` via reflection — a bare
`<!-- wp:woocommerce/checkout /-->` server-renders to nothing; the block
requires its full inner-block scaffold to mount). `fixtures/cleanup-v1-fixtures.php`
removes all of it, scoped the same way `cleanup-fixtures.php` is.

## Known site-specific quirks (do not mistake for UMC defects)

- **Coming-soon gate**: this DEV site hides the storefront behind a
  coming-soon page for anonymous visitors, so every spec authenticates first
  (`loginAsAdmin`) even for what would otherwise be a guest journey — same
  reason every test empties the shopper's (admin) cart at the start
  (`emptyCart()` in `fixtures/checkout.ts`): a logged-in WooCommerce cart is
  tied to user meta, not just cookies, and persists across runs.
- **Sticky theme header**: this theme's Elementor header overlaps
  checkout/cart controls on scroll. Every interaction with a field below the
  fold uses `{ force: true }` or `toBeAttached()` rather than a plain click/
  `toBeVisible()` — a deliberate, documented choice, not a missed wait.
- **Third-party payment-gateway JS race**: this site runs several crypto
  payment gateways whose own JS re-renders the payment-method list on every
  checkout totals refresh, silently reverting a selected gateway back to the
  first one in the list. Classic checkout (journeys A, C+D) works around it
  by clicking the desired gateway's label twice, bracketing the AJAX settle
  window. The Blocks checkout (journey F) did not yield to the same
  workaround within a reasonable investigation budget, so that spec
  deliberately stops short of completing order placement — see its own file
  header for the full reasoning and what it asserts instead.

## Dependency note

`@playwright/test` is pinned to `1.55.1`, not `1.55.0` — `npm audit` flags a
high-severity advisory (GHSA-7mvr-c777-76hp, browser-download SSL
verification) in `<1.55.1`. The advisory's actual attack surface (a MITM
during `playwright install`'s browser download) does not apply to this
suite's runtime regardless, since it only ever uses the browser already
baked into the pinned Docker image and never calls `playwright install` —
but the pin is kept current rather than relying on that distinction.

## Known test-authoring pitfalls (do not reintroduce)

Found and fixed during initial acceptance runs — documented so they are not
silently reintroduced by a future edit:

- **WooCommerce's exported CSV headers are its column *labels*** ("ID",
  "SKU", "Regular price", "UMC Fixed Regular Price (SEK)"), never the
  internal snake_case ids `map_to[]` values use. Reading an exported row by
  `row.id`/`row.sku`/`row.regular_price` silently returns `undefined`.
  `fixtures/csv.ts`'s `withUmcColumnAliases()` (applied automatically by
  `exportProducts()`) exposes UMC columns under both the label and
  `umc_fixed_(regular|sale)_<code>` forms; for everything else, either use
  the exact label key or, when the row already represents a fixture you
  created, read the identifier from the fixtures JSON (`fixtures.simple.id`)
  directly rather than round-tripping it through an export.
- **A CSV row with a blank/undefined SKU and ID is silently *skipped* by
  WooCommerce's own importer**, before any UMC hook ever runs. A test built
  from such a row can pass for the wrong reason (nothing happened, which
  looks identical to "the defense worked") — this is why every mutating test
  in this suite asserts on `doneSummary` (`/updated/i`) in addition to the
  UMC-specific outcome, not just the outcome alone.
- **Fixture values must be checked against configured FX rates before
  picking them**: a chosen "authored" figure that happens to equal
  `native_price × configured_rate` cannot distinguish "fixed value shown"
  from "coincidentally-matching converted value" on the storefront.
- **Partial updates can create genuine sale>regular inversions purely as an
  artifact of the chosen fixture numbers**, not a defect — e.g. lowering
  `regular` below an unmapped, unchanged `sale` value. `FixedPriceDocumentMerger`
  correctly rejects this atomically; make sure a test intending a *simple,
  valid* update doesn't accidentally exercise the rejection path instead
  (verify the resulting pair against the currently-expected sale/regular
  state before asserting).
