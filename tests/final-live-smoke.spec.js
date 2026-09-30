/**
 * FINAL LIVE SMOKE — one test, all three portals, real HTTP.
 *
 *   Admin   : /login.php -> /admin/dashboard.php -> /admin/batches.php
 *             (asserts the shipped promote UI: Semester column + green button)
 *   Faculty : /faculty-login.php -> /faculty/dashboard.php
 *   Student : /login.php -> /student/dashboard.php
 *
 * faculty-login.php bounces any live session away and refuses a POST without a
 * Geolocation fix (the submit button stays disabled), so the faculty leg clears
 * cookies and grants geolocation before the form is filled.
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const BASE = 'http://localhost:8000';
const MYSQL = 'C:\\xampp\\mysql\\bin\\mysql.exe';

const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };
const FACULTY = { email: 'faculty@bgsmalur.edu', password: 'BGSCCMALUR@563130', collegeId: '1' };
const STUDENT = { email: 'hariiphones83@gmail.com', password: 'Hari@2003' };

const GEO = { latitude: 12.9715987, longitude: 77.5945627 };

// Batch 9 is the promotable fixture batch (course 4, 8-semester ceiling). Pin it
// to semester 3 so the green Promote button is deterministic regardless of what
// an earlier promotion left behind. Idempotent and touches nothing else.
const PIN_BATCH_9 =
  `"${MYSQL}" -h 127.0.0.1 -u root test_platform ` +
  `-e "UPDATE batches SET semester_order = 3 WHERE id = 9;"`;

function runSql(cmd) {
  execSync(cmd, { shell: 'cmd.exe', stdio: 'pipe' });
}

test.beforeAll(() => runSql(PIN_BATCH_9));

test('student, admin and faculty each reach their own portal live', async ({ page }) => {
  // ── 1. ADMIN ────────────────────────────────────────────────────────────
  await page.goto(`${BASE}/login.php`);
  await page.fill('#email', ADMIN.email);
  await page.fill('#password', ADMIN.password);
  await page.selectOption('#role', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin/dashboard.php', { timeout: 15000 });

  await expect(page.locator('h1')).toHaveText('Dashboard', { timeout: 10000 });
  await expect(page.locator('a', { hasText: 'Batches' }).first()).toBeVisible();

  // The feature this run exists to prove: Semester column + green Promote button.
  await page.goto(`${BASE}/admin/batches.php`);
  await expect(page.locator('thead th', { hasText: 'Semester' })).toBeVisible({ timeout: 10000 });
  const promote = page.locator('button:has-text("Promote")').first();
  await expect(promote).toBeVisible({ timeout: 10000 });
  await expect(promote).toHaveClass(/btn-success/);
  await expect(promote).toHaveText('Promote');

  // ── 2. FACULTY (geo-locked sign-in, clean session) ──────────────────────
  await page.context().clearCookies();
  await page.context().grantPermissions(['geolocation']);
  await page.context().setGeolocation(GEO);

  await page.goto(`${BASE}/faculty-login.php`);
  await page.selectOption('select[name="college_id"]', FACULTY.collegeId);
  await page.fill('input[name="email"]', FACULTY.email);
  await page.fill('input[name="password"]', FACULTY.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/faculty/dashboard.php', { timeout: 15000 });

  await expect(page.locator('h1')).toContainText('Student Analytics', { timeout: 10000 });
  await expect(page.locator('#kpiTests')).toBeVisible();

  // ── 3. STUDENT (clean session) ──────────────────────────────────────────
  await page.context().clearCookies();

  await page.goto(`${BASE}/login.php`);
  await page.fill('#email', STUDENT.email);
  await page.fill('#password', STUDENT.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/student|index/, { timeout: 15000 });

  await page.goto(`${BASE}/src/php/public/student/dashboard.php`);
  await expect(page).toHaveURL(/student\/dashboard\.php/, { timeout: 10000 });
  await expect(page.locator('body')).toContainText(/hari/i);
});
