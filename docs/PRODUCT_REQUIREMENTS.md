# Product Requirements

## Goal

Create a universal multicurrency plugin for WooCommerce.

## MVP

-   Unlimited currencies
-   Manual exchange rates
-   Automatic exchange rates from a provider (Frankfurter; v0.8.0)
-   Currency switcher
-   Runtime conversion
-   Cart & checkout
-   Order snapshots
-   Single inventory truth
-   HPOS
-   Blocks support
-   Compatibility detection and supported-version matrix (v0.6.0)

## Compatibility

Only one runtime currency switcher may be authoritative on a store. When another
known switcher is detected, administrators receive a graded warning through
dashboard notices, the Multicurrency settings tab, and Site Health — never
automatic deactivation. Supported PHP, WordPress, and WooCommerce versions are
documented in `docs/COMPATIBILITY.md` and enforced in CI.

## Geo Detection (v0.11.0)

Optional ordered country/region routing to currencies. Disabled by default on
upgrade. Manual shopper selection and checkout currency locks always take
precedence. See [`docs/GEO_DETECTION.md`](GEO_DETECTION.md) and ADR-0016.

## Non-goals

Raw IP persistence, subscriptions, bundles, multi-warehouse,
currency-specific stock, automatic remediation of detected conflicts, and
deactivating or modifying other plugins. Additional exchange-rate providers
beyond Frankfurter, per-currency provider selection, and WP-CLI wrappers over
the rate services remain future work (see `docs/ROADMAP.md`).
