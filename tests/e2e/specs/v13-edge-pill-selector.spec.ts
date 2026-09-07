import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

/**
 * v1.3 Edge Pill / presentation-preset controller acceptance.
 *
 * These cases exercise the shared switcher.js controller against a static
 * harness that mirrors SwitcherRenderer markup. They do not mutate a DEV
 * WordPress site and do not require UMC_E2E_* credentials.
 *
 * Full DEV storefront acceptance (admin Display preview, real ?currency=
 * cookie round-trip on a live shop, shortcode/block coexistence against
 * AutomaticSwitcherPlacement) remains a post-deploy check on an authorized
 * DEV host once this branch is installed there.
 */
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const harnessUrl = pathToFileURL(
	path.resolve(__dirname, '../fixtures/switcher-harness.html')
).href;

async function openHarness(page: import('@playwright/test').Page, width = 1280, height = 800) {
	await page.setViewportSize({ width, height });
	await page.goto(harnessUrl);
	await expect(page.locator('[data-testid="switcher-a"]')).toBeVisible();
}

test.describe('v1.3 switcher presentation controller (harness)', () => {
	test('edge pill opens/closes without baking dialog attrs while collapsed', async ({ page }) => {
		await openHarness(page);
		const root = page.locator('[data-testid="switcher-a"]');
		const trigger = root.locator('.umc-switcher__trigger');
		const panel = root.locator('.umc-switcher__panel');

		await expect(panel).not.toHaveAttribute('role', 'dialog');
		await trigger.click();
		await expect(trigger).toHaveAttribute('aria-expanded', 'true');
		await expect(root).toHaveClass(/umc-switcher--open/);
		await expect(panel).not.toHaveAttribute('role', 'dialog');

		await page.keyboard.press('Escape');
		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
		await expect(root).not.toHaveClass(/umc-switcher--open/);
	});

	test('mobile edge pill uses sheet dialog contract with focus trap', async ({ page }) => {
		await openHarness(page, 390, 844);
		const root = page.locator('[data-testid="switcher-a"]');
		const trigger = root.locator('.umc-switcher__trigger');
		const panel = root.locator('.umc-switcher__panel');
		const close = root.locator('.umc-switcher__close');

		await trigger.click();
		await expect(root).toHaveClass(/umc-switcher--sheet/);
		await expect(panel).toHaveAttribute('role', 'dialog');
		await expect(panel).toHaveAttribute('aria-modal', 'true');
		await expect(close).toBeVisible();
		await expect(close).toBeFocused();

		await page.keyboard.press('Tab');
		await expect(root.locator('.umc-switcher__link').first()).toBeFocused();

		await page.keyboard.press('Escape');
		await expect(root).not.toHaveClass(/umc-switcher--sheet/);
		await expect(panel).not.toHaveAttribute('role', 'dialog');
		await expect(trigger).toBeFocused();
	});

	test('opening one non-sheet switcher closes the other non-sheet instance', async ({ page }) => {
		await openHarness(page);
		const a = page.locator('[data-testid="switcher-a"]');
		const b = page.locator('[data-testid="switcher-b"]');

		await b.locator('.umc-switcher__trigger').click();
		await expect(b.locator('.umc-switcher__trigger')).toHaveAttribute('aria-expanded', 'true');

		await a.locator('.umc-switcher__trigger').click();
		await expect(a.locator('.umc-switcher__trigger')).toHaveAttribute('aria-expanded', 'true');
		await expect(b.locator('.umc-switcher__trigger')).toHaveAttribute('aria-expanded', 'false');
	});

	test('floating card promotes to sheet when the menu cannot fit horizontally', async ({ page }) => {
		await openHarness(page, 320, 640);
		const card = page.locator('[data-testid="switcher-card"]');
		await card.locator('.umc-switcher__trigger').click();
		await expect(card).toHaveClass(/umc-switcher--sheet/);
		await expect(card.locator('.umc-switcher__panel')).toHaveAttribute('role', 'dialog');
		await expect(card.locator('.umc-switcher__close')).toBeVisible();
	});

	test('minimal icon trigger stays non-blank without a flag', async ({ page }) => {
		await openHarness(page);
		const minimal = page.locator('[data-testid="switcher-minimal"]');
		const triggerText = await minimal.locator('.umc-switcher__trigger-content').innerText();
		expect(triggerText.trim().length).toBeGreaterThan(0);
		await expect(minimal.locator('.umc-switcher__trigger-content .umc-switcher__icon')).toHaveCount(0);
		await expect(minimal.locator('.umc-switcher__symbol')).toContainText('¥');
	});

	test('currency links keep canonical ?currency= hrefs', async ({ page }) => {
		await openHarness(page);
		const link = page.locator('[data-testid="switcher-b"] .umc-switcher__link[href*="currency=SEK"]');
		await expect(link).toHaveAttribute('href', /\?currency=SEK/);
	});

	test('no-js fallback reveals the static currency list', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 800 });
		await page.route('**/assets/js/switcher.js', (route) => route.abort());
		await page.goto(harnessUrl);
		await page.evaluate(() => document.documentElement.classList.add('no-js'));
		const menu = page.locator('[data-testid="switcher-b"] .umc-switcher__menu');
		await expect(menu).toBeVisible();
		await expect(page.locator('[data-testid="switcher-b"] .umc-switcher__trigger')).toBeHidden();
	});
});
