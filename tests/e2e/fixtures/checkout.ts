import type { Page } from '@playwright/test';

/**
 * Empties the current shopper's cart via the real cart-page remove links,
 * so each journey starts from a deterministic empty state regardless of
 * what a prior run (or a shared authenticated session -- this DEV site
 * gates anonymous visitors behind a coming-soon page, so every journey runs
 * authenticated, the same way the M25 suite does) left behind.
 */
export async function emptyCart(page: Page): Promise<void> {
	await page.goto('/cart/');
	await page.waitForLoadState('networkidle');

	// Navigate the real remove-item href directly rather than clicking --
	// avoids flakiness from responsive-table layouts hiding the row's
	// visible-click-target, while still exercising the exact same
	// production `?remove_item=<key>&_wpnonce=<nonce>` action a click would.
	// eslint-disable-next-line no-constant-condition
	for (let i = 0; i < 20; i++) {
		const remove = page.locator('a.remove').first();
		if ((await remove.count()) === 0) {
			return;
		}
		const href = await remove.getAttribute('href');
		if (!href) {
			return;
		}
		await page.goto(href);
		await page.waitForLoadState('networkidle');
	}
}

/**
 * Fills the standard WooCommerce Classic Checkout billing fields with a
 * disposable, non-identifying test address. Shared by every v1.0 journey
 * spec that needs to complete a real order.
 */
export async function fillClassicBillingFields(page: Page, emailLocalPart: string): Promise<void> {
	await page.locator('#billing_first_name').fill('Release');
	await page.locator('#billing_last_name').fill('Acceptance');
	await page.locator('#billing_address_1').fill('Test Street 1');
	await page.locator('#billing_city').fill('Stockholm');
	await page.locator('#billing_postcode').fill('11122');
	await page.locator('#billing_country').selectOption('SE');
	await page.locator('#billing_email').fill(`${emailLocalPart}@example.invalid`);
	await page.locator('#billing_phone').fill('0000000000');
}

/**
 * Fills the WooCommerce Checkout Block's billing fields. The block uses its
 * own field markup (not the classic #billing_* IDs), keyed by
 * name="billing_address" wrapper's labeled inputs, addressed here by
 * accessible label -- stable across theme/appearance changes.
 */
export async function fillBlocksBillingFields(page: Page, emailLocalPart: string): Promise<void> {
	await page.getByLabel('Email address').fill(`${emailLocalPart}@example.invalid`);
	await page.getByLabel('First name').fill('Release');
	await page.getByLabel('Last name').fill('Acceptance');
	await page.getByLabel('Address', { exact: false }).first().fill('Test Street 1');
	await page.getByLabel('City').fill('Stockholm');
	await page.getByLabel('Country/Region', { exact: false }).selectOption({ label: 'Sweden' });
	await page.getByLabel('Postal code', { exact: false }).fill('11122');
}
