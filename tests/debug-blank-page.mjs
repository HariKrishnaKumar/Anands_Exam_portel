/**
 * Debug: reproduce blank page after "Create Test →" in Question Builder.
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'fs';

const DIR = '.gstack/qa-reports/blank-debug';
mkdirSync(DIR, { recursive: true });
const BASE = 'http://localhost:8000';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.on('console', msg => { if (msg.type() === 'error') console.log('CONSOLE ERROR:', msg.text()); });
page.on('pageerror', err => console.log('PAGE ERROR:', err.message));
page.on('response', resp => { if (resp.status() >= 400) console.log('HTTP', resp.status(), resp.url()); });

// Login
await page.goto(`${BASE}/src/php/public/login.php`);
await page.selectOption('#role', 'admin');
await page.fill('#email', 'admin@testplatform.com');
await page.fill('#password', 'admin123');
await page.click('button[type="submit"]');
await page.waitForURL(/dashboard/);

// Test Builder step 1
await page.goto(`${BASE}/src/php/public/admin/test_builder.php`);
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${DIR}/01-step1.png` });

// Fill step 1 (NO start/end times — those are step 3)
await page.fill('input[name="title"]', 'Blank Page Debug Test');
await page.selectOption('#collegeSelect', { label: 'BGS Institute of Management' });
await page.waitForTimeout(2000);

// Select BCA course (3rd option = index 3)
const courseCount = await page.locator('#courseSelect option').count();
console.log('Course options:', courseCount);
for (let i = 0; i < courseCount; i++) {
  const txt = await page.locator('#courseSelect option').nth(i).textContent();
  console.log(`  [${i}] ${txt}`);
}
// Find BCA option
for (let i = 1; i < courseCount; i++) {
  const txt = await page.locator('#courseSelect option').nth(i).textContent();
  if (txt.includes('BCA')) {
    await page.selectOption('#courseSelect', { index: i });
    console.log('Selected course index', i, txt);
    break;
  }
}
await page.waitForTimeout(2000);
await page.screenshot({ path: `${DIR}/02-step1-course.png` });

// Check batches
const batchCount = await page.locator('#batchList input[type="checkbox"]').count();
console.log('Batch checkboxes:', batchCount);
if (batchCount > 0) {
  await page.locator('#batchList input[type="checkbox"]').first().check();
  console.log('Checked first batch');
} else {
  console.log('WARNING: No batches loaded — form will fail "Please select at least one batch"');
}
await page.screenshot({ path: `${DIR}/03-step1-batch.png` });

// Submit
console.log('Clicking Create Test →');
await page.click('button[type="submit"]');
await page.waitForLoadState('networkidle').catch(() => {});
await page.waitForTimeout(3000);
await page.screenshot({ path: `${DIR}/04-after-submit.png`, fullPage: true });
console.log('URL after submit:', page.url());

// Read the page
const bodyText = await page.locator('body').textContent().catch(() => '');
console.log('Body length:', bodyText.length);
console.log('Body preview:', bodyText.trim().substring(0, 600));
const h2Text = await page.locator('h2').first().textContent().catch(() => 'none');
console.log('First h2:', h2Text);

// Check if there's an error message
const flash = await page.locator('.alert, .message, [style*="danger"], [style*="error"]').first().textContent().catch(() => 'none');
console.log('Flash message:', flash);

await browser.close();
