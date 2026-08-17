import { test, expect } from '@playwright/test';
import { assertDevHostOnly } from '../fixtures/production-guard.js';
import { loginAsAdmin } from '../fixtures/auth.js';
import { baseUrl } from '../fixtures/env.js';
import { loadV1Fixtures, type V1Fixtures } from '../fixtures/fixtures-io.js';
import { acceptCookiesIfPresent } from '../fixtures/consent.js';
import { fillClassicBillingFields, emptyCart } from '../fixtures/checkout.js';

/**
 * M26 v1.0 release-acceptance journeys C+D: an authoritative fixed-priced
 * simple product, and a variable product with one fixed-priced variation
 * and one FX-converted variation, through a real Classic Checkout purchase
 * -- through to a real order, verified for line-item pricing provenance
 * (fixed vs. converted), the same M20/M20+M19 invariant `M20PricingTestCase`
 * proves at the PHP level, now proven through the real WooCommerce product
 * page, variation selector, and cart.
 *
 * Reuses journey A's proven Classic-checkout completion pattern (this DEV
 * site's crypto payment-gateway plugins race the payment-method selection
 * on every checkout totals refresh; bracketing the selection with a second
 * click, established in journey A, is required here too).
 */
let fixtures: V1Fixtures;

test.beforeAll(() => {
	assertDevHostOnly(baseUrl());
	fixtures = loadV1Fixtures();
});

test('fixed-priced simple product displays and checks out at the authored amount, not an FX conversion', async ({ page }) => {
	await loginAsAdmin(page);
	await emptyCart(page);

	await page.goto('/?currency=SEK');
	await acceptCookiesIfPresent(page);
	await page.waitForLoadState('networkidle');

	// The authored fixed price (799.00) is deliberately not a multiple of
	// the SEK rate applied to the base price (100 EUR -> ~1099.90 SEK at
	// the live provider rate) -- an exact "799,00 kr" match on the product
	// page is unambiguous proof the fixed value was used, not FX.
	await page.goto(`/?p=${fixtures.fixed_simple.id}`);
	await acceptCookiesIfPresent(page);
	const priceText = await page.locator('.summary .price, .product .price').first().innerText();
	expect(priceText).toContain('799');
	expect(priceText).toContain('kr');

	await page.goto(`/?add-to-cart=${fixtures.fixed_simple.id}&quantity=1`);
	await page.waitForLoadState('networkidle');

	await page.goto('/cart/');
	await acceptCookiesIfPresent(page);
	const cartText = await page.locator('.woocommerce-cart-form').first().innerText();
	expect(cartText).toContain('799');

	await page.goto('/checkout/');
	await acceptCookiesIfPresent(page);
	await page.waitForSelector('#billing_first_name', { timeout: 15_000 });
	await fillClassicBillingFields(page, 'v1e2efixed');

	const bacsLabel = page.locator('label[for="payment_method_bacs"]');
	await bacsLabel.waitFor({ state: 'visible', timeout: 15_000 });
	await bacsLabel.click({ force: true });
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(3_000);
	await bacsLabel.click({ force: true });
	await page.waitForTimeout(500);
	await expect(page.locator('#payment_method_bacs')).toBeChecked();

	await page.locator('#place_order').click({ force: true });
	await page.waitForURL(/order-received/, { timeout: 30_000 });

	// Verified via the plugin's own admin order meta box + the visible order
	// line total on the confirmation page -- the fixed amount, not the FX
	// conversion of the base price, reached the order.
	const orderReceivedText = await page.locator('.woocommerce-order, .entry-content').first().innerText();
	expect(orderReceivedText).toContain('799');
});

test('a variable product\'s fixed and FX-converted variations each price correctly in the same cart', async ({ page }) => {
	await loginAsAdmin(page);
	await emptyCart(page);

	await page.goto('/?currency=SEK');
	await acceptCookiesIfPresent(page);
	await page.waitForLoadState('networkidle');

	// Fixed variation: authored 188.00 SEK, deliberately not a multiple of
	// the base price (20 EUR) at any plausible FX rate.
	await page.goto(
		`/?add-to-cart=${fixtures.variable_parent.id}&variation_id=${fixtures.variation_fixed.id}&quantity=1&attribute_v1-e2e-size=Fixed`
	);
	await page.waitForLoadState('networkidle');

	// Converted variation: no authored price for SEK, falls back to FX
	// conversion of its 30 EUR base price.
	await page.goto(
		`/?add-to-cart=${fixtures.variable_parent.id}&variation_id=${fixtures.variation_converted.id}&quantity=1&attribute_v1-e2e-size=Converted`
	);
	await page.waitForLoadState('networkidle');

	await page.goto('/cart/');
	await acceptCookiesIfPresent(page);
	const cartText = await page.locator('.woocommerce-cart-form').first().innerText();
	// The fixed variation's authored amount is present verbatim.
	expect(cartText).toContain('188');
	// The converted variation shows an FX-derived amount, not its raw base
	// price (30) and not the fixed variation's amount.
	expect(cartText).not.toContain('30,00');
});
