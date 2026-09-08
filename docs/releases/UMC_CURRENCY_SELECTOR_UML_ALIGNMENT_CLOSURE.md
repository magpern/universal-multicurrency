# UMC Currency Selector UML Alignment — Closure

Canonical freeze: ADR-0037 and `docs/architecture/switcher-uml-family-alignment.md`.

`docs/plans/` remains local-only (`ReleaseAuditTest` forbids tracking it). This
file is the committed implementation-closure record.

## Verdict

UMC CURRENCY SELECTOR UML ALIGNMENT: **CLOSED — PASS**

## Baseline

| Item | Value |
|---|---|
| Starting `origin/main` | `40b733df397e3b1c130a0c84891b08bbf0475a50` |
| Starting version | 1.2.1 |
| Starting Settings schema | 8 |
| Starting persisted inventory | 12 |
| OrderSnapshot | 5 |
| CacheState | 1 |
| Upstream drift | None material; plan reconciled against that SHA |
| UML visual reference | tag **v1.12.0** / `e1d80bc05da0395cc7e58422bb91ce4c52d49207` |
| Frozen plan | ADR-0037 + architecture spec |
| Feature branch | `feature/umc-selector-uml-alignment` |
| Implementation commits | `195d4bc` feat; `c370ee4` EUR admin notice + Escape capture |
| PR | [#34](https://github.com/magpern/universal-multicurrency/pull/34) |
| Merge SHA | `a429286c728323135db006561c9e3376f9f4b591` |
| Final version at milestone close | 1.2.1 (no version bump in the implementation merge) |
| Final schema / inventory | 8 / 12 |

## Implementation

Existing selector stack refactored (SwitcherSettings → Factory → Renderer → CSS/JS). **Not duplicated.**

- Family presentations: `edge_pill`, `minimal_icon`, additive `tab`
- Collapsed family trigger: flag + ISO code; code-only if asset missing
- EUR → EU authoritative; stored EUR override kept, ignored at runtime; no EUR picker
- Flags from presentation map/assets only (not geo)
- Added issuer SVGs: JP, CA, AU, NZ, KR, CN, IN, BR, MX, SG, HK, ZA, CZ
- Supranational X* → no flag; unknown → code only
- Open panel: flag + code + name + check; `aria-current="true"`
- Disclosure button + `?currency=` links; `data-umc-enhanced="1"` after JS
- Edge attrs: `data-um-edge-control="currency"` slot 2 priority 20
- Same-edge `:has()` offset `calc(2.75rem + 0.5rem)`; top down, bottom up, middle down
- `:has()` unsupported → no offset (documented)
- Saved `mobile_behavior` preserved; new Floating family default `retain`
- No UML runtime dependency; UMC-owned CSS/JS

Authority unchanged: resolver precedence `explicit > session > cookie > user_preferred > base`; session, cookie, preferred currency, geo, pricing/FX, `?currency=` + `CurrencySwitcher::maybe_switch()`. Cache `state_hash` unchanged. No DB migration.

## DEV acceptance (`dev.biopentra.eu` only)

Bind-mount active. Production `biopentra.eu` not touched.

Verified: Edge Pill collapsed flag+code; EUR→EU.svg; SEK→SE.svg; open panel; `?currency=EUR` persist+redirect (prices in €); Escape closes; same-right-edge with UML language control (no overlap, 0.5rem gap); UML absent when `floating_selector_enabled=false`.

Settings restored:

- **UMC display:** `classic_dropdown`, `floating_side`, `left`, `middle`, `mobile_behavior=retain`, trigger code-only
- **UML:** `floating_selector_enabled=false`
- **Session currency:** SEK

Bounded notes: Playwright Chromium not installed (`EACCES` on ms-playwright cache). Dedicated 375/768/1440 matrix not screenshot-asserted; live checks used the automation viewport (~300px) plus prior desktop pass.

## Gates

| Gate | Result |
|---|---|
| Local unit (pre-merge) | PASS (1404 tests) |
| PHPCS | PASS (PR + main) |
| PR CI `#34` run `34194491927` | 14/14 PASS |
| Independent review | PASS WITH NOTES after Escape/EUR-admin remediations |
| DEV acceptance | PASS (bounded Playwright) |
| Merge | `a429286` (merge commit, no-ff) |
| Fresh-main CI run `34194782549` | PASS |
| Release at milestone close | **NOT PERFORMED** |
| Production | **UNTOUCHED** |

## Deferred

- Playwright visual harness (browser install)
- Optional future UML vertical-region negotiation
- Residual theme `button { background !important }` clash (family uses its own `!important`)
- `:has()`-less browsers: possible same-edge overlap
