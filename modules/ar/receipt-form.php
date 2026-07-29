<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ar.create');

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $customerId = (int)$_POST['customer_id'];
    $receiptDate = $_POST['receipt_date'] ?? date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'Bank Transfer';
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $cashAccountId = (int)$_POST['cash_account_id'];
    $applyAmounts = $_POST['apply'] ?? [];

    $applications = [];
    $amount = 0;
    foreach ($applyAmounts as $invoiceId => $amt) {
        $amt = (float)$amt;
        if ($amt > 0) { $applications[(int)$invoiceId] = $amt; $amount += $amt; }
    }
    if (!$customerId) $errors[] = 'Customer is required.';
    if (!$cashAccountId) $errors[] = 'Cash/bank account is required.';
    if (empty($applications)) $errors[] = 'Apply the receipt to at least one open invoice.';

    if (empty($errors)) {
        $arAccountId = (int)get_setting('ar_control_account_id');
        $cashStmt = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ?");
        $cashStmt->execute([$cashAccountId]);
        $cashAcct = $cashStmt->fetch();

        try {
            $receiptNo = next_document_no('ARCPT', 'ar_receipts');
            $stmt = $db->prepare("INSERT INTO ar_receipts (receipt_no, customer_id, receipt_date, amount, payment_method, reference_no, cash_account_id, created_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$receiptNo, $customerId, $receiptDate, $amount, $paymentMethod, $referenceNo, $cashAccountId, current_user()['id']]);
            $receiptId = (int)$db->lastInsertId();

            $appStmt = $db->prepare("INSERT INTO ar_receipt_applications (receipt_id, invoice_id, amount_applied) VALUES (?,?,?)");
            foreach ($applications as $invoiceId => $amt) {
                $appStmt->execute([$receiptId, $invoiceId, $amt]);
                $inv = $db->prepare("SELECT total_amount, amount_received FROM ar_invoices WHERE id = ?");
                $inv->execute([$invoiceId]);
                $i = $inv->fetch();
                $newReceived = round($i['amount_received'] + $amt, 2);
                $newStatus = $newReceived >= (float)$i['total_amount'] - 0.005 ? 'Paid' : 'PartiallyPaid';
                $db->prepare("UPDATE ar_invoices SET amount_received = ?, status = ? WHERE id = ?")->execute([$newReceived, $newStatus, $invoiceId]);
            }

            $entryId = post_journal_entry([
                'entry_date' => $receiptDate,
                'reference' => $receiptNo,
                'source_module' => 'ar',
                'source_id' => $receiptId,
                'description' => 'AR Receipt ' . $receiptNo,
                'created_by' => current_user()['id'],
                'lines' => [
                    ['account_id' => $cashAcct['gl_account_id'], 'debit' => $amount, 'credit' => 0, 'memo' => 'Cash received - ' . $cashAcct['account_name']],
                    ['account_id' => $arAccountId, 'debit' => 0, 'credit' => $amount, 'memo' => 'AR receipt ' . $receiptNo],
                ],
            ]);
            $db->prepare("UPDATE ar_receipts SET journal_entry_id = ? WHERE id = ?")->execute([$entryId, $receiptId]);

            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $cashAccountId]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$cashAccountId, $receiptDate, 'Deposit', $amount, $receiptNo, 'AR receipt', 'ar', $receiptId, 'Operating', $entryId, current_user()['id']]);

            log_audit('create', 'ar', $receiptId, 'Recorded AR receipt ' . $receiptNo);
            flash('success', 'Receipt recorded and posted to the general ledger.');
            redirect('modules/ar/customer-view.php?id=' . $customerId);
        } catch (Throwable $e) {
            $errors[] = 'Could not record receipt: ' . $e->getMessage();
        }
    }
}

$customers = $db->query("SELECT id, name FROM ar_customers WHERE status='Active' ORDER BY name")->fetchAll();
$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();

$selectedCustomer = (int)($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$openInvoices = [];
if ($selectedCustomer) {
    $stmt = $db->prepare("SELECT * FROM ar_invoices WHERE customer_id = ? AND status IN ('Open','PartiallyPaid') ORDER BY due_date");
    $stmt->execute([$selectedCustomer]);
    $openInvoices = $stmt->fetchAll();
}

$pageTitle = 'New AR Receipt';
$pageHelp = [
    ['selector' => 'select[name="customer_id"]',
        'en' => ['title' => 'Customer', 'body' => 'Selecting one loads that customer\'s open invoices below.'],
        'tl' => ['title' => 'Customer', 'body' => 'Kapag pumili, ilo-load ang open na invoices ng customer na iyon sa ibaba.']],
    ['selector' => '.applyAmt',
        'en' => ['title' => 'Apply Amount column', 'body' => 'Enter how much of this receipt to apply toward each open invoice — full or partial, across as many invoices as you like.'],
        'tl' => ['title' => 'Apply Amount Column', 'body' => 'Ilagay kung magkano sa receipt na ito ang ilalapat sa bawat open na invoice — buo o bahagi, sa kahit ilang invoice.']],
    ['selector' => '#totalDisp',
        'en' => ['title' => 'Total Amount', 'body' => 'Sums everything you\'ve applied — this is what gets deposited into the chosen cash/bank account.'],
        'tl' => ['title' => 'Total Amount', 'body' => 'Kabuuan ng lahat ng inilapat mo — ito ang ide-deposito sa piniling cash/bank account.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="get" class="form-row">
        <div class="form-group">
            <label>Customer</label>
            <select name="customer_id" onchange="this.form.submit()">
                <option value="">— Select customer —</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCustomer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($selectedCustomer): ?>
    <form method="post" id="receiptForm">
        <?= csrf_field() ?>
        <input type="hidden" name="customer_id" value="<?= $selectedCustomer ?>">
        <div class="form-row">
            <div class="form-group"><label>Receipt Date</label><input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Payment Method</label>
                <select name="payment_method">
                    <?php foreach (['Bank Transfer','Check','Cash','Credit Card'] as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Reference No.</label><input type="text" name="reference_no" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Deposit To</label>
                <select name="cash_account_id" required>
                    <option value="">— Select cash/bank account —</option>
                    <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?> (<?= format_currency($c['current_balance']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <h4>Open Invoices</h4>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Invoice No.</th><th>Due Date</th><th class="num">Balance</th><th class="num">Apply Amount</th></tr></thead>
            <tbody>
            <?php foreach ($openInvoices as $i): $balance = $i['total_amount'] - $i['amount_received']; ?>
                <tr>
                    <td><?= e($i['invoice_no']) ?></td>
                    <td><?= format_date($i['due_date']) ?></td>
                    <td class="num"><?= format_currency($balance) ?></td>
                    <td class="num"><input type="number" step="0.01" name="apply[<?= $i['id'] ?>]" class="form-control applyAmt" value="0" onchange="calcTotal()"></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($openInvoices)): ?><tr><td colspan="4" class="empty-state">No open invoices for this customer.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <div class="form-group" style="max-width:240px;margin-top:16px;"><label>Total Amount</label><input type="text" id="totalDisp" class="form-control" readonly value="0.00"></div>
        <button type="submit" class="btn btn-primary">Record Receipt</button>
        <a href="receipts.php" class="btn btn-outline">Cancel</a>
    </form>
    <?php endif; ?>
</div>
<script>
function calcTotal() {
    var total = 0;
    document.querySelectorAll('.applyAmt').forEach(function(i){ total += parseFloat(i.value || 0); });
    document.getElementById('totalDisp').value = total.toFixed(2);
}
calcTotal();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
