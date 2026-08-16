import { test, expect } from '@playwright/test';
import { assertDevHostOnly } from '../fixtures/production-guard.js';
import { loginAsAdmin } from '../fixtures/auth.js';
import { baseUrl } from '../fixtures/env.js';
import { loadFixtures, type Fixtures } from '../fixtures/fixtures-io.js';
import { exportProducts, importCsv, hasNativeImportErrors } from '../fixtures/woocommerce-admin.js';
import { writeCsv, findRowBySku, type CsvRow } from '../fixtures/csv.js';
import { writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/**
 * M25 browser acceptance suite -- exercises WooCommerce's real Products ->
 * Export/Import admin UI (never a PHP-level shortcut) against disposable,
 * uniquely-prefixed fixture products on an authorized DEV environment only.
 *
 * Sequential by design (test.describe.serial): fixtures are shared and
 * mutated across cases in a deliberate, documented order so each test's
 * starting state is predictable. See README.md for the full state map.
 */

let fixtures: Fixtures;

function tmpCsvPath(name: string): string {
	return join(tmpdir(), `${name}-${Date.now()}.csv`);
}

test.beforeAll(() => {
	assertDevHostOnly(baseUrl());
	fixtures = loadFixtures();
});

test.describe.serial('M25 Fixed Pricing CSV Interchange', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	// -----------------------------------------------------------------
	// TEST 1-9: export-only, read-only w.r.t. fixture state.
	// -----------------------------------------------------------------

	test('TEST 1 -- export column discovery', async ({ page }) => {
		await page.goto('/wp-admin/edit.php?post_type=product&page=product_exporter');
		const options = await page.locator('#woocommerce-exporter-columns option').allTextContents();
		expect(options.some((o) => /UMC Fixed Regular Price \(SEK\)/.test(o))).toBe(true);
		expect(options.some((o) => /UMC Fixed Sale Price \(SEK\)/.test(o))).toBe(true);
	});

	test('TEST 2 -- base currency exclusion', async ({ page }) => {
		await page.goto('/wp-admin/edit.php?post_type=product&page=product_exporter');
		const options = await page.locator('#woocommerce-exporter-columns option').allTextContents();
		const baseColumnPattern = new RegExp(`UMC Fixed (Regular|Sale) Price \\(${fixtures.base_currency}\\)`);
		expect(options.some((o) => baseColumnPattern.test(o))).toBe(false);
	});

	test('TEST 3 -- enabled currency export contains the exact authored amount', async ({ page }) => {
		const rows = await exportProducts(page);
		const row = findRowBySku(rows, fixtures.simple.sku);
		expect(row.umc_fixed_regular_sek).toBe('799.00');
		expect(row.umc_fixed_sale_sek).toBe('650.00');
	});

	test('TEST 4 -- disabled configured currency export contains the retained authored value', async ({ page }) => {
		const rows = await exportProducts(page);
		const row = findRowBySku(rows, fixtures.simple.sku);
		expect(row.umc_fixed_regular_usd).toBe('90.00');
	});

	test('TEST 5 -- blank fixed field exports as a blank cell', async ({ page }) => {
		const rows = await exportProducts(page);
		const row = findRowBySku(rows, fixtures.simple.sku);
		expect(row.umc_fixed_regular_pln ?? '').toBe('');
		expect(row.umc_fixed_sale_pln ?? '').toBe('');
	});

	test('TEST 6 -- variable parent UMC cells are blank', async ({ page }) => {
		const rows = await exportProducts(page);
		const row = findRowBySku(rows, fixtures.variable_parent.sku);
		expect(row.umc_fixed_regular_sek ?? '').toBe('');
		expect(row.umc_fixed_sale_sek ?? '').toBe('');
	});

	test('TEST 7 -- variations export their own document, no leakage', async ({ page }) => {
		const rows = await exportProducts(page);
		const rowA = findRowBySku(rows, fixtures.variation_a.sku);
		const rowB = findRowBySku(rows, fixtures.variation_b.sku);
		expect(rowA.umc_fixed_regular_sek).toBe('188.00');
		expect(rowB.umc_fixed_regular_sek).toBe('277.00');
		expect(rowA.umc_fixed_regular_sek).not.toBe(rowB.umc_fixed_regular_sek);
	});

	test('TEST 8 -- default "export all columns" includes UMC columns', async ({ page }) => {
		// exportProducts() with no `columns` leaves WooCommerce's picker
		// untouched -- the real default "export all columns" behavior.
		const rows = await exportProducts(page);
		const row = findRowBySku(rows, fixtures.simple.sku);
		expect(Object.keys(row)).toEqual(expect.arrayContaining(['umc_fixed_regular_sek', 'umc_fixed_sale_sek']));
	});

	test('TEST 9 -- narrowed export selection can include/exclude UMC columns individually', async ({ page }) => {
		const rows = await exportProducts(page, { columns: ['id', 'sku', 'umc_fixed_regular_sek'] });
		const row = findRowBySku(rows, fixtures.simple.sku);
		expect(row.umc_fixed_regular_sek).toBe('799.00');
		// Narrowed to just id/sku/umc_fixed_regular_sek -- confirms the
		// picker actually constrains output (not "export all columns"
		// regardless of selection).
		expect(row.umc_fixed_sale_sek).toBeUndefined();
		// WooCommerce's own "Regular price" column (label form, not the
		// internal id) must also be excluded by the same narrowed selection.
		expect(row['Regular price']).toBeUndefined();
	});

	// -----------------------------------------------------------------
	// TEST 18/19: storefront, run early against pristine fixture values
	// (before any of the mutating import tests below change them).
	// -----------------------------------------------------------------

	test('TEST 18 -- storefront displays the authored fixed price, not a converted one', async ({ page }) => {
		await page.goto(`/?p=${fixtures.simple.id}&currency=SEK`);
		// Native sale is active on the fixture (regular 100 / sale 80 EUR),
		// so WooCommerce's own on-sale state is true and the authored SEK
		// SALE amount (650.00) is what must display -- not the SEK regular
		// (799.00), and not any FX-converted figure (100*11.50=1150.00,
		// 80*11.50=920.00 -- neither of which is 650.00, so a match is
		// unambiguous proof the fixed value was used).
		await expect(page.locator('.summary').first()).toContainText('650');
		await expect(page.locator('.summary').first()).not.toContainText('1 150');
		await expect(page.locator('.summary').first()).not.toContainText('920');
	});

	test('TEST 19 -- FX fallback still works for a currency with no authored fixed price', async ({ page }) => {
		await page.goto(`/?p=${fixtures.simple.id}&currency=PLN`);
		// PLN was never authored for this product -- M20 FX fallback must
		// still convert the active (sale) base price: 80 EUR * 4.3068 rate
		// (umc_settings) = 344.54.
		await expect(page.locator('.summary').first()).toContainText('344');
	});

	// -----------------------------------------------------------------
	// TEST 10: import mapping -- idempotent no-op reimport of the pristine
	// exported row, proving auto-mapping AND that re-importing unchanged
	// values doesn't corrupt state (fixture remains pristine for TEST 11).
	// -----------------------------------------------------------------

	test('TEST 10 -- import recognizes and auto-maps the structured UMC columns', async ({ page }) => {
		const rows = await exportProducts(page);
		const row = findRowBySku(rows, fixtures.simple.sku);
		const header = Object.keys(row);
		const csvPath = tmpCsvPath('test10-mapping');
		writeFileSync(csvPath, writeCsv([row], header));

		const { mapping } = await importCsv(page, csvPath, header, { updateExisting: true });

		expect(mapping['UMC Fixed Regular Price (SEK)']).toBe('umc_fixed_regular_sek');
		expect(mapping['UMC Fixed Sale Price (SEK)']).toBe('umc_fixed_sale_sek');
		expect(mapping['UMC Fixed Regular Price (USD)']).toBe('umc_fixed_regular_usd');

		// Idempotent reimport: fixture state must be unchanged.
		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		expect(after.umc_fixed_regular_sek).toBe('799.00');
		expect(after.umc_fixed_sale_sek).toBe('650.00');
	});

	// -----------------------------------------------------------------
	// TEST 11: raw meta bypass defense. Scenarios A/B/D are pure raw-meta
	// attacks with NO structured columns (fixture stays pristine).
	// Scenario C adds a genuine structured change to DKK, a currency no
	// other test touches, to isolate its effect.
	// -----------------------------------------------------------------

	function maliciousPayload(baseCurrency: string): string {
		return JSON.stringify({
			schema_version: 1,
			currencies: {
				[baseCurrency]: { regular: '1.00' }, // base-currency injection attempt
				SEK: { regular: '666.00', sale: '666.00' }, // attempts to overwrite legitimate SEK data
			},
		});
	}

	test('TEST 11a -- raw meta bypass: existing legitimate document + no raw column -> unchanged', async ({ page }) => {
		// Baseline control case (scenario A): a completely ordinary update
		// import with no meta: column at all. Uses the fixture's own known
		// numeric id/sku directly -- NOT re-read from the exported CSV, whose
		// "ID"/"SKU" columns are WooCommerce's human-readable LABELS
		// (csv-parse's columns:true uses header text verbatim as keys), not
		// lowercase `id`/`sku`. Reading those as `row.id`/`row.sku` silently
		// yields undefined, producing a CSV with blank identifier cells that
		// WooCommerce's own importer then SKIPS entirely before any UMC hook
		// runs -- which would make this test pass for the wrong reason (an
		// unprocessed row looks identical to a correctly-defended one). The
		// doneSummary assertion below exists specifically to catch that
		// failure mode should it recur.
		const header = ['id', 'sku'];
		const csvRow: CsvRow = { id: String(fixtures.simple.id), sku: fixtures.simple.sku };
		const csvPath = tmpCsvPath('test11a-no-raw-column');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		const { doneSummary } = await importCsv(page, csvPath, header, { updateExisting: true });
		expect(doneSummary).toMatch(/updated/i); // proves the row was actually processed, not skipped

		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		expect(after.umc_fixed_regular_sek).toBe('799.00');
		expect(after.umc_fixed_sale_sek).toBe('650.00');
	});

	test('TEST 11b -- raw meta bypass: malicious meta: column cannot overwrite or inject base currency', async ({
		page,
	}) => {
		const header = ['id', 'sku', 'meta:_umc_fixed_prices'];
		const csvRow: CsvRow = {
			id: String(fixtures.simple.id),
			sku: fixtures.simple.sku,
			'meta:_umc_fixed_prices': maliciousPayload(fixtures.base_currency),
		};
		const csvPath = tmpCsvPath('test11b-malicious-raw');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		const { doneSummary } = await importCsv(page, csvPath, header, { updateExisting: true });
		expect(doneSummary).toMatch(/updated/i);

		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		// Legitimate SEK data survives byte-identical -- the malicious
		// overwrite attempt (666.00/666.00) never took effect.
		expect(after.umc_fixed_regular_sek).toBe('799.00');
		expect(after.umc_fixed_sale_sek).toBe('650.00');
		// Base currency never becomes an exported UMC column at all, which
		// is itself proof no effective base-currency fixed price exists --
		// re-assert TEST 2's invariant held even under direct attack.
		const baseColumnPattern = new RegExp(`umc_fixed_(regular|sale)_${fixtures.base_currency.toLowerCase()}`);
		expect(Object.keys(after).some((k) => baseColumnPattern.test(k))).toBe(false);
	});

	test('TEST 11c -- raw meta bypass: malicious raw column + valid structured column in the same row', async ({
		page,
	}) => {
		const header = ['id', 'sku', 'meta:_umc_fixed_prices', 'umc_fixed_regular_dkk'];
		const csvRow: CsvRow = {
			id: String(fixtures.simple.id),
			sku: fixtures.simple.sku,
			'meta:_umc_fixed_prices': maliciousPayload(fixtures.base_currency),
			umc_fixed_regular_dkk: '55.00', // sanctioned, first-time DKK authoring
		};
		const csvPath = tmpCsvPath('test11c-raw-plus-structured');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		const { doneSummary } = await importCsv(page, csvPath, header, { updateExisting: true });
		expect(doneSummary).toMatch(/updated/i);

		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		// Raw blob ignored -- legitimate existing SEK data untouched.
		expect(after.umc_fixed_regular_sek).toBe('799.00');
		expect(after.umc_fixed_sale_sek).toBe('650.00');
		// Sanctioned structured column applied correctly on top of the
		// (correctly preserved) legitimate document.
		expect(after.umc_fixed_regular_dkk).toBe('55.00');
	});

	test('TEST 11d -- raw meta bypass: new product, raw column only -> never persists', async ({ page }) => {
		const sku = `m25e2e-${fixtures.run_id}-newraw`;
		const header = ['sku', 'name', 'regular_price', 'meta:_umc_fixed_prices'];
		const csvRow: CsvRow = {
			sku,
			name: `M25 E2E New Raw ${fixtures.run_id}`,
			regular_price: '50',
			'meta:_umc_fixed_prices': maliciousPayload(fixtures.base_currency),
		};
		const csvPath = tmpCsvPath('test11d-new-product-raw-only');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		// No `id` column and update_existing is irrelevant for a brand-new
		// SKU -- this is a genuine create path (§11 of the acceptance spec:
		// "new-product raw meta test").
		await importCsv(page, csvPath, header, { updateExisting: false });

		const rows = await exportProducts(page);
		const row = findRowBySku(rows, sku);
		const baseColumnPattern = new RegExp(`umc_fixed_(regular|sale)_${fixtures.base_currency.toLowerCase()}`);
		expect(Object.keys(row).some((k) => baseColumnPattern.test(k))).toBe(false);
		expect(row.umc_fixed_regular_sek ?? '').toBe('');
		expect(row.umc_fixed_sale_sek ?? '').toBe('');
	});

	// -----------------------------------------------------------------
	// TEST 12-17: mutating structured-column tests, in a deliberate order.
	// State after each (SEK only unless noted; USD/DKK/PLN untouched
	// throughout this whole block, verified in TEST 14):
	//   after 12: regular=700.00 sale=650.00 (unchanged; 700>=650, a valid pair)
	//   after 13: regular=700.00 sale=''      (explicit clear)
	//   after 16: regular=333.00 sale=''      (unchanged, not remapped)
	//   after 17: regular=333.00 sale=''      (rejected, unchanged)
	// TEST 12's target (700.00) is deliberately >= the pristine sale
	// (650.00) it leaves unmapped/unchanged -- an earlier version of this
	// test used 555.00, which is LESS than 650.00 and therefore produces an
	// invalid sale>regular pair purely as an artifact of the chosen fixture
	// numbers; FixedPriceDocumentMerger correctly rejected that atomically
	// and reverted to the pristine state, which is exactly TEST 17's
	// intended scenario, not TEST 12's -- a genuine test-authoring bug, not
	// a product defect (verified by reproducing it, then choosing a
	// deliberately valid target here instead).
	// -----------------------------------------------------------------

	test('TEST 12 -- simple structured update changes the canonical fixed-price state', async ({ page }) => {
		const header = ['id', 'sku', 'umc_fixed_regular_sek'];
		const csvRow: CsvRow = { id: String(fixtures.simple.id), sku: fixtures.simple.sku, umc_fixed_regular_sek: '700.00' };
		const csvPath = tmpCsvPath('test12-structured-update');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		await importCsv(page, csvPath, header, { updateExisting: true });

		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		expect(after.umc_fixed_regular_sek).toBe('700.00');
		expect(after.umc_fixed_sale_sek).toBe('650.00'); // untouched -- not mapped in this row
	});

	test('TEST 13 -- explicit blank clear removes the targeted value, unrelated values survive', async ({ page }) => {
		const header = ['id', 'sku', 'umc_fixed_sale_sek'];
		const csvRow: CsvRow = { id: String(fixtures.simple.id), sku: fixtures.simple.sku, umc_fixed_sale_sek: '' };
		const csvPath = tmpCsvPath('test13-explicit-clear');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		await importCsv(page, csvPath, header, { updateExisting: true });

		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		expect(after.umc_fixed_sale_sek ?? '').toBe(''); // cleared
		expect(after.umc_fixed_regular_sek).toBe('700.00'); // survives -- field-level, not whole-currency
	});

	test('TEST 14 -- patch semantics: currencies never mapped in TEST 12/13 remain untouched', async ({ page }) => {
		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		expect(after.umc_fixed_regular_usd).toBe('90.00');
		expect(after.umc_fixed_regular_dkk).toBe('55.00'); // set in TEST 11c
		expect(after.umc_fixed_regular_pln ?? '').toBe('');
	});

	test('TEST 15 -- variation update changes only the target variation', async ({ page }) => {
		const header = ['id', 'sku', 'umc_fixed_regular_sek'];
		const csvRow: CsvRow = {
			id: String(fixtures.variation_a.id),
			sku: fixtures.variation_a.sku,
			umc_fixed_regular_sek: '222.00',
		};
		const csvPath = tmpCsvPath('test15-variation-update');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		await importCsv(page, csvPath, header, { updateExisting: true });

		const rows = await exportProducts(page);
		expect(findRowBySku(rows, fixtures.variation_a.sku).umc_fixed_regular_sek).toBe('222.00');
		expect(findRowBySku(rows, fixtures.variation_b.sku).umc_fixed_regular_sek).toBe('277.00'); // sibling untouched
		const parentRow = findRowBySku(rows, fixtures.variable_parent.sku);
		expect(parentRow.umc_fixed_regular_sek ?? '').toBe(''); // parent never gains a fixed price
	});

	test('TEST 16 -- partial-column import: only mapped/present fields participate', async ({ page }) => {
		const header = ['id', 'sku', 'umc_fixed_regular_sek']; // sale/USD/DKK/PLN deliberately absent
		const csvRow: CsvRow = { id: String(fixtures.simple.id), sku: fixtures.simple.sku, umc_fixed_regular_sek: '333.00' };
		const csvPath = tmpCsvPath('test16-partial-column');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		const { doneSummary } = await importCsv(page, csvPath, header, { updateExisting: true });
		expect(doneSummary).toContain('1');

		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		expect(after.umc_fixed_regular_sek).toBe('333.00');
		expect(after.umc_fixed_sale_sek ?? '').toBe(''); // still cleared from TEST 13, not remapped here
		expect(after.umc_fixed_regular_usd).toBe('90.00'); // still untouched
	});

	test('TEST 17 -- sale > regular is rejected atomically, WooCommerce does not fail the row', async ({ page }) => {
		// Current state: SEK regular=333.00, sale=''. Mapping only `sale` at
		// a value greater than the existing regular is a partial update that
		// would invert the pair -- must be rejected wholesale, reverting to
		// the prior state, never a half-applied write.
		const header = ['id', 'sku', 'umc_fixed_sale_sek'];
		const csvRow: CsvRow = { id: String(fixtures.simple.id), sku: fixtures.simple.sku, umc_fixed_sale_sek: '999.00' };
		const csvPath = tmpCsvPath('test17-sale-gt-regular');
		writeFileSync(csvPath, writeCsv([csvRow], header));

		const { doneSummary } = await importCsv(page, csvPath, header, { updateExisting: true });

		// WooCommerce must still report this as a normal, successful product
		// update -- a UMC-only field problem must never surface as a native
		// import failure.
		expect(doneSummary).toMatch(/updated/i);
		expect(await hasNativeImportErrors(page)).toBe(false);

		const after = findRowBySku(await exportProducts(page), fixtures.simple.sku);
		expect(after.umc_fixed_regular_sek).toBe('333.00'); // previous regular survives
		expect(after.umc_fixed_sale_sek ?? '').toBe(''); // rejected -- not set to 999.00
	});
});
