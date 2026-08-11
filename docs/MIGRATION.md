# Merchant migration guide

How to move a WooCommerce store from another currency switcher to Universal
Multicurrency (UMC) **without automatic import**.

This document is the authoritative merchant migration playbook for the Release
Candidate. It describes a **manual** cut-over path only.

**Related architecture:**

- [ADR-0003](adr/0003-standalone-no-fox-woocs-coupling.md) — standalone plugin; no foreign coupling
- [ADR-0007](adr/0007-passive-conflict-detection.md) — passive conflict detection; observation only
- [ADR-0009](adr/0009-uninstall-retention-policy.md) — uninstall retention of order meta
- [`PERSISTED_DATA.md`](PERSISTED_DATA.md) — every key UMC persists
- [`COMPATIBILITY.md`](COMPATIBILITY.md) — version matrix and known incompatible switchers
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — per-release deploy records

---

## Migration overview

Universal Multicurrency is **standalone**. It does not read, import, or migrate
configuration from FOX/WOOCS, CURCY, WPML Multicurrency, YayCurrency, or any
other switcher. That is intentional (ADR-0003, ADR-0007).

What migration **does** mean for UMC:

1. **Deactivate** the old switcher so only one runtime converter is active.
2. **Install and configure** UMC manually (WooCommerce → Settings → Multicurrency).
3. **Verify** catalogue, cart, checkout, orders, refunds, and reporting.
4. **Cut over** traffic once validation passes.

What stays intact without any import step:

- WooCommerce orders, refunds, products, and customer records
- Permanent `_umc_*` / `_umc_parent_*` metadata already written by UMC (if any)
- Historical order totals in WooCommerce's native fields

What must be **recreated manually**:

- Enabled currencies and manual exchange rates in `umc_settings`
- Per-currency formatting (symbol, position, decimals) in UMC settings

