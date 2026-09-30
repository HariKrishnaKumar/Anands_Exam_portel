/**
 * Faculty E2E â€” ONE continuous spec file for the whole faculty feature.
 *
 * Why this exists: faculty-login.spec.js + faculty-dashboard.spec.js re-sign-in
 * before every single assertion and wait on `networkidle`, so a full run is slow.
 * This file signs in ONCE and walks the entire feature in order, using
 * auto-waiting assertions instead of networkidle. It is the fast gate you can
 * run continuously while iterating.
 *
 * Phases (run in file order):
 *   1. Login  â†’ every rejection path, then a successful sign-in
 *   2. Scope  â†’ college isolation, KPIs, roster, semester/year columns
 *   3. Filtersâ†’ attendance test picker, semester filter, year filter
 *   4. Read-only â†’ no mutations, charts mount, no mojibake
 *   5. Guards â†’ logged-out bounce, faculty blocked from admin + admin card
 *   6. Admin  â†’ assign credential, blank password keeps hash, validation,
 *               duplicate email (SQLSTATE 23000), restore
 *
 * Fixture: tests/fixtures/faculty.sql
 *   college 1 (BGS Institute Of Management Malur): 5 students, tests 1/2/3,
 *   faculty@bgsmalur.edu / BGSCCMALUR@563130
 *   college 2 (Other Institute Of Technology): student only, NO credential
 *
 * RUN: npx playwright test tests/faculty-e2e.spec.js
 *      npx playwright test tests/faculty-e2e.spec.js -g "Phase 3"
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const FACULTY_LOGIN = '/faculty-login.php';
const FACULTY_DASH = '/faculty/dashboard.php';

const COLLEGE_ID = '1';
const OTHER_COLLEGE_ID = '2';
const COLLEGE_NAME = 'BGS Institute Of Management Malur';
const OTHER_COLLEGE_NAME = 'Other Institute Of Technology';
const FACULTY_EMAIL = 'faculty@bgsmalur.edu';
const FACULTY_PASSWORD = 'BGSCCMALUR@563130';
const FOREIGN_STUDENT = 'foreign.student@testplatform.com';

const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };

// Rejections land in failed_login_log; without a reset the 5-attempt /
// 15-minute throttle would lock the fixture account for the next run.
const MYSQL = process.env.MYSQL_BIN || 'C:\\xampp\\mysql\\bin\\mysql.exe';
test.beforeAll(() => {
  execSync(
    `"${MYSQL}" -h 127.0.0.1 -u root test_platform -e "DELETE FROM failed_login_log WHERE email IN ('faculty@bgsmalur.edu','nobody@example.com','harikrishnamrb@gmail.com');"`,
    { shell: 'cmd.exe', stdio: 'pipe' }
  );
});

/** Shared session for phases 1â€“4 so we only sign in once. */
let ctx;
let p; // the shared, signed-in-by-phase-1 page

test.beforeAll(async ({ browser }) => {
  ctx = await browser.newContext();
  p = await ctx.newPage();
  // Phases 1-4 share this context; the login page refuses to submit without
  // a location fix, so grant it once here.
  await grantGeo(p);
});
test.afterAll(async () => {
  await ctx?.close();
});

async function fillFacultyForm(page, { collegeId, email, password }) {
  await page.goto(FACULTY_LOGIN);
  await page.selectOption('select[name="college_id"]', collegeId);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
}

// Location is MANDATORY for a faculty sign-in: the page keeps the submit
// button disabled until the Geolocation API reports a fix, and the server
// rejects the POST when geo_lat/geo_lng are missing. Playwright denies the
// permission by default, so every sign-in path must grant it first.
const GEO = { latitude: 12.9715987, longitude: 77.5945627 };
async function grantGeo(page) {
  const context = page.context();
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation(GEO);
}

async function facultySignIn(page, creds = {}) {
  await grantGeo(page);
  // faculty-login.php redirects any live faculty/admin/student session away
  // (see the isFaculty/isAdmin/isStudent guards at its top), so start clean.
  await page.context().clearCookies();
  await fillFacultyForm(page, {
    collegeId: COLLEGE_ID,
    email: FACULTY_EMAIL,
    password: FACULTY_PASSWORD,
    ...creds,
  });
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/faculty\/dashboard\.php/, { timeout: 15000 });
}

