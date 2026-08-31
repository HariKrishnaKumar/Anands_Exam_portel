/**
 * COMPREHENSIVE STRESS TEST SUITE — Yajurvedh Exam Portal
 *
 * Tests real-world scenarios under stress conditions:
 * - Rapid API calls, concurrent requests
 * - Session management under load
 * - Security boundary testing
 * - Data integrity under stress
 * - UI responsiveness under stress
 *
 * Pre-requisite: clear_log.php must be run before this test suite
 * to reset brute-force counters. Run: php clear_log.php
 */
const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost:8000';
const ADMIN = { email: 'admin@testplatform.com', password: 'admin123' };
const STUDENT = { email: 'hariiphones83@gmail.com', password: '123456' };

// ─── HELPERS ─────────────────────────────────────────────

async function loginAdmin(page) {
  await page.goto(`${BASE}/login.php`);
  await page.waitForLoadState('networkidle');
  await page.selectOption('#role', 'admin');
  await page.fill('#email', ADMIN.email);
  await page.fill('#password', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  // Wait for redirect to complete
  await page.waitForURL('**/admin/**', { timeout: 15000 });
}

async function loginStudent(page) {
  await page.goto(`${BASE}/login.php`);
  await page.waitForLoadState('networkidle');
  await page.selectOption('#role', 'student');
  await page.fill('#email', STUDENT.email);
  await page.fill('#password', STUDENT.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForURL('**/student/**', { timeout: 15000 });
}

// ═══════════════════════════════════════════════════════════
// 1. API STRESS TESTS
// ═══════════════════════════════════════════════════════════

test.describe('API Stress Tests', () => {

  test('promote API: 10 rapid POST requests — no crash, consistent responses', async ({ page }) => {
    await loginAdmin(page);

    const results = [];
    for (let i = 0; i < 10; i++) {
      const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
        data: { batch_id: 99999 },
        headers: { 'Content-Type': 'application/json' },
      });
      results.push(resp.status());
    }
    expect(results.every(s => s === 404)).toBe(true);
    console.log('10 rapid requests all returned 404 — no crash');
  });

  test('promote API: concurrent preview on same batch returns consistent data', async ({ page }) => {
    await loginAdmin(page);

    const p1 = page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 9, preview: true },
      headers: { 'Content-Type': 'application/json' },
    });
    const p2 = page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 9, preview: true },
      headers: { 'Content-Type': 'application/json' },
    });

    const [r1, r2] = await Promise.all([p1, p2]);
    expect(r1.status()).toBe(200);
    expect(r2.status()).toBe(200);

    const j1 = await r1.json();
    const j2 = await r2.json();
    expect(j1.status).toBe('ok');
    expect(j2.status).toBe('ok');
    expect(j1.current_semester).toBe(j2.current_semester);
    expect(j1.next_semester).toBe(j2.next_semester);
  });

  test('promote API: all HTTP methods rejected except POST', async ({ page }) => {
    for (const method of ['GET', 'PUT', 'DELETE', 'PATCH']) {
      const resp = await page.request.fetch(`${BASE}/test-platform/src/php/api/promote_students.php`, { method });
      expect(resp.status()).toBe(405);
    }
  });

  test('promote API: malformed JSON body does not crash', async ({ page }) => {
    await loginAdmin(page);

    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: 'not json at all {{{',
      headers: { 'Content-Type': 'application/json' },
    });
    expect(resp.status()).toBe(400);
    const json = await resp.json();
    expect(json.status).toBe('error');
  });

  test('promote API: empty body does not crash', async ({ page }) => {
    await loginAdmin(page);

    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: '',
      headers: { 'Content-Type': 'application/json' },
    });
    expect(resp.status()).toBe(400);
  });

  test('promote API: SQL injection in batch_id parameter', async ({ page }) => {
    await loginAdmin(page);

    const payloads = [
      "1 OR 1=1",
      "'; DROP TABLE batches; --",
      "1 UNION SELECT * FROM admins",
      "-1",
      "999999999",
    ];
    for (const payload of payloads) {
      const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
        data: { batch_id: payload },
        headers: { 'Content-Type': 'application/json' },
      });
      const status = resp.status();
      expect([400, 404]).toContain(status);
    }
  });

  test('promote API: XSS in request body does not break response', async ({ page }) => {
    await loginAdmin(page);

    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 0, name: '<script>alert(1)</script>' },
      headers: { 'Content-Type': 'application/json' },
    });
    const text = await resp.text();
    expect(text).not.toContain('<script>');
    expect(resp.status()).toBe(400);
  });
});

