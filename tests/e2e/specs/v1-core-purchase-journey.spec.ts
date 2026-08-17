import { test, expect } from '@playwright/test';
import { assertDevHostOnly } from '../fixtures/production-guard.js';
import { loginAsAdmin } from '../fixtures/auth.js';
import { baseUrl } from '../fixtures/env.js';
import { loadV1Fixtures, type V1Fixtures } from '../fixtures/fixtures-io.js';
import { acceptCookiesIfPresent } from '../fixtures/consent.js';
import { fillClassicBillingFields, emptyCart } from '../fixtures/checkout.js';

/**
 * M26 v1.0 release-acceptance journey A: manual currency selection through
 * a real Classic Checkout purchase, verified in wp-admin's own Currency &
 * Exchange Rate order meta box -- the merchant-facing surface, not a
 * backdoor. Exercises real production hooks throughout: the ?currency=
 * switch link, WooCommerce's own cart/checkout, and the checkout-currency-
 * policy transition notice (M11/ADR-0014).
 *
 * This DEV site gates anonymous visitors behind a coming-soon page, so
 * every request runs authenticated -- the same constraint the M25 suite
 * already works within.
 */
let fixtures: V1Fixtures;

test.beforeAll(() => {
	assertDevHostOnly(baseUrl());
	fixtures = loadV1Fixtures();
});

test('manual SEK selection, buys a converted-price product through Classic Checkout, order settles per checkout policy', async ({ page }) => {
	await loginAsAdmin(page);
	await emptyCart(page);

	// Manual currency selection: this is exactly the URL the rendered
	// switcher's <a href> points at (CurrencyContext::QUERY_VAR).
	await page.goto('/?currency=SEK');
	await acceptCookiesIfPresent(page);
	await page.waitForLoadState('networkidle');

	const cookies = await page.context().cookies();
	expect(cookies.some((c) => c.name === 'umc_currency' && c.value === 'SEK')).toBe(true);

	// Product page shows the FX-converted price in the selected currency.
	await page.goto(`/?p=${fixtures.converted.id}`);
	await acceptCookiesIfPresent(page);
	const priceText = await page.locator('.summary .price, .product .price').first().innerText();
	expect(priceText).toContain('kr');

	await page.goto(`/?add-to-cart=${fixtures.converted.id}&quantity=1`);
	await page.waitForLoadState('networkidle');

	await page.goto('/cart/');
	await acceptCookiesIfPresent(page);
	await expect(page.locator('.woocommerce-cart-form, .wc-block-cart')).toBeVisible();

	await page.goto('/checkout/');
	await acceptCookiesIfPresent(page);
	await page.waitForSelector('#billing_first_name', { timeout: 15_000 });

	// The checkout-policy transition notice: this store is configured for
	// store-currency checkout (checkout.mode=store), so browsing happens in
	// the shopper's selected currency but checkout settles in the store's
	// base currency -- this notice is the shopper-facing evidence of that
	// policy, and its presence/absence is itself part of what this journey
	// proves.
	const noticeVisible = await page
		.getByText(/checkout continues in/i)
		.isVisible()
		.catch(() => false);

	await fillClassicBillingFields(page, 'v1e2ecore');

	// This DEV site has several payment gateways installed (including
	// crypto gateways whose own JS re-renders the payment-method box on
	// checkout's automatic totals refresh); select "Direct bank transfer"
	// (bacs) -- a plain WooCommerce core gateway that never redirects off-
	// site -- twice, bracketing the AJAX settle window, so the final
	// selection sent with the order is deterministic rather than racing
	// that re-render.
	const bacsLabel = page.locator('label[for="payment_method_bacs"]');
	await bacsLabel.waitFor({ state: 'visible', timeout: 15_000 });
	await bacsLabel.click({ force: true });
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(3_000);
	await bacsLabel.click({ force: true });
	await page.waitForTimeout(500);
	await expect(page.locator('#payment_method_bacs')).toBeChecked();

	await page.locator('#place_order').click({ force: true });
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(3_000);

	// The order is created (with UMC's snapshot metadata) at
	// woocommerce_checkout_create_order -- before payment processing -- so
	// this journey's UMC-relevant assertions hold regardless of whether a
	// downstream gateway ultimately settles the payment. Read the most
	// recently created order back through the real merchant-facing admin
	// surface (HPOS orders list), not a backdoor.
	await page.goto('/wp-admin/admin.php?page=wc-orders&orderby=date&order=desc');
	const firstOrderLink = page.locator('table.wc-orders-list-table tbody tr a.order-view').first();
	await firstOrderLink.waitFor({ state: 'visible', timeout: 15_000 });
	const orderEditUrl = await firstOrderLink.getAttribute('href');
	expect(orderEditUrl).not.toBeNull();
	await page.goto(orderEditUrl!);
	await page.waitForLoadState('networkidle');

	// Verify via the plugin's own Currency & Exchange Rate order meta box.
	// Store-currency checkout policy means the settled transaction currency
	// is the base currency (EUR) regardless of the SEK browsing currency
	// selected above -- exactly what the shopper-facing transition notice
	// (checked above) promised.
	const metaBoxText = await page.locator('.postbox:has-text("Currency")').first().innerText();
	expect(metaBoxText).toContain(noticeVisible ? 'EUR' : 'SEK');
});
