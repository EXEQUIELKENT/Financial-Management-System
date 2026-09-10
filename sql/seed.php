<?php
/**
 * One-time demo data generator. Run once via browser (http://localhost/Financial-Management-System/sql/seed.php,
 * substituting your actual htdocs folder name) or CLI (php seed.php) AFTER importing schema.sql. Pass ?reset=1 to wipe and
 * regenerate all business data (roles/permissions from schema.sql are kept).
 *
 * Every transaction here goes through the same post_journal_entry() engine
 * and cash-balance bookkeeping the real app pages use, so the seeded books
 * are guaranteed to be internally consistent (Trial Balance stays in balance).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Ledger.php';

$db = get_db();
$isCli = php_sapi_name() === 'cli';
function out(string $msg) { global $isCli; echo $isCli ? $msg . "\n" : $msg . "<br>\n"; }

function seed_doc_no(string $prefix, string $year): string {
    static $counters = [];
    $key = $prefix . $year;
    $counters[$key] = ($counters[$key] ?? 0) + 1;
    return sprintf('%s-%s-%05d', $prefix, $year, $counters[$key]);
}

$reset = isset($_GET['reset']) || (isset($argv) && in_array('--reset', $argv ?? [], true));

$existingUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($existingUsers > 0 && !$reset) {
    out('Demo data already exists. Re-run with ?reset=1 (browser) or --reset (CLI) to wipe and regenerate.');
    exit;
}

if ($reset) {
    out('Resetting business data...');
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = ['journal_lines','journal_entries','cash_transactions','cash_transfers','bank_reconciliation_items',
        'bank_reconciliations','cash_accounts','tax_transactions','tax_remittances','tax_types',
        'ap_payment_applications','ap_payments','ap_bill_lines','ap_bills','ap_vendors',
        'ar_receipt_applications','ar_receipts','ar_invoice_lines','ar_invoices','ar_customers',
        'dv_approval_history','disbursement_voucher_lines','disbursement_vouchers',
        'cr_approval_history','collection_receipt_lines','collection_receipts',
        'budget_line_monthly','budget_lines','budgets','budget_periods',
        'coa_accounts','departments','ai_assistant_query_log','audit_log','users'];
    foreach ($tables as $t) { $db->exec("TRUNCATE TABLE $t"); }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ---------------------------------------------------------------------------
// 1. Demo users (one per role)
// ---------------------------------------------------------------------------
$roleIds = [];
foreach ($db->query("SELECT id, name FROM roles")->fetchAll() as $r) { $roleIds[$r['name']] = $r['id']; }

$demoPassword = password_hash('Passw0rd!', PASSWORD_DEFAULT);
$userSpecs = [
    ['admin', 'admin@travelcore.test', 'System Administrator', 'Admin'],
    ['accountant', 'accountant@travelcore.test', 'Ana Contadora', 'Accountant'],
    ['approver', 'approver@travelcore.test', 'Marco Aprobado', 'Approver'],
    ['auditor', 'auditor@travelcore.test', 'Ivy Auditor', 'Auditor'],
];
$userIds = [];
$stmt = $db->prepare("INSERT INTO users (username, email, full_name, role_id, status, password_hash) VALUES (?,?,?,?,'Active',?)");
foreach ($userSpecs as [$username, $email, $fullName, $roleName]) {
    $stmt->execute([$username, $email, $fullName, $roleIds[$roleName], $demoPassword]);
    $userIds[$roleName] = (int)$db->lastInsertId();
}
$ADMIN = $userIds['Admin']; $ACCOUNTANT = $userIds['Accountant']; $APPROVER = $userIds['Approver'];
out('Created 4 demo users (admin/accountant/approver/auditor, password: Passw0rd!)');

// ---------------------------------------------------------------------------
// 2. Chart of Accounts
// ---------------------------------------------------------------------------
$coaSpecs = [
    ['1000','Cash on Hand','Asset','Debit'],
    ['1010','Cash in Bank - BDO','Asset','Debit'],
    ['1020','Cash in Bank - BPI','Asset','Debit'],
    ['1100','Accounts Receivable','Asset','Debit'],
    ['1150','Input Tax (VAT)','Asset','Debit'],
    ['1200','Withholding Tax Receivable','Asset','Debit'],
    ['1300','Prepaid Expenses','Asset','Debit'],
    ['1400','Office Equipment','Asset','Debit'],
    ['2000','Accounts Payable','Liability','Credit'],
    ['2010','Output Tax (VAT) Payable','Liability','Credit'],
    ['2020','Withholding Tax Payable','Liability','Credit'],
    ['2100','Accrued Expenses','Liability','Credit'],
    ['2200','Loans Payable','Liability','Credit'],
    ['3000',"Owner's Capital",'Equity','Credit'],
    ['3100','Retained Earnings','Equity','Credit'],
    ['4000','Tour Package Revenue','Revenue','Credit'],
    ['4010','Hotel Booking Revenue','Revenue','Credit'],
    ['4020','Transport Booking Revenue','Revenue','Credit'],
    ['4030','Visa & Documentation Fee Revenue','Revenue','Credit'],
    ['4900','Other Income','Revenue','Credit'],
    ['5000','Cost of Tours (Guides & Permits)','Expense','Debit'],
    ['5010','Cost of Hotel Bookings','Expense','Debit'],
    ['5020','Cost of Transport Bookings','Expense','Debit'],
    ['5100','Salaries and Wages','Expense','Debit'],
    ['5110','Employee Benefits','Expense','Debit'],
    ['5200','Rent Expense','Expense','Debit'],
    ['5210','Utilities Expense','Expense','Debit'],
    ['5220','Office Supplies Expense','Expense','Debit'],
    ['5300','Marketing and Advertising Expense','Expense','Debit'],
    ['5400','Bank Charges','Expense','Debit'],
    ['5600','Professional Fees','Expense','Debit'],
    ['5800','Miscellaneous Expense','Expense','Debit'],
];
$stmt = $db->prepare("INSERT INTO coa_accounts (account_code, account_name, account_type, normal_balance, is_active) VALUES (?,?,?,?,1)");
$acc = [];
foreach ($coaSpecs as [$code, $name, $type, $normal]) {
    $stmt->execute([$code, $name, $type, $normal]);
    $acc[$code] = (int)$db->lastInsertId();
}
out('Created ' . count($coaSpecs) . ' chart of accounts entries.');

// ---------------------------------------------------------------------------
// 3. Cash / bank accounts + opening balance journal entry
// ---------------------------------------------------------------------------
$historyMonths = 7;
$seedStart = date('Y-m-01', strtotime("first day of -" . ($historyMonths - 1) . " month"));

$cashSpecs = [
    ['Cash on Hand', 'Cash on Hand', null, null, $acc['1000'], 50000],
    ['Bank - BDO', 'Bank', 'BDO', '001-234-5678', $acc['1010'], 300000],
    ['Bank - BPI', 'Bank', 'BPI', '002-345-6789', $acc['1020'], 150000],
];
$cash = [];
$stmt = $db->prepare("INSERT INTO cash_accounts (account_name, account_type, bank_name, account_no, gl_account_id, opening_balance, current_balance, status) VALUES (?,?,?,?,?,?,?, 'Active')");
foreach ($cashSpecs as [$name, $type, $bank, $acctNo, $glId, $opening]) {
    $stmt->execute([$name, $type, $bank, $acctNo, $glId, $opening, $opening]);
    $cash[$name] = (int)$db->lastInsertId();
}
$openingTotal = array_sum(array_column($cashSpecs, 5));
post_journal_entry([
    'entry_date' => $seedStart, 'reference' => 'OPENING', 'source_module' => 'manual', 'source_id' => null,
    'description' => 'Opening balances', 'created_by' => $ADMIN,
    'lines' => [
        ['account_id' => $acc['1000'], 'debit' => 50000, 'credit' => 0, 'memo' => 'Opening cash on hand'],
        ['account_id' => $acc['1010'], 'debit' => 300000, 'credit' => 0, 'memo' => 'Opening bank - BDO'],
        ['account_id' => $acc['1020'], 'debit' => 150000, 'credit' => 0, 'memo' => 'Opening bank - BPI'],
        ['account_id' => $acc['3000'], 'debit' => 0, 'credit' => $openingTotal, 'memo' => "Owner's capital - opening"],
    ],
]);
out('Created 3 cash/bank accounts with opening balances (' . format_currency($openingTotal) . ' total).');

// ---------------------------------------------------------------------------
// 4. Tax types + control account settings
// ---------------------------------------------------------------------------
$db->prepare("INSERT INTO tax_types (code, name, rate_percent, is_active) VALUES (?,?,?,1)")->execute(['VAT', 'Value Added Tax', 12]);
$vatId = (int)$db->lastInsertId();
$db->prepare("INSERT INTO tax_types (code, name, rate_percent, is_active) VALUES (?,?,?,1)")->execute(['WHT', 'Withholding Tax', 2]);
$whtId = (int)$db->lastInsertId();

set_setting('ap_control_account_id', (string)$acc['2000']);
set_setting('ar_control_account_id', (string)$acc['1100']);
set_setting('input_tax_account_id', (string)$acc['1150']);
set_setting('output_tax_account_id', (string)$acc['2010']);
set_setting('withholding_tax_payable_account_id', (string)$acc['2020']);
set_setting('cash_runway_days_threshold', '60');
set_setting('ap_overdue_pct_threshold', '30');
set_setting('ar_aging_pct_threshold', '20');
set_setting('budget_warning_pct', '90');
set_setting('budget_critical_pct', '100');
set_setting('upcoming_payable_days', '30');
out('Configured tax types (VAT 12%, Withholding Tax 2%) and GL control accounts.');

// ---------------------------------------------------------------------------
// 5. Vendors and customers
// ---------------------------------------------------------------------------
$vendorNames = ['Boracay Island Tours Supplier', 'Cebu Pacific Air', 'Local Bus Transport Co.', 'Palawan Resort Partners'];
$vendors = [];
$stmt = $db->prepare("INSERT INTO ap_vendors (vendor_code, name, contact_person, email, payment_terms_days, status) VALUES (?,?,?,?,30,'Active')");
foreach ($vendorNames as $i => $name) {
    $code = 'V' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
    $stmt->execute([$code, $name, 'Contact ' . ($i + 1), strtolower(str_replace(' ', '', substr($name,0,10))) . '@supplier.test']);
    $vendors[] = (int)$db->lastInsertId();
}
$customerNames = ['Juan Dela Cruz', 'Maria Santos Family', 'ABC Corporate Events', 'Manila Travel Club'];
$customers = [];
$stmt = $db->prepare("INSERT INTO ar_customers (customer_code, name, contact_person, email, credit_terms_days, status) VALUES (?,?,?,?,15,'Active')");
foreach ($customerNames as $i => $name) {
    $code = 'C' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
    $stmt->execute([$code, $name, $name, strtolower(str_replace(' ', '', substr($name,0,10))) . '@customer.test']);
    $customers[] = (int)$db->lastInsertId();
}
out('Created ' . count($vendors) . ' vendors and ' . count($customers) . ' customers.');

// ---------------------------------------------------------------------------
// 6. Budget: FY2026 operating budget (approved)
// ---------------------------------------------------------------------------
$db->prepare("INSERT INTO budget_periods (name, start_date, end_date, status) VALUES (?,?,?, 'Open')")
   ->execute(['FY' . date('Y'), date('Y-01-01'), date('Y-12-31')]);
$periodId = (int)$db->lastInsertId();

$db->prepare("INSERT INTO budgets (budget_period_id, name, status, created_by, approved_by) VALUES (?,?, 'Approved', ?, ?)")
   ->execute([$periodId, 'Operating Budget ' . date('Y'), $ACCOUNTANT, $APPROVER]);
$budgetId = (int)$db->lastInsertId();

$budgetLineSpecs = [
    ['5100', 90000], // Salaries
    ['5200', 20000], // Rent
    ['5210', 8000],  // Utilities
    ['5300', 15000], // Marketing
    ['5220', 5000],  // Office supplies
];
foreach ($budgetLineSpecs as [$code, $monthlyAmt]) {
    $db->prepare("INSERT INTO budget_lines (budget_id, account_id) VALUES (?,?)")->execute([$budgetId, $acc[$code]]);
    $lineId = (int)$db->lastInsertId();
    $monthStmt = $db->prepare("INSERT INTO budget_line_monthly (budget_line_id, month, budgeted_amount) VALUES (?,?,?)");
    for ($m = 1; $m <= 12; $m++) { $monthStmt->execute([$lineId, $m, $monthlyAmt]); }
}
out('Created and approved FY' . date('Y') . ' operating budget with 5 account lines.');

// ---------------------------------------------------------------------------
// Helper functions mirroring the real app's posting logic
// ---------------------------------------------------------------------------
function seed_create_and_approve_bill(PDO $db, array $acc, int $vendorId, string $billDate, string $dueDate, array $lines, ?int $taxTypeId, int $createdBy, int $approvedBy): int {
    $subtotal = array_sum(array_column($lines, 'amount'));
    $taxAmount = $taxTypeId ? round($subtotal * 0.12, 2) : 0;
    $total = $subtotal + $taxAmount;
    $billNo = seed_doc_no('BILL', date('Y', strtotime($billDate)));

    $db->prepare("INSERT INTO ap_bills (bill_no, vendor_id, bill_date, due_date, subtotal, tax_amount, total_amount, amount_paid, status, created_by) VALUES (?,?,?,?,?,?,?,0,'Draft',?)")
       ->execute([$billNo, $vendorId, $billDate, $dueDate, $subtotal, $taxAmount, $total, $createdBy]);
    $billId = (int)$db->lastInsertId();

    $lineStmt = $db->prepare("INSERT INTO ap_bill_lines (bill_id, description, account_id, qty, unit_price, amount, tax_type_id) VALUES (?,?,?,1,?,?,?)");
    $jeLines = [];
    foreach ($lines as $l) {
        $lineStmt->execute([$billId, $l['description'], $l['account_id'], $l['amount'], $l['amount'], $taxTypeId]);
        $jeLines[] = ['account_id' => $l['account_id'], 'debit' => $l['amount'], 'credit' => 0, 'memo' => $l['description']];
    }
    if ($taxAmount > 0) $jeLines[] = ['account_id' => $acc['1150'], 'debit' => $taxAmount, 'credit' => 0, 'memo' => 'Input tax'];
    $jeLines[] = ['account_id' => $acc['2000'], 'debit' => 0, 'credit' => $total, 'memo' => 'AP - bill ' . $billNo];

    $entryId = post_journal_entry(['entry_date' => $billDate, 'reference' => $billNo, 'source_module' => 'ap', 'source_id' => $billId,
        'description' => 'AP Bill ' . $billNo, 'created_by' => $createdBy, 'lines' => $jeLines]);
    $db->prepare("UPDATE ap_bills SET status='Open', journal_entry_id=?, approved_by=? WHERE id=?")->execute([$entryId, $approvedBy, $billId]);

    if ($taxAmount > 0) {
        $db->prepare("INSERT INTO tax_transactions (tax_type_id, source_module, source_id, transaction_date, taxable_amount, tax_amount, direction, status) VALUES (?,?,?,?,?,?, 'Input', 'Pending')")
           ->execute([$taxTypeId, 'AP', $billId, $billDate, $subtotal, $taxAmount]);
    }
    return $billId;
}

function seed_pay_bill_full(PDO $db, array $acc, array $cash, int $billId, string $cashKey, string $paymentDate, int $createdBy): void {
    $bill = $db->prepare("SELECT * FROM ap_bills WHERE id = ?"); $bill->execute([$billId]); $b = $bill->fetch();
    $balance = (float)$b['total_amount'] - (float)$b['amount_paid'];
    if ($balance <= 0) return;
    $cashAccountId = $cash[$cashKey];
    $cashGl = $cashKey === 'Cash on Hand' ? $acc['1000'] : ($cashKey === 'Bank - BDO' ? $acc['1010'] : $acc['1020']);

    $paymentNo = seed_doc_no('APPMT', date('Y', strtotime($paymentDate)));
    $db->prepare("INSERT INTO ap_payments (payment_no, vendor_id, payment_date, amount, payment_method, cash_account_id, created_by) VALUES (?,?,?,?, 'Bank Transfer', ?, ?)")
       ->execute([$paymentNo, $b['vendor_id'], $paymentDate, $balance, $cashAccountId, $createdBy]);
    $paymentId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO ap_payment_applications (payment_id, bill_id, amount_applied) VALUES (?,?,?)")->execute([$paymentId, $billId, $balance]);
    $db->prepare("UPDATE ap_bills SET amount_paid = total_amount, status='Paid' WHERE id=?")->execute([$billId]);

    $entryId = post_journal_entry(['entry_date' => $paymentDate, 'reference' => $paymentNo, 'source_module' => 'ap', 'source_id' => $paymentId,
        'description' => 'AP Payment ' . $paymentNo, 'created_by' => $createdBy,
        'lines' => [['account_id' => $acc['2000'], 'debit' => $balance, 'credit' => 0, 'memo' => 'AP payment'], ['account_id' => $cashGl, 'debit' => 0, 'credit' => $balance, 'memo' => 'Cash paid']]]);
    $db->prepare("UPDATE ap_payments SET journal_entry_id=? WHERE id=?")->execute([$entryId, $paymentId]);
    $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$balance, $cashAccountId]);
    $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,'Withdrawal',?,?,?, 'ap', ?, 'Operating', ?, ?)")
       ->execute([$cashAccountId, $paymentDate, $balance, $paymentNo, 'AP payment', $paymentId, $entryId, $createdBy]);
}

function seed_create_and_approve_invoice(PDO $db, array $acc, int $customerId, string $invDate, string $dueDate, array $lines, ?int $taxTypeId, int $createdBy, int $approvedBy): int {
    $subtotal = array_sum(array_column($lines, 'amount'));
    $taxAmount = $taxTypeId ? round($subtotal * 0.12, 2) : 0;
    $total = $subtotal + $taxAmount;
    $invNo = seed_doc_no('INV', date('Y', strtotime($invDate)));

    $db->prepare("INSERT INTO ar_invoices (invoice_no, customer_id, invoice_date, due_date, subtotal, tax_amount, total_amount, amount_received, status, created_by) VALUES (?,?,?,?,?,?,?,0,'Draft',?)")
       ->execute([$invNo, $customerId, $invDate, $dueDate, $subtotal, $taxAmount, $total, $createdBy]);
    $invId = (int)$db->lastInsertId();

    $lineStmt = $db->prepare("INSERT INTO ar_invoice_lines (invoice_id, description, account_id, qty, unit_price, amount, tax_type_id) VALUES (?,?,?,1,?,?,?)");
    $jeLines = [];
    $jeLines[] = ['account_id' => $acc['1100'], 'debit' => $total, 'credit' => 0, 'memo' => 'AR - invoice ' . $invNo];
    foreach ($lines as $l) {
        $lineStmt->execute([$invId, $l['description'], $l['account_id'], $l['amount'], $l['amount'], $taxTypeId]);
        $jeLines[] = ['account_id' => $l['account_id'], 'debit' => 0, 'credit' => $l['amount'], 'memo' => $l['description']];
    }
    if ($taxAmount > 0) $jeLines[] = ['account_id' => $acc['2010'], 'debit' => 0, 'credit' => $taxAmount, 'memo' => 'Output tax'];

    $entryId = post_journal_entry(['entry_date' => $invDate, 'reference' => $invNo, 'source_module' => 'ar', 'source_id' => $invId,
        'description' => 'AR Invoice ' . $invNo, 'created_by' => $createdBy, 'lines' => $jeLines]);
    $db->prepare("UPDATE ar_invoices SET status='Open', journal_entry_id=?, approved_by=? WHERE id=?")->execute([$entryId, $approvedBy, $invId]);

    if ($taxAmount > 0) {
        $db->prepare("INSERT INTO tax_transactions (tax_type_id, source_module, source_id, transaction_date, taxable_amount, tax_amount, direction, status) VALUES (?,?,?,?,?,?, 'Output', 'Pending')")
           ->execute([$taxTypeId, 'AR', $invId, $invDate, $subtotal, $taxAmount]);
    }
    return $invId;
}

function seed_receive_invoice(PDO $db, array $acc, array $cash, int $invId, string $cashKey, string $receiptDate, int $createdBy, float $portion = 1.0): void {
    $inv = $db->prepare("SELECT * FROM ar_invoices WHERE id = ?"); $inv->execute([$invId]); $i = $inv->fetch();
    $balance = round(((float)$i['total_amount'] - (float)$i['amount_received']) * $portion, 2);
    if ($balance <= 0) return;
    $cashAccountId = $cash[$cashKey];
    $cashGl = $cashKey === 'Cash on Hand' ? $acc['1000'] : ($cashKey === 'Bank - BDO' ? $acc['1010'] : $acc['1020']);

    $receiptNo = seed_doc_no('ARCPT', date('Y', strtotime($receiptDate)));
    $db->prepare("INSERT INTO ar_receipts (receipt_no, customer_id, receipt_date, amount, payment_method, cash_account_id, created_by) VALUES (?,?,?,?, 'Bank Transfer', ?, ?)")
       ->execute([$receiptNo, $i['customer_id'], $receiptDate, $balance, $cashAccountId, $createdBy]);
    $receiptId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO ar_receipt_applications (receipt_id, invoice_id, amount_applied) VALUES (?,?,?)")->execute([$receiptId, $invId, $balance]);
    $newReceived = round((float)$i['amount_received'] + $balance, 2);
    $newStatus = $newReceived >= (float)$i['total_amount'] - 0.005 ? 'Paid' : 'PartiallyPaid';
    $db->prepare("UPDATE ar_invoices SET amount_received=?, status=? WHERE id=?")->execute([$newReceived, $newStatus, $invId]);

    $entryId = post_journal_entry(['entry_date' => $receiptDate, 'reference' => $receiptNo, 'source_module' => 'ar', 'source_id' => $receiptId,
        'description' => 'AR Receipt ' . $receiptNo, 'created_by' => $createdBy,
        'lines' => [['account_id' => $cashGl, 'debit' => $balance, 'credit' => 0, 'memo' => 'Cash received'], ['account_id' => $acc['1100'], 'debit' => 0, 'credit' => $balance, 'memo' => 'AR receipt']]]);
    $db->prepare("UPDATE ar_receipts SET journal_entry_id=? WHERE id=?")->execute([$entryId, $receiptId]);
    $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$balance, $cashAccountId]);
    $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,'Deposit',?,?,?, 'ar', ?, 'Operating', ?, ?)")
       ->execute([$cashAccountId, $receiptDate, $balance, $receiptNo, 'AR receipt', $receiptId, $entryId, $createdBy]);
}

function seed_pay_expense(PDO $db, array $acc, array $cash, string $cashKey, string $expenseCode, float $amount, string $date, string $description, int $createdBy): void {
    $cashAccountId = $cash[$cashKey];
    $cashGl = $cashKey === 'Cash on Hand' ? $acc['1000'] : ($cashKey === 'Bank - BDO' ? $acc['1010'] : $acc['1020']);
    $entryId = post_journal_entry(['entry_date' => $date, 'reference' => '', 'source_module' => 'cash', 'source_id' => null,
        'description' => $description, 'created_by' => $createdBy,
        'lines' => [['account_id' => $acc[$expenseCode], 'debit' => $amount, 'credit' => 0, 'memo' => $description], ['account_id' => $cashGl, 'debit' => 0, 'credit' => $amount, 'memo' => $description]]]);
    $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, description, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,'Withdrawal',?,?, 'Operating', ?, ?)")
       ->execute([$cashAccountId, $date, $amount, $description, $entryId, $createdBy]);
    $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $cashAccountId]);
}

// ---------------------------------------------------------------------------
// 7. Six-plus months of transaction history
// ---------------------------------------------------------------------------
$revenueAccounts = ['4000', '4010', '4020'];
$tourDescriptions = ['Boracay 3D2N Package', 'Palawan Island Hopping Tour', 'Cebu City + Beach Package', 'Baguio Weekend Getaway', 'Bohol Chocolate Hills Tour'];
$expenseDescriptions = ['Tour guide and permit fees', 'Hotel booking settlement', 'Land transport arrangement', 'Air ticketing services'];
$bankRotation = ['Bank - BDO', 'Bank - BPI', 'Cash on Hand'];

$monthCount = 0;
for ($m = $historyMonths - 1; $m >= 0; $m--) {
    $monthCount++;
    $monthStart = date('Y-m-01', strtotime("first day of -$m month"));
    $isCurrentMonth = ($m === 0);
    $invoiceIdsThisMonth = [];
    $billIdsThisMonth = [];

    // 4-7 AR invoices this month (sized so revenue plausibly covers payroll-scale fixed costs)
    $numInvoices = mt_rand(4, 7);
    for ($k = 0; $k < $numInvoices; $k++) {
        $day = mt_rand(1, 22);
        $invDate = date('Y-m-d', strtotime($monthStart . " +$day days"));
        $dueDate = date('Y-m-d', strtotime($invDate . ' +15 days'));
        $customerId = $customers[array_rand($customers)];
        $revCode = $revenueAccounts[array_rand($revenueAccounts)];
        $amount = mt_rand(20, 70) * 1000;
        $applyTax = mt_rand(0, 1) === 1;
        $invId = seed_create_and_approve_invoice($db, $acc, $customerId, $invDate, $dueDate,
            [['description' => $tourDescriptions[array_rand($tourDescriptions)], 'account_id' => $acc[$revCode], 'amount' => $amount]],
            $applyTax ? $vatId : null, $ACCOUNTANT, $APPROVER);
        $invoiceIdsThisMonth[] = ['id' => $invId, 'due' => $dueDate];
    }

    // 2 AP bills this month
    $numBills = mt_rand(1, 2);
    for ($k = 0; $k < $numBills; $k++) {
        $day = mt_rand(1, 22);
        $billDate = date('Y-m-d', strtotime($monthStart . " +$day days"));
        $dueDate = date('Y-m-d', strtotime($billDate . ' +30 days'));
        $vendorId = $vendors[array_rand($vendors)];
        $expCodes = ['5000','5010','5020'];
        $expCode = $expCodes[array_rand($expCodes)];
        $amount = mt_rand(10, 40) * 1000;
        $applyTax = mt_rand(0, 1) === 1;
        $billId = seed_create_and_approve_bill($db, $acc, $vendorId, $billDate, $dueDate,
            [['description' => $expenseDescriptions[array_rand($expenseDescriptions)], 'account_id' => $acc[$expCode], 'amount' => $amount]],
            $applyTax ? $vatId : null, $ACCOUNTANT, $APPROVER);
        $billIdsThisMonth[] = ['id' => $billId, 'due' => $dueDate];
    }

    // Recurring monthly operating expenses paid from bank -- alternate which account
    // carries the biggest fixed costs each month so neither bank account gets overloaded.
    $payDay = date('Y-m-d', strtotime($monthStart . ' +25 days'));
    $payrollAccount = $monthCount % 2 === 0 ? 'Bank - BDO' : 'Bank - BPI';
    $secondaryAccount = $monthCount % 2 === 0 ? 'Bank - BPI' : 'Bank - BDO';
    seed_pay_expense($db, $acc, $cash, $payrollAccount, '5100', 88000 + mt_rand(-2000, 2000), $payDay, 'Monthly salaries and wages', $ACCOUNTANT);
    seed_pay_expense($db, $acc, $cash, $secondaryAccount, '5200', 20000, date('Y-m-d', strtotime($monthStart . ' +5 days')), 'Office rent', $ACCOUNTANT);
    seed_pay_expense($db, $acc, $cash, $secondaryAccount, '5210', mt_rand(6000, 9000), date('Y-m-d', strtotime($monthStart . ' +10 days')), 'Utilities (power/water/internet)', $ACCOUNTANT);
    if (mt_rand(0, 1)) {
        seed_pay_expense($db, $acc, $cash, $payrollAccount, '5300', mt_rand(8000, 16000), date('Y-m-d', strtotime($monthStart . ' +15 days')), 'Marketing and social media ads', $ACCOUNTANT);
    }
    seed_pay_expense($db, $acc, $cash, 'Cash on Hand', '5800', mt_rand(500, 2000), date('Y-m-d', strtotime($monthStart . ' +18 days')), 'Miscellaneous office expense', $ACCOUNTANT);
    seed_pay_expense($db, $acc, $cash, 'Cash on Hand', '5220', mt_rand(3000, 6000), date('Y-m-d', strtotime($monthStart . ' +12 days')), 'Office supplies purchase', $ACCOUNTANT);

    // Collections and payments: settle most of what's NOT from the current (most recent) month,
    // leave current-month items open so aging/forecast/decision-support have real signal.
    if (!$isCurrentMonth) {
        foreach ($invoiceIdsThisMonth as $inv) {
            if (mt_rand(1, 100) <= 80) {
                $receiptDate = date('Y-m-d', strtotime($inv['due'] . ' -' . mt_rand(0, 10) . ' days'));
                seed_receive_invoice($db, $acc, $cash, $inv['id'], $bankRotation[array_rand($bankRotation)], $receiptDate, $ACCOUNTANT, mt_rand(1, 100) <= 85 ? 1.0 : 0.5);
            }
        }
        foreach ($billIdsThisMonth as $bill) {
            if (mt_rand(1, 100) <= 75) {
                $paymentDate = date('Y-m-d', strtotime($bill['due'] . ' -' . mt_rand(0, 10) . ' days'));
                seed_pay_bill_full($db, $acc, $cash, $bill['id'], $bankRotation[array_rand($bankRotation)], $paymentDate, $ACCOUNTANT);
            }
        }
    }
}
out("Generated $monthCount months of AR/AP activity, recurring operating expenses, and collections/payments.");

out('<strong>Seed complete.</strong> Log in with admin / accountant / approver / auditor using password Passw0rd!');
