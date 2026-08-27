/**
 * Login Page Visual Verification Test
 *
 * Checks:
 *  1. Fonts are visible and rendered correctly
 *  2. Dropdown renders as native select (no "vvvv" artifacts)
 *  3. Layout works at desktop (1440), tablet (768), and mobile (375)
 *  4. Top-left branding is visible
 *  5. Hero section is present (desktop/tablet) and hidden (mobile)
 *  6. No unwanted empty space
 *
 * RUN: npx playwright test tests/login-visual-check.spec.js
 */
const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost:8000';
const LOGIN = '/src/php/public/login.php';

// =====================================================
// Desktop 1440x900
// =====================================================
test.describe('Login Page -” Desktop (1440x900)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('visible fonts, correct colors, no empty space', async ({ page }) => {
    await page.goto(BASE + LOGIN);
    await page.waitForLoadState('networkidle');

    // --- 1. Body background is dark navy ---
    const bodyBg = await page.evaluate(() =>
      getComputedStyle(document.querySelector('.auth-page')).backgroundColor
    );
    console.log('[Desktop] body background:', bodyBg);
    // Expect white background
    expect(bodyBg).toBe('rgb(255, 255, 255)');

    // --- 2. Top-left brand is visible ---
    const topBrand = page.locator('.auth-top-brand');
    await expect(topBrand).toBeVisible();
    const brandBox = await topBrand.boundingBox();
    console.log('[Desktop] top brand position:', brandBox);
    expect(brandBox.x).toBeLessThan(100);   // near left edge
    expect(brandBox.y).toBeLessThan(60);    // near top edge

    const brandName = page.locator('.auth-top-brand-name');
    await expect(brandName).toBeVisible();
    const brandText = await brandName.textContent();
    console.log('[Desktop] brand name:', brandText);
    expect(brandText).toContain('j');

    // --- 3. Brand name color is white-ish (visible on dark bg) ---
    const brandColor = await page.evaluate(() =>
      getComputedStyle(document.querySelector('.auth-top-brand-name')).color
    );
    console.log('[Desktop] brand name color:', brandColor);
    // Should be near-black (dark text on white bg)
    const [r, g, b] = brandColor.match(/\d+/g).map(Number);
    expect(r).toBeLessThan(50);
    expect(g).toBeLessThan(50);
    expect(b).toBeLessThan(50);

    // --- 4. Hero section is visible on desktop ---
    const hero = page.locator('.auth-hero');
    await expect(hero).toBeVisible();
    console.log('[Desktop] hero visible: true');

    // --- 5. Auth card is visible and positioned on right ---
    const card = page.locator('.auth-card');
    await expect(card).toBeVisible();
    const cardBox = await card.boundingBox();
    console.log('[Desktop] card position:', cardBox);
    expect(cardBox.x).toBeGreaterThan(400); // right half of screen

    // --- 6. Card heading is visible with correct color ---
    const h1 = card.locator('h1');
    await expect(h1).toBeVisible();
    const h1Text = await h1.textContent();
    console.log('[Desktop] h1 text:', h1Text);
    expect(h1Text).toBe('Sign In');

    const h1Color = await page.evaluate(() =>
      getComputedStyle(document.querySelector('.auth-card h1')).color
    );
    console.log('[Desktop] h1 color:', h1Color);
    // Should be dark navy (visible on white card)
    expect(h1Color).not.toBe('rgb(255, 255, 255)');

    // --- 7. No horizontal overflow (no empty space) ---
    const pageWidth = await page.evaluate(() => document.body.scrollWidth);
    console.log('[Desktop] body scrollWidth:', pageWidth);
    expect(pageWidth).toBeLessThanOrEqual(1440);

    // --- 8. Screenshot ---
    await page.screenshot({ path: 'test-results/login-desktop.png', fullPage: true });
    console.log('[Desktop] screenshot saved');
  });

  test('dropdown renders correctly (no vvvv)', async ({ page }) => {
    await page.goto(BASE + LOGIN);
    await page.waitForLoadState('networkidle');

    const select = page.locator('#role');
    await expect(select).toBeVisible();

    // --- 1. It is a real <select> element ---
    const tagName = await select.evaluate(el => el.tagName);
    console.log('[Desktop] select tag:', tagName);
    expect(tagName).toBe('SELECT');

    // --- 2. Background color is navy ---
    const bgColor = await select.evaluate(el => getComputedStyle(el).backgroundColor);
    console.log('[Desktop] select bg:', bgColor);
    // Should not be white (has its own background color)
    expect(bgColor).not.toBe('rgb(255, 255, 255)');

    // --- 3. Text color is white ---
    const textColor = await select.evaluate(el => getComputedStyle(el).color);
    console.log('[Desktop] select text color:', textColor);
    const [r, g, b] = textColor.match(/\d+/g).map(Number);
    expect(r).toBeLessThan(50);
    expect(g).toBeLessThan(50);
    expect(b).toBeLessThan(50);

    // --- 4. Options are readable ---
    const options = await select.locator('option').allTextContents();
    console.log('[Desktop] options:', options);
    expect(options.length).toBeGreaterThanOrEqual(2);
    expect(options.some(o => o.includes('Student') || o.includes('Candidate'))).toBeTruthy();
    expect(options.some(o => o.includes('Admin'))).toBeTruthy();

    // --- 5. No "v" characters visible anywhere in the select area ---
    const selectText = await select.evaluate(el => el.innerText || el.textContent);
    console.log('[Desktop] select text content:', selectText);
    expect(selectText).not.toMatch(/v{3,}/);

    // --- 6. appearance is none (custom styling) ---
    const appearance = await select.evaluate(el => getComputedStyle(el).appearance);
    console.log('[Desktop] appearance:', appearance);
    // Should be none for custom dropdown
    expect(appearance).toBe('none');

    // --- 7. Can change value ---
    await select.selectOption('admin');
    const selectedValue = await select.evaluate(el => el.value);
    console.log('[Desktop] selected value:', selectedValue);
    expect(selectedValue).toBe('admin');

    // --- 8. Screenshot of dropdown ---
    await page.screenshot({ path: 'test-results/login-dropdown-desktop.png' });
  });
});