/** Bounced to the login page with the generic auth error still on screen. */
async function expectRejected(page) {
  await expect(page).toHaveURL(/\/faculty-login\.php/, { timeout: 15000 });
  const alert = page.locator('.auth-alert.error');
  await expect(alert).toBeVisible();
  await expect(alert).toHaveText(/Invalid email or password\./);
}

async function adminSignIn(page) {
  await page.goto('/bgs.php');
  // Already authenticated â†’ bgs.php redirects to the dashboard with no form.
  const roleSelect = page.locator('select[name="role"]');
  if (await roleSelect.count()) {
    await roleSelect.selectOption('admin');
    await page.fill('input[name="email"]', ADMIN.email);
    await page.fill('input[name="password"]', ADMIN.password);
    await page.click('button[type="submit"]');
  }
  await expect(page).toHaveURL(/\/admin\/dashboard\.php/, { timeout: 15000 });
}

const kpi = async (id) => (await p.locator('#' + id).textContent()).trim();
const gotoDash = async (qs = '') => {
  await p.goto(FACULTY_DASH + qs);
  await expect(p.locator('#facultyRoster')).toBeVisible({ timeout: 15000 });
};

// =====================================================
// PHASE 1 â€” login (rejections first, ends signed in)
// =====================================================
test.describe.serial('Phase 1 Â· faculty login', () => {
  test('rejection paths leak nothing, then the right credential signs in', async () => {
    await p.goto(FACULTY_LOGIN);

    // Structure: all three inputs present and required
    await expect(p.locator('select[name="college_id"]')).toBeVisible();
    await expect(p.locator('input[name="email"]')).toBeVisible();
    await expect(p.locator('input[name="password"]')).toBeVisible();
    await expect(p.locator('input[name="csrf_token"]')).toHaveCount(1);
    // No self-registration affordance
    await expect(p.locator('a[href*="signup"]')).toHaveCount(0);

    // 1. no college selected
    await p.goto(FACULTY_LOGIN);
    await p.fill('input[name="email"]', FACULTY_EMAIL);
    await p.fill('input[name="password"]', FACULTY_PASSWORD);
    // Bypass HTML5 validation so the server-side branch is exercised
    await p.evaluate(() => document.querySelector('form').noValidate = true);
    await p.click('button[type="submit"]');
    await expect(p).toHaveURL(/\/faculty-login\.php/, { timeout: 15000 });
    await expect(p.locator('.auth-alert.error')).toHaveText(/Please select your college\./);

    // 2. wrong password (valid credential, valid college)
    await fillFacultyForm(p, { collegeId: COLLEGE_ID, email: FACULTY_EMAIL, password: 'WrongPass@1' });
    await p.click('button[type="submit"]');
    await expectRejected(p);

    // 3. unknown email
    await fillFacultyForm(p, { collegeId: COLLEGE_ID, email: 'nobody@example.com', password: 'Whatever@1' });
    await p.click('button[type="submit"]');
    await expectRejected(p);

    // 4. right credential, WRONG college â†’ generic error (no cross-college login)
    await fillFacultyForm(p, { collegeId: OTHER_COLLEGE_ID, email: FACULTY_EMAIL, password: FACULTY_PASSWORD });
    await p.click('button[type="submit"]');
    await expectRejected(p);

    // 5. a student email must never be treated as faculty
    await fillFacultyForm(p, { collegeId: COLLEGE_ID, email: FOREIGN_STUDENT, password: 'Whatever@1' });
    await p.click('button[type="submit"]');
    await expectRejected(p);

    // 6. success
    await facultySignIn(p);
    await expect(p.locator('.dashboard-header h1')).toContainText(COLLEGE_NAME);
  });
});

