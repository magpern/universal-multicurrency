# Product Requirements

## Goal

Create a universal multicurrency plugin for WooCommerce.

## MVP

-   Unlimited currencies
-   Manual exchange rates
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

## Non-goals

GeoIP, subscriptions, bundles, automatic rates, multi-warehouse,
currency-specific stock, automatic remediation of detected conflicts, and
deactivating or modifying other plugins.
