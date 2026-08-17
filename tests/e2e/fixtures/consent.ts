import type { Page } from '@playwright/test';

/**
 * Dismisses a cookie-consent banner if the target site has one, so it never
 * intercepts subsequent clicks. Best-effort and silent when absent -- this
 * suite targets whatever DEV site UMC_E2E_BASE_URL points at, which may or
 * may not run a consent plugin.
 */
export async function acceptCookiesIfPresent(page: Page): Promise<void> {
	const acceptButton = page.getByRole('button', { name: /accept all/i });
	try {
		await acceptButton.waitFor({ state: 'visible', timeout: 3_000 });
		await acceptButton.click();
	} catch {
		// No consent banner on this page load -- nothing to dismiss.
	}
}
