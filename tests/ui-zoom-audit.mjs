/**
 * UI readability audit across screen sizes (15" → 32").
 * Captures the admin dashboard at 4 real-world viewport sizes and reports
 * the computed root/body font sizes, so the fluid-scaling fix can be
 * verified before/after.
 *
 * Run: node tests/ui-zoom-audit.mjs [before|after]
 */
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'fs';

const TAG = process.argv[2] || 'before';
const BASE = 'http://localhost:8000';
const SHOT_DIR = `.gstack/qa-reports/ui-zoom/${TAG}`;
mkdirSync(SHOT_DIR, { recursive: true });

// Real viewport sizes: 15.6" FHD laptop, 24" FHD, 27" QHD, 32" 4K
const SIZES = [
  { label: '15in-1920x1080', width: 1920, height: 1080 },
  { label: '24in-1920x1080', width: 1920, height: 1080 },
  { label: '27in-2560x1440', width: 2560, height: 1440 },
  { label: '32in-3840x2160', width: 3840, height: 2160 },
];

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

await page.goto(`${BASE}/src/php/public/login.php`);
await page.selectOption('#role', 'admin');
await page.fill('#email', 'admin@testplatform.com');
await page.fill('#password', 'admin123');
await page.click('button[type="submit"]');
await page.waitForURL(/dashboard\.php/);

const report = [];
for (const s of SIZES) {
  await page.setViewportSize({ width: s.width, height: s.height });
  await page.goto(`${BASE}/src/php/public/admin/dashboard.php`);
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: `${SHOT_DIR}/${s.label}.png` });

  const metrics = await page.evaluate(() => {
    const gs = (sel, prop) => {
      const el = document.querySelector(sel);
      return el ? getComputedStyle(el)[prop] : 'n/a';
    };
    return {
      rootFont: gs('html', 'fontSize'),
      bodyFont: gs('body', 'fontSize'),
      h1: gs('h1', 'fontSize'),
      tableText: gs('.data-table td', 'fontSize') || gs('td', 'fontSize'),
      btnText: gs('.btn', 'fontSize'),
      navText: gs('.sidebar a, .nav-item', 'fontSize'),
    };
  });
  report.push({ size: s.label, ...metrics });
  console.log(`${s.label.padEnd(18)} root=${metrics.rootFont.padEnd(7)} body=${metrics.bodyFont.padEnd(7)} h1=${metrics.h1.padEnd(9)} table=${metrics.tableText}`);
}

writeFileSync(`${SHOT_DIR}/report.json`, JSON.stringify(report, null, 2));
await browser.close();
console.log(`\nAudit saved → ${SHOT_DIR}`);