// =====================================================
// PHASE 2 â€” scope + KPIs (still the same session)
// =====================================================
test.describe.serial('Phase 2 Â· college scope', () => {
  test('only this college is visible and the KPIs add up', async () => {
    await gotoDash();

    // Heading is the signed-in college
    await expect(p.locator('.dashboard-header h1')).toContainText(COLLEGE_NAME);

    // Another college's data must never appear anywhere on the page
    const body = await p.locator('body').textContent();
    expect(body).not.toContain(OTHER_COLLEGE_NAME);
    expect(body).not.toContain('Foreign College Student');
    expect(body).not.toContain(FOREIGN_STUDENT);

    // Fixture numbers
    expect(await kpi('kpiStudents')).toBe('5');
    expect(await kpi('kpiTests')).toBe('3');
    expect(Number(await kpi('kpiAttendedAny'))).toBe(3);
    await expect(p.locator('#facultyRoster tbody tr')).toHaveCount(5);
  });

  test('roster exposes semester and year, with nothing left unset', async () => {
    await gotoDash();

    const headers = await p.locator('#facultyRoster thead th').allTextContents();
    expect(headers).toContain('Semester');
    expect(headers).toContain('Year');

    // students.semester is admin-entered and the fixture fills it in
    const roster = await p.locator('#facultyRoster tbody').textContent();
    expect(roster).not.toContain('Not set');
  });
});

// =====================================================
// PHASE 3 â€” attendance picker + filters
// =====================================================
test.describe.serial('Phase 3 Â· filters', () => {
  test('attendance picker drives attended / not attended / %', async () => {
    // Test 3 â†’ batch 1 â†’ students 1,2,3 all attended; others are "â€”"
    await gotoDash('?test_id=3');
    expect(await kpi('kpiAttended')).toBe('3');
    expect(await kpi('kpiNotAttended')).toBe('0');
    expect(await kpi('kpiAttendancePct')).toBe('100%');
    expect(await p.locator('.badge-success[id^="att-"]')).toHaveCount(3);
    expect(await p.locator('.badge-pending[id^="att-"]')).toHaveCount(0);

    // Test 1 â†’ batch 1 â†’ 2 attended, 1 not
    await gotoDash('?test_id=1');
    expect(await kpi('kpiAttended')).toBe('2');
    expect(await kpi('kpiNotAttended')).toBe('1');
    expect(await kpi('kpiAttendancePct')).toBe('66.7%');

    // Test 2 â†’ batch 2 â†’ only student 4 eligible, did not attend
    await gotoDash('?test_id=2');
    expect(await kpi('kpiAttended')).toBe('0');
    expect(await kpi('kpiNotAttended')).toBe('1');
    expect(await kpi('kpiAttendancePct')).toBe('0%');
  });

  test('batch filter scopes the roster and narrows the test picker', async () => {
    // The Batch select exists and is part of the GET filter form
    await gotoDash();
    await expect(p.locator('#batch_id')).toBeVisible();
    const batchOptions = await p.locator('#batch_id option').allTextContents();
    expect(batchOptions.some(o => o.includes('All batches'))).toBe(true);
    expect(batchOptions.length).toBeGreaterThan(1);

    // Batch 1 â†’ 3 students (1,2,3) and its 2 tests (1 and 3)
    await gotoDash('?batch_id=1');
    expect(await kpi('kpiStudents')).toBe('3');
    expect(await kpi('kpiTests')).toBe('2');
    expect(await p.locator('#facultyRoster tbody tr')).toHaveCount(3);
    let testOptions = await p.locator('#test_id option').allTextContents();
    expect(testOptions).toHaveLength(3); // placeholder + batch 1's 2 tests

    // Batch 2 â†’ 1 student (4) and 1 test (2)
    await gotoDash('?batch_id=2');
    expect(await kpi('kpiStudents')).toBe('1');
    expect(await kpi('kpiTests')).toBe('1');
    testOptions = await p.locator('#test_id option').allTextContents();
    expect(testOptions).toHaveLength(2); // placeholder + the single batch-2 test

    // Batch with tests but no students still renders (0, not an error)
    await gotoDash('?batch_id=4');
    expect(await kpi('kpiStudents')).toBe('1');
    expect(await kpi('kpiTests')).toBe('0');

    // Batch 3 has no students AND no tests
    await gotoDash('?batch_id=3');
    expect(await kpi('kpiStudents')).toBe('0');
    expect(await kpi('kpiTests')).toBe('0');
    await expect(p.locator('text=No students match these filters for this college.')).toBeVisible();

    // Batch + test together â†’ attendance is scoped to that batch's roster
    await gotoDash('?batch_id=1&test_id=3');
    expect(await kpi('kpiAttended')).toBe('3');
    expect(await kpi('kpiNotAttended')).toBe('0');
    expect(await kpi('kpiAttendancePct')).toBe('100%');

    // Batch + semester combine
    await gotoDash('?batch_id=1&semester=4');
    expect(await kpi('kpiStudents')).toBe('1'); // student 3 only
  });

  test('an unknown or another college\'s batch id is ignored', async () => {
    // Not a real batch
    await gotoDash('?batch_id=999');
    expect(await kpi('kpiStudents')).toBe('5');
    expect(await kpi('kpiTests')).toBe('3');
    await expect(p.locator('#batch_id')).toHaveValue('');

    // Batch 5 belongs to college 2 â€” must not leak into college 1
    await gotoDash('?batch_id=5');
    expect(await kpi('kpiStudents')).toBe('5');
    expect(await kpi('kpiTests')).toBe('3');
    await expect(p.locator('#batch_id')).toHaveValue('');
  });

  test('semester and year filters narrow the roster', async () => {
    await gotoDash('?semester=3');
    expect(await kpi('kpiStudents')).toBe('2'); // students 1, 2

    await gotoDash('?semester=1');
    expect(await kpi('kpiStudents')).toBe('1'); // student 5

    await gotoDash('?year=2024');
    expect(await kpi('kpiStudents')).toBe('3'); // students 1, 2, 3

    await gotoDash('?year=2026');
    expect(await kpi('kpiStudents')).toBe('1'); // student 5
  });
});

