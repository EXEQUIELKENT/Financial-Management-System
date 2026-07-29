<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('disbursement.create');

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $payeeType = $_POST['payee_type'] ?? 'Other';
    $vendorId = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $payeeName = trim($_POST['payee_name'] ?? '');
    $particulars = trim($_POST['particulars'] ?? '');
    $dvDate = $_POST['dv_date'] ?? date('Y-m-d');
    $cashAccountId = (int)$_POST['cash_account_id'];

    $lines = [];
    $applyAmounts = $_POST['apply_bill'] ?? [];
    foreach ($applyAmounts as $billId => $amt) {
        $amt = (float)$amt;
        if ($amt > 0) $lines[] = ['account_id' => (int)get_setting('ap_control_account_id'), 'description' => 'Settlement of bill', 'amount' => $amt, 'ap_bill_id' => (int)$billId];
    }
    $adhocDesc = $_POST['adhoc_description'] ?? [];
    $adhocAccount = $_POST['adhoc_account_id'] ?? [];
    $adhocAmount = $_POST['adhoc_amount'] ?? [];
    foreach ($adhocDesc as $i => $desc) {
        $amt = (float)($adhocAmount[$i] ?? 0);
        if ($desc !== '' && !empty($adhocAccount[$i]) && $amt > 0) {
            $lines[] = ['account_id' => (int)$adhocAccount[$i], 'description' => $desc, 'amount' => $amt, 'ap_bill_id' => null];
        }
    }
    $totalAmount = array_sum(array_column($lines, 'amount'));

    if ($payeeName === '') $errors[] = 'Payee name is required.';
    if (!$cashAccountId) $errors[] = 'Cash/bank account is required.';
    if (empty($lines)) $errors[] = 'Add at least one bill settlement or ad hoc expense line.';

    if (empty($errors)) {
        $db->beginTransaction();
        $dvNo = next_document_no('DV', 'disbursement_vouchers');
        $stmt = $db->prepare("INSERT INTO disbursement_vouchers (dv_no, dv_date, payee_type, vendor_id, payee_name, particulars, amount, cash_account_id, status, requested_by) VALUES (?,?,?,?,?,?,?,?,'PendingApproval',?)");
        $stmt->execute([$dvNo, $dvDate, $payeeType, $vendorId, $payeeName, $particulars, $totalAmount, $cashAccountId, current_user()['id']]);
        $dvId = (int)$db->lastInsertId();

        $lineStmt = $db->prepare("INSERT INTO disbursement_voucher_lines (dv_id, account_id, description, amount, ap_bill_id) VALUES (?,?,?,?,?)");
        foreach ($lines as $l) {
            $lineStmt->execute([$dvId, $l['account_id'], $l['description'], $l['amount'], $l['ap_bill_id']]);
        }
        $db->prepare("INSERT INTO dv_approval_history (dv_id, action, actor_id, comments) VALUES (?, 'Submitted', ?, ?)")
           ->execute([$dvId, current_user()['id'], 'Submitted for approval']);
        $db->commit();

        log_audit('create', 'disbursement', $dvId, 'Created disbursement voucher ' . $dvNo);
        flash('success', 'Disbursement voucher submitted for approval.');
        redirect('modules/disbursement/voucher-view.php?id=' . $dvId);
    }
}

$vendors = $db->query("SELECT id, name FROM ap_vendors WHERE status='Active' ORDER BY name")->fetchAll();
$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();
$expenseAccounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active=1 AND account_type IN ('Expense','Asset') ORDER BY account_code")->fetchAll();

$selectedVendor = (int)($_GET['vendor_id'] ?? 0);
$openBills = [];
if ($selectedVendor) {
    $stmt = $db->prepare("SELECT * FROM ap_bills WHERE vendor_id = ? AND status IN ('Open','PartiallyPaid') ORDER BY due_date");
    $stmt->execute([$selectedVendor]);
    $openBills = $stmt->fetchAll();
}

