# Extension Integration Guide

Developer guide for third-party WooCommerce extension compatibility with
Universal Multicurrency (UMC). Authoritative architecture:
[`docs/architecture/extension-compatibility.md`](architecture/extension-compatibility.md),
ADR-0024.

## Principles

1. UMC converts **base-authored** monetary inputs at defined seams — once.
2. Adapters bridge extension-specific inputs into existing UMC services only.
3. Do not implement a second converter, rate resolver, or checkout policy.
4. Do not claim **Integrated** without E3 real-extension validation.

## Compatibility statuses

| Status | Meaning |
|---|---|
| Native | Works via normal WooCommerce hooks |
| Integrated | Adapter + E3 real-extension tests |
| Characterized | E1 contract and/or E2 hook-double tests |
| Known limitation | Documented constraints |
| Incompatible | Reproduced failure |
| Not evaluated | No evidence |

Merchant-visible sub-labels for Characterized/Integrated are defined in ADR-0024.

## Adapter authoring

1. Implement `ExtensionCompatibilityAdapterInterface` under `src/Compatibility/Extension/Adapters/`.
2. Register the extension in `ExtensionCompatibilityRegistry::built_in_definitions()` or via `umc_extension_compatibilities`.
3. Use `PriceConversionService` through `AbstractExtensionAdapter::convert_amount()` for base-authored amounts.
4. Add E1 contract tests and E2 hook-double tests before claiming Characterized.
5. Promote to Integrated only after E3 tests against the real licensed extension.

## Opt-in seams

| Hook | Default | Use when |
|---|---|---|
| `umc_convert_fee` | false | Fee amount is base-authored |
| `umc_convert_shipping_rate` | false for third-party | Shipping rate cost is base-authored |
| `umc_convert_product_addon_price` | true (adapter) | Product Add-Ons flat/quantity raw price |
| `umc_convert_bundled_item_price` | true (adapter) | Product Bundles item price |
| `umc_should_convert_product_price` | true | Return false to suppress browsing-currency conversion (renewals) |

## Dynamic pricing boundary

Extensions that modify the current WooCommerce price must either:

- Run before UMC conversion and supply base-authored amounts, or
- Return active-currency amounts (UMC must not convert again).

## Evidence requirements

| Tier | Source | Max claim |
|---|---|---|
| E1 | Unit contract tests | Characterized — contract tests |
| E2 | UMC-owned hook doubles | Characterized — simulated extension hooks |
| E3 | Real licensed extension CI/manual | Integrated — real extension validated |

## Persistence

Settings schema **6**, PersistedKeys **8**, order snapshot schema **4** — frozen.
If an adapter requires new persisted monetary state, stop and escalate for
architectural review (ADR-0024 persistence stop gate).

## Test doubles

UMC-owned test plugins under `tests/fixtures/` (installed when
`UMC_TEST_EXTENSION_FIXTURES=1`) simulate hook timing only. They must never be
used to claim real-plugin Integrated status.

## Related

- [`docs/HOOKS.md`](HOOKS.md)
- [`docs/COMPATIBILITY.md`](COMPATIBILITY.md)
- [`docs/TEST_STRATEGY.md`](TEST_STRATEGY.md)
