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

export interface V1Fixtures {
	run_id: string;
	base_currency: string;
	converted: { id: number; sku: string };
	fixed_simple: { id: number; sku: string };
	variable_parent: { id: number; sku: string };
	variation_fixed: { id: number; sku: string };
	variation_converted: { id: number; sku: string };
	blocks_page_id: number;
	blocks_page_url: string;
	blocks_cart_page_id: number;
	blocks_cart_page_url: string;
}

/**
 * Reads the fixture identifiers written by fixtures/setup-v1-fixtures.php
 * (M26 v1.0 release-acceptance journeys), the same way loadFixtures() reads
 * the M25 CSV fixtures.
 */
export function loadV1Fixtures(): V1Fixtures {
	const path = process.env.UMC_E2E_V1_FIXTURES_JSON ?? '.v1-fixtures.json';
	const raw = readFileSync(path, 'utf-8');
	return JSON.parse(raw) as V1Fixtures;
}
