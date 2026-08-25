/**
 * QA: Hybrid evaluation lifecycle
 * Flow 1 (pure MCQ): submit -> INSTANT result screen + evaluated breakdown on results page
 * Flow 2 (hybrid):   submit -> "Result Not Yet Announced" banner + Under Evaluation badges
 * Credentials supplied by repo owner for local QA only.
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');

const STUDENT = { email: 'hariiphones83@gmail.com', password: 'Hari@2003' };
const LOGIN = '/src/php/public/login.php';
const DASH = '/src/php/public/student/dashboard.php';
const RESULTS = '/src/php/public/student/results.php';
const PURE_MCQ_TEST_ID = 7; // test_live: 10 MCQs, 13 marks
const HYBRID_TEST_ID = 8;   // QA Hybrid Test: 2 MCQ + coding + explanation

// Self-sufficient fixtures: make both exams retakeable before the suite runs,
// regardless of what earlier suites/manual sessions left behind.
const MYSQL = process.env.MYSQL_BIN || 'C:\\xampp\\mysql\\bin\\mysql.exe';
function resetEvalFixtures() {
  execSync(
    `"${MYSQL}" -h 127.0.0.1 -u root test_platform < "${path.join(__dirname, 'reset-eval-qa.sql')}"`,
    { shell: 'cmd.exe', stdio: 'pipe' }
  );
}

async function login(page) {
  await page.goto(LOGIN);
  await page.fill('#email', STUDENT.email);
  await page.fill('#password', STUDENT.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  // Logged-in students land on the dashboard; fail fast with context if not
  await expect(page).toHaveURL(/dashboard\.php|index\.php/, { timeout: 10000 });
}

async function openTest(page, testId) {
  await page.goto(DASH);
  // Target the visible action button ("Start Test" / "View Result"), not the
  // hidden "View Details" link inside the overflow dropdown.
  const link = page.locator(`a.btn[href*="test_id=${testId}"]`).first();
  await expect(link).toBeVisible({ timeout: 10000 });
  await link.click();
  await page.waitForURL(new RegExp(`test\\.php\\?test_id=${testId}`));
}

async function submitTest(page) {
  page.on('dialog', d => d.accept()); // "Are you sure you want to submit?"
  await page.click('#submitBtn');
}

test.describe('Hybrid evaluation lifecycle', () => {
  test.beforeAll(() => {
    resetEvalFixtures();
  });

  test('student can log in', async ({ page }) => {
    await login(page);
    await page.goto(DASH);
    await expect(page.locator('body')).toContainText(/hari/i);
  });

  test('pure-MCQ test evaluates instantly after submit', async ({ page }) => {
    await login(page);
    await openTest(page, PURE_MCQ_TEST_ID);

    // Answer every MCQ with its first option
    const cards = page.locator('.question-card');
    await expect(cards).toHaveCount(10);
    for (let i = 0; i < 10; i++) {
      const radio = cards.nth(i).locator('input[type="radio"]').first();
      await radio.check();
    }

    await submitTest(page);

    // BUG-FIX REGRESSION: must land back on the result screen, NOT raw JSON
    await expect(page).toHaveURL(new RegExp(`submitted=1`), { timeout: 15000 });
    await expect(page.locator('h1')).toContainText('Test Completed');
    await expect(page.locator('body')).toContainText('%'); // instant score shown

    // Results page shows the evaluated breakdown
    await page.goto(RESULTS);
    const row = page.locator('tr', { hasText: 'test_live' }).first();
    await expect(row).toBeVisible();
    await expect(row).toContainText('Objective:');
    await expect(row.locator('.badge')).toHaveText(/PASS|FAIL/);
  });

  test('hybrid test goes to pending review with banner, no premature score', async ({ page }) => {
    await login(page);
    await openTest(page, HYBRID_TEST_ID);

    const cards = page.locator('.question-card');
    await expect(cards).toHaveCount(4);

    // MCQ answers known from fixture: Q1=B (correct), Q2=A (correct)
    await cards.nth(0).locator('input[type="radio"][value="B"]').check();
    await cards.nth(1).locator('input[type="radio"][value="A"]').check();

    // Subjective answers
    await cards.nth(2).locator('textarea').fill('def add(a, b):\n    return a + b');
    await cards.nth(3).locator('textarea').fill('A loop repeats a block of instructions until a condition is met.');

    await submitTest(page);

    // Pending state: informational banner, no percentage anywhere
    await expect(page).toHaveURL(new RegExp(`submitted=1`), { timeout: 15000 });
    await expect(page.locator('h1')).toContainText('Result Not Yet Announced');
    await expect(page.getByText('Under Evaluation')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('%'); // no premature score

    // Dashboard badge reflects pending review
    await page.goto(DASH);
    const hybridRow = page.locator('tr', { hasText: 'QA Hybrid Test' }).first();
    await expect(hybridRow).toContainText('Under Evaluation');

    // Results page banner lists the test under evaluation
    await page.goto(RESULTS);
    await expect(page.locator('body')).toContainText('Result Not Yet Announced');
    const pendingBadge = page.locator('.badge', { hasText: 'QA Hybrid Test' });
    await expect(pendingBadge).toBeVisible();
    // Evaluated section must NOT list the hybrid test yet
    await expect(page.locator('tr', { hasText: 'QA Hybrid Test' })).toHaveCount(0);
  });

});
