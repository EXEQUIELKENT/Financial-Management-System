<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ap.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT b.*, v.name AS vendor_name, v.id AS vendor_id, u.full_name AS created_by_name
                       FROM ap_bills b JOIN ap_vendors v ON v.id = b.vendor_id JOIN users u ON u.id = b.created_by
                       WHERE b.id = ?");
$stmt->execute([$id]);
$bill = $stmt->fetch();
if (!$bill) { flash('error', 'Bill not found.'); redirect('modules/ap/bills.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        require_permission('ap.approve');
        if ((int)$bill['created_by'] === (int)current_user()['id'] && ($_SESSION['role_name'] ?? '') !== 'Admin') {
            flash('error', 'Segregation of duties: you cannot approve a bill you created yourself.');
            redirect('modules/ap/bill-view.php?id=' . $id);
        }
        $apAccountId = (int)get_setting('ap_control_account_id');
        $inputTaxAccountId = (int)get_setting('input_tax_account_id');
        if (!$apAccountId) {
            flash('error', 'AP control account is not configured. Ask an Admin to set it in Settings.');
            redirect('modules/ap/bill-view.php?id=' . $id);
        }
        $lineStmt = $db->prepare("SELECT * FROM ap_bill_lines WHERE bill_id = ?");
        $lineStmt->execute([$id]);
        $bLines = $lineStmt->fetchAll();

        $jeLines = [];
        foreach ($bLines as $bl) {
            $jeLines[] = ['account_id' => $bl['account_id'], 'debit' => $bl['amount'], 'credit' => 0, 'memo' => $bl['description']];
        }
        if ((float)$bill['tax_amount'] > 0 && $inputTaxAccountId) {
            $jeLines[] = ['account_id' => $inputTaxAccountId, 'debit' => $bill['tax_amount'], 'credit' => 0, 'memo' => 'Input tax on ' . $bill['bill_no']];
        }
        $jeLines[] = ['account_id' => $apAccountId, 'debit' => 0, 'credit' => $bill['total_amount'], 'memo' => 'AP - ' . $bill['vendor_name']];

        try {
            $entryId = post_journal_entry([
                'entry_date' => $bill['bill_date'],
                'reference' => $bill['bill_no'],
                'source_module' => 'ap',
                'source_id' => $id,
                'description' => 'AP Bill ' . $bill['bill_no'] . ' - ' . $bill['vendor_name'],
                'created_by' => current_user()['id'],
                'lines' => $jeLines,
            ]);
            $db->prepare("UPDATE ap_bills SET status='Open', journal_entry_id=?, approved_by=? WHERE id=?")
               ->execute([$entryId, current_user()['id'], $id]);

            // Aggregate tax by tax_type for tax_transactions
            $taxByType = [];
            foreach ($bLines as $bl) {
                if (!empty($bl['tax_type_id'])) {
                    $rate = $db->prepare("SELECT rate_percent FROM tax_types WHERE id=?");
                    $rate->execute([$bl['tax_type_id']]);
                    $r = (float)$rate->fetchColumn();
                    $taxByType[$bl['tax_type_id']] = ($taxByType[$bl['tax_type_id']] ?? 0) + round($bl['amount'] * $r / 100, 2);
                }
            }
            $taxStmt = $db->prepare("INSERT INTO tax_transactions (tax_type_id, source_module, source_id, transaction_date, taxable_amount, tax_amount, direction, status) VALUES (?,?,?,?,?,?,?,'Pending')");
            foreach ($taxByType as $taxTypeId => $amt) {
                $taxStmt->execute([$taxTypeId, 'AP', $id, $bill['bill_date'], $bill['subtotal'], $amt, 'Input']);
            }

            log_audit('approve', 'ap', $id, 'Approved and posted AP bill ' . $bill['bill_no']);
            flash('success', 'Bill approved and posted to the general ledger.');
        } catch (Throwable $e) {
            flash('error', 'Could not post bill: ' . $e->getMessage());
        }
    } elseif ($action === 'void') {
        require_permission('ap.approve');
        try {
            if ($bill['journal_entry_id']) {
                void_journal_entry((int)$bill['journal_entry_id'], current_user()['id'], 'AP bill void: ' . $bill['bill_no']);
            }
            $db->prepare("UPDATE ap_bills SET status='Void' WHERE id=?")->execute([$id]);
            log_audit('void', 'ap', $id, 'Voided AP bill ' . $bill['bill_no']);
            flash('success', 'Bill voided.');
        } catch (Throwable $e) {
            flash('error', 'Could not void bill: ' . $e->getMessage());
        }
    }
    redirect('modules/ap/bill-view.php?id=' . $id);
}

