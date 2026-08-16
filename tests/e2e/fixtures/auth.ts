import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';
import { adminPassword, adminUser } from './env.js';

/**
 * Logs into wp-admin using the standard WordPress login form. Standard,
 * stable field names (log/pwd/wp-submit) rather than any DOM-position
 * selector.
 */
export async function loginAsAdmin(page: Page): Promise<void> {
	await page.goto('/wp-login.php');
	await page.locator('#user_login').fill(adminUser());
	await page.locator('#user_pass').fill(adminPassword());
	await page.locator('#wp-submit').click();
	await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15_000 });
}
