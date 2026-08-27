const { test, expect } = require('@playwright/test');

test('Login page - Enterprise uses Poppins font, not Tangerine', async ({ page }) => {
  await page.goto('http://localhost:8000/login.php');
  await page.waitForLoadState('networkidle');
  
  // Check the Enterprise text
  const enterpriseEl = page.locator('.auth-top-brand-enterprise');
  const fontFamily = await enterpriseEl.evaluate(el => getComputedStyle(el).fontFamily);
  console.log('Enterprise font-family:', fontFamily);
  
  // Should contain Poppins, should NOT contain Tangerine
  expect(fontFamily.toLowerCase()).toContain('poppins');
  expect(fontFamily.toLowerCase()).not.toContain('tangerine');
  
  // Verify the text content
  const text = await enterpriseEl.textContent();
  console.log('Enterprise text:', text.trim());
  expect(text.trim()).toBe('Enterprise');
});

test('Login page - Tangerine font not loaded', async ({ page }) => {
  await page.goto('http://localhost:8000/login.php');
  await page.waitForLoadState('networkidle');
  
  // Check that the Google Fonts link does NOT include Tangerine
  const fontLinks = await page.locator('link[href*="fonts.googleapis.com"]').all();
  for (const link of fontLinks) {
    const href = await link.getAttribute('href');
    console.log('Font link:', href);
    if (href) {
      expect(href.toLowerCase()).not.toContain('tangerine');
    }
  }
});

test('Signup -> OTP: OTP must NOT appear in URL', async ({ page }) => {
  // Go to signup page
  await page.goto('http://localhost:8000/signup.php');
  await page.waitForLoadState('networkidle');
  
  // Fill the form with unique data
  const uniqueEmail = `test_${Date.now()}@example.com`;
  
  // Select college (if dropdown exists)
  const collegeSelect = page.locator('select[name="college_id"]');
  const collegeCount = await collegeSelect.locator('option').count();
  if (collegeCount > 1) {
    const secondOption = await collegeSelect.locator('option').nth(1).getAttribute('value');
    if (secondOption) await collegeSelect.selectOption(secondOption);
  }
  
  // Fill form fields
  const nameInput = page.locator('input[name="name"]');
  if (await nameInput.isVisible()) await nameInput.fill('Test Security User');
  
  const emailInput = page.locator('input[name="email"]');
  if (await emailInput.isVisible()) await emailInput.fill(uniqueEmail);
  
  const phoneInput = page.locator('input[name="phone"]');
  if (await phoneInput.isVisible()) await phoneInput.fill('9876543210');
  
  const rollInput = page.locator('input[name="roll_number"]');
  if (await rollInput.isVisible()) await rollInput.fill('TEST001');
  
  const yearSelect = page.locator('select[name="year_of_joining"]');
  if (await yearSelect.isVisible()) {
    const yearOptions = await yearSelect.locator('option').all();
    if (yearOptions.length > 1) {
      const yearVal = await yearOptions[1].getAttribute('value');
      if (yearVal) await yearSelect.selectOption(yearVal);
    }
  }
  
  const passwordInput = page.locator('input[name="password"]');
  if (await passwordInput.isVisible()) await passwordInput.fill('TestPass123!');
  
  const confirmInput = page.locator('input[name="password_confirm"]');
  if (await confirmInput.isVisible()) await confirmInput.fill('TestPass123!');
  
  // Submit form
  const submitBtn = page.locator('button[type="submit"]');
  if (await submitBtn.isVisible()) {
    await submitBtn.click();
    await page.waitForLoadState('networkidle');
  }
  
  // Check current URL
  const currentUrl = page.url();
  console.log('After signup URL:', currentUrl);
  
  // CRITICAL: OTP must NOT be in the URL
  expect(currentUrl).not.toContain('otp_dev=');
  expect(currentUrl).not.toContain('otp=');
  
  // Should redirect to verify-otp.php with only student_id and email
  if (currentUrl.includes('verify-otp.php')) {
    const url = new URL(currentUrl);
    const params = Array.from(url.searchParams.keys());
    console.log('URL params:', params);
    
    // Should only have student_id and email, NOT otp_dev
    expect(params).not.toContain('otp_dev');
    expect(params).toContain('student_id');
    expect(params).toContain('email');
  }
  
  await page.screenshot({ path: 'tests/screenshots/otp-security-check.png', fullPage: true });
});