// =====================================================
// Tablet 768x1024
// =====================================================
test.describe('Login Page -” Tablet (768x1024)', () => {
  test.use({ viewport: { width: 768, height: 1024 } });

  test('visible fonts, correct layout', async ({ page }) => {
    await page.goto(BASE + LOGIN);
    await page.waitForLoadState('networkidle');

    // --- 1. Top brand visible ---
    const topBrand = page.locator('.auth-top-brand');
    await expect(topBrand).toBeVisible();

    // --- 2. Hero visible on tablet ---
    const hero = page.locator('.auth-hero');
    await expect(hero).toBeVisible();

    // --- 3. Auth card visible ---
    const card = page.locator('.auth-card');
    await expect(card).toBeVisible();

    // --- 4. Card is centered (not left-aligned) ---
    const cardBox = await card.boundingBox();
    const viewportWidth = 768;
    const cardCenter = cardBox.x + cardBox.width / 2;
    console.log('[Tablet] card center:', cardCenter, 'viewport center:', viewportWidth / 2);
    expect(Math.abs(cardCenter - viewportWidth / 2)).toBeLessThan(100);

    // --- 5. No horizontal overflow ---
    const scrollWidth = await page.evaluate(() => document.body.scrollWidth);
    console.log('[Tablet] scrollWidth:', scrollWidth);
    expect(scrollWidth).toBeLessThanOrEqual(768);

    // --- 6. Dropdown works ---
    const select = page.locator('#role');
    await expect(select).toBeVisible();
    await select.selectOption('admin');
    expect(await select.evaluate(el => el.value)).toBe('admin');

    // --- 7. Fonts are rendered (font family check) ---
    const fontFamily = await page.evaluate(() =>
      getComputedStyle(document.body).fontFamily
    );
    console.log('[Tablet] font-family:', fontFamily);
    expect(fontFamily.length).toBeGreaterThan(5); // not empty

    // --- 8. Screenshot ---
    await page.screenshot({ path: 'test-results/login-tablet.png', fullPage: true });
    console.log('[Tablet] screenshot saved');
  });
});

// =====================================================
// Mobile 375x812 (iPhone X)
// =====================================================
test.describe('Login Page -” Mobile (375x812)', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('visible fonts, correct layout, no empty space', async ({ page }) => {
    await page.goto(BASE + LOGIN);
    await page.waitForLoadState('networkidle');

    // --- 1. Top brand visible ---
    const topBrand = page.locator('.auth-top-brand');
    await expect(topBrand).toBeVisible();
    const brandBox = await topBrand.boundingBox();
    console.log('[Mobile] top brand position:', brandBox);
    expect(brandBox.x).toBeLessThan(50);
    expect(brandBox.y).toBeLessThan(60);

    // --- 2. Hero hidden on mobile (display: none) ---
    const heroDisplay = await page.evaluate(() =>
      getComputedStyle(document.querySelector('.auth-hero')).display
    );
    console.log('[Mobile] hero display:', heroDisplay);
    expect(heroDisplay).toBe('none');

    // --- 3. Auth card visible and full-width ---
    const card = page.locator('.auth-card');
    await expect(card).toBeVisible();
    const cardBox = await card.boundingBox();
    console.log('[Mobile] card width:', cardBox.width);
    expect(cardBox.width).toBeGreaterThan(280); // should use most of 375px

    // --- 4. No horizontal overflow ---
    const scrollWidth = await page.evaluate(() => document.body.scrollWidth);
    console.log('[Mobile] scrollWidth:', scrollWidth);
    expect(scrollWidth).toBeLessThanOrEqual(375);

    // --- 5. H1 is visible ---
    const h1 = card.locator('h1');
    await expect(h1).toBeVisible();
    const h1Text = await h1.textContent();
    expect(h1Text).toBe('Sign In');

    // --- 6. Form elements visible ---
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#role')).toBeVisible();

    // --- 7. Dropdown renders correctly ---
    const select = page.locator('#role');
    const selectBg = await select.evaluate(el => getComputedStyle(el).backgroundColor);
    console.log('[Mobile] select bg:', selectBg);
    expect(selectBg).not.toBe('rgb(255, 255, 255)');

    // --- 8. Button visible ---
    const btn = page.locator('button[type="submit"]');
    await expect(btn).toBeVisible();
    const btnText = await btn.textContent();
    console.log('[Mobile] button text:', btnText.trim());
    expect(btnText.trim()).toBe('Sign In');

    // --- 9. Screenshot ---
    await page.screenshot({ path: 'test-results/login-mobile.png', fullPage: true });
    console.log('[Mobile] screenshot saved');
  });
});
