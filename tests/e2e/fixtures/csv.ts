import { parse } from 'csv-parse/sync';
import { stringify } from 'csv-stringify/sync';

export type CsvRow = Record<string, string>;

/**
 * Parses a real WooCommerce-exported CSV buffer into header-keyed rows.
 * Uses a proper RFC4180 parser (csv-parse) rather than string splitting --
 * WooCommerce's own export quotes/escapes fields (product descriptions,
 * names) that can legitimately contain commas and quotes.
 */
export function parseCsv(content: string): CsvRow[] {
	return parse(content, {
		columns: true,
		skip_empty_lines: true,
		bom: true,
	}) as CsvRow[];
}

/**
 * Serializes header-keyed rows back into CSV text for re-upload, preserving
 * proper quoting/escaping via csv-stringify rather than manual string
 * concatenation.
 */
export function writeCsv(rows: CsvRow[], columns: string[]): string {
	return stringify(rows, { header: true, columns });
}

/**
 * Finds the row whose SKU column matches, or throws with a clear message --
 * every M25 acceptance case needs an unambiguous row lookup, never a
 * silent [0]-index guess.
 */
export function findRowBySku(rows: CsvRow[], sku: string): CsvRow {
	const row = rows.find((r) => r.SKU === sku || r.sku === sku);
	if (!row) {
		throw new Error(
			`No exported CSV row found for SKU "${sku}". Available SKUs: ` +
				rows.map((r) => r.SKU ?? r.sku ?? '(none)').join(', ')
		);
	}
	return row;
}

const UMC_LABEL_PATTERN = /^UMC Fixed (Regular|Sale) Price \(([A-Za-z]+)\)$/;

/**
 * WooCommerce's real exported CSV uses the merchant-facing column LABEL as
 * the header ("UMC Fixed Regular Price (SEK)"), never the internal snake_case
 * id -- only the import mapping step deals in ids (map_to[] values). This
 * normalizes a parsed export row so both spellings are readable, letting
 * test assertions use the stable internal-id form
 * (`row.umc_fixed_regular_sek`) regardless of which representation produced
 * the row.
 */
export function withUmcColumnAliases(row: CsvRow): CsvRow {
	const aliased: CsvRow = { ...row };
	for (const [key, value] of Object.entries(row)) {
		const match = key.match(UMC_LABEL_PATTERN);
		if (match) {
			const field = match[1].toLowerCase();
			const code = match[2].toLowerCase();
			aliased[`umc_fixed_${field}_${code}`] = value;
		}
	}
	return aliased;
}
