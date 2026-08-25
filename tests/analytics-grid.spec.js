/**
 * Playwright tests for admin.css analytics-grid changes.
 *
 * Tests the CSS modifications applied to admin.css:
 *   1. Reduced gap: var(--space-4) → var(--space-3)
 *   2. Tighter padding on .analytics-card-header and .analytics-card-body
 *   3. Shorter chart wrapper: 220px → 180px
 *   4. Responsive: 2-column layout maintained down to 1100px (was 1400px)
 *
 * Uses a standalone HTML fixture with admin.css loaded so no PHP/DB required.
 */

const { test, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const FIXTURE_PATH = path.join(__dirname, 'fixtures', 'analytics-grid-test.html');
const CSS_PATH = path.join(__dirname, '..', 'assets', 'css', 'admin.css');

// ─── Helper: read computed style ───────────────────────────────────────────
async function getStyle(page, selector, prop) {
  return page.evaluate(({ selector, prop }) => {
    const el = document.querySelector(selector);
    if (!el) return null;
    return getComputedStyle(el)[prop];
  }, { selector, prop });
}

// ─── Helper: read CSS custom property value ───────────────────────────────
async function getCssVar(page, varName) {
  return page.evaluate((varName) => {
    return getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
  }, varName);
}

// ─── Helper: get grid template columns ────────────────────────────────────
async function getGridColumns(page, selector) {
  return page.evaluate((selector) => {
    const el = document.querySelector(selector);
    if (!el) return null;
    return getComputedStyle(el).gridTemplateColumns;
  }, selector);
}

// ─── Helper: check grid item count per row at a given viewport ────────────
async function getFirstRowItemCount(page) {
  return page.evaluate(() => {
    const grid = document.querySelector('.analytics-grid');
    if (!grid) return 0;
    const items = grid.querySelectorAll('.analytics-card:not(.full-width)');
    if (items.length === 0) return 0;
    const firstRect = items[0].getBoundingClientRect();
    const firstTop = firstRect.top;
    let count = 0;
    for (const item of items) {
      if (Math.abs(item.getBoundingClientRect().top - firstTop) < 2) count++;
      else break;
    }
    return count;
  });
}

test.describe('Analytics Grid — admin.css changes', () => {

  test.beforeEach(async ({ page }) => {
    // Read the HTML fixture and inline the CSS (avoids relative path issues with setContent)
    let html = fs.readFileSync(FIXTURE_PATH, 'utf-8');
    const css = fs.readFileSync(CSS_PATH, 'utf-8');

    // Replace the external <link> with an inline <style>
    html = html.replace(
      '<link rel="stylesheet" href="../../assets/css/admin.css">',
      `<style>${css}</style>`
    );

    await page.setContent(html, { waitUntil: 'networkidle' });
  });

  // ─── 1. Gap ──────────────────────────────────────────────────────────────
  test('gap between cards is var(--space-5) (20px)', async ({ page }) => {
    const gap = await getStyle(page, '.analytics-grid', 'gap');
    expect(gap).toBe('20px');
  });

  // ─── 2. Header padding ──────────────────────────────────────────────────
  test('analytics-card-header has enterprise padding', async ({ page }) => {
    const padding = await getStyle(page, '.analytics-card-header', 'padding');
    // Enterprise redesign: 20px 32px 0 (top horizontal bottom)
    expect(padding).toBe('20px 32px 0px');
  });

  // ─── 3. Body padding ─────────────────────────────────────────────────────
  test('analytics-card-body has enterprise padding', async ({ page }) => {
    const padding = await getStyle(page, '.analytics-card-body', 'padding');
    // Enterprise redesign: 16px 32px 32px
    expect(padding).toBe('16px 32px 32px');
  });

  // ─── 4. Chart wrapper height ────────────────────────────────────────────
  test('analytics-chart-wrapper height is 180px (inline style)', async ({ page }) => {
    const height = await getStyle(page, '.analytics-chart-wrapper', 'height');
    // Full-width card has inline style="height:180px"
    const numHeight = parseFloat(height);
    expect(numHeight).toBeGreaterThanOrEqual(178);
    expect(numHeight).toBeLessThanOrEqual(182);
  });

  // ─── 5. Ring container exists ──────────────────────────────────────────
  test('segmented ring container exists with correct layout', async ({ page }) => {
    const ringFlexDisplay = await getStyle(page, '.ring-container', 'display');
    expect(ringFlexDisplay).toBe('flex');
    const ringSvgDisplay = await getStyle(page, '.ring-svg', 'display');
    expect(ringSvgDisplay).toBe('block');
  });

  // ─── 5. Two columns above 1100px ────────────────────────────────────────
  test('displays 2 columns at viewport width 1200px', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 900 });
    await page.waitForTimeout(100);

    const cols = await getGridColumns(page, '.analytics-grid');
    const count = await getFirstRowItemCount(page);

    // Browser resolves 1fr to pixel values — check for 2 space-separated tracks
    const trackCount = cols.trim().split(/\s+/).length;
    expect(trackCount).toBe(2);
    expect(count).toBe(2);
  });

  // ─── 6. Single column below 1100px (edge: 1099px) ───────────────────────
  test('collapses to 1 column at viewport width 1099px', async ({ page }) => {
    await page.setViewportSize({ width: 1099, height: 900 });
    await page.waitForTimeout(100);

    const cols = await getGridColumns(page, '.analytics-grid');
    const count = await getFirstRowItemCount(page);

    // Single track = 1 column
    const trackCount = cols.trim().split(/\s+/).length;
    expect(trackCount).toBe(1);
    expect(count).toBe(1);
  });

  // ─── 7. Single column at 768px (existing breakpoint) ────────────────────
  test('collapses to 1 column at viewport width 768px', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.waitForTimeout(100);

    const cols = await getGridColumns(page, '.analytics-grid');
    const trackCount = cols.trim().split(/\s+/).length;
    expect(trackCount).toBe(1);
  });

  // ─── 8. CSS custom properties exist ─────────────────────────────────────
  test('design tokens are properly defined', async ({ page }) => {
    const space3 = await getCssVar(page, '--space-3');
    const space4 = await getCssVar(page, '--space-4');

    expect(space3).toBe('12px');
    expect(space4).toBe('16px');
  });

  // ─── 9. Full-width card spans entire grid ───────────────────────────────
  test('.full-width card spans all columns', async ({ page }) => {
    const gridCol = await getStyle(page, '.analytics-card.full-width', 'gridColumn');
    expect(gridCol).toBe('1 / -1');
  });
});
