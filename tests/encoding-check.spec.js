const { test, expect } = require('@playwright/test');

test('Reports page - verify no mojibake after fix', async ({ page }) => {
  // Login as admin
  await page.goto('http://localhost:8000/login.php');
  await page.selectOption('select[name="role"]', 'admin');
  await page.waitForTimeout(500);
  await page.fill('input[name="email"], input[type="email"]', 'admin@testplatform.com');
  await page.fill('input[name="password"], input[type="password"]', 'admin123');
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForLoadState('networkidle');

  // Go to reports page
  await page.goto('http://localhost:8000/admin/reports.php?test_id=3&get_data=1');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'tests/screenshots/encoding-check-reports.png', fullPage: true });

  // Get full page text
  const fullText = await page.textContent('body');
  
  // Check for mojibake patterns
  const mojibakePatterns = ['â€', 'Ã—', 'Ã', 'Â', 'â€œ', 'â€', 'â€"', 'â€"'];
  let foundMojibake = [];
  for (const pattern of mojibakePatterns) {
    if (fullText.includes(pattern)) {
      foundMojibake.push(pattern);
    }
  }

  console.log('=== Mojibake Check ===');
  console.log('Found mojibake patterns:', foundMojibake.length > 0 ? foundMojibake : 'NONE');

  // Check specific expected text
  const expectedTexts = [
    'Select Test',
    'Generate PCI Scores',
    'Get Data',
    'Generate PCI'
  ];
  
  for (const expected of expectedTexts) {
    const found = fullText.includes(expected);
    console.log(`"${expected}": ${found ? 'FOUND' : 'MISSING'}`);
  }

  // Check PCI formula text
  const pciText = fullText.includes('MCQ') && fullText.includes('40%');
  console.log(`PCI formula text: ${pciText ? 'FOUND' : 'MISSING'}`);

  // Check the "Student Marks" table
  const marksTable = fullText.includes('Student Marks');
  console.log(`Marks table: ${marksTable ? 'FOUND' : 'MISSING'}`);

  // Assert no mojibake
  expect(foundMojibake).toHaveLength(0);
});

test('Login page - verify no mojibake', async ({ page }) => {
  await page.goto('http://localhost:8000/login.php');
  await page.waitForLoadState('networkidle');
  
  const fullText = await page.textContent('body');
  const mojibakePatterns = ['â€', 'Ã—', 'Ã', 'Â', 'â€œ', 'â€', 'â€"'];
  let foundMojibake = [];
  for (const pattern of mojibakePatterns) {
    if (fullText.includes(pattern)) {
      foundMojibake.push(pattern);
    }
  }
  console.log('Login page mojibake:', foundMojibake.length > 0 ? foundMojibake : 'NONE');
  expect(foundMojibake).toHaveLength(0);
});

test('Student dashboard - verify no mojibake', async ({ page }) => {
  // First need student login - just check the page renders
  await page.goto('http://localhost:8000/login.php');
  await page.waitForLoadState('networkidle');
  
  const fullText = await page.textContent('body');
  const mojibakePatterns = ['â€', 'Ã—', 'Ã', 'Â'];
  let foundMojibake = [];
  for (const pattern of mojibakePatterns) {
    if (fullText.includes(pattern)) {
      foundMojibake.push(pattern);
    }
  }
  console.log('Login page (student) mojibake:', foundMojibake.length > 0 ? foundMojibake : 'NONE');
  expect(foundMojibake).toHaveLength(0);
});
