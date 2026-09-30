/**
 * QA: CSV question import through the admin Test Builder UI.
 * 1. Create a fresh test (BGS Institute → BCA → active batch)
 * 2. Upload sample_questions.csv, verify client-side preview
 * 3. Import and verify all 10 questions landed
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');
const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };
const CSV = path.join(__dirname, '..', 'sample_questions.csv');
const MYSQL = process.env.MYSQL_BIN || 'C:\\xampp\\mysql\\bin\\mysql.exe';

// The faculty specs assert exact fixture counts for their college (3 tests),
// so the throwaway test built here has to be removed again afterwards —
// otherwise kpiTests drifts on every subsequent run.
test.afterAll(() => {
  execSync(
    `"${MYSQL}" -h 127.0.0.1 -u root test_platform < "${path.join(__dirname, 'csv-import-cleanup.sql')}"`,
    { shell: 'cmd.exe', stdio: 'pipe' }
  );
});

test('CSV import adds all 10 questions to a new test', async ({ page }) => {
  // ── Admin login ──
  await page.goto('/src/php/public/login.php');
  await page.selectOption('#role', 'admin');
  await page.fill('#email', ADMIN.email);
  await page.fill('#password', ADMIN.password);
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/dashboard\.php/);

  // ── Step 1: configure the test ──
  await page.goto('/src/php/public/admin/test_builder.php');
  await page.fill('input[name="title"]', 'CSV Import QA Test');
  await page.fill('textarea[name="description"]', 'Created by csv-import.spec.js to validate sample_questions.csv');

  await page.selectOption('#collegeSelect', { label: 'BGS Institute Of Management Malur' });
  const courseSelect = page.locator('#courseSelect');
  await courseSelect.selectOption({ label: 'Bachelor of Computer Applications (BCA)' });
  await page.locator('#batchList input[name="batch_ids[]"]').first().check();

  await page.fill('input[name="duration_minutes"]', '30');
  await page.fill('input[name="passing_marks"]', '5');

  await page.click('button[type="submit"]');
  // Step 2 should now be active
  await expect(page.locator('.wizard-stepper li.active span')).toHaveText('Questions');

  // ── Step 2: CSV import ──
  await page.setInputFiles('#csvInput', CSV);

  // Client-side preview must render and mark rows valid, enabling the button
  await expect(page.locator('#csvPreviewArea .csv-preview-box')).toBeVisible();
  await expect(page.locator('#csvPreviewArea tr[data-valid="true"]')).toHaveCount(10); // 10 question rows
  await expect(page.locator('#csvSubmitBtn')).toBeEnabled();

  await page.click('#csvSubmitBtn');
  await expect(page.locator('body')).toContainText(/Imported 10 question/i);

  // ── Verify: questions visible in the preview list ──
  await expect(page.locator('body')).toContainText('What is the capital of France?');
  await expect(page.locator('body')).toContainText('What is the speed of light approximately?');
});
