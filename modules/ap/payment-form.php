<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ap.create');

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $vendorId = (int)$_POST['vendor_id'];
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'Bank Transfer';
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $cashAccountId = (int)$_POST['cash_account_id'];
    $applyAmounts = $_POST['apply'] ?? []; // bill_id => amount
    $withholdTaxTypeId = !empty($_POST['withhold_tax_type_id']) ? (int)$_POST['withhold_tax_type_id'] : null;
    $withheldAmount = parse_nonnegative_amount($_POST['withheld_amount'] ?? '', 'Withheld amount', $errors);

    $applications = parse_amount_map($applyAmounts, $errors);
    $grossAmount = round(array_sum($applications), 2);
    if (!$vendorId) $errors[] = 'Vendor is required.';
    if (!$cashAccountId) $errors[] = 'Cash/bank account is required.';
    if (empty($applications)) $errors[] = 'Apply the payment to at least one open bill.';
    if ($withheldAmount > $grossAmount) {
        $errors[] = 'Withheld amount cannot exceed the gross payment amount.';
    } elseif ($withheldAmount > 0) {
        if (!$withholdTaxTypeId) $errors[] = 'Choose the withholding tax type for the withheld amount.';
        if (!(int)get_setting('withholding_tax_payable_account_id')) $errors[] = 'Withholding Tax Payable account is not configured. Ask an Admin to set it in Settings.';
    }
    if (!(int)get_setting('ap_control_account_id')) $errors[] = 'AP control account is not configured. Ask an Admin to set it in Settings.';
    if (empty($errors) && $vendorId) {
        $errors = apply_to_open_documents('ap', $vendorId, $applications, true);
    }

    $cashStmt = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ? AND status = 'Active'");
    $cashStmt->execute([$cashAccountId]);
    $cashAcct = $cashStmt->fetch();
    if ($cashAccountId && !$cashAcct) $errors[] = 'Selected cash/bank account was not found or is inactive.';

    if (empty($errors)) {
        $cashPaid = round($grossAmount - $withheldAmount, 2);
        $apAccountId = (int)get_setting('ap_control_account_id');
        $whtPayableId = $withheldAmount > 0 ? (int)get_setting('withholding_tax_payable_account_id') : null;

        $db->beginTransaction();
        try {
            $paymentNo = next_document_no('APPMT', 'ap_payments');
            $stmt = $db->prepare("INSERT INTO ap_payments (payment_no, vendor_id, payment_date, amount, payment_method, reference_no, cash_account_id, created_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$paymentNo, $vendorId, $paymentDate, $grossAmount, $paymentMethod, $referenceNo, $cashAccountId, current_user()['id']]);
            $paymentId = (int)$db->lastInsertId();

            $appStmt = $db->prepare("INSERT INTO ap_payment_applications (payment_id, bill_id, amount_applied) VALUES (?,?,?)");
            foreach ($applications as $billId => $amt) {
                $appStmt->execute([$paymentId, $billId, $amt]);
            }
            apply_to_open_documents('ap', $vendorId, $applications);

            $jeLines = [];
            $jeLines[] = ['account_id' => $apAccountId, 'debit' => $grossAmount, 'credit' => 0, 'memo' => 'AP payment ' . $paymentNo];
            $jeLines[] = ['account_id' => $cashAcct['gl_account_id'], 'debit' => 0, 'credit' => $cashPaid, 'memo' => 'Cash paid - ' . $cashAcct['account_name']];
            if ($withheldAmount > 0 && $whtPayableId) {
                $jeLines[] = ['account_id' => $whtPayableId, 'debit' => 0, 'credit' => $withheldAmount, 'memo' => 'Withholding tax withheld'];
            }
            $entryId = post_journal_entry([
                'entry_date' => $paymentDate,
                'reference' => $paymentNo,
                'source_module' => 'ap',
                'source_id' => $paymentId,
                'description' => 'AP Payment ' . $paymentNo,
                'created_by' => current_user()['id'],
                'lines' => $jeLines,
            ]);
            $db->prepare("UPDATE ap_payments SET journal_entry_id = ? WHERE id = ?")->execute([$entryId, $paymentId]);

            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$cashPaid, $cashAccountId]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$cashAccountId, $paymentDate, 'Withdrawal', $cashPaid, $paymentNo, 'AP payment', 'ap', $paymentId, 'Operating', $entryId, current_user()['id']]);

            if ($withheldAmount > 0) {
                $db->prepare("INSERT INTO tax_transactions (tax_type_id, source_module, source_id, transaction_date, taxable_amount, tax_amount, direction, status) VALUES (?,?,?,?,?,?,?,'Pending')")
                   ->execute([$withholdTaxTypeId, 'AP', $paymentId, $paymentDate, $grossAmount, $withheldAmount, 'Withholding']);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Could not record payment: ' . $e->getMessage();
        }
        if (empty($errors)) {
            log_audit('create', 'ap', $paymentId, 'Recorded AP payment ' . $paymentNo);
            flash('success', 'Payment recorded and posted to the general ledger.');
            redirect('modules/ap/vendor-view.php?id=' . $vendorId);
        }
    }
}

$vendors = $db->query("SELECT id, name FROM ap_vendors WHERE status='Active' ORDER BY name")->fetchAll();
$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();
$taxTypes = $db->query("SELECT id, name, rate_percent FROM tax_types WHERE is_active=1")->fetchAll();

