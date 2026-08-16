/**
 * Hard production-host guard for the M25 browser acceptance suite.
 *
 * This is an allowlist, not a blocklist: the suite refuses to run against
 * ANY hostname that is not explicitly authorized for DEV acceptance,
 * rather than trying to enumerate every possible production hostname (which
 * this repository -- deliberately generic, per CLAUDE.md -- has no reason to
 * know). Unknown host => refuse, never "unknown host => assume safe".
 *
 * This guard must be imported and invoked before ANY mutating action
 * (fixture creation, CSV import, product deletion). It throws, terminating
 * the test run before mutation, rather than merely warning.
 */

const DEFAULT_ALLOWED_HOSTS = ['dev.biopentra.eu'];

export function assertDevHostOnly(baseUrl: string | undefined): void {
	if (!baseUrl) {
		throw new Error(
			'UMC_E2E_BASE_URL is not set. Refusing to run: the M25 acceptance suite ' +
				'must never fall back to a default/implicit target.'
		);
	}

	let hostname: string;
	try {
		hostname = new URL(baseUrl).hostname.toLowerCase();
	} catch {
		throw new Error(`UMC_E2E_BASE_URL ("${baseUrl}") is not a valid URL. Refusing to run.`);
	}

	const allowlist = (process.env.UMC_E2E_ALLOWED_HOSTS ?? DEFAULT_ALLOWED_HOSTS.join(','))
		.split(',')
		.map((h) => h.trim().toLowerCase())
		.filter((h) => h.length > 0);

	if (allowlist.length === 0) {
		throw new Error('UMC_E2E_ALLOWED_HOSTS resolved to an empty allowlist. Refusing to run.');
	}

	if (!allowlist.includes(hostname)) {
		throw new Error(
			`Target host "${hostname}" is not on the DEV acceptance allowlist ` +
				`(${allowlist.join(', ')}). The M25 browser acceptance suite refuses to run ` +
				'against any host that is not explicitly authorized -- this is a hard stop, ' +
				'not a warning, and applies regardless of how confident the caller is that the ' +
				'host is safe.'
		);
	}
}
