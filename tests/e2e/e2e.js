const { chromium } = require('playwright-core');
const { execSync } = require('child_process');
const ROOT = require('path').resolve(__dirname, '../..');
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

    // 9. Money integrity. Forged POSTs (same session + CSRF token) send what the
    // browser's min/max attributes would block, to prove the server rejects it too.
    const sql = q => execSync(`docker compose exec -T db mariadb -N -utravelcore -ptravelcore travelcore_fms -e "${q}"`, { cwd: ROOT }).toString().trim();
    await page.goto(BASE + '/modules/ap/payment-form.php?vendor_id=1');
    const csrf = await page.locator('input[name=csrf_token]').first().getAttribute('value');
    const post = async (path, fields) => {
      const body = new URLSearchParams([['csrf_token', csrf], ...fields]);
      const r = await page.request.post(BASE + path, { headers: { 'content-type': 'application/x-www-form-urlencoded' }, data: body.toString() });
      const html = await r.text();
      const alerts = [...html.matchAll(/class="alert[^"]*"[^>]*>([\s\S]*?)<\/div>/g)].map(m => m[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim()).join(' | ');
      return { url: r.url(), alerts };
    };
    const [billId, vendorId, billBal] = sql("SELECT id, vendor_id, total_amount-amount_paid FROM ap_bills WHERE status IN ('Open','PartiallyPaid') ORDER BY id LIMIT 1").split('\t');
    const otherBill = sql(`SELECT id FROM ap_bills WHERE status IN ('Open','PartiallyPaid') AND vendor_id <> ${vendorId} LIMIT 1`);
    const paidBefore = sql(`SELECT amount_paid FROM ap_bills WHERE id = ${billId}`);
    const pay = extra => post('/modules/ap/payment-form.php', [['vendor_id', vendorId], ['cash_account_id', '1'], ['payment_date', '2026-10-08'], ...extra]);

    let r = await pay([[`apply[${billId}]`, String(Number(billBal) + 1000)]]);
    check('AP overpayment rejected', /unpaid balance/.test(r.alerts) && sql(`SELECT amount_paid FROM ap_bills WHERE id = ${billId}`) === paidBefore, r.alerts);
    r = await pay([[`apply[${otherBill}]`, '1']]);
    check('AP payment on another vendor\'s bill rejected', /does not belong/.test(r.alerts), r.alerts);
    r = await pay([[`apply[${billId}]`, '100'], ['withheld_amount', '-50']]);
    check('negative withheld rejected', /Withheld amount must be/.test(r.alerts), r.alerts);
    r = await pay([[`apply[${billId}]`, '100'], ['withheld_amount', '10']]);
    check('withheld without tax type rejected', /withholding tax type/.test(r.alerts), r.alerts);
    r = await pay([[`apply[${billId}]`, '1,000'], ['withheld_amount', '20'], ['withhold_tax_type_id', '2']]);
    const lastJe = sql('SELECT ROUND(SUM(debit)-SUM(credit),2), COUNT(*) FROM journal_lines WHERE journal_entry_id = (SELECT MAX(id) FROM journal_entries)');
    check('valid AP payment with WHT posts balanced 3-line entry', r.url.includes('vendor-view') && lastJe === '0.00\t3' && Number(sql(`SELECT amount_paid FROM ap_bills WHERE id = ${billId}`)) === Number(paidBefore) + 1000, `${r.url} ${lastJe} ${r.alerts}`);

    r = await post('/modules/gl/journal-entry-form.php', [['entry_date', '2026-10-08'], ['account_id[]', '1'], ['debit[]', '200'], ['credit[]', '0'], ['account_id[]', '14'], ['debit[]', '-100'], ['credit[]', '0'], ['account_id[]', '14'], ['debit[]', '0'], ['credit[]', '100']]);
    check('JE negative debit rejected', /zero or more/.test(r.alerts), r.alerts);
    r = await post('/modules/gl/journal-entry-form.php', [['entry_date', '2026-10-08'], ['account_id[]', '1'], ['debit[]', '100'], ['credit[]', '100'], ['account_id[]', '14'], ['debit[]', '50'], ['credit[]', '0'], ['account_id[]', '14'], ['debit[]', '0'], ['credit[]', '50']]);
    check('JE line with debit and credit rejected', /not both/.test(r.alerts), r.alerts);

    const [draftId, draftNo] = sql("SELECT id, bill_no FROM ap_bills WHERE status = 'Draft' ORDER BY id DESC LIMIT 1").split('\t');
    await post(`/modules/ap/bill-view.php?id=${draftId}`, [['action', 'approve']]);
    await post(`/modules/ap/bill-view.php?id=${draftId}`, [['action', 'approve']]);
    const jeCount = sql(`SELECT COUNT(*) FROM journal_entries WHERE reference = '${draftNo}'`);
    check('bill approved twice posts once', jeCount === '1' && sql(`SELECT status FROM ap_bills WHERE id = ${draftId}`) === 'Open', `entries=${jeCount}`);

    r = await post('/modules/cash/account-form.php', [['account_name', 'E2E Petty'], ['account_type', 'Petty Cash'], ['gl_account_id', '1'], ['opening_balance', '-5'], ['opening_offset_account_id', '14'], ['status', 'Active']]);
    check('negative opening balance rejected', /zero or more/.test(r.alerts), r.alerts);
    r = await post('/modules/cash/account-form.php', [['account_name', 'E2E Petty'], ['account_type', 'Petty Cash'], ['gl_account_id', '1'], ['opening_balance', '1,000'], ['opening_offset_account_id', '14'], ['status', 'Active']]);
    const newCash = sql("SELECT id FROM cash_accounts WHERE account_name = 'E2E Petty' ORDER BY id DESC LIMIT 1");
    const openJe = sql(`SELECT ROUND(SUM(jl.debit),2) FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id = je.id WHERE je.reference = 'OPEN-${newCash}'`);
    check('opening balance posted to GL', openJe === '1000.00', `${r.url} ${openJe} ${r.alerts}`);

    r = await post('/modules/tax/tax-type-form.php', [['code', 'E2E'], ['name', 'E2E Tax'], ['rate_percent', '150']]);
    check('tax rate over 100 rejected', /0 to 100/.test(r.alerts), r.alerts);

    // Happy paths of every handler that now runs in one transaction.
    const [invId, custId] = sql("SELECT id, customer_id FROM ar_invoices WHERE status IN ('Open','PartiallyPaid') ORDER BY id LIMIT 1").split('\t');
    const recvBefore = Number(sql(`SELECT amount_received FROM ar_invoices WHERE id = ${invId}`));
    r = await post('/modules/ar/receipt-form.php', [['customer_id', custId], ['cash_account_id', '2'], ['receipt_date', '2026-10-08'], [`apply[${invId}]`, '500']]);
    check('AR receipt recorded', r.url.includes('customer-view') && Number(sql(`SELECT amount_received FROM ar_invoices WHERE id = ${invId}`)) === recvBefore + 500, r.url + ' ' + r.alerts);

    r = await post('/modules/cash/transfer-form.php', [['from_cash_account_id', '2'], ['to_cash_account_id', '3'], ['amount', '1,234.50'], ['transfer_date', '2026-10-08']]);
    check('cash transfer recorded', r.url.includes('transfers.php'), r.url + ' ' + r.alerts);
    r = await post('/modules/cash/transaction-form.php', [['cash_account_id', '1'], ['offset_account_id', '30'], ['type', 'Withdrawal'], ['amount', '75'], ['transaction_date', '2026-10-08']]);
    check('cash transaction recorded', r.url.includes('account-register'), r.url + ' ' + r.alerts);

    const pendingWht = sql("SELECT COUNT(*) FROM tax_transactions WHERE direction = 'Withholding' AND status = 'Pending'");
    r = await post('/modules/tax/remittance-form.php', [['tax_type_id', '2'], ['direction', 'Withholding'], ['period_start', '2026-01-01'], ['period_end', '2026-12-31'], ['cash_account_id', '2']]);
    check('withholding remittance recorded', pendingWht !== '0' && r.url.includes('remittances.php') && sql("SELECT COUNT(*) FROM tax_transactions WHERE direction = 'Withholding' AND status = 'Pending'") === '0', `${pendingWht} pending; ${r.url} ${r.alerts}`);

    const [dvBill, dvVendor] = sql("SELECT id, vendor_id FROM ap_bills WHERE status IN ('Open','PartiallyPaid') ORDER BY id DESC LIMIT 1").split('\t');
    const dvPaidBefore = Number(sql(`SELECT amount_paid FROM ap_bills WHERE id = ${dvBill}`));
    r = await post('/modules/disbursement/voucher-form.php', [['payee_type', 'Vendor'], ['vendor_id', dvVendor], ['payee_name', 'E2E Vendor'], ['dv_date', '2026-10-08'], ['cash_account_id', '2'], [`apply_bill[${dvBill}]`, '300'], ['adhoc_description[]', 'E2E fee'], ['adhoc_account_id[]', '30'], ['adhoc_amount[]', '45.50']]);
    const dvId = (r.url.match(/id=(\d+)/) || [])[1];
    await post(`/modules/disbursement/voucher-view.php?id=${dvId}`, [['action', 'approve']]);
    await post(`/modules/disbursement/voucher-view.php?id=${dvId}`, [['action', 'pay']]);
    await post(`/modules/disbursement/voucher-view.php?id=${dvId}`, [['action', 'pay']]);
    check('voucher paid once, bill updated', sql(`SELECT status FROM disbursement_vouchers WHERE id = ${dvId}`) === 'Paid'
      && Number(sql(`SELECT amount_paid FROM ap_bills WHERE id = ${dvBill}`)) === dvPaidBefore + 300
      && sql(`SELECT COUNT(*) FROM journal_entries WHERE source_module = 'disbursement' AND source_id = ${dvId}`) === '1', `dv=${dvId} ${r.alerts}`);

    const crRecvBefore = Number(sql(`SELECT amount_received FROM ar_invoices WHERE id = ${invId}`));
    r = await post('/modules/collection/receipt-form.php', [['payer_type', 'Customer'], ['customer_id', custId], ['payer_name', 'E2E Customer'], ['cr_date', '2026-10-08'], ['cash_account_id', '2'], [`apply_invoice[${invId}]`, '250']]);
    const crId = (r.url.match(/id=(\d+)/) || [])[1];
    await post(`/modules/collection/receipt-view.php?id=${crId}`, [['action', 'approve']]);
    await post(`/modules/collection/receipt-view.php?id=${crId}`, [['action', 'deposit']]);
    check('collection deposited, invoice updated', sql(`SELECT status FROM collection_receipts WHERE id = ${crId}`) === 'Deposited'
      && Number(sql(`SELECT amount_received FROM ar_invoices WHERE id = ${invId}`)) === crRecvBefore + 250, `cr=${crId} ${r.alerts}`);
    r = await post('/modules/collection/receipt-form.php', [['payer_type', 'Customer'], ['customer_id', custId], ['payer_name', 'E2E Customer'], ['cr_date', '2026-10-08'], ['cash_account_id', '2'], [`apply_invoice[${invId}]`, '99999999']]);
    check('collection over invoice balance rejected', /unpaid balance/.test(r.alerts), r.alerts);

    const tb = sql("SELECT ROUND(SUM(jl.debit)-SUM(jl.credit),2) FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE je.status IN ('Posted','Void')");
    check('whole ledger still balances', tb === '0.00', tb);
  } catch (e) {
    check('script error', false, e.message.split('\n')[0]);
    await page.screenshot({ path: SHOTS + 'error.png', fullPage: true });
  }
  check('no JS errors', jsErrors.length === 0, jsErrors.join(' | '));
  console.log(results.join('\n'));
  await browser.close();
})();
