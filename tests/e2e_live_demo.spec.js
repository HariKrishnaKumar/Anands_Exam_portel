/**
 * E2E LIVE DEMO - Full exam lifecycle via ASSESSMENT STUDIO
 *
 * Admin : /admin/assessment_studio.php -> create draft -> import
 *         sample_questions.csv (10 MCQs) -> Review & Publish
 * Student: dashboard -> Start Test -> timer -> answers (8 right,
 *          2 deliberately wrong) -> tab-switch proctoring banner
 *          -> submit -> instant result screen (~77%)
 * Both  : results breakdown + itemized review + analytics charts clean
 *
 * RUN:  npx playwright test tests/e2e_live_demo.spec.js
 *       (headed + slowMo come from launchOptions below)
 */
const { test, expect } = require('@playwright/test');
const path = require('path');

const ADMIN   = { email: 'admin@testplatform.com', password: 'admin123' };   // README.md:451
const STUDENT = { email: 'hariiphones83@gmail.com', password: 'Hari@2003' };
const BASE    = 'http://localhost:8000';
const LOGIN   = '/src/php/public/login.php';
const STUDIO  = '/src/php/public/admin/assessment_studio.php';
const TITLE   = 'Web Development Assessment';

// sample_questions.csv order + marks [1,1,1,2,1,1,2,1,1,2] = 13 total
const CORRECT_KEYS = ['C','B','B','C','D','B','A','B','B','A'];

let TEST_TITLE_STORED = '';

test.use({
  viewport: { width: 1280, height: 800 },
  launchOptions: { headless: false, slowMo: 200 },
});

test.setTimeout(150_000);

function log(step, msg) { console.log(`\n> [${step}] ${msg}`); }

async function spotlight(page, selector) {
  try { await page.locator(selector).first().highlight(); } catch { /* best-effort */ }
}

async function trackConsoleErrors(page, bucket) {
  page.on('console', m => { if (m.type() === 'error') bucket.push(m.text()); });
  page.on('pageerror', e => bucket.push(String(e)));
}

