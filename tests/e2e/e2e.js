const { chromium } = require('playwright-core');
const BASE = process.env.E2E_BASE || 'http://localhost:8080';
const SHOTS = __dirname + '/shots/';
const results = [];
const check = (name, ok, extra = '') => { results.push(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  -- ' + extra : ''}`); };

(async () => {
  require('fs').mkdirSync(SHOTS, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const jsErrors = [];
  page.on('pageerror', e => jsErrors.push(e.message));
  page.on('dialog', d => d.accept());
  const flash = async () => (await page.locator('.alert').allInnerTexts()).join(' | ');

  try {
    // 1. Forgot password with mail unconfigured (production)
    await page.goto(BASE + '/forgot-password.php');
    await page.fill('#email', 'admin@example.com');
    await page.click('button[type=submit]');
    const fp = await flash();
    check('forgot-password says reset unavailable', /not available/i.test(fp) && page.url().includes('forgot-password'), fp);

    // 2. Login (OTP skipped because mail is off) -> dashboard
    await page.goto(BASE + '/login.php');
    await page.fill('#username', 'admin');
    await page.fill('#password', 'Passw0rd!');
    await page.click('button[type=submit]');
    await page.waitForLoadState();
    check('login reaches dashboard', page.url().includes('dashboard'), page.url());
    const kpis = await page.locator('.kpi-value').allInnerTexts();
    const moneyKpis = kpis.slice(0, 3);
    check('KPI amounts formatted as peso', moneyKpis.every(t => /^\u20B1-?[\d,]+\.\d{2}$/.test(t.trim())), JSON.stringify(kpis));
    const heights = await page.locator('.kpi-value').evaluateAll(els => els.map(e => e.getBoundingClientRect().height));
    check('KPI values on one line', heights.every(h => h < 40), JSON.stringify(heights));
    await page.screenshot({ path: SHOTS + '1-dashboard.png' });
    await page.setViewportSize({ width: 900, height: 900 });
    await page.screenshot({ path: SHOTS + '1b-dashboard-narrow.png' });
    await page.setViewportSize({ width: 1280, height: 900 });

    // 3. Settings mail card + test button
    await page.goto(BASE + '/modules/settings/system-settings.php');
    const body = await page.innerText('body');
    check('settings shows mail-not-configured', /Mail is not configured/.test(body));
    await page.click('button:has-text("Send test email to me")');
    await page.waitForLoadState();
    const mt = await flash();
    check('mail test reports why', /not configured|no valid email/i.test(mt), mt.slice(0, 160));

    // 4. Budget period validation
    await page.goto(BASE + '/modules/budget/period-form.php');
    await page.evaluate(() => document.querySelector('input[name=name]').removeAttribute('maxlength'));
    await page.fill('input[name=name]', 'X'.repeat(60));
    await page.click('button:has-text("Save Period")');
    check('period name >50 rejected with message', /50 characters/.test(await flash()));
    await page.fill('input[name=name]', 'E2E FY');
    await page.fill('input[name=start_date]', '2026-12-31');
    await page.fill('input[name=end_date]', '2026-01-01');
    await page.click('button:has-text("Save Period")');
    check('period end<start rejected', /End date must be/.test(await flash()));
    await page.fill('input[name=start_date]', '2026-01-01');
    await page.fill('input[name=end_date]', '2026-12-31');
    await page.click('button:has-text("Save Period")');
    await page.waitForLoadState();
    check('valid period created', page.url().includes('periods.php'));

    // 5. Budget create + amounts
    await page.goto(BASE + '/modules/budget/budget-form.php');
    await page.selectOption('select[name=budget_period_id]', { label: /E2E FY/ }).catch(async () => {
      const v = await page.locator('select[name=budget_period_id] option', { hasText: 'E2E FY' }).first().getAttribute('value');
      await page.selectOption('select[name=budget_period_id]', v);
    });
    await page.fill('input[name=name]', 'E2E Budget');
    await page.click('button:has-text("Create Budget")');
    await page.waitForLoadState();
    check('budget created', /budget-form\.php\?id=\d+/.test(page.url()), await flash());
    const opt = await page.locator('select[name=account_id] option').nth(1).getAttribute('value');
    await page.selectOption('select[name=account_id]', opt);
    await page.click('button:has-text("+ Add")');
    await page.waitForLoadState();
    const cells = page.locator('.budget-amount');
    check('line added with 12 month cells', (await cells.count()) === 12);
    await cells.nth(0).fill('3,000');
    await cells.nth(1).fill('1500.555');
    await cells.nth(2).click();
    const liveTotal = (await page.locator('.budget-row-total').first().innerText()).trim();
    check('live row total updates', liveTotal === '4,500.56' || liveTotal === '4,500.55', liveTotal);
    check('cell reformatted on blur', (await cells.nth(0).inputValue()) === '3,000.00', await cells.nth(0).inputValue());
    await page.click('button:has-text("Save Amounts")');
    await page.waitForLoadState();
    const v0 = await page.locator('.budget-amount').nth(0).inputValue();
    const v1 = await page.locator('.budget-amount').nth(1).inputValue();
    check('amounts saved (3,000 / 1500.555)', v0 === '3,000.00' && v1 === '1,500.56', `${v0} / ${v1}; ${await flash()}`);
    await page.screenshot({ path: SHOTS + '2-budget.png', fullPage: true });
    await page.locator('.budget-amount').nth(3).fill('abc');
    await page.click('button:has-text("Save Amounts")');
    await page.waitForLoadState();
    const bad = await flash();
    check('invalid amount rejected, nothing half-saved', /not a valid amount/.test(bad) && (await page.locator('.budget-amount').nth(0).inputValue()) === '3,000.00', bad.slice(0, 160));

    // 6. Bill with blank description (the screenshot case)
    await page.goto(BASE + '/modules/ap/bill-form.php');
    const vend = await page.locator('#vendor_id option').nth(1).getAttribute('value');
    await page.selectOption('#vendor_id', vend);
    const acct = await page.locator('select[name="account_id[]"] option').nth(1).getAttribute('value');
    await page.selectOption('select[name="account_id[]"]', acct);
    await page.fill('.lineQty', '100');
    await page.fill('.linePrice', '');
    await page.locator('.linePrice').pressSequentially('30');
    const liveBill = (await page.innerText('#totalDisp')).trim();
    check('bill total live + formatted while typing', liveBill === '3,000.00', liveBill);
    // wheel over focused number input must not change it
    await page.locator('.lineQty').focus();
    await page.locator('.lineQty').hover();
    await page.mouse.wheel(0, 300);
    check('mouse wheel does not change qty', (await page.inputValue('.lineQty')) === '100', await page.inputValue('.lineQty'));
    await page.screenshot({ path: SHOTS + '3-bill-form.png' });
    await page.click('button:has-text("Save as Draft")');
    await page.waitForLoadState();
    const billText = await page.innerText('body');
    check('bill with blank description saves 3,000.00', page.url().includes('bill-view') && billText.includes('3,000.00'), page.url() + ' ' + (await flash()));
    await page.screenshot({ path: SHOTS + '4-bill-view.png', fullPage: true });

    // 7. Invoice negative qty rejected
    await page.goto(BASE + '/modules/ar/invoice-form.php');
    const cust = await page.locator('select[name=customer_id] option').nth(1).getAttribute('value');
    await page.selectOption('select[name=customer_id]', cust);
    const acct2 = await page.locator('select[name="account_id[]"] option').nth(1).getAttribute('value');
    await page.selectOption('select[name="account_id[]"]', acct2);
    await page.evaluate(() => document.querySelector('.lineQty').removeAttribute('min'));
    await page.fill('.lineQty', '-2');
    await page.fill('.linePrice', '50');
    await page.click('button[type=submit]');
    await page.waitForLoadState();
    check('invoice negative qty rejected', /quantity must be greater than zero/.test(await flash()), await flash());

    // 8. Variance report + dashboard load cleanly
    await page.goto(BASE + '/modules/budget/variance-report.php');
    const vr = await page.innerText('body');
    check('variance report renders', /budgeted/i.test(vr), vr.replace(/\s+/g, ' ').slice(0, 300));
    await page.screenshot({ path: SHOTS + '5-variance.png', fullPage: true });
  } catch (e) {
    check('script error', false, e.message.split('\n')[0]);
    await page.screenshot({ path: SHOTS + 'error.png', fullPage: true });
  }
  check('no JS errors', jsErrors.length === 0, jsErrors.join(' | '));
  console.log(results.join('\n'));
  await browser.close();
})();
