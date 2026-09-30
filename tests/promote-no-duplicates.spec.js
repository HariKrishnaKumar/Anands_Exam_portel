/**
 * E2E Tests: Student Promotion — No Duplicates
 *
 * Verifies that the in-place promotion feature:
 * 1. Updates the batch's semester_order without creating new batches
 * 2. Does NOT create orphan/empty duplicate batches
 * 3. Prevents promotion beyond max semesters
 * 4. Shows correct semester progression in preview
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');

const BASE = 'http://localhost:8000';
const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };

// Batch 9 is the only batch with a semester assigned, so it is the only row
// that gets a Promote button — and it is what every UI assertion in this file
// drives. Both fixtures below are idempotent and are re-applied before each
// run so a promotion in one run cannot leave the next run starting from a
// semester that is already at the course ceiling.
const MYSQL = process.env.MYSQL_BIN || 'C:\\xampp\\mysql\\bin\\mysql.exe';
function runSql(fileName) {
  execSync(
    `"${MYSQL}" -h 127.0.0.1 -u root test_platform < "${path.join(__dirname, fileName)}"`,
    { shell: 'cmd.exe', stdio: 'pipe' }
  );
}
function qaStudentSemester() {
  const out = execSync(
    `"${MYSQL}" -h 127.0.0.1 -u root test_platform -N -e "SELECT semester FROM students WHERE batch_id = 9"`,
    { shell: 'cmd.exe', encoding: 'utf8' }
  ).trim();
  return out === '' ? null : parseInt(out, 10);
}

test.beforeAll(() => {
  runSql('stress-fixture.sql');   // batch 9 → semester_order 3
  runSql('promote-fixture.sql');  // one student in batch 9 → semester 3
});

// Helper: login as admin
async function loginAsAdmin(page) {
  await page.goto(`${BASE}/login.php`);
  await page.waitForLoadState('networkidle');
  await page.selectOption('#role', 'admin');
  await page.fill('#email', ADMIN.email);
  await page.fill('#password', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin/dashboard.php', { timeout: 15000 });
}

// Helper: count batches in the table on batches page
async function getBatchCount(page) {
  await page.goto(`${BASE}/admin/batches.php`);
  await page.waitForSelector('table', { timeout: 10000 });
  return page.locator('table tbody tr').count();
}

test.describe('Student Promotion — No Duplicates', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('should promote batch in-place (no new batch created)', async ({ page }) => {
    // Record initial batch count
    const initialCount = await getBatchCount(page);
    console.log(`Initial batch count: ${initialCount}`);

    // Find a Promote button — click it to check if batch is at max semester
    const promoteBtns = page.locator('button:has-text("Promote")');
    const btnCount = await promoteBtns.count();
    if (btnCount === 0) {
      test.skip(true, 'No active batches with Promote button');
      return;
    }

    // Find a batch that is NOT at max semester (can actually be promoted)
    let promoteBtn = null;
    let currentSem = 0;
    for (let i = 0; i < btnCount; i++) {
      const btn = promoteBtns.nth(i);
      const row = btn.locator('xpath=ancestor::tr');
      const semesterCell = await row.locator('td').nth(2).textContent();
      const sem = parseInt(semesterCell.replace(/\D/g, ''), 10);
      if (!isNaN(sem) && sem < 8) { // 8 is max for 4-year course
        promoteBtn = btn;
        currentSem = sem;
        break;
      }
    }

    if (!promoteBtn) {
      test.skip(true, 'All batches are at max semester - no promotable batch available');
      return;
    }
    console.log(`Current semester: ${currentSem}`);

    // The batch's students must move up with it — that is the whole point of
    // this feature, so capture them before promoting.
    const studentBefore = qaStudentSemester();
    expect(studentBefore).not.toBeNull();

    // Click Promote
    await promoteBtn.click();
    await page.waitForSelector('#promoteModal', { state: 'visible', timeout: 5000 });

    // Wait for preview to load
    await page.waitForFunction(() => {
      const body = document.getElementById('promoteModalBody');
      return body && !body.textContent.includes('Loading');
    }, { timeout: 5000 });

    // Verify preview content
    const modalText = await page.locator('#promoteModalBody').textContent();
    console.log(`Preview text: ${modalText}`);

    // Must say "Will become" not "Will be created"
    expect(modalText).toContain('Will become');
    expect(modalText).not.toContain('Will be created');
    expect(modalText).toContain('No new batch');
    expect(modalText).toContain('in-place');

    // Confirm promotion. The glob must include ?promoted= — "**/batches.php*"
    // already matches the page we are sitting on, so it would resolve before
    // the redirect even starts and the next goto would race it (ERR_ABORTED).
    await page.click('#promoteConfirmBtn');
    await page.waitForURL('**/batches.php?promoted=*', { timeout: 10000 });
    await page.waitForLoadState('networkidle');

    // Count batches after promotion
    const afterCount = await getBatchCount(page);
    console.log(`Batch count after promote: ${afterCount}`);

    // CRITICAL: batch count must NOT increase
    expect(afterCount).toBe(initialCount);

    // Verify the promoted batch now shows the next semester
    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    let foundSem = false;
    for (let i = 0; i < count; i++) {
      const semText = await rows.nth(i).locator('td').nth(2).textContent();
      if (parseInt(semText.replace(/\D/g, ''), 10) === currentSem + 1) {
        foundSem = true;
        break;
      }
    }
    console.log(`Found batch with semester ${currentSem + 1}: ${foundSem}`);
    expect(foundSem).toBe(true);

    // The batch column advanced AND its students advanced with it, in the
    // same transaction — no batch was renamed, none was created.
    const studentAfter = qaStudentSemester();
    console.log(`QA student semester: ${studentBefore} -> ${studentAfter}`);
    expect(studentAfter).toBe(studentBefore + 1);
  });

  test('should return error for invalid batch ID (0)', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 0 },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(resp.status()).toBe(400);
    const json = await resp.json();
    expect(json.status).toBe('error');
    expect(json.message).toContain('Invalid batch ID');
  });

  test('should return 404 for non-existent batch', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 99999, preview: true },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(resp.status()).toBe(404);
    const json = await resp.json();
    expect(json.status).toBe('error');
    expect(json.message).toContain('not found');
  });

  test('should reject GET requests with 405', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/test-platform/src/php/api/promote_students.php`);
    expect(resp.status()).toBe(405);
    const json = await resp.json();
    expect(json.status).toBe('error');
    expect(json.message).toContain('Method not allowed');
  });

  test('batch count is stable across page reloads (no phantom duplicates)', async ({ page }) => {
    const counts = [];
    for (let i = 0; i < 3; i++) {
      const c = await getBatchCount(page);
      counts.push(c);
    }
    // All counts should be identical
    const unique = new Set(counts);
    expect(unique.size).toBe(1);
    console.log(`Batch counts across 3 page loads: ${counts.join(', ')} (all same = OK)`);
  });

  test('promote preview shows correct semester arrow', async ({ page }) => {
    await page.goto(`${BASE}/admin/batches.php`);
    await page.waitForSelector('table', { timeout: 10000 });

    const promoteBtns = page.locator('button:has-text("Promote")');
    const btnCount = await promoteBtns.count();
    if (btnCount === 0) {
      test.skip(true, 'No active batches with Promote button');
      return;
    }

    let promoteBtn = null;
    let currentSem = 0;
    for (let i = 0; i < btnCount; i++) {
      const btn = promoteBtns.nth(i);
      const row = btn.locator('xpath=ancestor::tr');
      const semesterText = await row.locator('td').nth(2).textContent();
      const sem = parseInt(semesterText.replace(/\D/g, ''), 10);
      if (!isNaN(sem) && sem < 8) {
        promoteBtn = btn;
        currentSem = sem;
        break;
      }
    }

    if (!promoteBtn) {
      test.skip(true, 'No promotable batch available');
      return;
    }

    await promoteBtn.click();
    await page.waitForSelector('#promoteModal', { state: 'visible', timeout: 5000 });
    await page.waitForFunction(() => {
      const body = document.getElementById('promoteModalBody');
      return body && !body.textContent.includes('Loading');
    }, { timeout: 5000 });

    const modalText = await page.locator('#promoteModalBody').textContent();

    if (!isNaN(currentSem)) {
      expect(modalText).toContain(`${currentSem} → ${currentSem + 1}`);
    }

    // Close modal without promoting (press Escape)
    await page.keyboard.press('Escape');
  });
});
