/**
 * Live demo: create "BGS Institute of Management (BIM)" college via the admin wizard.
 * Ref: https://bim.edu.in/ — required info only.
 * Courses: BCom, BBA, BCA · One ACTIVE batch for BCA.
 *
 * Run:  node tests/create-bim-college.mjs
 * Headed + slowMo(2000ms) so every click is visible live.
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'fs';

const BASE = 'http://localhost:8000';
const SHOT_DIR = '.gstack/qa-reports/bim-wizard';
const DELAY = 2000;
mkdirSync(SHOT_DIR, { recursive: true });

const COLLEGE = {
  name: 'BGS Institute of Management',
  nick_name: 'BIM',
  established_year: '2020',
  website: 'https://bim.edu.in/',
  email: 'info@bim.edu.in',
  phone: '7090279207',
  address: 'Pipeline Rd, Nagapura, Mahalakshmipuram',
  city: 'Bengaluru',
  state: 'Karnataka',
  pincode: '560086',
};

const STREAMS = [
  { short: '(BCom)', full: 'Bachelor of Commerce (BCom)' },
  { short: '(BBA)', full: 'Bachelor of Business Administration (BBA)' },
  { short: '(BCA)', full: 'Bachelor of Computer Applications (BCA)' },
];

const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

const browser = await chromium.launch({ headless: false, slowMo: DELAY });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

try {
  // ── Admin login ──────────────────────────────────────────
  log('Opening login page…');
  await page.goto(`${BASE}/src/php/public/login.php`);
  await page.screenshot({ path: `${SHOT_DIR}/0-login.png` });

  await page.selectOption('#role', 'admin');
  await page.fill('#email', 'admin@testplatform.com');
  await page.fill('#password', 'admin123');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  log('Logged in as admin →', page.url());

  // ── Wizard Step 1: College details ───────────────────────
  log('Opening College Create wizard…');
  await page.goto(`${BASE}/src/php/public/admin/college_create.php`);
  await page.waitForSelector('#name');
  await page.screenshot({ path: `${SHOT_DIR}/1-step1-empty.png` });

  await page.fill('#name', COLLEGE.name);
  await page.fill('#nick_name', COLLEGE.nick_name);
  await page.fill('#established_year', COLLEGE.established_year);
  await page.fill('#website', COLLEGE.website);
  await page.fill('#email', COLLEGE.email);
  await page.fill('#phone', COLLEGE.phone);
  await page.fill('#address', COLLEGE.address);
  await page.fill('#city', COLLEGE.city);
  await page.fill('#state', COLLEGE.state);
  await page.fill('#pincode', COLLEGE.pincode);
  await page.screenshot({ path: `${SHOT_DIR}/1-step1-filled.png` });
  log('Step 1 filled — clicking Next');

  await page.click('.ws-actions button.btn-primary'); // Next →
  await page.waitForSelector('.ws-step-2'); // step container (checkbox inputs are visually hidden)
  log('Step 2 loaded (Streams picker)');

  // ── Wizard Step 2: University + accreditation + streams ──
  await page.fill('#affiliated_university', 'Bangalore University');
  await page.locator('input[name="accreditation_ugc"]').check();
  log('Affiliated: Bangalore University · UGC Recognized ✓');

  for (const s of STREAMS) {
    // Target the pill by its SHORT FORM in parentheses, e.g. "(BCom)"
    await page.locator(`label.degree-pill:has-text("${s.short}")`).click(); // input is visually hidden; the pill is the click target
    log('Checked stream:', s.full);
  }
  await page.screenshot({ path: `${SHOT_DIR}/2-step2-streams.png` });
  log('Step 2 done — clicking Next');

  await page.click('.ws-actions button.btn-primary'); // Next →
  await page.waitForSelector('.batch-row');
  log('Step 3 loaded (Batch creation)');

  // ── Wizard Step 3: Add ONE active batch under BCA ────────
  const row = page.locator('.batch-row').first();
  // Streams render in selection order: 1=BCom, 2=BBA, 3=BCA
  await row.locator('select[name="batch_stream_id[]"]').selectOption({ index: 3 });
  await row.locator('select[name="batch_joining_year[]"]').selectOption('2026');
  await row.locator('select[name="batch_joining_month[]"]').selectOption('8');   // Aug
  await row.locator('select[name="batch_duration[]"]').selectOption('3');        // 3 yrs (BCA)
  // Status defaults to Active — leave as-is
  await page.screenshot({ path: `${SHOT_DIR}/3-step3-batch.png` });
  log('Batch added: BCA · 2026 Aug · 3 yrs · Active');

  await page.click('.ws-actions button.btn-primary'); // Next →
  await page.waitForSelector('button.btn-success');
  await page.screenshot({ path: `${SHOT_DIR}/4-step4-review.png`, fullPage: true });
  log('Step 4 review loaded');

  // ── Wizard Step 4: Create ────────────────────────────────
  await page.click('.ws-actions button.btn-success'); // Create College
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: `${SHOT_DIR}/5-final.png`, fullPage: true });
  log('Create clicked → landed on:', page.url());

  log('DONE ✅ — browser stays open for 15s so you can inspect');
  await page.waitForTimeout(15000);
} catch (err) {
  await page.screenshot({ path: `${SHOT_DIR}/error.png`, fullPage: true }).catch(() => {});
  console.error('FAILED ❌:', err.message);
  console.error('URL at failure:', page.url());
  process.exitCode = 1;
  await page.waitForTimeout(10000); // keep window open to see the failure
} finally {
  await browser.close();
}
