/**
 * Faculty Login Verification Test
 *
 * Checks:
 *  1. /faculty-login.php renders a College dropdown + email + password
 *  2. Valid credential for the right college â†’ 302 to /faculty/dashboard.php
 *  3. Wrong password / unknown email / wrong college â†’ generic error (no leak)
 *  4. No college selected â†’ "Please select your college."
 *  5. /faculty/dashboard.php is unreachable while logged out
 *  6. bgs.php exposes a "Faculty sign in" entry point
 *  7. No self-registration affordance on the faculty login page
 *
 * RUN: npx playwright test tests/faculty-login.spec.js
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');

const BASE = 'http://localhost:8000';
const FACULTY_LOGIN = '/faculty-login.php';
const FACULTY_DASH = '/faculty/dashboard.php';

// Fixture credentials â€” tests/fixtures/faculty.sql
const COLLEGE_ID = '1';
const OTHER_COLLEGE_ID = '2'; // exists, but has NO faculty credential
const COLLEGE_NAME = 'BGS Institute Of Management Malur';
const FACULTY_EMAIL = 'faculty@bgsmalur.edu';
const FACULTY_PASSWORD = 'BGSCCMALUR@563130';

// Rejections write into failed_login_log; without a reset the 5-attempt /
// 15-minute throttle would lock the fixture account for later runs.
const MYSQL = process.env.MYSQL_BIN || 'C:\\xampp\\mysql\\bin\\mysql.exe';
test.beforeAll(() => {
  execSync(
    `"${MYSQL}" -h 127.0.0.1 -u root test_platform -e "DELETE FROM failed_login_log WHERE email IN ('faculty@bgsmalur.edu','nobody@example.com','harikrishnamrb@gmail.com');"`,
    { shell: 'cmd.exe', stdio: 'pipe' }
  );
});

async function facultySignIn(page, creds = {}) {
  const { collegeId, email, password } = {
    collegeId: COLLEGE_ID,
    email: FACULTY_EMAIL,
    password: FACULTY_PASSWORD,
    ...creds,
  };
  await page.goto(FACULTY_LOGIN);
  await page.selectOption('select[name="college_id"]', collegeId);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

// =====================================================
// Page structure
// =====================================================
test.describe('Faculty Login - page structure', () => {
  test('college dropdown, email and password are all present and required', async ({ page }) => {
    await page.goto(FACULTY_LOGIN);
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveTitle(/Faculty Sign In/i);

    const collegeSelect = page.locator('select[name="college_id"]');
    await expect(collegeSelect).toBeVisible();
    await expect(collegeSelect).toHaveAttribute('required', '');

    // The seeded college must be offered
    const options = await collegeSelect.locator('option').allTextContents();
    expect(options.join(' ')).toContain(COLLEGE_NAME);

    await expect(page.locator('input[name="email"]')).toHaveAttribute('required', '');
    await expect(page.locator('input[name="password"]')).toHaveAttribute('required', '');

    // Role switcher from bgs.php must NOT exist here â€” faculty pick a college instead
    await expect(page.locator('select[name="role"]')).toHaveCount(0);

    // No self-registration path on the faculty portal
    await expect(page.locator('a[href="signup.php"]')).toHaveCount(0);
  });
});

// =====================================================
// Successful login
// =====================================================
test.describe('Faculty Login - success', () => {
  test('correct credential for the right college reaches the dashboard', async ({ page }) => {
    await facultySignIn(page, {
      collegeId: COLLEGE_ID,
      email: FACULTY_EMAIL,
      password: FACULTY_PASSWORD,
    });

    expect(page.url()).toContain('/faculty/dashboard.php');
    await expect(page.locator('#kpiStudents')).toBeVisible();
    await expect(page.locator('#facultyRoster')).toBeVisible();
  });
});

// =====================================================
// Rejections (must never leak which part was wrong)
// =====================================================
test.describe('Faculty Login - rejections', () => {
  const rejectionCases = [
    { label: 'wrong password', collegeId: COLLEGE_ID, email: FACULTY_EMAIL, password: 'definitely-not-it' },
    { label: 'unknown email', collegeId: COLLEGE_ID, email: 'nobody@example.com', password: FACULTY_PASSWORD },
    { label: 'valid credential, different college', collegeId: OTHER_COLLEGE_ID, email: FACULTY_EMAIL, password: FACULTY_PASSWORD },
    { label: 'a student email', collegeId: COLLEGE_ID, email: 'harikrishnamrb@gmail.com', password: FACULTY_PASSWORD },
  ];

  for (const c of rejectionCases) {
    test(`${c.label} â†’ generic error, stays on the login page`, async ({ page }) => {
      await facultySignIn(page, c);

      expect(page.url()).toContain('/faculty-login.php');
      const alert = page.locator('.auth-alert.error');
      await expect(alert).toBeVisible();
      await expect(alert).toHaveText(/Invalid email or password\./);
    });
  }

  test('no college selected â†’ "Please select your college."', async ({ page }) => {
    await page.goto(FACULTY_LOGIN);
    await page.fill('input[name="email"]', FACULTY_EMAIL);
    await page.fill('input[name="password"]', FACULTY_PASSWORD);
    // Bypass HTML5 validation so the server-side branch is exercised
    await page.evaluate(() => document.querySelector('form').noValidate = true);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.auth-alert.error')).toHaveText(/Please select your college\./);
  });
});

// =====================================================
// Admin: assigning the credential (college dashboard card)
// =====================================================
test.describe('Faculty credential assignment (admin)', () => {
  const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };
  const COLLEGE_PAGE = '/admin/colleges/1';

  async function adminSignIn(page) {
    await page.goto('/bgs.php');
    // Already authenticated â†’ bgs.php redirects straight to the dashboard,
    // so only fill the login form when it is actually rendered.
    const roleSelect = page.locator('select[name="role"]');
    if (await roleSelect.count()) {
      await roleSelect.selectOption('admin');
      await page.fill('input[name="email"]', ADMIN.email);
      await page.fill('input[name="password"]', ADMIN.password);
      await page.click('button[type="submit"]');
    }
    await expect(page).toHaveURL(/\/admin\/dashboard\.php/, { timeout: 15000 });
  }

  async function openCard(page) {
    await page.goto(COLLEGE_PAGE);
    await page.waitForLoadState('networkidle');
    return page.locator('form:has(input[name="action"][value="assign_faculty"])');
  }

  test('card renders with a CSRF token and a college-scoped form', async ({ page }) => {
    await adminSignIn(page);
    const form = await openCard(page);

    await expect(page.locator('text=Faculty Login Credentials')).toBeVisible();
    await expect(form.locator('input[name="csrf_token"]')).toHaveCount(1);
    await expect(form.locator('input[name="faculty_email"]')).toBeVisible();
    await expect(form.locator('input[name="faculty_password"]')).toBeVisible();
    await expect(form.locator('input[name="faculty_active"]')).toBeChecked();
    // No self-registration link on the admin side either
    await expect(page.locator('a[href="signup.php"]')).toHaveCount(0);
  });

  test('assign â†’ blank password keeps the old password â†’ restore', async ({ page }) => {
    // Several bcrypt hashes + full page loads; the default 30s is not enough.
    test.setTimeout(300000);

    const submitCard = async (name, email, password) => {
      // canSignIn() leaves a FACULTY session behind; start from a clean
      // cookie jar so bgs.php actually renders the admin login form.
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
      await expect(page.locator('text=Faculty credentials saved')).toBeVisible({ timeout: 15000 });
    };

    const canSignIn = async (password) => {
      await page.context().clearCookies();
      await page.goto(FACULTY_LOGIN);
      await page.selectOption('select[name="college_id"]', COLLEGE_ID);
      await page.fill('input[name="email"]', FACULTY_EMAIL);
      await page.fill('input[name="password"]', password);
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/\/faculty\/dashboard\.php/, { timeout: 15000 });
    };

    await adminSignIn(page);

    try {
      // 1. Rotate the password
      await submitCard('Prof. Faculty', FACULTY_EMAIL, 'TempPass@999');
      await canSignIn('TempPass@999');

      // 2. Blank password must leave the password untouched (name may change)
      await submitCard('Prof. Faculty Updated', FACULTY_EMAIL, '');
      await canSignIn('TempPass@999');
    } finally {
      // ALWAYS restore the fixture credential so this suite stays repeatable
      try {
        await submitCard('Prof. Faculty', FACULTY_EMAIL, FACULTY_PASSWORD);
      } catch (e) {
        console.log('[restore] could not restore via UI:', e.message);
      }
    }

    await canSignIn(FACULTY_PASSWORD);
  });

  test('validation: bad email and short password are rejected without changing anything', async ({ page }) => {
    await adminSignIn(page);

    let form = await openCard(page);
    // Bypass HTML5 type=email validation so the SERVER-side check is exercised
    await page.evaluate(() => {
      document.querySelectorAll('form').forEach(f => { f.noValidate = true; });
    });
    await form.locator('input[name="faculty_email"]').fill('not-an-email');
    await form.locator('input[name="faculty_password"]').fill(FACULTY_PASSWORD);
    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=Enter a valid faculty email address.')).toBeVisible();

    form = await openCard(page);
    await page.evaluate(() => {
      document.querySelectorAll('form').forEach(f => { f.noValidate = true; });
    });
    await form.locator('input[name="faculty_email"]').fill(FACULTY_EMAIL);
    await form.locator('input[name="faculty_password"]').fill('short');
    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=Password must be at least 8 characters.')).toBeVisible();

    // Credential still intact
    await page.context().clearCookies();
    await facultySignIn(page, { collegeId: COLLEGE_ID, email: FACULTY_EMAIL, password: FACULTY_PASSWORD });
    expect(page.url()).toContain('/faculty/dashboard.php');
  });

  test('an email already used by another college is rejected (SQLSTATE 23000)', async ({ page }) => {
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

    // College 1's credential must be untouched by the failed attempt.
    await page.context().clearCookies();
    await facultySignIn(page);
    await expect(page).toHaveURL(/\/faculty\/dashboard\.php/, { timeout: 15000 });
  });

  test('a faculty session cannot open the assignment form', async ({ page }) => {
    await facultySignIn(page);
    await page.goto(COLLEGE_PAGE);
    await page.waitForLoadState('networkidle');
    // requireAdmin() sends the faculty session to login.php
    expect(page.url()).toContain('/login.php');
  });
});

// =====================================================
// Route guard + entry point
// =====================================================
test.describe('Faculty Login - guards', () => {
  test('dashboard redirects to the faculty login when logged out', async ({ page }) => {
    await page.goto(FACULTY_DASH);
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/faculty-login.php');
  });

  test('bgs.php links to the faculty sign-in page', async ({ page }) => {
    await page.goto('/bgs.php');
    await page.waitForLoadState('networkidle');
    const link = page.locator('a[href="faculty-login.php"]');
    await expect(link).toBeVisible();
    await link.click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/faculty-login.php');
  });
});
