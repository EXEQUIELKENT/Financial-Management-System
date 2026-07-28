<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('collection.create');

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $payerType = $_POST['payer_type'] ?? 'Other';
    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $payerName = trim($_POST['payer_name'] ?? '');
    $particulars = trim($_POST['particulars'] ?? '');
    $crDate = $_POST['cr_date'] ?? date('Y-m-d');
    $cashAccountId = (int)$_POST['cash_account_id'];

    $lines = [];
    $applyAmounts = $_POST['apply_invoice'] ?? [];
    foreach ($applyAmounts as $invoiceId => $amt) {
        $amt = (float)$amt;
        if ($amt > 0) $lines[] = ['account_id' => (int)get_setting('ar_control_account_id'), 'description' => 'Collection against invoice', 'amount' => $amt, 'ar_invoice_id' => (int)$invoiceId];
    }
    $adhocDesc = $_POST['adhoc_description'] ?? [];
    $adhocAccount = $_POST['adhoc_account_id'] ?? [];
    $adhocAmount = $_POST['adhoc_amount'] ?? [];
    foreach ($adhocDesc as $i => $desc) {
        $amt = (float)($adhocAmount[$i] ?? 0);
        if ($desc !== '' && !empty($adhocAccount[$i]) && $amt > 0) {
            $lines[] = ['account_id' => (int)$adhocAccount[$i], 'description' => $desc, 'amount' => $amt, 'ar_invoice_id' => null];
        }
    }
    $totalAmount = array_sum(array_column($lines, 'amount'));

    if ($payerName === '') $errors[] = 'Payer name is required.';
    if (!$cashAccountId) $errors[] = 'Cash/bank account is required.';
    if (empty($lines)) $errors[] = 'Add at least one invoice application or ad hoc collection line.';

    if (empty($errors)) {
        $db->beginTransaction();
        $crNo = next_document_no('CR', 'collection_receipts');
        $stmt = $db->prepare("INSERT INTO collection_receipts (cr_no, cr_date, payer_type, customer_id, payer_name, particulars, amount, cash_account_id, status, received_by) VALUES (?,?,?,?,?,?,?,?,'PendingApproval',?)");
        $stmt->execute([$crNo, $crDate, $payerType, $customerId, $payerName, $particulars, $totalAmount, $cashAccountId, current_user()['id']]);
        $crId = (int)$db->lastInsertId();

        $lineStmt = $db->prepare("INSERT INTO collection_receipt_lines (cr_id, account_id, description, amount, ar_invoice_id) VALUES (?,?,?,?,?)");
        foreach ($lines as $l) {
            $lineStmt->execute([$crId, $l['account_id'], $l['description'], $l['amount'], $l['ar_invoice_id']]);
        }
        $db->prepare("INSERT INTO cr_approval_history (cr_id, action, actor_id, comments) VALUES (?, 'Submitted', ?, ?)")
           ->execute([$crId, current_user()['id'], 'Submitted for approval']);
        $db->commit();

        log_audit('create', 'collection', $crId, 'Created collection receipt ' . $crNo);
        flash('success', 'Collection receipt submitted for approval.');
        redirect('modules/collection/receipt-view.php?id=' . $crId);
    }
}

$customers = $db->query("SELECT id, name FROM ar_customers WHERE status='Active' ORDER BY name")->fetchAll();
$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();
$revenueAccounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active=1 AND account_type IN ('Revenue','Asset') ORDER BY account_code")->fetchAll();

$selectedCustomer = (int)($_GET['customer_id'] ?? 0);
$openInvoices = [];
if ($selectedCustomer) {
    $stmt = $db->prepare("SELECT * FROM ar_invoices WHERE customer_id = ? AND status IN ('Open','PartiallyPaid') ORDER BY due_date");
    $stmt->execute([$selectedCustomer]);
    $openInvoices = $stmt->fetchAll();
}

$pageTitle = 'New Collection Receipt';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="crForm">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label>Payer Type</label>
                <select name="payer_type" id="payerType" onchange="toggleCustomer()">
                    <option value="Customer">Customer</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group" id="customerSelectGroup">
                <label>Customer</label>
                <select name="customer_id" id="customerSelect" onchange="reloadInvoices()">
                    <option value="">— Select customer —</option>
                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" <?= $selectedCustomer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Payer Name</label><input type="text" name="payer_name" class="form-control" value="<?= old('payer_name') ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>CR Date</label><input type="date" name="cr_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Deposit To</label>
                <select name="cash_account_id" required>
                    <option value="">— Select cash/bank account —</option>
                    <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?> (<?= format_currency($c['current_balance']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Particulars</label><input type="text" name="particulars" class="form-control" value="<?= old('particulars') ?>"></div>

        <h4>Apply Against Open Invoices (optional)</h4>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Invoice No.</th><th>Due Date</th><th class="num">Balance</th><th class="num">Apply Amount</th></tr></thead>
            <tbody>
            <?php foreach ($openInvoices as $i): $balance = $i['total_amount'] - $i['amount_received']; ?>
                <tr>
                    <td><?= e($i['invoice_no']) ?></td>
                    <td><?= format_date($i['due_date']) ?></td>
                    <td class="num"><?= format_currency($balance) ?></td>
                    <td class="num"><input type="number" step="0.01" name="apply_invoice[<?= $i['id'] ?>]" class="form-control" value="0" onchange="calcTotal()"></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($openInvoices)): ?><tr><td colspan="4" class="empty-state">Select a customer to see open invoices.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <h4>Ad Hoc Collection Lines (optional, for miscellaneous income)</h4>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Description</th><th>Account</th><th class="num">Amount</th><th></th></tr></thead>
            <tbody id="adhocBody">
                <tr>
                    <td><input type="text" name="adhoc_description[]" class="form-control"></td>
                    <td><select name="adhoc_account_id[]"><option value="">—</option><?php foreach ($revenueAccounts as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['account_code'].' - '.$a['account_name']) ?></option><?php endforeach; ?></select></td>
                    <td><input type="number" step="0.01" name="adhoc_amount[]" class="form-control adhocAmt" value="0" onchange="calcTotal()"></td>
                    <td><button type="button" class="btn btn-outline btn-sm" onclick="removeAdhoc(this)">✕</button></td>
                </tr>
            </tbody>
            <tfoot><tr><td colspan="4"><button type="button" class="btn btn-outline btn-sm" onclick="addAdhoc()">+ Add Line</button></td></tr></tfoot>
        </table>
        </div>
        <div class="form-group" style="max-width:240px;"><label>Total Amount</label><input type="text" id="totalDisp" class="form-control" readonly value="0.00"></div>
        <button type="submit" class="btn btn-primary">Submit for Approval</button>
        <a href="receipts.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<script>
function toggleCustomer() {
    document.getElementById('customerSelectGroup').style.display = document.getElementById('payerType').value === 'Customer' ? 'block' : 'none';
}
function reloadInvoices() {
    var c = document.getElementById('customerSelect').value;
    window.location = 'receipt-form.php' + (c ? '?customer_id=' + c : '');
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
    document.querySelectorAll('input[name^="apply_invoice"]').forEach(function(i){ total += parseFloat(i.value || 0); });
    document.querySelectorAll('.adhocAmt').forEach(function(i){ total += parseFloat(i.value || 0); });
    document.getElementById('totalDisp').value = total.toFixed(2);
}
toggleCustomer();
calcTotal();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
