# Universal Multicurrency v1.3.0 — Release Closure

**Status:** **TAGGED / GITHUB RELEASE PUBLISHED** — published artifact independently verified  
**Version:** 1.3.0  
**Settings schema:** **8**  
**OrderSnapshot:** **5** (unchanged)  
**PersistedKeys inventory:** **12**  
**CacheState:** **v1** (unchanged)  
**Migration:** **NONE** (schema 7→8 in-place settings upgrade; preferred-currency user meta additive)  
**Release-ready commit (tagged):** `2da36a0709e65af73b82b18eead9141a8d0846e2`  
**Annotated tag:** `v1.3.0`  
**GitHub Release:** https://github.com/magpern/universal-multicurrency/releases/tag/v1.3.0  
**Previous release:** `v1.2.1` @ `be18f2d629caec5dfd45d2b459fe31cfb4178b8a`  
**Production:** **UNTOUCHED**

## Preflight

| Field | Value |
|---|---|
| Starting `origin/main` (implementation closure) | `d55aec7408b63c010ee6b929d13e86cc211634d5` |
| Latest published tag before this release | `v1.2.1` |
| Plugin version at start | 1.2.1 |
| Chosen next version | **1.3.0** (MINOR: user-facing Regional Preferences + Edge Pill / UML-family selector since `v1.2.1`) |
| Settings schema | **8** |
| PersistedKeys | **12** |
| DB migration | **NONE** |

## Scope included (all merged work since v1.2.1)

### Regional preferences (PR #33, ADR-0036)

- Authenticated preferred currency on WordPress Profile and WooCommerce Account details
- Regional Preferences composition with Universal Multilingual
- Resolver precedence remains `explicit > session > cookie > user_preferred > base`

### Currency selector / display (PR #32, PR #34, ADR-0035, ADR-0037)

- Edge Pill / Minimal Icon / Tab floating family
- UML v1.12.0 visual alignment (no UML runtime dependency)
- Flag + ISO currency code on the collapsed family control
- EUR always uses the EU flag (presentation-only; not derived from geo)
- Unknown/unmapped currencies fall back to code
- Same-edge stacking via `data-um-edge-control` + CSS `:has()`
- Accessibility / progressive enhancement; switching remains `?currency=`

### Architecture / compatibility

- `?currency=`, session, cookie, geo, pricing/FX unchanged
- Settings schema 8, inventory 12, OrderSnapshot 5, CacheState v1
- No DB migration

Selector UML-alignment implementation closure:
[`UMC_CURRENCY_SELECTOR_UML_ALIGNMENT_CLOSURE.md`](UMC_CURRENCY_SELECTOR_UML_ALIGNMENT_CLOSURE.md)
(`d55aec7`).

## Tag

| Field | Value |
|---|---|
| Tag | `v1.3.0` |
| Type | Annotated |
| Target commit | `2da36a0709e65af73b82b18eead9141a8d0846e2` |
| Tag object | `070adbbc33d264b714c715479422c73389a0b7ba` |
| Push | SUCCESS — origin `refs/tags/v1.3.0` |

**Do not move this tag** for later docs-only closure commits.

## Release automation

| Workflow | Run | Result |
|---|---|---|
| Main CI (release-prep push) | [34195971983](https://github.com/magpern/universal-multicurrency/actions/runs/34195971983) | **SUCCESS** 14/14 |
| Release (`.github/workflows/release.yml`) | [34196263976](https://github.com/magpern/universal-multicurrency/actions/runs/34196263976) | **SUCCESS** |
| Publish release package | [34196263971](https://github.com/magpern/universal-multicurrency/actions/runs/34196263971) | **SUCCESS** |

GitHub Release created by the Release workflow (`generate_release_notes: true`). No duplicate manual release.

## Published GitHub Release asset (source of truth)

Independently downloaded from the GitHub Release (not a local ZIP).

| Field | Value |
|---|---|
| Filename | `universal-multicurrency-1.3.0.zip` |
| Source | GitHub Release `v1.3.0` asset |
| Byte size | **769487** |
| SHA-256 | `81ecf26b1a7d11bded2573d4b86d524938dcddb26cab964603e14028febaceb8` |
| Archive entries | **522** |
| Root directory | `universal-multicurrency/` |
| Plugin header Version | **1.3.0** |
| `UMC_VERSION` | **1.3.0** |
| `readme.txt` Stable tag | **1.3.0** |
| Selector CSS/JS | `assets/css/switcher.css`, `assets/js/switcher.js` |
| Presentation flags | `SE.svg`, `EU.svg` present |
| Forbidden paths (`tests/`, `.git`, `.github`, `docs/plans/`, `node_modules`) | None |

Draft: **no**. Prerelease: **no**.

## Validation gates

| Gate | Result |
|---|---|
| PHPCS | PASS (0 errors / 0 warnings, 598 files) |
| Unit | PASS (1404 tests) |
| Integration | PASS (GitHub CI matrix) |
| Authority / CurrencyResolver / PreferredCurrency / Geo | PASS (unit + CI) |
| Release-audit job | PASS |
| POT | PASS |
| Mutation | PASS |
| Playwright Chromium | Bounded (cache `EACCES`; live DEV used instead) |
| DEV acceptance (`dev.biopentra.eu` only) | PASS |
| Production | **UNTOUCHED** |

## DEV acceptance restore

- UMC display: `classic_dropdown`, `floating_side`, `left`, `middle`, `mobile_behavior=retain`, trigger code-only
- UML: `floating_selector_enabled=false`
- Session currency: SEK

## Related plugins

| Repo | Decision |
|---|---|
| `magpern/universal-multilingual` | **v1.12.0 ALREADY CURRENT** — only docs-only commit after tag (`96b8d9977`). No new release. |
| `magpern/universal-geo-context` | Not part of this train (unrelated; latest tag current on origin). |
| `biopentra-custom-plugins` / storefront | No unreleased runtime for this work (`storefront-v0.9.45`). |

## Residuals

- Playwright visual harness still not installed on this host
- `:has()`-less browsers: possible same-edge overlap (documented)
- Production install remains a separate manual step