// =====================================================
// PHASE 4 â€” read-only guarantees
// =====================================================
test.describe.serial('Phase 4 Â· read only', () => {
  test('no mutation affordance, charts mount, no mojibake', async () => {
    await gotoDash('?test_id=3');

    // Only the GET filter form exists
    expect(await p.locator('form[method="post" i]').count()).toBe(0);
    expect(
      await p.locator('button:has-text("Delete"), button:has-text("Remove"), button:has-text("Edit")').count()
    ).toBe(0);
    expect(await p.locator('a[href*="/admin/"]').count()).toBe(0);

    // Charts actually painted into both canvases
    await expect(p.locator('#attendanceChart')).toBeVisible();
    await expect(p.locator('#semesterChart')).toBeVisible();
    const painted = await p.evaluate(() => {
      const has = (id) => {
        const c = document.getElementById(id);
        return !!(c && typeof Chart !== 'undefined' && Chart.getChart(c));
      };
      return { attendance: has('attendanceChart'), semester: has('semesterChart') };
    });
    expect(painted.attendance).toBe(true);
    expect(painted.semester).toBe(true);

    // Encoding sanity
    const text = await p.textContent('body');
    expect(text).not.toContain('Ã¢â‚¬');
    expect(text).not.toContain('Ãƒâ€”');
  });
});

// =====================================================
// PHASE 5 â€” route guards (uses its own pages/contexts)
// =====================================================
test.describe('Phase 5 Â· guards', () => {
  test('logged-out visitor is bounced to the faculty login', async ({ page }) => {
    await page.goto(FACULTY_DASH);
    await expect(page).toHaveURL(/\/faculty-login\.php/, { timeout: 15000 });
  });

  test('faculty session cannot open admin pages or the assignment card', async ({ page }) => {
    await facultySignIn(page);

    await page.goto('/admin/dashboard.php');
    await expect(page).toHaveURL(/\/login\.php/, { timeout: 15000 });

    await page.goto('/admin/colleges/1');
    await expect(page).toHaveURL(/\/login\.php/, { timeout: 15000 });
  });

  test('bgs.php links to the faculty sign-in page', async ({ page }) => {
    await page.goto('/bgs.php');
    await expect(page.locator('a[href="faculty-login.php"]')).toBeVisible();
  });
});