$pageTitle = 'New Disbursement Voucher';
$pageHelp = [
    ['selector' => '#payeeType',
        'en' => ['title' => 'Payee Type', 'body' => 'Vendor shows a vendor picker and lets you settle their open bills below; Employee/Other skip straight to ad hoc lines.'],
        'tl' => ['title' => 'Payee Type', 'body' => 'Ipinapakita ng Vendor ang vendor picker at puwede mong i-settle ang mga open bill nila sa ibaba; ang Employee/Other ay dumideretso sa ad hoc lines.']],
    ['selector' => '.table-wrap', 'nth' => 0,
        'en' => ['title' => 'Settle Open AP Bills', 'body' => 'Optional — check off a vendor\'s open bills and how much to apply toward each.'],
        'tl' => ['title' => 'Settle Open AP Bills', 'body' => 'Opsyonal — i-check ang mga open bill ng vendor at magkano ang ilalapat sa bawat isa.']],
    ['selector' => '.table-wrap', 'nth' => 1,
        'en' => ['title' => 'Ad Hoc Expense Lines', 'body' => 'For cash advances or expenses not tied to an existing AP bill — pick an account, description, and amount.'],
        'tl' => ['title' => 'Ad Hoc Expense Lines', 'body' => 'Para sa cash advance o gastos na hindi kabit sa umiiral na AP bill — pumili ng account, description, at halaga.']],
    ['selector' => 'button[type="submit"]',
        'en' => ['title' => 'Submit for Approval', 'body' => 'Sends the voucher straight to Pending Approval — there is no separate Draft stage to save first.'],
        'tl' => ['title' => 'Isumite para sa Approval', 'body' => 'Direktang ipapadala ang voucher sa Pending Approval — walang hiwalay na Draft stage bago ito.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="dvForm">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label>Payee Type</label>
                <select name="payee_type" id="payeeType" onchange="toggleVendor()">
                    <option value="Vendor">Vendor</option>
                    <option value="Employee">Employee</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group" id="vendorSelectGroup">
                <label>Vendor</label>
                <select name="vendor_id" id="vendorSelect" onchange="reloadBills()">
                    <option value="">— Select vendor —</option>
                    <?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>" <?= $selectedVendor === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Payee Name</label><input type="text" name="payee_name" id="payeeName" class="form-control" value="<?= old('payee_name') ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>DV Date</label><input type="date" name="dv_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Pay From</label>
                <select name="cash_account_id" required>
                    <option value="">— Select cash/bank account —</option>
                    <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?> (<?= format_currency($c['current_balance']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Particulars</label><input type="text" name="particulars" class="form-control" value="<?= old('particulars') ?>" placeholder="Purpose of this disbursement"></div>

        <h4>Settle Open AP Bills (optional)</h4>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Bill No.</th><th>Due Date</th><th class="num">Balance</th><th class="num">Apply Amount</th></tr></thead>
            <tbody>
            <?php foreach ($openBills as $b): $balance = $b['total_amount'] - $b['amount_paid']; ?>
                <tr>
                    <td><?= e($b['bill_no']) ?></td>
                    <td><?= format_date($b['due_date']) ?></td>
                    <td class="num"><?= format_currency($balance) ?></td>
                    <td class="num"><input type="number" step="0.01" name="apply_bill[<?= $b['id'] ?>]" class="form-control" value="0" onchange="calcTotal()"></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($openBills)): ?><tr><td colspan="4" class="empty-state">Select a vendor to see open bills.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <h4>Ad Hoc Expense Lines (optional, for cash advances / non-AP expenses)</h4>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Description</th><th>Account</th><th class="num">Amount</th><th></th></tr></thead>
            <tbody id="adhocBody">
                <tr>
                    <td><input type="text" name="adhoc_description[]" class="form-control"></td>
                    <td><select name="adhoc_account_id[]"><option value="">—</option><?php foreach ($expenseAccounts as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['account_code'].' - '.$a['account_name']) ?></option><?php endforeach; ?></select></td>
                    <td><input type="number" step="0.01" name="adhoc_amount[]" class="form-control adhocAmt" value="0" onchange="calcTotal()"></td>
                    <td><button type="button" class="btn btn-outline btn-sm" onclick="removeAdhoc(this)">✕</button></td>
                </tr>
            </tbody>
            <tfoot><tr><td colspan="4"><button type="button" class="btn btn-outline btn-sm" onclick="addAdhoc()">+ Add Line</button></td></tr></tfoot>
        </table>
        </div>
        <div class="form-group" style="max-width:240px;"><label>Total Amount</label><input type="text" id="totalDisp" class="form-control" readonly value="0.00"></div>
        <button type="submit" class="btn btn-primary">Submit for Approval</button>
        <a href="vouchers.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<script>
function toggleVendor() {
    document.getElementById('vendorSelectGroup').style.display = document.getElementById('payeeType').value === 'Vendor' ? 'block' : 'none';
}
function reloadBills() {
    var v = document.getElementById('vendorSelect').value;
    window.location = 'voucher-form.php' + (v ? '?vendor_id=' + v : '');
}
var adhocTemplate = document.querySelector('#adhocBody tr').outerHTML;
function addAdhoc() {
    var tbody = document.getElementById('adhocBody');
    var div = document.createElement('tbody'); div.innerHTML = adhocTemplate;
    var row = div.firstElementChild;
    row.querySelectorAll('input').forEach(function(i){ i.value = i.type === 'number' ? 0 : ''; });
    row.querySelector('select').selectedIndex = 0;
    tbody.appendChild(row);
}
function removeAdhoc(btn) { btn.closest('tr').remove(); calcTotal(); }
function calcTotal() {
    var total = 0;
    document.querySelectorAll('input[name^="apply_bill"]').forEach(function(i){ total += parseFloat(i.value || 0); });
    document.querySelectorAll('.adhocAmt').forEach(function(i){ total += parseFloat(i.value || 0); });
    document.getElementById('totalDisp').value = total.toFixed(2);
}
toggleVendor();
calcTotal();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