$selectedVendor = (int)($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0);
$openBills = [];
if ($selectedVendor) {
    $stmt = $db->prepare("SELECT * FROM ap_bills WHERE vendor_id = ? AND status IN ('Open','PartiallyPaid') ORDER BY due_date");
    $stmt->execute([$selectedVendor]);
    $openBills = $stmt->fetchAll();
}

$pageTitle = 'New AP Payment';
$pageHelp = [
    ['selector' => 'select[name="vendor_id"]',
        'en' => ['title' => 'Vendor', 'body' => 'Selecting one loads that vendor\'s open bills below.'],
        'tl' => ['title' => 'Vendor', 'body' => 'Kapag pumili, ilo-load ang open na bills ng vendor na iyon sa ibaba.']],
    ['selector' => '.applyAmt',
        'en' => ['title' => 'Apply Amount column', 'body' => 'Enter how much to pay toward each open bill — full or partial, across as many bills as you like.'],
        'tl' => ['title' => 'Apply Amount Column', 'body' => 'Ilagay kung magkano ang ibabayad sa bawat open na bill — buo o bahagi, sa kahit ilang bill.']],
    ['selector' => '#whtType',
        'en' => ['title' => 'Withhold Tax?', 'body' => 'Optional — deducts a withholding tax percentage from the gross amount; the net cash actually paid out is reduced accordingly.'],
        'tl' => ['title' => 'Withhold Tax?', 'body' => 'Opsyonal — magbabawas ng withholding tax percentage mula sa gross amount; mababawasan din ang aktwal na cash na ibabayad.']],
    ['selector' => '#netDisp',
        'en' => ['title' => 'Net Cash Paid', 'body' => 'What actually leaves the cash/bank account, after any withholding.'],
        'tl' => ['title' => 'Net Cash Paid', 'body' => 'Ang aktwal na lalabas sa cash/bank account, pagkatapos ng anumang withholding.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="get" class="form-row">
        <div class="form-group">
            <label>Vendor</label>
            <select name="vendor_id" onchange="this.form.submit()">
                <option value="">— Select vendor —</option>
                <?php foreach ($vendors as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $selectedVendor === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($selectedVendor): ?>
    <form method="post" id="paymentForm">
        <?= csrf_field() ?>
        <input type="hidden" name="vendor_id" value="<?= $selectedVendor ?>">
        <div class="form-row">
            <div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Payment Method</label>
                <select name="payment_method">
                    <?php foreach (['Bank Transfer','Check','Cash','Credit Card'] as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Reference No.</label><input type="text" name="reference_no" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Pay From</label>
                <select name="cash_account_id" required>
                    <option value="">— Select cash/bank account —</option>
                    <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?> (<?= format_currency($c['current_balance']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <h4>Open Bills</h4>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Bill No.</th><th>Due Date</th><th class="num">Balance</th><th class="num">Apply Amount</th></tr></thead>
            <tbody>
            <?php foreach ($openBills as $b): $balance = $b['total_amount'] - $b['amount_paid']; ?>
                <tr>
                    <td><?= e($b['bill_no']) ?></td>
                    <td><?= format_date($b['due_date']) ?></td>
                    <td class="num"><?= format_currency($balance) ?></td>
                    <td class="num"><input type="text" inputmode="decimal" autocomplete="off" name="apply[<?= $b['id'] ?>]" class="money form-control applyAmt" data-max="<?= $balance ?>" value="0" onchange="calcGross()"></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($openBills)): ?><tr><td colspan="4" class="empty-state">No open bills for this vendor.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <div class="form-row" style="margin-top:16px;">
            <div class="form-group"><label>Gross Amount Applied</label><input type="text" id="grossDisp" class="form-control" readonly value="0.00"></div>
            <div class="form-group"><label>Withhold Tax? (optional)</label>
                <select name="withhold_tax_type_id" id="whtType" onchange="calcGross()">
                    <option value="">None</option>
                    <?php foreach ($taxTypes as $t): ?><option value="<?= $t['id'] ?>" data-rate="<?= $t['rate_percent'] ?>"><?= e($t['name']) ?> (<?= $t['rate_percent'] ?>%)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Withheld Amount</label><input type="text" inputmode="decimal" autocomplete="off" name="withheld_amount" id="whtAmount" class="money form-control" value="0" onchange="calcGross(true)"></div>
            <div class="form-group"><label>Net Cash Paid</label><input type="text" id="netDisp" class="form-control" readonly value="0.00"></div>
        </div>
        <button type="submit" class="btn btn-primary">Record Payment</button>
        <a href="payments.php" class="btn btn-outline">Cancel</a>
    </form>
    <?php endif; ?>
</div>
<script>
function calcGross(manualWht) {
    var gross = 0;
    document.querySelectorAll('.applyAmt').forEach(function(i){ gross += (parseMoney(i.value) || 0); });
    document.getElementById('grossDisp').value = gross.toFixed(2);
    var whtSel = document.getElementById('whtType');
    var whtInput = document.getElementById('whtAmount');
    if (!manualWht) {
        var rate = whtSel.selectedOptions[0] ? parseFloat(whtSel.selectedOptions[0].getAttribute('data-rate') || 0) : 0;
        whtInput.value = (gross * rate / 100).toFixed(2);
    }
    var net = gross - (parseMoney(whtInput.value) || 0);
    document.getElementById('netDisp').value = net.toFixed(2);
}
if (document.getElementById('paymentForm')) calcGross();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