// =====================================================
// PHASE 6 â€” admin assigns the credential
// =====================================================
test.describe('Phase 6 Â· admin assignment', () => {
  const COLLEGE_PAGE = '/admin/colleges/1';

  const submitCard = async (page, name, email, password) => {
    // Earlier steps may have left a faculty session in this context.
    await page.context().clearCookies();
    await adminSignIn(page);
    await page.goto(COLLEGE_PAGE);
    await expect(page.locator('input[name="faculty_name"]')).toBeVisible({ timeout: 15000 });
    await page.locator('input[name="faculty_name"]').fill(name);
    await page.locator('input[name="faculty_email"]').fill(email);
    await page.locator('input[name="faculty_password"]').fill(password);
    await page
      .locator('form:has(input[name="action"][value="assign_faculty"]) button[type="submit"]')
      .click();
  };

  test('card renders with a CSRF token and is college-scoped', async ({ page }) => {
    await adminSignIn(page);
    await page.goto(COLLEGE_PAGE);

    await expect(page.locator('text=Faculty Login Credentials')).toBeVisible();
    const form = page.locator('form:has(input[name="action"][value="assign_faculty"])');
    await expect(form.locator('input[name="csrf_token"]')).toHaveCount(1);
    await expect(form.locator('input[name="faculty_email"]')).toBeVisible();
    await expect(form.locator('input[name="faculty_password"]')).toBeVisible();
    await expect(form.locator('input[name="faculty_active"]')).toBeChecked();
    await expect(page.locator('a[href="signup.php"]')).toHaveCount(0);
  });

  test('assign â†’ blank password keeps the old password â†’ restore', async ({ page }) => {
    // Two bcrypt rotations + several full page loads.
    test.setTimeout(120000);

    const canSignIn = async (password) => {
      await grantGeo(page);
      await page.context().clearCookies();
      await page.goto(FACULTY_LOGIN);
      await page.selectOption('select[name="college_id"]', COLLEGE_ID);
      await page.fill('input[name="email"]', FACULTY_EMAIL);
      await page.fill('input[name="password"]', password);
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/\/faculty\/dashboard\.php/, { timeout: 15000 });
    };

    try {
      // 1. rotate
      await submitCard(page, 'Prof. Faculty', FACULTY_EMAIL, 'TempPass@999');
      await expect(page.locator('text=Faculty credentials saved')).toBeVisible({ timeout: 15000 });
      await canSignIn('TempPass@999');

      // 2. blank password must not change the password
      await submitCard(page, 'Prof. Faculty Updated', FACULTY_EMAIL, '');
      await expect(page.locator('text=Faculty credentials saved')).toBeVisible({ timeout: 15000 });
      await canSignIn('TempPass@999');
    } finally {
      // ALWAYS restore the fixture credential so runs stay repeatable.
      try {
        await submitCard(page, 'Prof. Faculty', FACULTY_EMAIL, FACULTY_PASSWORD);
        await expect(page.locator('text=Faculty credentials saved')).toBeVisible({ timeout: 15000 });
      } catch (e) {
        console.log('[restore] could not restore via UI:', e.message);
      }
    }

    await canSignIn(FACULTY_PASSWORD);
  });

  test('validation rejects bad input without changing anything', async ({ page }) => {
    await adminSignIn(page);

    // Bad email (bypass HTML5 type=email so the SERVER check runs)
    await page.goto(COLLEGE_PAGE);
    await page.evaluate(() => document.querySelectorAll('form').forEach(f => { f.noValidate = true; }));
    await page.locator('input[name="faculty_email"]').fill('not-an-email');
    await page.locator('input[name="faculty_password"]').fill(FACULTY_PASSWORD);
    await page
      .locator('form:has(input[name="action"][value="assign_faculty"]) button[type="submit"]')
      .click();
    await expect(page.locator('text=Enter a valid faculty email address.')).toBeVisible({ timeout: 15000 });

    // Short password
    await page.goto(COLLEGE_PAGE);
    await page.evaluate(() => document.querySelectorAll('form').forEach(f => { f.noValidate = true; }));
    await page.locator('input[name="faculty_email"]').fill(FACULTY_EMAIL);
    await page.locator('input[name="faculty_password"]').fill('short');
    await page
      .locator('form:has(input[name="action"][value="assign_faculty"]) button[type="submit"]')
      .click();
    await expect(page.locator('text=Password must be at least 8 characters.')).toBeVisible({ timeout: 15000 });

    // Credential still intact after both rejections
    await facultySignIn(page);
  });

  test('an email used by another college is rejected (SQLSTATE 23000)', async ({ page }) => {
    await adminSignIn(page);

    // College 2 has no credential yet; take college 1's already-used email.
    await page.goto('/admin/colleges/2');
    await expect(page.locator('input[name="faculty_name"]')).toBeVisible({ timeout: 15000 });
    await page.locator('input[name="faculty_name"]').fill('Impostor Faculty');
    await page.locator('input[name="faculty_email"]').fill(FACULTY_EMAIL);
    await page.locator('input[name="faculty_password"]').fill('TakenPass@99');
    await page
      .locator('form:has(input[name="action"][value="assign_faculty"]) button[type="submit"]')
      .click();

    await expect(
      page.locator('text=That faculty email is already assigned to another college.')
    ).toBeVisible({ timeout: 15000 });

    // College 1's credential untouched by the failed attempt
    await facultySignIn(page);
  });
});

