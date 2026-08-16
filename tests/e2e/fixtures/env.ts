/**
 * Required environment for the M25 browser acceptance suite. Never commit
 * actual values -- see tests/e2e/.env.example and tests/e2e/README.md.
 */

function required(name: string): string {
	const value = process.env[name];
	if (!value) {
		throw new Error(`${name} is required but not set. See tests/e2e/README.md.`);
	}
	return value;
}

export function baseUrl(): string {
	return required('UMC_E2E_BASE_URL');
}

export function adminUser(): string {
	return required('UMC_E2E_ADMIN_USER');
}

export function adminPassword(): string {
	return required('UMC_E2E_ADMIN_PASSWORD');
}

/**
 * A short, unique-per-run identifier so fixture SKUs/names never collide
 * across repeated acceptance runs and are trivially identifiable for
 * cleanup. Override with UMC_E2E_RUN_ID for a reproducible/debuggable run.
 */
export function runId(): string {
	return process.env.UMC_E2E_RUN_ID ?? Date.now().toString(36);
}

export const FIXTURE_PREFIX = 'm25e2e';
