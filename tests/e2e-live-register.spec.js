/**
 * Live E2E Test — Full Student Registration Flow
 *
 * 1. Get temp email from temp-mail.org
 * 2. Register on exam portal
 * 3. Get OTP from temp-mail.org (click email → scroll → extract)
 * 4. Verify OTP
 * 5. Login as student
 * 6. Explore dashboard
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://localhost:8000';
const TIMEOUT = 30000;

async function screenshot(page, name) {
  const filePath = `test-results/e2e-live/${name}.png`;
  try {
    await page.screenshot({ path: filePath, fullPage: false, timeout: 10000 });
    console.log(`  📸 Screenshot: ${filePath}`);
  } catch (e) {
    console.log(`  ⚠️ Screenshot failed: ${e.message.split('\n')[0]}`);
  }
}

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

(async () => {
  // Create output dir
  fs.mkdirSync('test-results/e2e-live', { recursive: true });

  const browser = await chromium.launch({ headless: false, args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  // ═══════════════════════════════════════════
  // STEP 1: Get temp email from temp-mail.org
  // ═══════════════════════════════════════════
  console.log('═══ STEP 1: Getting temp email from temp-mail.org ═══');
  const tempMailPage = await context.newPage();
  await tempMailPage.goto('https://temp-mail.org/en/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await sleep(8000); // Wait for JS to generate email

  // Wait for temp email to be generated
  await tempMailPage.waitForSelector('#mail, #pre_button, .email-address, #email, #mail-mobile', { timeout: 20000 });
  await sleep(2000);

  // Try multiple selectors to get the email
  let tempEmail = '';
  const selectors = ['#mail', '#mail-mobile', '#email', '.email-address', '#pre_button', 'input[type="text"]', '.mail-address'];
  for (const sel of selectors) {
    try {
      const el = tempMailPage.locator(sel).first();
      if (await el.count() > 0) {
        tempEmail = await el.inputValue().catch(() => '') || await el.textContent().catch(() => '');
        if (tempEmail && tempEmail.includes('@')) {
          tempEmail = tempEmail.trim();
          break;
        }
      }
    } catch (e) { /* continue */ }
  }

  if (!tempEmail || !tempEmail.includes('@')) {
    // Try clicking refresh button
    try {
      await tempMailPage.click('#pre_button, .refresh, button:has-text("Refresh"), #click-to-refresh', { timeout: 5000 });
      await sleep(3000);
    } catch (e) { /* continue */ }

    for (const sel of selectors) {
      try {
        const el = tempMailPage.locator(sel).first();
        if (await el.count() > 0) {
          tempEmail = await el.inputValue().catch(() => '') || await el.textContent().catch(() => '');
          if (tempEmail && tempEmail.includes('@')) {
            tempEmail = tempEmail.trim();
            break;
          }
        }
      } catch (e) { /* continue */ }
    }
  }

  // Last resort: extract from page text or DOM value
  if (!tempEmail || !tempEmail.includes('@')) {
    // Try evaluating JS directly
    tempEmail = await tempMailPage.evaluate(() => {
      // Try common patterns
      const el = document.querySelector('#mail') || document.querySelector('#mail-mobile');
      if (el) return el.value || el.textContent || el.innerText || '';
      // Regex from body
      const body = document.body.innerText;
      const m = body.match(/[\w.-]+@[\w.-]+\.\w{2,}/);
      return m ? m[0] : '';
    }).catch(() => '');
  }

  if (!tempEmail || !tempEmail.includes('@')) {
    const bodyText = await tempMailPage.textContent('body').catch(() => '');
    const match = bodyText.match(/[\w.-]+@[\w.-]+\.\w{2,}/);
    if (match) tempEmail = match[0];
  }

  await screenshot(tempMailPage, '01-temp-mail-inbox');

  if (!tempEmail || !tempEmail.includes('@')) {
    console.error('  ❌ Could not extract temp email. Page content:');
    console.error(await tempMailPage.textContent('body').catch(() => '(empty)'));
    // Dump page HTML for debugging
    const html = await tempMailPage.content().catch(() => '');
    fs.writeFileSync('test-results/e2e-live/debug-tempmail.html', html);
    console.log('  📄 Saved page HTML to test-results/e2e-live/debug-tempmail.html');
    await browser.close();
    process.exit(1);
  }

  console.log(`  ✅ Got temp email: ${tempEmail}`);

  // ═══════════════════════════════════════════
  // STEP 2: Register on exam portal
  // ═══════════════════════════════════════════
  console.log('\n═══ STEP 2: Registering new student account ═══');
  const regPage = await context.newPage();
  await regPage.goto(`${BASE}/signup.php`, { waitUntil: 'networkidle', timeout: TIMEOUT });
  await screenshot(regPage, '02-signup-page');

  // Check if signup form exists
  const hasForm = await regPage.locator('form').count();
  console.log(`  Signup form found: ${hasForm > 0 ? 'YES' : 'NO'}`);

  // Check what fields are available
  const fields = await regPage.evaluate(() => {
    const inputs = document.querySelectorAll('input, select');
    return Array.from(inputs).map(i => ({
      tag: i.tagName,
      type: i.type,
      name: i.name,
      id: i.id,
      placeholder: i.placeholder,
    }));
  });
  console.log('  Form fields:', JSON.stringify(fields, null, 2));

  // Fill the registration form
  const studentName = 'E2E Test Student ' + Date.now().toString(36).slice(-4);
  const phone = '9' + Math.floor(100000000 + Math.random() * 900000000);

  // Fill name
  const nameInput = regPage.locator('#name, input[name="name"]').first();
  if (await nameInput.count() > 0) {
    await nameInput.fill(studentName);
    console.log(`  ✅ Name: ${studentName}`);
  }

  // Fill email
  const emailInput = regPage.locator('#email, input[name="email"]').first();
  if (await emailInput.count() > 0) {
    await emailInput.fill(tempEmail);
    console.log(`  ✅ Email: ${tempEmail}`);
  }

  // Fill phone
  const phoneInput = regPage.locator('#phone, input[name="phone"]').first();
  if (await phoneInput.count() > 0) {
    await phoneInput.fill(phone);
    console.log(`  ✅ Phone: ${phone}`);
  }

  // Fill password
  const passInput = regPage.locator('#password, input[name="password"]').first();
  if (await passInput.count() > 0) {
    await passInput.fill('TestPass123!');
    console.log(`  ✅ Password filled`);
  }

  // Fill confirm password if exists
  const confirmPass = regPage.locator('#confirm_password, input[name="confirm_password"], input[name="password_confirmation"]').first();
  if (await confirmPass.count() > 0) {
    await confirmPass.fill('TestPass123!');
    console.log(`  ✅ Confirm password filled`);
  }

  // Handle college/course/batch dropdowns if they exist
  const collegeSelect = regPage.locator('#college_id, select[name="college_id"]').first();
  if (await collegeSelect.count() > 0) {
    const options = await collegeSelect.locator('option').allTextContents();
    console.log(`  College options: ${options.join(', ')}`);
    const vals = await collegeSelect.locator('option').evaluateAll(opts =>
      opts.filter(o => o.value).map(o => ({ value: o.value, text: o.textContent }))
    );
    if (vals.length > 0) {
      await collegeSelect.selectOption(vals[0].value);
      console.log(`  ✅ Selected college: ${vals[0].text}`);
      await sleep(1000);
    }
  }

  // Wait for dependent dropdowns to load
  await sleep(1500);

  const courseSelect = regPage.locator('#course_id, select[name="course_id"]').first();
  if (await courseSelect.count() > 0) {
    const vals = await courseSelect.locator('option').evaluateAll(opts =>
      opts.filter(o => o.value).map(o => ({ value: o.value, text: o.textContent }))
    );
    if (vals.length > 0) {
      await courseSelect.selectOption(vals[0].value);
      console.log(`  ✅ Selected course: ${vals[0].text}`);
      await sleep(1000);
    }
  }

  const batchSelect = regPage.locator('#batch_id, select[name="batch_id"]').first();
  if (await batchSelect.count() > 0) {
    const vals = await batchSelect.locator('option').evaluateAll(opts =>
      opts.filter(o => o.value).map(o => ({ value: o.value, text: o.textContent }))
    );
    if (vals.length > 0) {
      await batchSelect.selectOption(vals[0].value);
      console.log(`  ✅ Selected batch: ${vals[0].text}`);
    }
  }

  // Fill roll number if exists
  const rollInput = regPage.locator('#roll_number, input[name="roll_number"]').first();
  if (await rollInput.count() > 0) {
    const rollNum = 'E2E' + Math.floor(100000 + Math.random() * 900000);
    await rollInput.fill(rollNum);
    console.log(`  ✅ Roll number: ${rollNum}`);
  }

  // Fill year of joining if exists
  const yearSelect = regPage.locator('#year_of_joining, select[name="year_of_joining"]').first();
  if (await yearSelect.count() > 0) {
    const yearVals = await yearSelect.locator('option').evaluateAll(opts =>
      opts.filter(o => o.value).map(o => ({ value: o.value, text: o.textContent.trim() }))
    );
    if (yearVals.length > 0) {
      const pick = yearVals[yearVals.length - 1];
      await yearSelect.selectOption(pick.value);
      console.log(`  ✅ Year of joining: ${pick.text}`);
    }
  }

  await screenshot(regPage, '03-form-filled');

  // Submit the form
  console.log('  Submitting registration form...');
  const submitBtn = regPage.locator('button[type="submit"], input[type="submit"]').first();
  await submitBtn.click();

  // Wait for response
  await regPage.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await sleep(2000);
  await screenshot(regPage, '04-after-submit');

  const regUrl = regPage.url();
  const regContent = await regPage.textContent('body');
  console.log(`  After submit URL: ${regUrl}`);
  console.log(`  Page contains OTP: ${regContent.toLowerCase().includes('otp') ? 'YES' : 'NO'}`);
  console.log(`  Page contains error: ${regContent.toLowerCase().includes('error') || regContent.toLowerCase().includes('invalid') ? 'YES' : 'NO'}`);

  // ═══════════════════════════════════════════
  // STEP 3: Get OTP from temp-mail.org
  // Flow: wait for email in inbox → click to open → scroll down → extract OTP
  // ═══════════════════════════════════════════
  console.log('\n═══ STEP 3: Retrieving OTP from temp-mail.org ═══');

  await tempMailPage.bringToFront();
  console.log('  Waiting for email to arrive in inbox...');

  let otpCode = '';
  let emailOpened = false;

  // ── Phase A: Wait for email to appear in inbox, then click to open ──
  for (let attempt = 0; attempt < 20; attempt++) {
    await sleep(5000);

    // Click refresh button
    try {
      await tempMailPage.click(
        '#refresh, #click-to-refresh, .refresh, button:has-text("Refresh"), .fa-sync, .fa-refresh, [data-testid="refresh"]',
        { timeout: 3000 }
      );
      await sleep(2000);
    } catch (e) { /* no refresh button, continue */ }

    if (attempt < 3 || attempt % 5 === 0) {
      await screenshot(tempMailPage, `05-inbox-check-${attempt}`);
    }

    // ── Look for clickable email items ──
    const clickTargets = [
      // Table rows (temp-mail.org often uses <tr>)
      '#mail-list tr',
      '#email-list tr',
      '.mail-list tr',
      'table tr',
      // List items
      '.inbox-item', '.mail-item', '.email-list-item',
      '.mail-list-item', '.inbox__item', '.mailmessages li',
      // Links inside inbox
      'a[href*="mail/"]', 'a[href*="inbox/"]', 'a[href*="id="]',
      // Generic clickable items
      '[class*="inbox"] li', '[class*="mail-list"] *',
      '.list-group-item', '.card-body a',
      // Fallback: anything with "mail" in class name that looks clickable
      '[class*="mail"] a', '[class*="inbox"] a',
    ];

    for (const sel of clickTargets) {
      try {
        const items = tempMailPage.locator(sel);
        const count = await items.count();
        if (count === 0) continue;

        console.log(`    Attempt ${attempt + 1}: "${sel}" matched ${count} items`);

        for (let i = 0; i < Math.min(count, 5); i++) {
          const text = await items.nth(i).textContent().catch(() => '');
          const lower = text.toLowerCase();
          // Check if this item is from our exam portal
          if (lower.includes('yajurvedh') || lower.includes('otp') ||
              lower.includes('verify') || lower.includes('exam') ||
              lower.includes('code') || lower.includes('verification')) {
            console.log(`    ✅ Clicking email: "${text.substring(0, 100).trim()}"`);
            await items.nth(i).click();
            emailOpened = true;
            break;
          }
        }
        if (emailOpened) break;

        // After several attempts, just click the first item
        if (attempt > 3 && count > 0) {
          console.log(`    No keyword match — clicking first item as fallback...`);
          await items.first().click();
          emailOpened = true;
          break;
        }
      } catch (e) { /* try next selector */ }
    }

    if (emailOpened) break;
    console.log(`  Attempt ${attempt + 1}: No email found yet, retrying...`);
  }

  if (!emailOpened) {
    console.error('  ❌ Could not find/open email in inbox after 100s');
    const html = await tempMailPage.content().catch(() => '');
    fs.writeFileSync('test-results/e2e-live/debug-inbox.html', html);
    console.log('  📄 Saved inbox HTML → test-results/e2e-live/debug-inbox.html');
    await browser.close();
    process.exit(1);
  }

  // ── Phase B: Wait for email body to load, scroll down, extract OTP ──
  console.log('  Email opened — waiting for body to load...');
  await sleep(5000);
  await screenshot(tempMailPage, '06-email-opened');

  // The email content may be in an iframe or in the main page
  // Try to find OTP in main page first
  for (let scrollAttempt = 0; scrollAttempt < 5; scrollAttempt++) {
    // Scroll down to reveal OTP at bottom of email
    await tempMailPage.evaluate(() => window.scrollBy(0, 500));
    await sleep(1000);

    const pageText = await tempMailPage.textContent('body').catch(() => '');

    // Look for 6-digit OTP
    let match = pageText.match(/\b(\d{6})\b/);
    if (match) {
      otpCode = match[1];
      console.log(`  ✅ OTP found (6-digit): ${otpCode}`);
      break;
    }
    // Look for "OTP: XXXXXX" or "code: XXXXXX"
    match = pageText.match(/(?:OTP|code|Code|verification code)[:\s]*(\d{4,6})/i);
    if (match) {
      otpCode = match[1];
      console.log(`  ✅ OTP found (pattern): ${otpCode}`);
      break;
    }

    console.log(`    Scroll attempt ${scrollAttempt + 1}: no OTP yet, scrolling more...`);
  }

  // If OTP not found in main page, check iframes
  if (!otpCode) {
    console.log('  Checking iframes for OTP...');
    const frames = tempMailPage.frames();
    console.log(`  Found ${frames.length} frames`);
    for (const frame of frames) {
      try {
        const frameText = await frame.textContent('body').catch(() => '');
        let match = frameText.match(/\b(\d{6})\b/);
        if (match) {
          otpCode = match[1];
          console.log(`  ✅ OTP found in iframe: ${otpCode}`);
          break;
        }
        match = frameText.match(/(?:OTP|code|Code)[:\s]*(\d{4,6})/i);
        if (match) {
          otpCode = match[1];
          console.log(`  ✅ OTP found in iframe (pattern): ${otpCode}`);
          break;
        }
      } catch (e) { /* skip this frame */ }
    }
  }

  await screenshot(tempMailPage, '07-otp-extracted');

  if (!otpCode) {
    console.error('  ❌ Could not extract OTP from opened email');
    console.log('  Saving page HTML for debugging...');
    const html = await tempMailPage.content().catch(() => '');
    fs.writeFileSync('test-results/e2e-live/debug-tempmail-otp.html', html);
    console.log('  📄 Saved to test-results/e2e-live/debug-tempmail-otp.html');
    await browser.close();
    process.exit(1);
  }

  // ═══════════════════════════════════════════
  // STEP 4: Verify OTP
  // ═══════════════════════════════════════════
  console.log('\n═══ STEP 4: Verifying OTP ═══');
  await regPage.bringToFront();

  // Check if we're on OTP verification page
  const otpInput = regPage.locator('#otp, input[name="otp"], input[placeholder*="OTP"]').first();
  if (await otpInput.count() > 0) {
    await otpInput.fill(otpCode);
    console.log(`  ✅ OTP entered: ${otpCode}`);
    await screenshot(regPage, '07-otp-entered');

    const verifyBtn = regPage.locator('button[type="submit"], button:has-text("Verify"), button:has-text("Submit")').first();
    await verifyBtn.click();
    await regPage.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await sleep(2000);
    await screenshot(regPage, '08-after-verify');

    console.log(`  After verify URL: ${regPage.url()}`);
    const verifyContent = await regPage.textContent('body');
    console.log(`  Success: ${verifyContent.toLowerCase().includes('success') || verifyContent.toLowerCase().includes('verified') ? 'YES' : 'CHECK'}`);
  } else {
    console.log('  OTP input not found on current page, checking URL...');
    console.log(`  Current URL: ${regPage.url()}`);
    await screenshot(regPage, '07-no-otp-input');
  }

  // ═══════════════════════════════════════════
  // STEP 5: Login as new student
  // ═══════════════════════════════════════════
  console.log('\n═══ STEP 5: Logging in as new student ═══');
  await regPage.goto(`${BASE}/login.php`, { waitUntil: 'networkidle', timeout: TIMEOUT });
  await screenshot(regPage, '09-login-page');

  // Select student role
  const roleSelect = regPage.locator('#role, select[name="role"]').first();
  if (await roleSelect.count() > 0) {
    await roleSelect.selectOption('student');
    console.log('  ✅ Role: student');
  }

  await sleep(500);

  const loginEmail = regPage.locator('#email, input[name="email"]').first();
  await loginEmail.fill(tempEmail);
  console.log(`  ✅ Email: ${tempEmail}`);

  const loginPass = regPage.locator('#password, input[name="password"]').first();
  await loginPass.fill('TestPass123!');
  console.log('  ✅ Password filled');

  await screenshot(regPage, '10-login-filled');

  const loginBtn = regPage.locator('button[type="submit"]').first();
  await loginBtn.click();
  await regPage.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await sleep(2000);
  await screenshot(regPage, '11-after-login');

  const dashUrl = regPage.url();
  console.log(`  After login URL: ${dashUrl}`);
  const isDashboard = dashUrl.includes('dashboard');
  console.log(`  Reached dashboard: ${isDashboard ? 'YES ✅' : 'NO ❌'}`);

  if (isDashboard) {
    // ═══════════════════════════════════════════
    // STEP 6: Explore student portal
    // ═══════════════════════════════════════════
    console.log('\n═══ STEP 6: Exploring student portal ═══');

    await screenshot(regPage, '12-student-dashboard');

    const dashContent = await regPage.textContent('body');
    console.log(`  Dashboard contains student name: ${dashContent.includes(studentName) ? 'YES' : 'NO'}`);
    console.log(`  Dashboard contains batch info: ${dashContent.includes('Batch') || dashContent.includes('batch') ? 'YES' : 'NO'}`);

    const navLinks = await regPage.evaluate(() => {
      const links = document.querySelectorAll('a');
      return Array.from(links).map(a => ({ href: a.href, text: a.textContent.trim() })).filter(l => l.text);
    });
    console.log(`  Navigation links: ${navLinks.length}`);
    navLinks.forEach(l => console.log(`    - ${l.text} → ${l.href}`));
  }

  // ═══════════════════════════════════════════
  // SUMMARY
  // ═══════════════════════════════════════════
  console.log('\n═══ E2E LIVE TEST SUMMARY ═══');
  console.log(`  Temp Email: ${tempEmail}`);
  console.log(`  Student Name: ${studentName}`);
  console.log(`  OTP: ${otpCode}`);
  console.log(`  Dashboard reached: ${isDashboard ? 'YES ✅' : 'NO ❌'}`);
  console.log(`  Screenshots saved in: test-results/e2e-live/`);
  console.log('═══════════════════════════════════════════\n');

  await browser.close();
})();