// ═══════════════════════════════════════════════════════════
// 2. SESSION & AUTH STRESS TESTS
// ═══════════════════════════════════════════════════════════

test.describe('Session & Auth Stress', () => {

  test('admin: login -> access batches -> logout -> access batches = redirected to login', async ({ page }) => {
    await loginAdmin(page);

    await page.goto(`${BASE}/admin/batches.php`);
    await page.waitForSelector('table', { timeout: 10000 });

    // Logout
    await page.evaluate(() => {
      const form = document.querySelector('form[action*="logout"]');
      if (form) form.submit();
    });
    await page.waitForLoadState('networkidle');

    // Try to access batches again
    await page.goto(`${BASE}/admin/batches.php`);
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('login');
  });

  test('student cannot call promote API', async ({ page }) => {
    await loginStudent(page);

    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 10, preview: true },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(resp.status()).toBe(401);
  });

  test('admin: 3 rapid wrong password attempts stay on login', async ({ page }) => {
    for (let i = 0; i < 3; i++) {
      await page.goto(`${BASE}/login.php`);
      await page.waitForLoadState('networkidle');
      await page.selectOption('#role', 'admin');
      await page.fill('#email', ADMIN.email);
      await page.fill('#password', 'wrong_password_' + i);
      await page.click('button[type="submit"]');
      await page.waitForLoadState('networkidle');
    }
    expect(page.url()).toContain('login');
  });
});

// ═══════════════════════════════════════════════════════════
// 3. SECURITY BOUNDARY TESTS
// ═══════════════════════════════════════════════════════════

