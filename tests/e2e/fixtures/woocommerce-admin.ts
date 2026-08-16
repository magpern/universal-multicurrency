import type { Page } from '@playwright/test';
import { parseCsv, withUmcColumnAliases, type CsvRow } from './csv.js';

/**
 * Drives WooCommerce's real Products -> Export screen
 * (edit.php?post_type=product&page=product_exporter) and returns the
 * downloaded CSV parsed into rows. `columns`, when provided, narrows the
 * "Which columns should be exported?" select2 picker (Test 9); omit it to
 * exercise the default "export all columns" behavior (Test 8).
 */
export async function exportProducts(page: Page, options: { columns?: string[] } = {}): Promise<CsvRow[]> {
	await page.goto('/wp-admin/edit.php?post_type=product&page=product_exporter');

	if (options.columns) {
		// The native <select> is select2-enhanced (visually hidden), but
		// selectOption() operates on the DOM element directly and dispatches
		// the change event select2 listens for -- the standard robust way to
		// drive a WooCommerce admin enhanced-select without depending on the
		// select2 popup's own (more brittle) search/click UI.
		await page.locator('#woocommerce-exporter-columns').selectOption(options.columns);
	}

	const downloadPromise = page.waitForEvent('download', { timeout: 45_000 });
	await page.locator('button.woocommerce-exporter-button').click();
	const download = await downloadPromise;

	const stream = await download.createReadStream();
	const chunks: Buffer[] = [];
	for await (const chunk of stream!) {
		chunks.push(chunk as Buffer);
	}
	const content = Buffer.concat(chunks).toString('utf-8');

	return parseCsv(content).map(withUmcColumnAliases);
}

/**
 * Drives WooCommerce's real Products -> Import screen
 * (edit.php?post_type=product&page=product_importer) end to end: upload,
 * confirm/adjust column mapping, run the import, and wait for the "Done!"
 * step. Returns the mapping actually offered for each raw CSV header (for
 * assertions like Test 10 -- "WooCommerce recognizes/maps the structured
 * UMC columns correctly") and the done-step summary text.
 */
export async function importCsv(
	page: Page,
	csvPath: string,
	csvHeader: string[],
	options: { updateExisting?: boolean; overrideMapping?: Record<string, string> } = {}
): Promise<{ mapping: Record<string, string>; doneSummary: string }> {
	await page.goto('/wp-admin/edit.php?post_type=product&page=product_importer');

	await page.locator('#upload').setInputFiles(csvPath);

	if (options.updateExisting ?? true) {
		await page.locator('#woocommerce-importer-update-existing').check();
	}

	await page.locator('button[name="save_step"]').click();

	// Column mapping step: one <select name="map_to[N]"> per raw CSV header
	// (N = the header's index in the uploaded file, which we control),
	// auto-mapped by WooCommerce's own auto_map_columns(). Correlate by
	// index rather than by scraping the display text, which mixes the
	// column name with a "Sample: <value>" hint in the same cell.
	await page.locator('table.wc-importer-mapping-table').waitFor({ state: 'visible', timeout: 15_000 });

	const mapping: Record<string, string> = {};
	for (let i = 0; i < csvHeader.length; i++) {
		const select = page.locator(`select[name="map_to[${i}]"]`);
		const override = options.overrideMapping?.[csvHeader[i]];
		if (override !== undefined) {
			await select.selectOption(override);
		}
		mapping[csvHeader[i]] = await select.inputValue();
	}

	await page.locator('button.button-primary[name="save_step"]').click();

	// Import-in-progress step is Ajax-driven; wait for the real completion
	// state (WooCommerce's own "Import complete!" summary section becoming
	// visible) rather than a fixed sleep.
	const doneSection = page.locator('section.woocommerce-importer-done');
	await doneSection.waitFor({ state: 'visible', timeout: 60_000 });
	const doneSummary = (await doneSection.innerText()).trim();

	return { mapping, doneSummary };
}

/**
 * Whether WooCommerce's own native per-row error/skip log (rendered on the
 * Done step) has any entries. Used to assert that a UMC-only field-level
 * problem (e.g. an atomically-rejected sale>regular pair) never causes
 * WooCommerce to misclassify the underlying product row as failed/skipped
 * (architecture doc, "false WC row failure" invariant).
 */
export async function hasNativeImportErrors(page: Page): Promise<boolean> {
	const errorLog = page.locator('section.wc-importer-error-log');
	if ((await errorLog.count()) === 0) {
		return false;
	}
	const rows = errorLog.locator('table.wc-importer-error-log-table tbody tr');
	return (await rows.count()) > 0;
}