// =====================================================
// PHASE 7 — admin audit log: when + where the faculty signed in
// =====================================================
test.describe('Phase 7 · login audit log', () => {
  test('admin sees the sign-in time, IP and machine coordinates', async ({ page }) => {
    // Grant geolocation so the login page's JS can report lat/long.
    await grantGeo(page);

    // Faculty signs in → logFacultyLogin() writes the audit row.
    await facultySignIn(page);
    await expect(page.locator('.dashboard-header h1')).toContainText(COLLEGE_NAME);

    // Admin views it on the college page.
    await page.context().clearCookies();
    await adminSignIn(page);
    await page.goto('/admin/colleges/1');

    await expect(page.locator('text=Faculty Login Activity')).toBeVisible({ timeout: 15000 });
    const row = page.locator('#facultyLoginLog tbody tr').first();
    await expect(row).toBeVisible();
    const text = (await row.textContent()).replace(/\s+/g, ' ');

    expect(text).toContain(FACULTY_EMAIL);
    expect(text).toContain('12.9715987');   // latitude
    expect(text).toContain('77.5945627');   // longitude
    expect(text).toMatch(/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/); // timestamp
    // IP — the built-in PHP server on localhost reports ::1 (IPv6), so
    // accept both forms rather than assuming dotted-quad IPv4.
    const ipCell = (await row.locator('td').nth(4).textContent()).trim();
    expect(ipCell).toMatch(/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}|[0-9a-f:]+)$/i);
    expect(text).not.toContain('Not shared');
  });

  test('a denied geolocation blocks the sign-in completely', async ({ page }) => {
    // Default context: geolocation permission denied.
    await page.goto(FACULTY_LOGIN);
    await page.selectOption('select[name="college_id"]', COLLEGE_ID);
    await page.fill('input[name="email"]', FACULTY_EMAIL);
    await page.fill('input[name="password"]', FACULTY_PASSWORD);
    // Bypass HTML5 validation so the gate below is what stops us
    await page.evaluate(() => document.querySelector('form').noValidate = true);

    // No fix → the page enters the blocked state on its own
    await expect(page.locator('#geoStatus')).toHaveClass(/is-blocked/, { timeout: 15000 });

    await page.click('button[type="submit"]');

    // Nothing was posted: we are still on the login page with the reason shown
    await expect(page).toHaveURL(/\/faculty-login\.php/);
    await expect(page.locator('#geoStatus')).toContainText(/Location access is required/i, { timeout: 15000 });
    await expect(page.locator('.auth-alert.error')).toHaveCount(0);
  });

  test('the server rejects a sign-in with the coordinates stripped (no-JS bypass)', async ({ page }) => {
    await grantGeo(page);
    await page.goto(FACULTY_LOGIN);

    const body = await page.evaluate(async () => {
      const form = document.getElementById('facultyLoginForm');
      const data = new FormData(form);
      data.delete('geo_lat');
      data.delete('geo_lng');
      data.delete('geo_accuracy');
      const res = await fetch(window.location.pathname, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
      });
      return await res.text();
    });

    expect(body).toContain('Location access is required to sign in');
    expect(body).not.toContain('facultyRoster');   // never reached the dashboard
  });
});
