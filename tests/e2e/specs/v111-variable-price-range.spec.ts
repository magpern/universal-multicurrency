import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { assertDevHostOnly } from '../fixtures/production-guard.js';
import { loginAsAdmin } from '../fixtures/auth.js';
import { baseUrl } from '../fixtures/env.js';
import { acceptCookiesIfPresent } from '../fixtures/consent.js';

/**
 * v1.1.1 regression: variable parent range must resolve to the active
 * currency (not base amounts with a foreign symbol) after currency switch,
 * and must agree with the selected variation.
 */

interface V111Fixtures {
	run_id: string;
	parent: { id: number; url: string };
	expected_dkk_min: string;
	expected_dkk_max: string;
}

function loadV111Fixtures(): V111Fixtures {
	const path = process.env.UMC_E2E_V111_FIXTURES_JSON ?? '.v111-fixtures.json';
	return JSON.parse(readFileSync(path, 'utf-8')) as V111Fixtures;
}

function normalizeAmount(text: string): string {
	return text.replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
}

function extractRangeAmounts(priceText: string): { min: string; max: string } {
	const matches = [...priceText.matchAll(/(\d[\d\s]*(?:[.,]\d{2})?)/g)].map((m) =>
		normalizeAmount(m[1])
	);
	if (matches.length < 2) {
		throw new Error(`Could not parse range amounts from: ${priceText}`);
	}
	return { min: matches[0], max: matches[1] };
}

let fixtures: V111Fixtures;

test.beforeAll(() => {
	assertDevHostOnly(baseUrl());
	fixtures = loadV111Fixtures();
});

test('EUR → DKK variable range converts numerically and matches selected variation', async ({ page }) => {
	await loginAsAdmin(page);

	await page.goto('/?currency=EUR');
	await acceptCookiesIfPresent(page);
	await page.goto(fixtures.parent.url);
	await acceptCookiesIfPresent(page);
	await page.waitForLoadState('networkidle');

	const eurRange = await page.locator('.summary .price, .product .price').first().innerText();
	expect(eurRange).toMatch(/35/);
	expect(eurRange).toMatch(/65/);

	await page.goto('/?currency=DKK');
	await acceptCookiesIfPresent(page);
	await page.goto(fixtures.parent.url);
	await acceptCookiesIfPresent(page);
	await page.waitForLoadState('networkidle');

	const dkkRangeText = await page.locator('.summary .price, .product .price').first().innerText();
	expect(dkkRangeText.toLowerCase()).toContain('kr');
	const range = extractRangeAmounts(dkkRangeText);
	expect(range.min).toBe(fixtures.expected_dkk_min);
	expect(range.max).toBe(fixtures.expected_dkk_max);
	// Explicitly reject the original defect shape (base amounts + foreign symbol).
	expect(range.min).not.toBe('35.99');
	expect(range.max).not.toBe('65.99');

	const strength = page.locator('select[name="attribute_strength"], select#strength').first();
	await strength.selectOption({ label: '10mg' });
	await page.waitForTimeout(500);
	const variationPrice = await page
		.locator('.woocommerce-variation-price .price, .summary .woocommerce-variation .price')
		.first()
		.innerText();
	const variationAmount = normalizeAmount(variationPrice.match(/(\d[\d\s]*(?:[.,]\d{2})?)/)?.[1] ?? '');
	expect(variationAmount).toBe(fixtures.expected_dkk_min);

	await page.goto('/?currency=EUR');
	await acceptCookiesIfPresent(page);
	await page.goto(fixtures.parent.url);
	await acceptCookiesIfPresent(page);
	const eurAgain = await page.locator('.summary .price, .product .price').first().innerText();
	const eurParsed = extractRangeAmounts(eurAgain);
	expect(eurParsed.min).toBe('35.99');
	expect(eurParsed.max).toBe('65.99');
});
