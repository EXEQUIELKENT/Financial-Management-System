<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ar.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT i.*, c.name AS customer_name, c.id AS customer_id, u.full_name AS created_by_name
                       FROM ar_invoices i JOIN ar_customers c ON c.id = i.customer_id JOIN users u ON u.id = i.created_by
                       WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) { flash('error', 'Invoice not found.'); redirect('modules/ar/invoices.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        require_permission('ar.approve');
        if ((int)$invoice['created_by'] === (int)current_user()['id'] && ($_SESSION['role_name'] ?? '') !== 'Admin') {
            flash('error', 'Segregation of duties: you cannot approve an invoice you created yourself.');
            redirect('modules/ar/invoice-view.php?id=' . $id);
        }
        $arAccountId = (int)get_setting('ar_control_account_id');
        $outputTaxAccountId = (int)get_setting('output_tax_account_id');
        if (!$arAccountId) {
            flash('error', 'AR control account is not configured. Ask an Admin to set it in Settings.');
            redirect('modules/ar/invoice-view.php?id=' . $id);
        }
        $lineStmt = $db->prepare("SELECT * FROM ar_invoice_lines WHERE invoice_id = ?");
        $lineStmt->execute([$id]);
        $iLines = $lineStmt->fetchAll();

        $jeLines = [];
        $jeLines[] = ['account_id' => $arAccountId, 'debit' => $invoice['total_amount'], 'credit' => 0, 'memo' => 'AR - ' . $invoice['customer_name']];
        foreach ($iLines as $il) {
            $jeLines[] = ['account_id' => $il['account_id'], 'debit' => 0, 'credit' => $il['amount'], 'memo' => $il['description']];
        }
        if ((float)$invoice['tax_amount'] > 0 && $outputTaxAccountId) {
            $jeLines[] = ['account_id' => $outputTaxAccountId, 'debit' => 0, 'credit' => $invoice['tax_amount'], 'memo' => 'Output tax on ' . $invoice['invoice_no']];
        }

        try {
            $entryId = post_journal_entry([
                'entry_date' => $invoice['invoice_date'],
                'reference' => $invoice['invoice_no'],
                'source_module' => 'ar',
                'source_id' => $id,
                'description' => 'AR Invoice ' . $invoice['invoice_no'] . ' - ' . $invoice['customer_name'],
                'created_by' => current_user()['id'],
                'lines' => $jeLines,
            ]);
            $db->prepare("UPDATE ar_invoices SET status='Open', journal_entry_id=?, approved_by=? WHERE id=?")
               ->execute([$entryId, current_user()['id'], $id]);

            $taxByType = [];
            foreach ($iLines as $il) {
                if (!empty($il['tax_type_id'])) {
                    $rate = $db->prepare("SELECT rate_percent FROM tax_types WHERE id=?");
                    $rate->execute([$il['tax_type_id']]);
                    $r = (float)$rate->fetchColumn();
                    $taxByType[$il['tax_type_id']] = ($taxByType[$il['tax_type_id']] ?? 0) + round($il['amount'] * $r / 100, 2);
                }
            }
            $taxStmt = $db->prepare("INSERT INTO tax_transactions (tax_type_id, source_module, source_id, transaction_date, taxable_amount, tax_amount, direction, status) VALUES (?,?,?,?,?,?,?,'Pending')");
            foreach ($taxByType as $taxTypeId => $amt) {
                $taxStmt->execute([$taxTypeId, 'AR', $id, $invoice['invoice_date'], $invoice['subtotal'], $amt, 'Output']);
            }

            log_audit('approve', 'ar', $id, 'Approved and posted AR invoice ' . $invoice['invoice_no']);
            flash('success', 'Invoice approved and posted to the general ledger.');
        } catch (Throwable $e) {
            flash('error', 'Could not post invoice: ' . $e->getMessage());
        }
    } elseif ($action === 'void') {
        require_permission('ar.approve');
        try {
            if ($invoice['journal_entry_id']) {
                void_journal_entry((int)$invoice['journal_entry_id'], current_user()['id'], 'AR invoice void: ' . $invoice['invoice_no']);
            }
            $db->prepare("UPDATE ar_invoices SET status='Void' WHERE id=?")->execute([$id]);
            log_audit('void', 'ar', $id, 'Voided AR invoice ' . $invoice['invoice_no']);
            flash('success', 'Invoice voided.');
        } catch (Throwable $e) {
            flash('error', 'Could not void invoice: ' . $e->getMessage());
        }
    }
    redirect('modules/ar/invoice-view.php?id=' . $id);
}

$lineStmt = $db->prepare("SELECT l.*, a.account_code, a.account_name, t.name AS tax_name FROM ar_invoice_lines l JOIN coa_accounts a ON a.id = l.account_id LEFT JOIN tax_types t ON t.id = l.tax_type_id WHERE l.invoice_id = ?");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();

$pageTitle = 'Invoice ' . $invoice['invoice_no'];
include __DIR__ . '/../../includes/header.php';
$eff = display_status($invoice['status'], $invoice['due_date']);
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($invoice['invoice_no']) ?> <span class="badge <?= status_badge_class($eff) ?>"><?= e($eff) ?></span></h3>
        <div>
            <?php if ($invoice['status'] === 'Draft'): ?>
                <?php if (has_permission('ar.create')): ?><a href="invoice-form.php?id=<?= $id ?>" class="btn btn-outline">Edit</a><?php endif; ?>
                <?php if (has_permission('ar.approve')): ?>
                    <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-accent" data-confirm="Approve and post this invoice to the GL?">Approve &amp; Post</button></form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (in_array($invoice['status'], ['Open','PartiallyPaid'], true) && has_permission('ar.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="void">
                    <button type="submit" class="btn btn-danger" data-confirm="Void this invoice? A reversing entry will be booked.">Void</button></form>
            <?php endif; ?>
            <a href="invoices.php" class="btn btn-outline">Back</a>
        </div>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Customer</span><br><a href="customer-view.php?id=<?= $invoice['customer_id'] ?>"><?= e($invoice['customer_name']) ?></a></div>
        <div><span class="text-muted">Invoice Date</span><br><?= format_date($invoice['invoice_date']) ?></div>
        <div><span class="text-muted">Due Date</span><br><?= format_date($invoice['due_date']) ?></div>
        <div><span class="text-muted">Created By</span><br><?= e($invoice['created_by_name']) ?></div>
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
            <tr><td colspan="5" style="text-align:right;">Subtotal</td><td class="num"><?= format_currency($invoice['subtotal']) ?></td></tr>
            <tr><td colspan="5" style="text-align:right;">Tax</td><td class="num"><?= format_currency($invoice['tax_amount']) ?></td></tr>
            <tr style="font-weight:600;"><td colspan="5" style="text-align:right;">Total</td><td class="num"><?= format_currency($invoice['total_amount']) ?></td></tr>
            <tr><td colspan="5" style="text-align:right;">Received</td><td class="num"><?= format_currency($invoice['amount_received']) ?></td></tr>
            <tr style="font-weight:600;"><td colspan="5" style="text-align:right;">Balance</td><td class="num"><?= format_currency($invoice['total_amount'] - $invoice['amount_received']) ?></td></tr>
        </tfoot>
    </table>
    </div>
    <?php if ($invoice['journal_entry_id']): ?>
        <p class="text-muted">Posted as journal entry: <a href="../gl/journal-entry-view.php?id=<?= $invoice['journal_entry_id'] ?>">View GL Entry</a></p>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
