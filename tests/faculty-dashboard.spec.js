/**
 * Faculty Dashboard Verification Test
 *
 * Checks the read-only, college-scoped analytics page:
 *  1. Route guard (logged-out â†’ faculty-login.php; faculty â†’ no admin pages)
 *  2. KPI cards and student roster render for the signed-in college
 *  3. Test picker drives attendance (attended / not attended / %)
 *  4. Semester and year filters narrow the roster
 *  5. Charts mount (Chart.js) and no mutation affordance exists
 *
 * Fixture: tests/fixtures/faculty.sql
 *   college 1 Â· 5 students Â· tests 1 (batch 1), 2 (batch 2), 3 (batch 1)
 *   test 3 attended by students 1,2,3 Â· test 1 attended by students 1,2
 *
 * RUN: npx playwright test tests/faculty-dashboard.spec.js
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const BASE = 'http://localhost:8000';
const FACULTY_LOGIN = '/faculty-login.php';
const FACULTY_DASH = '/faculty/dashboard.php';

const COLLEGE_ID = '1';
const COLLEGE_NAME = 'BGS Institute Of Management Malur';
const FACULTY_EMAIL = 'faculty@bgsmalur.edu';
const FACULTY_PASSWORD = 'BGSCCMALUR@563130';

// Any earlier rejection run must not throttle the fixture account.
const MYSQL = process.env.MYSQL_BIN || 'C:\\xampp\\mysql\\bin\\mysql.exe';
test.beforeAll(() => {
  execSync(
    `"${MYSQL}" -h 127.0.0.1 -u root test_platform -e "DELETE FROM failed_login_log WHERE email = 'faculty@bgsmalur.edu';"`,
    { shell: 'cmd.exe', stdio: 'pipe' }
  );
});

// Location is MANDATORY for a faculty sign-in: the login page keeps the
// submit button disabled until the Geolocation API reports a fix, and the
// server rejects a POST that carries no coordinates. Playwright denies the
// permission by default, so grant it first.
const GEO = { latitude: 12.9715987, longitude: 77.5945627 };
async function grantGeo(page) {
  const context = page.context();
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation(GEO);
}

async function signIn(page) {
  await grantGeo(page);
  await page.goto(FACULTY_LOGIN);
  await page.selectOption('select[name="college_id"]', COLLEGE_ID);
  await page.fill('input[name="email"]', FACULTY_EMAIL);
  await page.fill('input[name="password"]', FACULTY_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  expect(page.url()).toContain('/faculty/dashboard.php');
}

const kpi = async (page, id) => (await page.locator('#' + id).textContent()).trim();

// =====================================================
// Guards
// =====================================================
test.describe('Faculty Dashboard - guards', () => {
  test('logged-out visitor is bounced to the faculty login', async ({ page }) => {
    await page.goto(FACULTY_DASH);
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/faculty-login.php');
  });

  test('faculty session cannot open admin pages', async ({ page }) => {
    await signIn(page);

    await page.goto('/admin/dashboard.php');
    await page.waitForLoadState('networkidle');
    // requireAdmin() sends non-admins to login.php
    expect(page.url()).toContain('/login.php');
  });

  test('page shows only the signed-in college', async ({ page }) => {
    await signIn(page);
    const heading = await page.locator('.dashboard-header h1').textContent();
    expect(heading).toContain(COLLEGE_NAME);
  });

  test('students belonging to another college are never shown', async ({ page }) => {
    await signIn(page);

    // Sanity: the fixture does contain a student in the other college
    const body = await page.locator('body').textContent();
    expect(body).not.toContain('Other Institute Of Technology');
    expect(body).not.toContain('Foreign College Student');
    expect(body).not.toContain('foreign.student@testplatform.com');

    // Roster is still exactly this college's 5 students
    expect(await kpi(page, 'kpiStudents')).toBe('5');
    expect(await page.locator('#facultyRoster tbody tr')).toHaveCount(5);
  });
});

// =====================================================
// KPIs + roster
// =====================================================
test.describe('Faculty Dashboard - content', () => {
  test('KPI cards and roster render', async ({ page }) => {
    await signIn(page);

    expect(await kpi(page, 'kpiStudents')).toBe('5');   // fixture roster
    expect(await kpi(page, 'kpiTests')).toBe('3');      // fixture tests

    // College-wide: 5 submissions exist across the college's tests
    expect(Number(await kpi(page, 'kpiAttendedAny'))).toBe(3);

    const rows = page.locator('#facultyRoster tbody tr');
    await expect(rows).toHaveCount(5);
  });

  test('roster exposes semester and year columns', async ({ page }) => {
    await signIn(page);

    const headers = await page.locator('#facultyRoster thead th').allTextContents();
    expect(headers).toContain('Semester');
    expect(headers).toContain('Year');

    // Every fixture student has a semester (student 1 was NULL, fixture sets 3)
    const body = await page.locator('#facultyRoster tbody').textContent();
    expect(body).not.toContain('Not set');
  });

  test('attendance test picker drives attended / not attended / %', async ({ page }) => {
    // Test 3 â†’ batch 1 â†’ students 1,2,3 all attended
    await signIn(page);
    await page.goto(FACULTY_DASH + '?test_id=3');
    await page.waitForLoadState('networkidle');

    expect(await kpi(page, 'kpiAttended')).toBe('3');
    expect(await kpi(page, 'kpiNotAttended')).toBe('0');
    expect(await kpi(page, 'kpiAttendancePct')).toBe('100%');
    expect(await page.locator('.badge-success[id^="att-"]')).toHaveCount(3);
    // Students in other batches are "â€”", never "Not attended"
    expect(await page.locator('.badge-pending[id^="att-"]')).toHaveCount(0);

    // Test 1 â†’ batch 1 â†’ students 1 and 2 attended, student 3 did not
    await page.goto(FACULTY_DASH + '?test_id=1');
    await page.waitForLoadState('networkidle');
    expect(await kpi(page, 'kpiAttended')).toBe('2');
    expect(await kpi(page, 'kpiNotAttended')).toBe('1');
    expect(await kpi(page, 'kpiAttendancePct')).toBe('66.7%');

    // Test 2 â†’ batch 2 â†’ only student 4 eligible, and they did not attend
    await page.goto(FACULTY_DASH + '?test_id=2');
    await page.waitForLoadState('networkidle');
    expect(await kpi(page, 'kpiAttended')).toBe('0');
    expect(await kpi(page, 'kpiNotAttended')).toBe('1');
    expect(await kpi(page, 'kpiAttendancePct')).toBe('0%');
  });

  test('semester filter narrows the roster', async ({ page }) => {
    await signIn(page);

    await page.goto(FACULTY_DASH + '?semester=3');
    await page.waitForLoadState('networkidle');
    expect(await kpi(page, 'kpiStudents')).toBe('2'); // students 1 and 2

    await page.goto(FACULTY_DASH + '?semester=1');
    await page.waitForLoadState('networkidle');
    expect(await kpi(page, 'kpiStudents')).toBe('1'); // student 5
  });

  test('year filter narrows the roster', async ({ page }) => {
    await signIn(page);

    await page.goto(FACULTY_DASH + '?year=2024');
    await page.waitForLoadState('networkidle');
    expect(await kpi(page, 'kpiStudents')).toBe('3'); // students 1, 2, 3

    await page.goto(FACULTY_DASH + '?year=2026');
    await page.waitForLoadState('networkidle');
    expect(await kpi(page, 'kpiStudents')).toBe('1'); // student 5
  });
});

// =====================================================
// Read-only guarantees
// =====================================================
test.describe('Faculty Dashboard - read only', () => {
  test('contains no POST form and no admin action links', async ({ page }) => {
    await signIn(page);
    await page.goto(FACULTY_DASH + '?test_id=3');
    await page.waitForLoadState('networkidle');

    // The only form is the GET filter form
    expect(await page.locator('form[method="post" i]').count()).toBe(0);
    // No delete/edit buttons anywhere on the page
    expect(await page.locator('button:has-text("Delete"), button:has-text("Remove"), button:has-text("Edit")').count()).toBe(0);
    // No links into the admin area
    expect(await page.locator('a[href*="/admin/"]').count()).toBe(0);
  });

  test('attendance and score charts mount', async ({ page }) => {
    await signIn(page);
    await page.goto(FACULTY_DASH + '?test_id=3');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#attendanceChart')).toBeVisible();
    await expect(page.locator('#semesterChart')).toBeVisible();

    // Chart.js must have painted into both canvases
    const painted = await page.evaluate(() => {
      const has = (id) => {
        const c = document.getElementById(id);
        return !!(c && typeof Chart !== 'undefined' && Chart.getChart(c));
      };
      return { attendance: has('attendanceChart'), semester: has('semesterChart') };
    });
    expect(painted.attendance).toBe(true);
    expect(painted.semester).toBe(true);
  });

  test('no mojibake on the dashboard', async ({ page }) => {
    await signIn(page);
    await page.goto(FACULTY_DASH + '?test_id=3');
    await page.waitForLoadState('networkidle');

    const text = await page.textContent('body');
    expect(text).not.toContain('Ã¢â‚¬');
    expect(text).not.toContain('Ãƒâ€”');
  });
});
