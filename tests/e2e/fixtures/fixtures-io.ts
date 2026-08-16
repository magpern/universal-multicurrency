import { readFileSync } from 'node:fs';

export interface Fixtures {
	run_id: string;
	base_currency: string;
	simple: { id: number; sku: string };
	variable_parent: { id: number; sku: string };
	variation_a: { id: number; sku: string };
	variation_b: { id: number; sku: string };
}

/**
 * Reads the fixture identifiers written by fixtures/setup-fixtures.php (run
 * on the host via WP-CLI by run-acceptance.sh before Playwright starts --
 * Playwright itself has no Docker access, by design, so it never creates
 * fixtures directly).
 */
export function loadFixtures(): Fixtures {
	const path = process.env.UMC_E2E_FIXTURES_JSON ?? '.fixtures.json';
	const raw = readFileSync(path, 'utf-8');
	return JSON.parse(raw) as Fixtures;
}
