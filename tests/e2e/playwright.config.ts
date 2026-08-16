import { defineConfig, devices } from '@playwright/test';

/**
 * M25 Fixed Pricing CSV Interchange -- browser acceptance suite.
 *
 * Targets a real, authorized DEV WordPress + WooCommerce environment only
 * (see fixtures/production-guard.ts). Never run against production -- the
 * suite refuses to start if the configured host is not on the explicit DEV
 * allowlist.
 *
 * Required environment variables (never commit values):
 *   UMC_E2E_BASE_URL       e.g. https://dev.biopentra.eu
 *   UMC_E2E_ADMIN_USER
 *   UMC_E2E_ADMIN_PASSWORD
 *   UMC_E2E_ALLOWED_HOSTS  optional, comma-separated hostname allowlist
 *                          (defaults to "dev.biopentra.eu" -- see
 *                          fixtures/production-guard.ts)
 */
export default defineConfig({
  testDir: './specs',
  timeout: 60_000,
  expect: { timeout: 15_000 },
  fullyParallel: false, // Fixture products/CSV state are shared across cases in one run.
  retries: 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: process.env.UMC_E2E_BASE_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: false,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