UMC may **upgrade its own** legacy `umc_settings` shape on first load after a
plugin upgrade (see [Internal settings schema migrations](#internal-settings-schema-migrations)).
That is unrelated to foreign switcher migration and never reads another
plugin's data.

---

## Internal settings schema migrations

These run automatically inside UMC when it first reads `umc_settings` after an
upgrade. They are **not** a foreign-switcher import: `SettingsUpgrader` only
ever reads UMC's own option.

`Settings::SCHEMA_VERSION` is **6**. Six production migrations exist, keyed by
the version they produce, and are applied in ascending order:

| From → To | Migration | Change |
|---|---|---|
| 0 → 1 | `SettingsUpgrader::migrate_0_to_1` | Adds `schema_version: 1`; currency rows copied unchanged |
| 1 → 2 | `SettingsUpgrader::migrate_1_to_2` | Introduces the automatic-rate shape (below) |
| 2 → 3 | `SettingsUpgrader::migrate_2_to_3` | Adds the Display switcher settings block with safe defaults |
| 3 → 4 | `SettingsUpgrader::migrate_3_to_4` | Adds checkout policy defaults (`checkout.mode`, `checkout.show_notice`) |
| 4 → 5 | `SettingsUpgrader::migrate_4_to_5` | Adds Geo Detection defaults (`geo` subtree disabled, empty rules) |
| 5 → 6 | `SettingsUpgrader::migrate_5_to_6` | Restructures the Display block for layered switcher presentation (below) |

### What v1 → v2 changes

| Field | Before (v1) | After (v2) |
|---|---|---|
| Per-currency rate | `rate` | Renamed to **`manual_rate`**, value carried across unchanged; the old `rate` key is removed |
| Per-currency provider rate | *(absent)* | **`provider_rate`** initialized to `''` — no rate is invented, and no provider is contacted during migration |
| Per-currency adjustment | *(absent)* | **`merchant_adjustment`** initialized to `'0'` (no markup or discount) |
| Per-currency mode override | *(absent)* | **`rate_mode`** initialized to `''` (inherit the global mode) |
| Global mode | *(absent)* | **`rate_mode`** defaults to **`manual`** |
| Global provider | *(absent)* | `rate_provider` defaults to `frankfurter` |
| Global interval | *(absent)* | `rate_update_interval` defaults to `P1D` |
| Global staleness limit | *(absent)* | `rate_max_age_hours` defaults to `48` |
| Schema marker | `schema_version: 1` | **`schema_version: 2`** |

### What v2 → v3 changes

| Field | Before (v2) | After (v3) |
|---|---|---|
| Display switcher | *(absent)* | **`display`** block initialized from `SwitcherSettings::default_array()` |
| Schema marker | `schema_version: 2` | **`schema_version: 3`** |

The upgrade preserves all currency and exchange-rate configuration. The
switcher remains disabled (`display.enabled: false`) until a merchant enables
it in WooCommerce → Settings → Multicurrency → Display.

### What v3 → v4 changes

| Field | Before (v3) | After (v4) |
|---|---|---|
| Checkout policy | *(absent)* | **`checkout`** block initialized with `mode: selected`, `show_notice: true` |
| Schema marker | `schema_version: 3` | **`schema_version: 4`** |

Existing currency, exchange-rate, and display configuration is preserved
unchanged. Default checkout mode keeps v0.9.x behaviour (selected currency
through checkout).

### What v5 → v6 changes

| Field | Before (v5) | After (v6) |
|---|---|---|
| Theme / size / shape | `display.appearance.theme|size|shape` | Copied verbatim into **`display.design.theme|size|shape`**; `appearance` is removed |
| Preset | *(absent)* | **`design.preset`** always initialized to `default` (never inferred from shape) |
| Structured overrides | *(absent)* | **`design.overrides`** initialized to `{}` |
| Motion | *(absent)* | **`design.motion`** initialized to `subtle` |
| Trigger content | flat `content.show_code|show_symbol|show_name` | **`content.trigger`** keeps code/symbol; `show_name` is `false` |
| Menu content | flat `content.show_code|show_symbol|show_name` | **`content.menu`** keeps all three toggles |
| Element order | *(absent)* | `content.trigger.order` / `content.menu.order` list the visible elements in `code → symbol → name` order |
| Chevron | *(absent)* | **`content.show_chevron`** initialized to `false` |
| Responsive bag | *(absent)* | **`responsive.hide_name_on_mobile|compact_on_mobile`** initialized to `false` |
| Advanced Custom CSS | *(absent)* | **`custom_css`** initialized to `''` |
| Schema marker | `schema_version: 5` | **`schema_version: 6`** |

`enabled`, `placement`, `style`, `position`, `behavior`, and `visibility` are
preserved unchanged. Because theme, size, and shape stay independent enums and
`--preset-default` is a visual no-op, an upgraded switcher renders exactly as it
did on v0.15 (ADR-0022).

Defaults for the initialized display fields come from `Settings::sanitize()`,
which every migration result passes through.

Defaults for the initialized per-currency fields come from
`Settings::sanitize()`, which every migration result passes through.

### Conversion-fidelity guarantee

**An upgraded store converts money exactly as it did before the upgrade.**

Because the global mode defaults to `manual`, no per-currency override is
written, `provider_rate` starts empty and `merchant_adjustment` starts at `0`,
`RateResolver` resolves the effective rate to the same `manual_rate` string the
old `rate` key held. Nothing schedules a fetch until a merchant explicitly
switches a currency, or the store, to automatic mode.

This is enforced, not merely asserted:
`tests/unit/SettingsMigrationFidelityTest.php` converts a representative set of
amounts through `Converter` before and after `SettingsUpgrader::upgrade()` on a
v1 fixture and requires **byte-identical** output, alongside the schema
assertions above.

### Failure behaviour

- A stored version **newer** than `SCHEMA_VERSION` is rejected: callers receive
  defaults in memory and the stored option is left untouched.
- Any `Throwable` during migration fails closed the same way — partial
  migrations are never persisted.
- Reading an already-canonical store performs **no** write
  (`CEILING_SETTINGS_WRITE_CANONICAL_LOAD`).

Automatic-rate operational data (last fetch time, statuses, failure history,
provider cache validators) lives in a **separate** option, `umc_rate_state`,
which has its own defaults and is never part of a settings migration. See
[`PERSISTED_DATA.md`](PERSISTED_DATA.md) and ADR-0012.

---

## Supported migration path

The **only supported path** from another currency switcher:

| Step | Action |
|---|---|
| 1 | Full backup (database + files) |
| 2 | Staging rehearsal on a copy of production |
| 3 | Document old switcher currencies and rates from its admin UI (spreadsheet) |
| 4 | Deactivate the old switcher on staging |
| 5 | Confirm UMC Diagnostics shows no HIGH conflict (or resolve before prod) |
| 6 | Install/activate UMC; configure currencies and rates manually |
| 7 | Run the [verification checklist](#verification-checklist) on staging |
| 8 | Schedule production cut-over; repeat deactivate → configure → verify |
| 9 | Monitor Site Health and smoke-test checkout after cut-over |

Passive conflict **detection** may warn while another switcher is still active.
It never deactivates or modifies another plugin.

---

## Unsupported migration path

The following are **explicitly unsupported** and must not be attempted with
UMC core (no workaround is provided in the Release Candidate):

| Unsupported approach | Why |
|---|---|
| Automatic import from another switcher's options or database | Forbidden by ADR-0003 / ADR-0007 |
| Reading foreign plugin options, sessions, cookies, or rates at runtime | Forbidden coupling |
| Admin CSV/JSON import UI in UMC | Not shipped in RC; see [UMC CSV format spec](#umc-csv-format-specification-future-only) for a future-native format only |
| Running two runtime currency switchers together | Double conversion corrupts totals permanently |
| Expecting UMC to map FOX/WOOCS/WPML field names | No foreign schema support |
| Migrating historical orders by re-converting stored totals | Orders remain authoritative in their stored currency; see ADR-0004/0005 |

If a agency script reads foreign data and writes **`umc_settings` directly**,
that is outside UMC and unsupported — the supported path is manual entry through
the WooCommerce settings UI (or a future UMC-native import that targets UMC
schema only).

---

## Manual migration checklist

Use this checklist on **staging first**, then again on production cut-over.

### Preparation

- [ ] Full database backup completed and restore tested
- [ ] Plugin/files backup or deploy rollback plan documented
- [ ] Maintenance window communicated (if needed)
- [ ] Old switcher currency list exported to a spreadsheet (codes, rates, enabled flags, symbols)
- [ ] Base store currency confirmed in WooCommerce → General (UMC never duplicates it)

### Plugin transition

- [ ] Old currency switcher **deactivated** (not merely disabled in settings)
- [ ] No HIGH conflict reported on Dashboard / Plugins / UMC settings tab
- [ ] UMC installed and activated; WooCommerce and HPOS requirements met ([`COMPATIBILITY.md`](COMPATIBILITY.md))
- [ ] Object cache and page cache flush planned for cut-over

### Configuration recreation

- [ ] Each non-base currency added in WooCommerce → Settings → Multicurrency
- [ ] Manual exchange rates entered (positive decimal strings; invalid rates blanked on save)
- [ ] Per-currency symbol, position, and decimals verified
- [ ] Base currency row behaviour understood (base comes from WooCommerce General, not `umc_settings`)

### Exchange-rate verification

- [ ] Spot-check rate × base price on a simple product in each enabled currency
- [ ] Change currency via storefront switcher; confirm page reload and consistent totals
- [ ] Edit a rate; confirm cart/catalogue reflect new rate after reload (rate identity invalidates caches)

### Order verification

- [ ] Place a **test order** in a non-base currency (classic checkout)
- [ ] Confirm order currency and totals match cart at checkout
- [ ] Confirm admin order audit meta box shows `_umc_*` snapshot ([`PERSISTED_DATA.md`](PERSISTED_DATA.md))
- [ ] Open the order confirmation in a **different** session currency; order still displays its own currency

### Refund verification

- [ ] Create a partial refund on the test order
- [ ] Confirm refund currency matches parent order
- [ ] Confirm `_umc_parent_*` refund meta present when parent had a snapshot

### Checkout validation

- [ ] Classic checkout: coupons, core shipping, taxes, gateways in selected currency
- [ ] Cart/Checkout **blocks**: Store API cart totals, shipping, taxes, gateways in selected currency
- [ ] Payment method list matches currency (unsupported gateways hidden, not rewritten)

### Payment validation

- [ ] Successful payment in non-base currency (use test gateway)
- [ ] Failed payment + retry path (Store API draft) still consistent with order currency

### Reporting validation

- [ ] WooCommerce reports and exports show expected currencies for new orders post cut-over
- [ ] Legacy orders unchanged (no re-conversion of historical totals)

### Production cut-over

- [ ] Staging checklist fully green
- [ ] Production: deactivate old switcher → activate/configure UMC → flush caches
- [ ] Post-deploy smoke documented in [`DEPLOYMENT.md`](DEPLOYMENT.md) for the deployed version
- [ ] Site Health → Universal Multicurrency tests reviewed

---

## Recommended deployment sequence

1. **Rehearse on staging** using the full checklist above.
2. **Deploy UMC** (release zip from GitHub or your build pipeline) without deactivating the old switcher if you need side-by-side comparison — but **never** accept live traffic with both converters active.
3. **Deactivate the old switcher** during the cut-over window.
4. **Configure UMC** manually from your spreadsheet; do not import foreign options.
5. **Flush** object cache, page cache, and any CDN cache for shop/cart routes.
6. **Verify** catalogue → cart → checkout → order admin → refund on production.
7. **Record** deployed commit/version in your runbook ([`DEPLOYMENT.md`](DEPLOYMENT.md) template per milestone).

For version-specific rollback notes, see the deployed release section in
[`DEPLOYMENT.md`](DEPLOYMENT.md).

---

## Rollback recommendations

| Scenario | Recommendation |
|---|---|
| Roll back UMC plugin files only | Safe to prior release zip; `_umc_*` order meta **remains** (permanent audit data) |
| Roll back `umc_settings` | Restore from backup if you changed rates. Downgrading below the plugin version that wrote schema v2 is **not** supported: an older build parses `schema_version: 2` as an unsupported future version and falls back to defaults in memory without rewriting the option |
| Roll back `umc_rate_state` | Safe to delete; it holds operational facts only and is rebuilt on the next update |
| Re-enable old switcher | Deactivate UMC first; never run both; re-test totals on staging |
| Uninstall UMC | Deletes `umc_settings` only; order/refund meta and dismissal user meta preserved (ADR-0009) |

**Never** delete `_umc_*` order metadata to "clean up" after rollback — it is
permanent commerce audit data.

Downgrading across major UMC versions: follow the rollback subsection of the
target release in [`DEPLOYMENT.md`](DEPLOYMENT.md).

---

## Verification checklist

Quick post-cut-over smoke (15–30 minutes):

1. Homepage / shop — prices in currency A and B
2. Switch currency — reload, cart recalculates
3. Add to cart — line totals match product display
4. Checkout — shipping and tax in active currency
5. Pay — order stored in transaction currency with snapshot
6. Admin order screen — audit box populated
7. My Account — order history shows order currency
8. Optional: block cart + block checkout parity

For automated regression, run the project's CI suites before tagging releases;
merchants rely on staging rehearsal, not CI.

---

## Common pitfalls

| Pitfall | Consequence | Prevention |
|---|---|---|
| Two switchers active | Multiplied rates, wrong order totals | Deactivate old switcher before go-live; heed Diagnostics HIGH |
| Copying rates from memory | Wrong checkout totals | Export rates to spreadsheet during prep |
| Skipping cache flush | Stale shipping or catalogue prices | Flush object/page cache at cut-over |
| Expecting automatic FOX/WOOCS import | Blocked by design | Use manual checklist |
| Re-converting old orders | Violates historical currency model | Leave legacy orders as-is |
| Editing base currency during cut-over | Rate and display confusion | Freeze WooCommerce General currency until stable |
| Dismissing conflict notices without fixing | Hidden dual conversion | Resolve conflicts; dismissal is per-user only |
| Testing only classic checkout | Blocks path untested | Exercise Store API cart/checkout if blocks are live |

---

## FAQ

**Can UMC import my WOOCS/FOX settings?**  
No. Automatic import from foreign switchers is intentionally not provided
(ADR-0003, ADR-0007).

**Will my old orders change currency?**  
No. Existing WooCommerce order currency and totals are authoritative. UMC does
not re-convert historical orders.

**What happens to `_umc_*` meta if I uninstall UMC?**  
Order and refund snapshot meta is **never** deleted on uninstall (ADR-0009).

**Can I run UMC alongside WPML Multicurrency?**  
No for production. WPML multicurrency is a built-in *Incompatible* detector
(see [`COMPATIBILITY.md`](COMPATIBILITY.md)).

**Is there a CSV import?**  
Not in the Release Candidate. A [UMC-native CSV format](#umc-csv-format-specification-future-only) is specified for possible future tooling; there is no parser or admin UI in RC.

**Does UMC migrate PHP sessions or cookies from the old switcher?**  
No. Shoppers may need to re-select currency after cut-over.

**Staging showed a conflict after I deactivated the old plugin**  
See ADR-0007: one residual notice can appear on the deactivation confirmation
screen; it self-heals on the next request. Persistent HIGH means another
converter is still active or registered.

**Who can see conflict notices?**  
Dashboard/plugins notices require `activate_plugins`. Settings tab warnings
require `manage_woocommerce` (see [`COMPATIBILITY.md`](COMPATIBILITY.md)).

---

## UMC CSV format specification (future only)

This section defines an optional **UMC-native** CSV format for **future**
import tooling. The Release Candidate ships **no parser, no exporter, and no
admin UI**. The format targets `umc_settings` only — never foreign switcher
exports.

### Design goals

- Human-editable bulk entry of currency rows
- Validated with the same rules as `Settings::sanitize()`
- Versioned independently from `umc_settings.schema_version`
- Extensible without breaking v1 consumers

### File metadata

First line may be a UTF-8 BOM; encoding must be UTF-8.

Optional header comment row (ignored by future parser if prefixed with `#`):

```csv
# umc_csv_version=1
# umc_csv_kind=currencies
# generated_by=optional-tool-name
```

| Field | Required | Meaning |
|---|---|---|
| `umc_csv_version` | Yes (in comment or dedicated column — see below) | Format version of **this CSV file**; start at `1` |
| `umc_csv_kind` | Yes | Must be `currencies` for currency-row files |

**Important:** `umc_csv_version` is **not** `umc_settings.schema_version`. File
format version governs CSV columns; settings schema version governs the WordPress
option shape ([`ARCHITECTURE.md`](ARCHITECTURE.md) § Settings schema upgrade).

### Column layout (umc_csv_version = 1)

Header row required. Column order fixed:

| Column | Required | Maps to | Validation |
|---|---|---|---|
| `currency_code` | Yes | `currencies[CODE]` key | `^[A-Z]{3}$` after trim/uppercase |
| `enabled` | No (default `true`) | `enabled` | Boolean: `1`/`0`, `true`/`false`, `yes`/`no` |
| `symbol` | No (default empty) | `symbol` | Stripped of HTML tags |
| `position` | No (default `left`) | `position` | One of `left`, `right`, `left_space`, `right_space` |
| `decimals` | No (default `2`) | `decimals` | Integer 0–4 |
| `rate` | Yes for non-base use | `manual_rate` (schema v2; the column keeps its v1 name) | Positive decimal string; empty or invalid → blanked |
| `rate_updated_at` | No (default `0`) | `rate_updated_at` | Non-negative Unix timestamp |

Example:

```csv
currency_code,enabled,symbol,position,decimals,rate,rate_updated_at
SEK,1,kr,right_space,2,11.50,1753440000
USD,1,$,left,2,1.20,0
JPY,1,¥,left,0,161,0
```

**Base currency:** Do not rely on CSV to set the store base currency. Base
currency remains WooCommerce `woocommerce_currency` only (ADR-0003). Rates
for the base code are ignored at runtime (same-currency rate is `1`).

### Validation rules (normative)

Future importers must:

1. Reject files with unknown `umc_csv_version`
2. Reject duplicate `currency_code` rows
3. Drop rows with invalid codes (do not fail entire file unless strict mode is explicitly requested by a future tool)
4. Normalize each row through the same rules as `Settings::sanitize()` for a single currency row
5. Merge into `umc_settings.currencies` without reading any foreign plugin data
6. Set `umc_settings.schema_version` to the current plugin `Settings::SCHEMA_VERSION` on save

### Versioning expectations

| Version | Change |
|---|---|
| `umc_csv_version` 1 | Initial column set above |

Bump `umc_csv_version` when columns are added or semantics change. Importers
must reject unknown versions rather than guess.

### Extension strategy

- **Additive columns** in CSV v2+: optional columns ignored by v1 importers
- **New `umc_csv_kind` values**: separate file types (e.g. future rate history)
  must not overload the `currencies` kind
- **No foreign mappings**: there will be no `woocs_*`, `fox_*`, or `wcml_*` columns in the UMC format

### Explicit non-goals (RC)

- No WordPress admin upload screen
- No runtime import hook in the plugin bootstrap
- No automatic detection of foreign CSV exports
- No migration from foreign switcher databases

---

## Document control

| Item | Value |
|---|---|
| Introduced | Milestone 7 Release Candidate |
| Last updated | Milestone 8 (v0.8.0) — schema v2 section added |
| Policy | Manual migration only; ADR-0003 / ADR-0007 |
| Settings schema | Internal 0→1 and 1→2 upgrades (`SettingsUpgrader`); see [`ARCHITECTURE.md`](ARCHITECTURE.md) |
| Operational state | `umc_rate_state`, never migrated with settings; ADR-0012 |
| Uninstall | [`ADR-0009`](adr/0009-uninstall-retention-policy.md) |
