# Translation readiness

How Universal Multicurrency prepares user-facing strings for translators and
how the project guards against i18n drift.

**Canonical text domain:** `universal-multicurrency` (plugin header `Text Domain:`)

Related: [`CLAUDE.md`](../CLAUDE.md) naming conventions, [`TEST_STRATEGY.md`](TEST_STRATEGY.md)
translation guards.

---

## Text domain policy

- One text domain only: **`universal-multicurrency`**
- No aliases, no secondary domains
- PHPCS `WordPress.WP.I18n` enforces the domain in `src/`, root plugin file, and tests
- `TranslationReadinessTest` binds the plugin header, source calls, POT metadata, and
  `load_plugin_textdomain()` registration

Strings that must **not** be translated include option names, meta keys, hook names,
currency codes, API field names, third-party detector product labels in
`DetectorManifest`, and developer-only exception messages in pure domain classes.

---

## POT regeneration

The canonical template lives at:

```
languages/universal-multicurrency.pot
```

Regenerate after changing any translatable string:

```bash
composer make-pot
```

Verify the committed file matches current source (CI runs this):

```bash
composer make-pot:check
```

Implementation: `bin/make-pot.sh` using `wp i18n make-pot` (host `wp` when
available, otherwise `wordpress:cli-php8.1` via Docker). The script strips
wall-clock POT headers (`POT-Creation-Date`, `PO-Revision-Date`, `X-Generator`)
so diffs are deterministic.

### Translation contribution workflow

1. Fork/branch and change source strings with the correct WordPress i18n function.
2. Run `composer make-pot` and commit the updated `.pot` when msgids change.
3. Create a locale file `languages/universal-multicurrency-{locale}.po` from the POT
   (standard gettext tooling or Poedit).
4. Compile to `languages/universal-multicurrency-{locale}.mo` for runtime loading.
5. Open a pull request; CI must pass `composer make-pot:check` and `TranslationReadinessTest`.

Placeholders use numbered `%1$s` / `%2$d` forms where reordering may be required.
Add `/* translators: … */` comments when placeholder meaning is not obvious (currency
code, plugin path, schema version, etc.).

---

## Runtime loading

`UMC\Plugin::init()` registers:

```php
load_plugin_textdomain(
    'universal-multicurrency',
    false,
    dirname( plugin_basename( UMC_PLUGIN_FILE ) ) . '/languages'
);
```

Release zips include the `languages/` directory when present (`bin/build-zip.sh`).

---

## JavaScript translation status

**No shipped JavaScript files.** The storefront switcher, admin settings table, and
diagnostics notices are server-rendered PHP only. There is no webpack/block editor
bundle and no `wp_set_script_translations()` registration.

`TranslationReadinessTest` asserts the repository contains no `*.js` files under
`src/` or `assets/` so accidental untranslated client-side strings are caught early.
If JavaScript is added later, strings must use `@wordpress/i18n`, script translations
must be registered, and msgids must be included in the POT via `@wordpress/i18n` extraction
or an documented equivalent.

---

## RTL readiness audit (Release Candidate)

Audit date: Milestone 7 Commit 5. **RTL CSS fixes are not mandatory for RC** (approved
plan decision). Findings below classify current markup/styles only.

| Area | Finding | Classification |
|---|---|---|
| Admin currency table (`CurrencyTableField`) | Only inline style is `max-width:820px` | **Compatible** |
| Symbol position labels (`Left` / `Right`) | Currency symbol placement, not layout direction | **Compatible** (translated terminology) |
| Order audit meta box | Default `widefat` table; no directional CSS | **Compatible** |
| Storefront switcher (`Frontend/Switcher.php`) | Unordered list + `<select>`; no custom CSS file | **Compatible** |
| Diagnostics notices | Standard WordPress `.notice` markup | **Compatible** |
| Site Health panels | Core Site Health layout | **Compatible** |
| Custom plugin CSS | None shipped | **Compatible** |

### Known RTL limitations (acceptable for RC)

- No dedicated RTL stylesheet or `rtl.css`
- WooCommerce admin table column order follows LTR defaults (same as core WC settings)
- Long English conflict-notice paragraphs rely on core notice wrapping

### Future fix candidates (post-RC)

- Add `rtl.css` only if merchant feedback shows switcher/list alignment issues in RTL locales
- Replace any future custom directional CSS with logical properties (`margin-inline`, `text-align: start`)

---

## Automated drift protection

| Guard | Purpose |
|---|---|
| `composer make-pot:check` / CI `pot` job | Regenerate-and-diff; fails on stale POT |
| `tests/unit/TranslationReadinessTest.php` | Text domain, POT presence, representative msgids, no shipped JS |
| PHPCS `WordPress.WP.I18n` | Wrong/missing text domain in PHP |
| `tests/unit/MigrationDocumentationTest.php` | Unrelated M7 doc guard (unchanged) |

---

## Document control

| Item | Value |
|---|---|
| Introduced | Milestone 7 Release Candidate — Commit 5 |
| Text domain | `universal-multicurrency` |
| POT path | `languages/universal-multicurrency.pot` |
| JS status | None shipped |
| RTL | Audit documented; no mandatory RC fixes |
