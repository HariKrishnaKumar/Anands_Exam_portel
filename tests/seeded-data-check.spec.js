const { test, expect } = require('@playwright/test');

test('Reports page - verify seeded data displays correctly', async ({ page }) => {
  // Login as admin
  await page.goto('http://localhost:8000/login.php');
  await page.selectOption('select[name="role"]', 'admin');
  await page.waitForTimeout(500);
  await page.fill('input[name="email"], input[type="email"]', 'admin@testplatform.com');
  await page.fill('input[name="password"], input[type="password"]', 'admin123');
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForLoadState('networkidle');

  // Go to reports with Get Data
  await page.goto('http://localhost:8000/admin/reports.php?test_id=3&get_data=1');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'tests/screenshots/reports-seeded-data.png', fullPage: true });

  // Count students in the marks table
  const rows = await page.locator('table.data-table tbody tr').count();
  console.log('Students in marks table:', rows);

  // Verify the table has multiple students
  expect(rows).toBeGreaterThan(1);
  
  // Verify no mojibake
  const bodyText = await page.textContent('body');
  expect(bodyText).not.toContain('â€');
  expect(bodyText).not.toContain('Ã—');
});