$lineStmt = $db->prepare("SELECT l.*, a.account_code, a.account_name, t.name AS tax_name FROM ap_bill_lines l JOIN coa_accounts a ON a.id = l.account_id LEFT JOIN tax_types t ON t.id = l.tax_type_id WHERE l.bill_id = ?");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();

$pageTitle = 'Bill ' . $bill['bill_no'];
include __DIR__ . '/../../includes/header.php';
$eff = display_status($bill['status'], $bill['due_date']);
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($bill['bill_no']) ?> <span class="badge <?= status_badge_class($eff) ?>"><?= e($eff) ?></span></h3>
        <div>
            <?php if ($bill['status'] === 'Draft'): ?>
                <?php if (has_permission('ap.create')): ?><a href="bill-form.php?id=<?= $id ?>" class="btn btn-outline">Edit</a><?php endif; ?>
                <?php if (has_permission('ap.approve')): ?>
                    <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-accent" data-confirm="Approve and post this bill to the GL?">Approve &amp; Post</button></form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (in_array($bill['status'], ['Open','PartiallyPaid'], true) && has_permission('ap.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="void">
                    <button type="submit" class="btn btn-danger" data-confirm="Void this bill? A reversing entry will be booked.">Void</button></form>
            <?php endif; ?>
            <a href="bills.php" class="btn btn-outline">Back</a>
        </div>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Vendor</span><br><a href="vendor-view.php?id=<?= $bill['vendor_id'] ?>"><?= e($bill['vendor_name']) ?></a></div>
        <div><span class="text-muted">Bill Date</span><br><?= format_date($bill['bill_date']) ?></div>
        <div><span class="text-muted">Due Date</span><br><?= format_date($bill['due_date']) ?></div>
        <div><span class="text-muted">Created By</span><br><?= e($bill['created_by_name']) ?></div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Description</th><th>Account</th><th class="num">Qty</th><th class="num">Unit Price</th><th>Tax</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
            <tr>
                <td><?= e($l['description']) ?></td>
                <td class="text-muted"><?= e($l['account_code'].' - '.$l['account_name']) ?></td>
                <td class="num"><?= e($l['qty']) ?></td>
                <td class="num"><?= format_currency($l['unit_price']) ?></td>
                <td class="text-muted"><?= e($l['tax_name'] ?? '—') ?></td>
                <td class="num"><?= format_currency($l['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="5" style="text-align:right;">Subtotal</td><td class="num"><?= format_currency($bill['subtotal']) ?></td></tr>
            <tr><td colspan="5" style="text-align:right;">Tax</td><td class="num"><?= format_currency($bill['tax_amount']) ?></td></tr>
            <tr style="font-weight:600;"><td colspan="5" style="text-align:right;">Total</td><td class="num"><?= format_currency($bill['total_amount']) ?></td></tr>
            <tr><td colspan="5" style="text-align:right;">Paid</td><td class="num"><?= format_currency($bill['amount_paid']) ?></td></tr>
            <tr style="font-weight:600;"><td colspan="5" style="text-align:right;">Balance</td><td class="num"><?= format_currency($bill['total_amount'] - $bill['amount_paid']) ?></td></tr>
        </tfoot>
    </table>
    </div>
    <?php if ($bill['journal_entry_id']): ?>
        <p class="text-muted">Posted as journal entry: <a href="../gl/journal-entry-view.php?id=<?= $bill['journal_entry_id'] ?>">View GL Entry</a></p>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
