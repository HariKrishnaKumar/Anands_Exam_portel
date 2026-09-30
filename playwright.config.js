/**
 * Playwright Test Configuration
 * @see https://playwright.dev/docs/test-configuration
 */
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  // Not a Playwright test: a standalone script that launches its own
  // headless:false browser and scrapes temp-mail.org for an OTP. Importing it
  // during collection fired the whole registration flow and hung the run.
  // Registration is covered by direct login instead.
  testIgnore: '**/e2e-live-register.spec.js',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['json', { outputFile: 'test-results.json' }],
    ['html', { outputFolder: 'playwright-report' }],
  ],
  use: {
    baseURL: 'http://localhost:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        browserName: 'chromium',
        viewport: { width: 1440, height: 900 },
      },
    },
  ],
});
