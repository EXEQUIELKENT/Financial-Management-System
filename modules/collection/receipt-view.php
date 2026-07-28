<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('collection.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT cr.*, u.full_name AS received_by_name, u2.full_name AS approved_by_name
                       FROM collection_receipts cr JOIN users u ON u.id = cr.received_by
                       LEFT JOIN users u2 ON u2.id = cr.approved_by WHERE cr.id = ?");
$stmt->execute([$id]);
$cr = $stmt->fetch();
if (!$cr) { flash('error', 'Receipt not found.'); redirect('modules/collection/receipts.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $comments = trim($_POST['comments'] ?? '');

    if ($action === 'approve' && $cr['status'] === 'PendingApproval') {
        require_permission('collection.approve');
        if ((int)$cr['received_by'] === (int)current_user()['id'] && ($_SESSION['role_name'] ?? '') !== 'Admin') {
            flash('error', 'Segregation of duties: you cannot approve a receipt you recorded yourself.');
            redirect('modules/collection/receipt-view.php?id=' . $id);
        }
        $db->prepare("UPDATE collection_receipts SET status='Approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([current_user()['id'], $id]);
        $db->prepare("INSERT INTO cr_approval_history (cr_id, action, actor_id, comments) VALUES (?, 'Approved', ?, ?)")->execute([$id, current_user()['id'], $comments]);
        log_audit('approve', 'collection', $id, 'Approved CR ' . $cr['cr_no']);
        flash('success', 'Receipt approved. It can now be marked Deposited.');
    } elseif ($action === 'reject' && $cr['status'] === 'PendingApproval') {
        require_permission('collection.approve');
        $db->prepare("UPDATE collection_receipts SET status='Rejected' WHERE id=?")->execute([$id]);
        $db->prepare("INSERT INTO cr_approval_history (cr_id, action, actor_id, comments) VALUES (?, 'Rejected', ?, ?)")->execute([$id, current_user()['id'], $comments]);
        log_audit('reject', 'collection', $id, 'Rejected CR ' . $cr['cr_no']);
        flash('success', 'Receipt rejected.');
    } elseif ($action === 'deposit' && $cr['status'] === 'Approved') {
        require_permission('collection.approve');
        $lineStmt = $db->prepare("SELECT * FROM collection_receipt_lines WHERE cr_id = ?");
        $lineStmt->execute([$id]);
        $lines = $lineStmt->fetchAll();
        $cashStmt = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ?");
        $cashStmt->execute([$cr['cash_account_id']]);
        $cashAcct = $cashStmt->fetch();

        try {
            $jeLines = [];
            $jeLines[] = ['account_id' => $cashAcct['gl_account_id'], 'debit' => $cr['amount'], 'credit' => 0, 'memo' => 'Cash received - ' . $cashAcct['account_name']];
            foreach ($lines as $l) {
                $jeLines[] = ['account_id' => $l['account_id'], 'debit' => 0, 'credit' => $l['amount'], 'memo' => $l['description']];
            }

            $entryId = post_journal_entry([
                'entry_date' => date('Y-m-d'),
                'reference' => $cr['cr_no'],
                'source_module' => 'collection',
                'source_id' => $id,
                'description' => 'Collection Receipt ' . $cr['cr_no'] . ' - ' . $cr['payer_name'],
                'created_by' => current_user()['id'],
                'lines' => $jeLines,
            ]);

            $arReceiptId = null;
            $invoiceLines = array_filter($lines, fn($l) => !empty($l['ar_invoice_id']));
            if (!empty($invoiceLines) && $cr['customer_id']) {
                $receiptNo = next_document_no('ARCPT', 'ar_receipts');
                $gross = array_sum(array_column($invoiceLines, 'amount'));
                $recStmt = $db->prepare("INSERT INTO ar_receipts (receipt_no, customer_id, receipt_date, amount, payment_method, reference_no, cash_account_id, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
                $recStmt->execute([$receiptNo, $cr['customer_id'], date('Y-m-d'), $gross, 'Collection Receipt', $cr['cr_no'], $cr['cash_account_id'], $entryId, current_user()['id']]);
                $arReceiptId = (int)$db->lastInsertId();
                $appStmt = $db->prepare("INSERT INTO ar_receipt_applications (receipt_id, invoice_id, amount_applied) VALUES (?,?,?)");
                foreach ($invoiceLines as $l) {
                    $appStmt->execute([$arReceiptId, $l['ar_invoice_id'], $l['amount']]);
                    $inv = $db->prepare("SELECT total_amount, amount_received FROM ar_invoices WHERE id = ?");
                    $inv->execute([$l['ar_invoice_id']]);
                    $i = $inv->fetch();
                    $newReceived = round($i['amount_received'] + $l['amount'], 2);
                    $newStatus = $newReceived >= (float)$i['total_amount'] - 0.005 ? 'Paid' : 'PartiallyPaid';
                    $db->prepare("UPDATE ar_invoices SET amount_received=?, status=? WHERE id=?")->execute([$newReceived, $newStatus, $l['ar_invoice_id']]);
                }
            }

            $db->prepare("UPDATE collection_receipts SET status='Deposited', ar_receipt_id=?, journal_entry_id=? WHERE id=?")->execute([$arReceiptId, $entryId, $id]);
            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$cr['amount'], $cr['cash_account_id']]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$cr['cash_account_id'], date('Y-m-d'), 'Deposit', $cr['amount'], $cr['cr_no'], 'Collection receipt deposit', 'collection', $id, 'Operating', $entryId, current_user()['id']]);
            $db->prepare("INSERT INTO cr_approval_history (cr_id, action, actor_id, comments) VALUES (?, 'Deposited', ?, ?)")->execute([$id, current_user()['id'], $comments]);

            log_audit('deposit', 'collection', $id, 'Marked CR ' . $cr['cr_no'] . ' as Deposited');
            flash('success', 'Receipt marked as Deposited and posted to the general ledger.');
        } catch (Throwable $e) {
            flash('error', 'Could not process deposit: ' . $e->getMessage());
        }
    } elseif ($action === 'void' && in_array($cr['status'], ['Draft','PendingApproval','Approved'], true)) {
        require_permission('collection.approve');
        $db->prepare("UPDATE collection_receipts SET status='Void' WHERE id=?")->execute([$id]);
        $db->prepare("INSERT INTO cr_approval_history (cr_id, action, actor_id, comments) VALUES (?, 'Void', ?, ?)")->execute([$id, current_user()['id'], $comments]);
        log_audit('void', 'collection', $id, 'Voided CR ' . $cr['cr_no']);
        flash('success', 'Receipt voided.');
    }
    redirect('modules/collection/receipt-view.php?id=' . $id);
}

$lineStmt = $db->prepare("SELECT l.*, a.account_code, a.account_name FROM collection_receipt_lines l JOIN coa_accounts a ON a.id = l.account_id WHERE l.cr_id = ?");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();

$historyStmt = $db->prepare("SELECT h.*, u.full_name FROM cr_approval_history h JOIN users u ON u.id = h.actor_id WHERE h.cr_id = ? ORDER BY h.created_at");
$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

$pageTitle = 'Collection Receipt ' . $cr['cr_no'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($cr['cr_no']) ?> <span class="badge <?= status_badge_class($cr['status']) ?>"><?= e($cr['status']) ?></span></h3>
        <div>
            <?php if ($cr['status'] === 'PendingApproval' && has_permission('collection.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button type="submit" class="btn btn-accent" data-confirm="Approve this receipt?">Approve</button></form>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><button type="submit" class="btn btn-danger" data-confirm="Reject this receipt?">Reject</button></form>
            <?php endif; ?>
            <?php if ($cr['status'] === 'Approved' && has_permission('collection.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="deposit"><button type="submit" class="btn btn-accent" data-confirm="Mark as Deposited and post to the GL?">Mark Deposited</button></form>
            <?php endif; ?>
            <?php if (in_array($cr['status'], ['Draft','PendingApproval','Approved'], true) && has_permission('collection.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="void"><button type="submit" class="btn btn-outline" data-confirm="Void this receipt?">Void</button></form>
            <?php endif; ?>
            <?php if ($cr['status'] === 'Deposited'): ?><a href="receipt-print.php?id=<?= $id ?>" class="btn btn-outline" target="_blank">Print</a><?php endif; ?>
            <a href="receipts.php" class="btn btn-outline">Back</a>
        </div>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Payer</span><br><?= e($cr['payer_name']) ?> (<?= e($cr['payer_type']) ?>)</div>
        <div><span class="text-muted">CR Date</span><br><?= format_date($cr['cr_date']) ?></div>
        <div><span class="text-muted">Received By</span><br><?= e($cr['received_by_name']) ?></div>
        <div><span class="text-muted">Approved By</span><br><?= e($cr['approved_by_name'] ?? '—') ?></div>
        <div><span class="text-muted">Total Amount</span><br><strong><?= format_currency($cr['amount']) ?></strong></div>
    </div>
    <p><?= e($cr['particulars']) ?></p>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Description</th><th>Account</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
            <tr><td><?= e($l['description']) ?></td><td class="text-muted"><?= e($l['account_code'].' - '.$l['account_name']) ?></td><td class="num"><?= format_currency($l['amount']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($cr['journal_entry_id']): ?><p class="text-muted">Posted as journal entry: <a href="../gl/journal-entry-view.php?id=<?= $cr['journal_entry_id'] ?>">View GL Entry</a></p><?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Approval History</h3></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Action</th><th>By</th><th>Comments</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr><td><?= e($h['action']) ?></td><td><?= e($h['full_name']) ?></td><td><?= e($h['comments']) ?></td><td><?= format_date($h['created_at'], 'M d, Y g:i A') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