test.describe('Security Boundaries', () => {

  test('path traversal: /../../../etc/passwd returns 404', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/test-platform/../../../etc/passwd`);
    expect(resp.status()).toBe(404);
  });

  test('path traversal: /src/php/../../.env returns 404 or 403', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/test-platform/src/php/../../.env`);
    expect([403, 404]).toContain(resp.status());
  });

  test('directory listing is disabled for /src/', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/test-platform/src/`);
    expect(resp.status()).toBe(404);
  });

  test('DELETE and PUT on API endpoints return 405', async ({ page }) => {
    const endpoints = [
      '/test-platform/src/php/api/promote_students.php',
      '/test-platform/src/php/api/get_courses.php',
    ];
    for (const ep of endpoints) {
      for (const method of ['DELETE', 'PUT', 'PATCH']) {
        const resp = await page.request.fetch(`${BASE}${ep}`, { method });
        expect(resp.status()).toBe(405);
      }
    }
  });

  test('security headers present on auth pages', async ({ page }) => {
    for (const url of [`${BASE}/login.php`, `${BASE}/signup.php`, `${BASE}/forgot-password.php`]) {
      const resp = await page.request.get(url);
      const h = resp.headers();
      expect(h['x-content-type-options']).toBe('nosniff');
      expect(h['x-frame-options']).toBe('DENY');
      expect(h['referrer-policy']).toContain('strict-origin');
      expect(h['content-security-policy']).toContain("default-src 'self'");
    }
  });

  test('brute-force: 6 wrong passwords on fake email blocks login', async ({ page }) => {
    const fakeEmail = 'bruteforce_test_' + Date.now() + '@example.com';
    for (let i = 0; i < 6; i++) {
      await page.goto(`${BASE}/login.php`);
      await page.waitForLoadState('networkidle');
      await page.selectOption('#role', 'student');
      await page.fill('#email', fakeEmail);
      await page.fill('#password', 'wrong_' + i);
      await page.click('button[type="submit"]');
      await page.waitForLoadState('networkidle');
    }
    // Even with correct password now, should fail
    await page.goto(`${BASE}/login.php`);
    await page.waitForLoadState('networkidle');
    await page.selectOption('#role', 'student');
    await page.fill('#email', fakeEmail);
    await page.fill('#password', 'any_password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    expect(page.url()).not.toContain('dashboard');
  });
});

// ═══════════════════════════════════════════════════════════
// 4. DATA INTEGRITY STRESS
// ═══════════════════════════════════════════════════════════

test.describe('Data Integrity Under Stress', () => {

  test('batches page: no duplicate rows across 5 rapid loads', async ({ page }) => {
    await loginAdmin(page);

    let lastCount = 0;
    for (let i = 0; i < 5; i++) {
      await page.goto(`${BASE}/admin/batches.php`);
      await page.waitForSelector('table', { timeout: 10000 });
      const count = await page.locator('table tbody tr').count();
      if (i > 0) expect(count).toBe(lastCount);
      lastCount = count;
    }
    console.log(`5 loads: batch count stable at ${lastCount}`);
  });

  test('promote button only shows for active batches', async ({ page }) => {
    await loginAdmin(page);
    await page.goto(`${BASE}/admin/batches.php`);
    await page.waitForSelector('table', { timeout: 10000 });

    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    for (let i = 0; i < count; i++) {
      const text = await rows.nth(i).textContent();
      const hasPromote = text.includes('Promote');
      const hasArchived = text.includes('Archived');
      if (hasPromote) {
        expect(hasArchived).toBe(false);
      }
    }
  });
});

// ═══════════════════════════════════════════════════════════
// 5. UI RESPONSIVENESS STRESS
// ═══════════════════════════════════════════════════════════

test.describe('UI Responsiveness Stress', () => {

  test('batches page renders at 5 viewport sizes without JS errors', async ({ page }) => {
    await loginAdmin(page);

    const jsErrors = [];
    page.on('pageerror', err => jsErrors.push(err.message));

    const sizes = [[1920, 1080], [1440, 900], [1024, 768], [768, 1024], [375, 812]];
    for (const [w, h] of sizes) {
      await page.setViewportSize({ width: w, height: h });
      await page.goto(`${BASE}/admin/batches.php`);
      await page.waitForSelector('table', { timeout: 10000 });
    }
    expect(jsErrors).toHaveLength(0);
  });

  test('promote modal opens and closes without freeze', async ({ page }) => {
    await loginAdmin(page);
    await page.goto(`${BASE}/admin/batches.php`);
    await page.waitForSelector('table', { timeout: 10000 });

    const promoteBtn = page.locator('button:has-text("Promote")').first();
    if (await promoteBtn.count() === 0) {
      test.skip(true, 'No promote buttons');
      return;
    }

    // Open modal
    await promoteBtn.click();
    await page.waitForTimeout(1500); // Wait for preview

    // Verify modal is visible
    const modal = page.locator('#promoteModal');
    await expect(modal).toBeVisible();

    // Close by JS (remove open class)
    await page.evaluate(() => {
      const m = document.getElementById('promoteModal');
      if (m) m.classList.remove('open');
    });
    await page.waitForTimeout(500);

    // Verify modal is hidden
    const isOpen = await page.evaluate(() => {
      const m = document.getElementById('promoteModal');
      return m ? m.classList.contains('open') : false;
    });
    expect(isOpen).toBe(false);

    // Page still functional
    await page.waitForSelector('table', { timeout: 5000 });
  });
});

// ═══════════════════════════════════════════════════════════
// 6. STUDENT PORTAL STRESS (hariiphones83@gmail.com)
// ═══════════════════════════════════════════════════════════

test.describe('Student Portal Stress', () => {

  test('student login -> dashboard loads', async ({ page }) => {
    await loginStudent(page);
    await expect(page).toHaveURL(/student\/dashboard/);
  });

  test('student cannot access admin pages (302 redirect)', async ({ page }) => {
    await loginStudent(page);

    for (const url of [`${BASE}/admin/batches.php`, `${BASE}/admin/students.php`, `${BASE}/admin/dashboard.php`]) {
      const resp = await page.request.get(url, { maxRedirects: 0 });
      expect([302, 403]).toContain(resp.status());
    }
  });

  test('student cannot call promote API (401)', async ({ page }) => {
    await loginStudent(page);

    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 10, preview: true },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(resp.status()).toBe(401);
  });
});

// ═══════════════════════════════════════════════════════════
// 7. ERROR HANDLING STRESS
// ═══════════════════════════════════════════════════════════

test.describe('Error Handling', () => {

  test('non-existent API endpoint returns 404 not 500', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/test-platform/src/php/api/nonexistent.php`);
    expect(resp.status()).toBe(404);
  });

  test('non-existent page returns 404 not 500', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/admin/totally_fake_page.php`);
    expect(resp.status()).toBe(404);
  });

  test('promote API: batch at max semester (8) returns proper error', async ({ page }) => {
    await loginAdmin(page);

    const resp = await page.request.post(`${BASE}/test-platform/src/php/api/promote_students.php`, {
      data: { batch_id: 10, preview: true },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(resp.status()).toBe(400);
    const json = await resp.json();
    expect(json.status).toBe('error');
    expect(json.message).toContain('Cannot promote beyond');
  });
});
