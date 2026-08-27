const { test, expect } = require('@playwright/test');

test('Reports - check Get Data after server restart', async ({ page }) => {
  // Login as admin
  await page.goto('http://localhost:8000/login.php');
  await page.selectOption('select[name="role"]', 'admin');
  await page.waitForTimeout(500);
  await page.fill('input[name="email"], input[type="email"]', 'admin@testplatform.com');
  await page.fill('input[name="password"], input[type="password"]', 'admin123');
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForLoadState('networkidle');
  console.log('Logged in, URL:', page.url());

  // Go to reports with test selected AND get_data=1
  await page.goto('http://localhost:8000/admin/reports.php?test_id=3&get_data=1');
  await page.waitForLoadState('networkidle');

  // Search full HTML for "Get Data"
  const fullHTML = await page.content();
  const getDataCount = (fullHTML.match(/Get Data/g) || []).length;
  console.log(`"Get Data" occurrences in HTML: ${getDataCount}`);
  
  const getDataLinkCount = (fullHTML.match(/get_data/g) || []).length;
  console.log(`"get_data" occurrences in HTML: ${getDataLinkCount}`);

  if (getDataCount > 0) {
    const idx = fullHTML.indexOf('Get Data');
    console.log('Context:', fullHTML.substring(Math.max(0, idx - 150), idx + 200));
  }

  // Take screenshot
  await page.screenshot({ path: 'tests/screenshots/reports-after-restart.png', fullPage: true });

  // Check if the marks table appeared
  const tableVisible = await page.locator('table.data-table >> text=Student Name').isVisible().catch(() => false);
  console.log(`Marks table visible: ${tableVisible}`);
});
