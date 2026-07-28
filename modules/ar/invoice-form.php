<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ar.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$invoice = ['customer_id' => '', 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'), 'lines' => []];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM ar_invoices WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash('error', 'Invoice not found.'); redirect('modules/ar/invoices.php'); }
    if ($found['status'] !== 'Draft') { flash('error', 'Only Draft invoices can be edited.'); redirect('modules/ar/invoice-view.php?id=' . $id); }
    $invoice = $found;
    $lineStmt = $db->prepare("SELECT * FROM ar_invoice_lines WHERE invoice_id = ?");
    $lineStmt->execute([$id]);
    $invoice['lines'] = $lineStmt->fetchAll();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? date('Y-m-d');
    $descriptions = $_POST['description'] ?? [];
    $accountIds = $_POST['account_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['unit_price'] ?? [];
    $taxTypeIds = $_POST['tax_type_id'] ?? [];

    $lines = [];
    $subtotal = 0; $taxAmount = 0;
    foreach ($descriptions as $i => $desc) {
        if (empty($accountIds[$i]) || $desc === '') continue;
        $qty = (float)($qtys[$i] ?? 1);
        $price = (float)($prices[$i] ?? 0);
        $amount = round($qty * $price, 2);
        $taxTypeId = !empty($taxTypeIds[$i]) ? (int)$taxTypeIds[$i] : null;
        $lineTax = 0;
        if ($taxTypeId) {
            $rateStmt = $db->prepare("SELECT rate_percent FROM tax_types WHERE id = ?");
            $rateStmt->execute([$taxTypeId]);
            $rate = (float)($rateStmt->fetchColumn() ?: 0);
            $lineTax = round($amount * $rate / 100, 2);
        }
        $lines[] = ['description' => $desc, 'account_id' => (int)$accountIds[$i], 'qty' => $qty, 'unit_price' => $price, 'amount' => $amount, 'tax_type_id' => $taxTypeId];
        $subtotal += $amount;
        $taxAmount += $lineTax;
    }
    $total = round($subtotal + $taxAmount, 2);

    if (!$customerId) $errors[] = 'Please select a customer.';
    if (empty($lines)) $errors[] = 'Add at least one invoice line.';

    if (empty($errors)) {
        $db->beginTransaction();
        if ($id) {
            $stmt = $db->prepare("UPDATE ar_invoices SET customer_id=?, invoice_date=?, due_date=?, subtotal=?, tax_amount=?, total_amount=? WHERE id=?");
            $stmt->execute([$customerId, $invoiceDate, $dueDate, $subtotal, $taxAmount, $total, $id]);
            $db->prepare("DELETE FROM ar_invoice_lines WHERE invoice_id = ?")->execute([$id]);
        } else {
            $invoiceNo = next_document_no('INV', 'ar_invoices');
            $stmt = $db->prepare("INSERT INTO ar_invoices (invoice_no, customer_id, invoice_date, due_date, subtotal, tax_amount, total_amount, amount_received, status, created_by) VALUES (?,?,?,?,?,?,?,0,'Draft',?)");
            $stmt->execute([$invoiceNo, $customerId, $invoiceDate, $dueDate, $subtotal, $taxAmount, $total, current_user()['id']]);
            $id = (int)$db->lastInsertId();
        }
        $lineStmt = $db->prepare("INSERT INTO ar_invoice_lines (invoice_id, description, account_id, qty, unit_price, amount, tax_type_id) VALUES (?,?,?,?,?,?,?)");
        foreach ($lines as $l) {
            $lineStmt->execute([$id, $l['description'], $l['account_id'], $l['qty'], $l['unit_price'], $l['amount'], $l['tax_type_id']]);
        }
        $db->commit();
        log_audit($id ? 'save' : 'create', 'ar', $id, 'Saved AR invoice');
        flash('success', 'Invoice saved as Draft.');
        redirect('modules/ar/invoice-view.php?id=' . $id);
    }
}

$customers = $db->query("SELECT id, name, credit_terms_days FROM ar_customers WHERE status='Active' ORDER BY name")->fetchAll();
$accounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active=1 AND account_type = 'Revenue' ORDER BY account_code")->fetchAll();
$taxTypes = $db->query("SELECT id, name, rate_percent FROM tax_types WHERE is_active=1")->fetchAll();

$pageTitle = $id ? 'Edit Invoice' : 'New Invoice';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="invoiceForm">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Customer</label>
                <select name="customer_id" id="customer_id" onchange="updateDueDate()" required>
                    <option value="">— Select customer —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" data-terms="<?= (int)$c['credit_terms_days'] ?>" <?= (int)$invoice['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Invoice Date</label><input type="date" name="invoice_date" id="invoice_date" class="form-control" value="<?= e($invoice['invoice_date']) ?>" onchange="updateDueDate()"></div>
            <div class="form-group"><label>Due Date</label><input type="date" name="due_date" id="due_date" class="form-control" value="<?= e($invoice['due_date']) ?>"></div>
        </div>

        <div class="table-wrap">
        <table class="data-table" id="lineTable">
            <thead><tr><th>Description</th><th>Revenue Account</th><th>Qty</th><th>Unit Price</th><th>Tax</th><th class="num">Amount</th><th></th></tr></thead>
            <tbody id="lineBody">
                <?php $existing = !empty($invoice['lines']) ? $invoice['lines'] : [['description'=>'','account_id'=>'','qty'=>1,'unit_price'=>0,'tax_type_id'=>'']]; ?>
                <?php foreach ($existing as $l): ?>
                <tr>
                    <td><input type="text" name="description[]" class="form-control" value="<?= e($l['description']) ?>" placeholder="e.g. Boracay Tour Package"></td>
                    <td><select name="account_id[]">
                        <option value="">—</option>
                        <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= (int)($l['account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['account_code'].' - '.$a['account_name']) ?></option><?php endforeach; ?>
                    </select></td>
                    <td><input type="number" step="0.01" name="qty[]" class="form-control lineQty" value="<?= e((string)($l['qty'] ?? 1)) ?>" onchange="calcTotals()"></td>
                    <td><input type="number" step="0.01" name="unit_price[]" class="form-control linePrice" value="<?= e((string)($l['unit_price'] ?? 0)) ?>" onchange="calcTotals()"></td>
                    <td><select name="tax_type_id[]" class="lineTax" onchange="calcTotals()">
                        <option value="">None</option>
                        <?php foreach ($taxTypes as $t): ?><option value="<?= $t['id'] ?>" data-rate="<?= $t['rate_percent'] ?>" <?= (int)($l['tax_type_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= $t['rate_percent'] ?>%)</option><?php endforeach; ?>
                    </select></td>
                    <td class="num lineAmount">0.00</td>
                    <td><button type="button" class="btn btn-outline btn-sm" onclick="removeRow(this)">✕</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="5"><button type="button" class="btn btn-outline btn-sm" onclick="addRow()">+ Add Line</button></td><td colspan="2"></td></tr>
                <tr><td colspan="5" style="text-align:right;">Subtotal</td><td class="num" id="subtotalDisp">0.00</td><td></td></tr>
                <tr><td colspan="5" style="text-align:right;">Tax</td><td class="num" id="taxDisp">0.00</td><td></td></tr>
                <tr style="font-weight:600;"><td colspan="5" style="text-align:right;">Total</td><td class="num" id="totalDisp">0.00</td><td></td></tr>
            </tfoot>
        </table>
        </div>
        <button type="submit" class="btn btn-primary">Save as Draft</button>
        <a href="invoices.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<script>
var rowTemplate = document.querySelector('#lineBody tr').outerHTML;
function updateDueDate() {
    var sel = document.getElementById('customer_id');
    var terms = parseInt(sel.selectedOptions[0] ? sel.selectedOptions[0].getAttribute('data-terms') : 30) || 30;
    var invDate = new Date(document.getElementById('invoice_date').value || new Date());
    invDate.setDate(invDate.getDate() + terms);
    document.getElementById('due_date').value = invDate.toISOString().slice(0,10);
}
function addRow() {
    var tbody = document.getElementById('lineBody');
    var div = document.createElement('tbody');
    div.innerHTML = rowTemplate;
    var row = div.firstElementChild;
    row.querySelectorAll('input').forEach(function(i){ i.value = i.type === 'number' ? (i.name === 'qty[]' ? 1 : 0) : ''; });
    row.querySelectorAll('select').forEach(function(s){ s.selectedIndex = 0; });
    tbody.appendChild(row);
    calcTotals();
}
function removeRow(btn) { btn.closest('tr').remove(); calcTotals(); }
function calcTotals() {
    var rows = document.querySelectorAll('#lineBody tr');
    var subtotal = 0, tax = 0;
    rows.forEach(function(row){
        var qty = parseFloat(row.querySelector('.lineQty').value || 0);
        var price = parseFloat(row.querySelector('.linePrice').value || 0);
        var amount = qty * price;
        var taxSel = row.querySelector('.lineTax');
        var rate = taxSel.selectedOptions[0] ? parseFloat(taxSel.selectedOptions[0].getAttribute('data-rate') || 0) : 0;
        var lineTax = amount * rate / 100;
        row.querySelector('.lineAmount').textContent = amount.toFixed(2);
        subtotal += amount;
        tax += lineTax;
    });
    document.getElementById('subtotalDisp').textContent = subtotal.toFixed(2);
    document.getElementById('taxDisp').textContent = tax.toFixed(2);
    document.getElementById('totalDisp').textContent = (subtotal + tax).toFixed(2);
}
calcTotals();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
