/**
 * Regression: reports.php chart containers must be bounded.
 * Fails if the Chart.js resize loop ever comes back (page height grows
 * over time) or any .chart-wrapper deviates from its fixed height.
 */
const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost:8000';
const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };
const TEST_ID = process.env.PCI_TEST_ID || 7; // must have PCI records

async function loginAdmin(page) {
  await page.goto(BASE + '/src/php/public/login.php');
  await page.fill('#email', ADMIN.email);
  await page.fill('#password', ADMIN.password);
  await page.selectOption('#role', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

test.describe('reports.php bounded charts', () => {

  test('overview: no page growth, wrappers fixed at 350px', async ({ page }) => {
    await loginAdmin(page);
    await page.goto(`${BASE}/src/php/public/admin/reports.php?test_id=${TEST_ID}`);

    // All four overview charts mounted inside wrappers
    await page.waitForFunction(
      () => window.Chart && document.querySelectorAll('.chart-wrapper canvas').length >= 4,
      null, { timeout: 15000 });

    const h1 = await page.evaluate(() => document.documentElement.scrollHeight);
    const wrapperHeights = await page.evaluate(() =>
      [...document.querySelectorAll('.chart-wrapper')].map(w => w.clientHeight));

    expect(wrapperHeights.length).toBeGreaterThanOrEqual(4);
    wrapperHeights.forEach((h, i) =>
      expect(h, `wrapper ${i} must stay at 350px`).toBe(350));

    // The kill-shot assertion: with the old bug this number kept growing.
    await page.waitForTimeout(4000);
    const h2 = await page.evaluate(() => document.documentElement.scrollHeight);
    expect(h2, 'page height must not grow over time').toBe(h1);
  });

  test('drill-down: radar wrapped at 320px, page stable', async ({ page }) => {
    await loginAdmin(page);
    await page.goto(`${BASE}/src/php/public/admin/reports.php?test_id=${TEST_ID}`);

    const detail = page.locator('a[href*="student_id="]', { hasText: 'Detail' }).first();
    test.skip(!(await detail.count()), 'no PCI students to drill into');
    await detail.click();
    await page.waitForURL(/student_id=/);

    await page.waitForFunction(
      () => window.Chart && !!document.querySelector('.chart-wrapper'),
      null, { timeout: 15000 });

    const h1 = await page.evaluate(() => document.documentElement.scrollHeight);
    const wrapperH = await page.evaluate(() =>
      document.querySelector('.chart-wrapper')?.clientHeight ?? -1);
    expect(wrapperH).toBe(320);

    await page.waitForTimeout(3000);
    const h2 = await page.evaluate(() => document.documentElement.scrollHeight);
    expect(h2).toBe(h1);
  });

});
