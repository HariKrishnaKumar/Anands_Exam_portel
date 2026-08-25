/**
 * QA: Admin manual grading workflow (hybrid submissions)
 *
 *   Admin : grading.php -> pending queue -> award marks + evaluator
 *           remarks -> Save All Grades -> submission evaluated
 *   Student: results.php shows Objective/Subjective breakdown,
 *            PASS badge, and evaluator remarks in itemized review
 *
 * Prerequisite: a pending_manual_review submission for student id 2 on
 * test id 8 ("QA Hybrid Test"). Running the full suite provides one:
 * tests/evaluation.spec.js recreates it (runs earlier alphabetically).
 * Standalone reset+recreate:
 *   mysql -u root test_platform < tests/reset-eval-qa.sql
 *   npx playwright test tests/evaluation.spec.js --grep hybrid
 */
const { test, expect } = require('@playwright/test');

const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };
const STUDENT = { email: 'hariiphones83@gmail.com', password: 'Hari@2003' };
const HYBRID_TEST_ID = 8; // QA Hybrid Test

async function loginAs(page, who, role = 'student') {
  await page.goto('/src/php/public/login.php');
  await page.fill('#email', who.email);
  await page.fill('#password', who.password);
  if (role === 'admin') await page.selectOption('#role', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

test.describe('Admin grading workflow', () => {

  test('admin grades pending hybrid submission with remarks', async ({ page }) => {
    await loginAs(page, ADMIN, 'admin');
    await page.goto(`/src/php/public/admin/grading.php?test_id=${HYBRID_TEST_ID}`);

    const gradeBtn = page.locator(`a[href*="student_id=2"]`, { hasText: /Grade|Review/ }).first();
    await expect(gradeBtn).toBeVisible({ timeout: 10000 });
    await gradeBtn.click();
    await page.waitForURL(/student_id=2/);

    // Fill every empty marks input to its max; prefilled MCQ overrides stay.
    const marksInputs = page.locator('input[name^="marks["]');
    const n = await marksInputs.count();
    expect(n).toBeGreaterThan(0);
    for (let i = 0; i < n; i++) {
      const inp = marksInputs.nth(i);
      if ((await inp.inputValue()) === '') {
        await inp.fill((await inp.getAttribute('max')) ?? '0');
      }
    }
    const remarks = page.locator('textarea[name^="remarks["]');
    const nr = await remarks.count();
    const texts = ['Clean solution, well structured.', 'Correct concept, good explanation.'];
    for (let i = 0; i < nr; i++) await remarks.nth(i).fill(texts[i] ?? 'OK');

    await page.click('button:has-text("Save All Grades")');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.alert')).toContainText('Grades saved');
  });

  test('student sees final evaluated result with objective+subjective+remarks', async ({ page }) => {
    await loginAs(page, STUDENT, 'student');
    await page.goto('/src/php/public/student/results.php');
    const row = page.locator('tr', { hasText: 'QA Hybrid Test' }).first();
    await expect(row).toBeVisible({ timeout: 10000 });
    await expect(row).toContainText('Objective:');
    await expect(row).toContainText('Subjective:');
    await expect(row.locator('.badge')).toHaveText(/PASS|FAIL/);

    // Evaluator remarks must reach the student inside the review
    await row.locator('details summary').first().click();
    await expect(page.locator('body')).toContainText('Evaluator:');
    await expect(page.locator('body')).toContainText('well structured');
  });

});