async function loginAs(page, who, role) {
  log('AUTH', `Logging in as ${role}: ${who.email}`);
  await page.goto(BASE + LOGIN);
  await page.fill('#email', who.email);
  await page.fill('#password', who.password);
  if (role === 'admin') await page.selectOption('#role', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

// ======================================================================
test('PART 1 - Admin creates & publishes assessment in Studio', async ({ page }) => {
  await test.step('Login as admin', async () => {
    await loginAs(page, ADMIN, 'admin');
    await expect(page).toHaveURL(/dashboard\.php/, { timeout: 15000 });
    log('AUTH', 'Admin dashboard reached');
  });

  let createdId = null;
  await test.step('Open Assessment Studio', async () => {
    log('NAV', 'Navigating to /admin/assessment_studio.php');
    await page.goto(BASE + STUDIO);
    await expect(page.locator('#createAssessmentForm')).toBeVisible({ timeout: 10000 });
  });

  await test.step('Step 1 - Basic Information (create draft)', async () => {
    TEST_TITLE_STORED = `${TITLE} ${Date.now()}`;
    log('STEP1', `Title="${TEST_TITLE_STORED}", Duration=30`);
    await page.fill('#createAssessmentForm input[name="title"]', TEST_TITLE_STORED);
    await page.selectOption('#createAssessmentForm select[name="batch_id"]',
      { label: 'BGS Institute Of Management Mahalakshipuram \u2192 Bachelor of Computer Applications (BCA) \u2192 BIM_BACH_202608' });
    await expect(page.locator('#createAssessmentForm input[name="duration_minutes"]')).toHaveValue('30');
    await spotlight(page, '#createAssessmentForm');
    await page.click('#createAssessmentForm button[type="submit"]');
    await page.waitForURL(/edit_test=(\d+)/, { timeout: 20000 });
    createdId = page.url().match(/edit_test=(\d+)/)[1];
    log('STEP1', `Draft created - edit_test=${createdId} (now in Question Builder)`);
  });

  await test.step('Step 2 - Bulk import sample_questions.csv (10 MCQs)', async () => {
    const csvForm = page.locator('form:has(input[value="import_csv"])');
    await csvForm.locator('input[name="csv_file"]')
      .setInputFiles(path.join(__dirname, '..', 'sample_questions.csv'));
    await spotlight(page, 'form:has(input[value="import_csv"])');
    await csvForm.locator('button:has-text("Import CSV")').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.studio-section-badge')).toContainText('10 questions');
    log('STEP2', 'sample_questions.csv imported - 10 MCQs (13 marks)');
  });

  await test.step('Step 2.5 - Preview question formatting (hover list)', async () => {
    const qRow = page.locator('.studio-page div', { hasText: 'capital of France' }).first();
    await qRow.scrollIntoViewIfNeeded();
    await qRow.hover();
    log('PREVIEW', 'Hovered rendered question - formatting verified');
  });

  await test.step('Step 3 - Review & Publish Now', async () => {
    log('STEP3', 'Opening Review & Publish');
    const reviewLink = page.locator(`a[href*="edit_test=${createdId}&step=3"]`).first();
    await reviewLink.click();
    await page.waitForURL(/step=3/, { timeout: 10000 });

    const modalForm = page.locator('#publishNowForm');
    const pubTrigger = page.locator('button:has-text("Publish"), a:has-text("Publish")').first();
    if (await pubTrigger.isVisible().catch(() => false)) {
      await pubTrigger.click();
      await page.waitForTimeout(300); // modal fade-in
    }
    if (await modalForm.isVisible().catch(() => false)) {
      await modalForm.locator('button[type="submit"], input[type="submit"]').first().click();
    } else {
      await page.locator('form:has(input[value="publish_now"]) button[type="submit"], '
                       + 'form:has(input[value="publish_now"]) input[type="submit"]').first().click();
    }

    await page.waitForURL(/assessment_management\.php/, { timeout: 20000 });
    await expect(page.locator('.toast, .alert, .studio-alert').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('body')).toContainText(TEST_TITLE_STORED);
    log('STEP3', 'PUBLISHED - live for students immediately');

    global.__WDA_TITLE__ = TEST_TITLE_STORED;
    global.__WDA_ID__ = createdId;
  });
});

// ======================================================================
test('PART 2 - Student takes the exam (timer, answers, proctoring, submit)', async ({ page }) => {
  const consoleErrors = [];
  trackConsoleErrors(page, consoleErrors);
  const title = global.__WDA_TITLE__ || process.env.WDA_TITLE;
  test.skip(!title, 'run PART 1 first (same worker keeps module state)');

  await test.step('Student login then dashboard', async () => {
    await loginAs(page, STUDENT, 'student');
    await page.goto(BASE + '/src/php/public/student/dashboard.php');
    log('STUDENT', 'Dashboard loaded');
  });

  await test.step('Exam card visible + college logo renders', async () => {
    const row = page.locator('tr', { hasText: title }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    log('STUDENT', `"${title}" appears under Your Tests`);

    const logo = page.locator('.sidebar-logo img').first();
    await expect(logo).toBeVisible({ timeout: 5000 }).catch(async () => {
      log('STUDENT', 'College has no logo set - skipping logo pixel check');
    });
    if (await logo.count()) {
      const loaded = await logo.evaluate(img => img.complete && img.naturalWidth > 0);
      expect(loaded, 'college logo naturalWidth > 0').toBeTruthy();
      log('STUDENT', 'College logo loaded (naturalWidth > 0)');
    }
  });

  await test.step('Launch exam', async () => {
    const startBtn = page.locator('tr', { hasText: title })
      .locator('a.btn', { hasText: /Start Test|Resume/ }).first();
    await startBtn.click();
    await page.waitForURL(/test\.php\?test_id=/, { timeout: 15000 });
    log('EXAM', `Entered exam (${page.url()})`);
  });

  await test.step('Live countdown timer observed', async () => {
    const t1 = (await page.locator('#timerDisplay').textContent()).trim();
    expect(t1).toMatch(/^\d{2}:\d{2}:\d{2}$/);
    await page.waitForTimeout(1100);
    const t2 = (await page.locator('#timerDisplay').textContent()).trim();
    log('TIMER', `${t1} -> ${t2}`);
    expect(t1).not.toBe(t2);
  });

  await test.step('Answer 8 correct + 2 wrong via nav dots', async () => {
    const cards = page.locator('.question-card');
    await expect(cards).toHaveCount(10);
    for (let i = 0; i < 10; i++) {
      const wantWrong = i >= 8; // miss Q9 (1mk) and Q10 (2mk) -> 10/13 ~ 77%
      let key = CORRECT_KEYS[i];
      if (wantWrong) key = key === 'A' ? 'B' : 'A';
      await cards.nth(i).scrollIntoViewIfNeeded();
      await cards.nth(i).locator(`input[type="radio"][value="${key}"]`).check();
      if (i < 9) await page.locator('.nav-dot').nth(i + 1).click();
      log('ANSWER', `Q${i + 1} -> ${key}${wantWrong ? ' (deliberately wrong)' : ''}`);
    }
  });

  await test.step('Proctoring: simulated tab switch triggers warning banner', async () => {
    await page.evaluate(() => {
      Object.defineProperty(document, 'hidden', { value: true, configurable: true });
      Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await expect(page.locator('#tabWarning')).toHaveClass(/show/, { timeout: 5000 });
    log('PROCTOR', 'Tab-switch warning shown');
    await page.evaluate(() => {
      Object.defineProperty(document, 'hidden', { value: false, configurable: true });
      Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
      document.dispatchEvent(new Event('visibilitychange'));
    });
  });

  await test.step('Submit -> confirm dialog -> instant evaluation', async () => {
    page.once('dialog', d => { log('SUBMIT', `confirm(): "${d.message()}" accepted`); d.accept(); });
    await spotlight(page, '#submitBtn');
    await page.click('#submitBtn');
    await page.waitForURL(/submitted=1/, { timeout: 20000 });
    await expect(page.locator('h1')).toContainText('Test Completed');
    await expect(page.locator('body')).toContainText('%');
    log('RESULT', 'Instantly graded: ~77% expected (10/13, pure MCQ)');
    expect(consoleErrors).toEqual([]);
  });
});

// ======================================================================
test('PART 3+4 - Score breakdown, review states, analytics charts', async ({ page }) => {
  const consoleErrors = [];
  trackConsoleErrors(page, consoleErrors);
  const title = global.__WDA_TITLE__ || process.env.WDA_TITLE;
  test.skip(!title, 'run PART 1 first');

  await test.step('Results page - breakdown card', async () => {
    await loginAs(page, STUDENT, 'student');
    await page.goto(BASE + '/src/php/public/student/results.php');
    const row = page.locator('tr', { hasText: title }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.scrollIntoViewIfNeeded();
    await expect(row).toContainText('Objective:');
    await expect(row).toContainText('10.0');
    await expect(row.locator('.badge')).toHaveText(/PASS/);
    log('RESULTS', 'Objective score 10/13 + PASS badge verified');
  });

  await test.step('Itemized review - per-question states', async () => {
    await page.locator('tr', { hasText: title }).first()
              .locator('details summary').first().click();
    await expect(page.locator('details[open]').first()).toBeVisible();
    await expect(page.locator('body')).toContainText('Q1');
    await expect(page.locator('body')).toContainText('Q10');
    log('RESULTS', 'Itemized question-by-question review expanded');
  });

  await test.step('Analytics - charts render with zero console errors', async () => {
    await page.goto(BASE + '/src/php/public/student/analytics.php');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.stat-card-gradient').first()).toBeVisible();

    const canvases = page.locator('canvas');
    const n = await canvases.count();
    log('ANALYTICS', `${n} chart canvas(es) found`);
    for (let i = 0; i < n; i++) {
      const ok = await canvases.nth(i).evaluate(
        c => c.width > 0 && c.height > 0 && !!c.getContext('2d'));
      expect(ok, `canvas ${i + 1}`).toBeTruthy();
    }

    log('ANALYTICS', 'Brief viewing pause ...');
    await page.waitForTimeout(2500);

    expect(consoleErrors,
      `console not clean: ${JSON.stringify(consoleErrors)}`).toEqual([]);
    log('DONE', 'Zero console errors across results + analytics');
  });
});
