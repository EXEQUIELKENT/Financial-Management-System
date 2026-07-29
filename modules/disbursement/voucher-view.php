<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('disbursement.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT dv.*, u.full_name AS requested_by_name, u2.full_name AS approved_by_name
                       FROM disbursement_vouchers dv JOIN users u ON u.id = dv.requested_by
                       LEFT JOIN users u2 ON u2.id = dv.approved_by WHERE dv.id = ?");
$stmt->execute([$id]);
$dv = $stmt->fetch();
if (!$dv) { flash('error', 'Voucher not found.'); redirect('modules/disbursement/vouchers.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $comments = trim($_POST['comments'] ?? '');

    if ($action === 'approve' && $dv['status'] === 'PendingApproval') {
        require_permission('disbursement.approve');
        if ((int)$dv['requested_by'] === (int)current_user()['id'] && ($_SESSION['role_name'] ?? '') !== 'Admin') {
            flash('error', 'Segregation of duties: you cannot approve a voucher you requested yourself.');
            redirect('modules/disbursement/voucher-view.php?id=' . $id);
        }
        $db->prepare("UPDATE disbursement_vouchers SET status='Approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([current_user()['id'], $id]);
        $db->prepare("INSERT INTO dv_approval_history (dv_id, action, actor_id, comments) VALUES (?, 'Approved', ?, ?)")->execute([$id, current_user()['id'], $comments]);
        log_audit('approve', 'disbursement', $id, 'Approved DV ' . $dv['dv_no']);
        flash('success', 'Voucher approved. It can now be marked Paid.');
    } elseif ($action === 'reject' && $dv['status'] === 'PendingApproval') {
        require_permission('disbursement.approve');
        $db->prepare("UPDATE disbursement_vouchers SET status='Rejected' WHERE id=?")->execute([$id]);
        $db->prepare("INSERT INTO dv_approval_history (dv_id, action, actor_id, comments) VALUES (?, 'Rejected', ?, ?)")->execute([$id, current_user()['id'], $comments]);
        log_audit('reject', 'disbursement', $id, 'Rejected DV ' . $dv['dv_no']);
        flash('success', 'Voucher rejected.');
    } elseif ($action === 'pay' && $dv['status'] === 'Approved') {
        require_permission('disbursement.approve');
        $lineStmt = $db->prepare("SELECT * FROM disbursement_voucher_lines WHERE dv_id = ?");
        $lineStmt->execute([$id]);
        $lines = $lineStmt->fetchAll();
        $cashStmt = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ?");
        $cashStmt->execute([$dv['cash_account_id']]);
        $cashAcct = $cashStmt->fetch();

        try {
            $jeLines = [];
            foreach ($lines as $l) {
                $jeLines[] = ['account_id' => $l['account_id'], 'debit' => $l['amount'], 'credit' => 0, 'memo' => $l['description']];
            }
            $jeLines[] = ['account_id' => $cashAcct['gl_account_id'], 'debit' => 0, 'credit' => $dv['amount'], 'memo' => 'Cash paid - ' . $cashAcct['account_name']];

            $entryId = post_journal_entry([
                'entry_date' => date('Y-m-d'),
                'reference' => $dv['dv_no'],
                'source_module' => 'disbursement',
                'source_id' => $id,
                'description' => 'Disbursement Voucher ' . $dv['dv_no'] . ' - ' . $dv['payee_name'],
                'created_by' => current_user()['id'],
                'lines' => $jeLines,
            ]);

            $apPaymentId = null;
            $billLines = array_filter($lines, fn($l) => !empty($l['ap_bill_id']));
            if (!empty($billLines) && $dv['vendor_id']) {
                $paymentNo = next_document_no('APPMT', 'ap_payments');
                $gross = array_sum(array_column($billLines, 'amount'));
                $payStmt = $db->prepare("INSERT INTO ap_payments (payment_no, vendor_id, payment_date, amount, payment_method, reference_no, cash_account_id, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
                $payStmt->execute([$paymentNo, $dv['vendor_id'], date('Y-m-d'), $gross, 'Disbursement Voucher', $dv['dv_no'], $dv['cash_account_id'], $entryId, current_user()['id']]);
                $apPaymentId = (int)$db->lastInsertId();
                $appStmt = $db->prepare("INSERT INTO ap_payment_applications (payment_id, bill_id, amount_applied) VALUES (?,?,?)");
                foreach ($billLines as $l) {
                    $appStmt->execute([$apPaymentId, $l['ap_bill_id'], $l['amount']]);
                    $bill = $db->prepare("SELECT total_amount, amount_paid FROM ap_bills WHERE id = ?");
                    $bill->execute([$l['ap_bill_id']]);
                    $b = $bill->fetch();
                    $newPaid = round($b['amount_paid'] + $l['amount'], 2);
                    $newStatus = $newPaid >= (float)$b['total_amount'] - 0.005 ? 'Paid' : 'PartiallyPaid';
                    $db->prepare("UPDATE ap_bills SET amount_paid=?, status=? WHERE id=?")->execute([$newPaid, $newStatus, $l['ap_bill_id']]);
                }
            }

            $db->prepare("UPDATE disbursement_vouchers SET status='Paid', ap_payment_id=?, journal_entry_id=? WHERE id=?")->execute([$apPaymentId, $entryId, $id]);
            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$dv['amount'], $dv['cash_account_id']]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$dv['cash_account_id'], date('Y-m-d'), 'Withdrawal', $dv['amount'], $dv['dv_no'], 'Disbursement voucher payment', 'disbursement', $id, 'Operating', $entryId, current_user()['id']]);
            $db->prepare("INSERT INTO dv_approval_history (dv_id, action, actor_id, comments) VALUES (?, 'Paid', ?, ?)")->execute([$id, current_user()['id'], $comments]);

            log_audit('pay', 'disbursement', $id, 'Marked DV ' . $dv['dv_no'] . ' as Paid');
            flash('success', 'Voucher marked as Paid and posted to the general ledger.');
        } catch (Throwable $e) {
            flash('error', 'Could not process payment: ' . $e->getMessage());
        }
    } elseif ($action === 'void' && in_array($dv['status'], ['Draft','PendingApproval','Approved'], true)) {
        require_permission('disbursement.approve');
        $db->prepare("UPDATE disbursement_vouchers SET status='Void' WHERE id=?")->execute([$id]);
        $db->prepare("INSERT INTO dv_approval_history (dv_id, action, actor_id, comments) VALUES (?, 'Void', ?, ?)")->execute([$id, current_user()['id'], $comments]);
        log_audit('void', 'disbursement', $id, 'Voided DV ' . $dv['dv_no']);
        flash('success', 'Voucher voided.');
    }
    redirect('modules/disbursement/voucher-view.php?id=' . $id);
}

$lineStmt = $db->prepare("SELECT l.*, a.account_code, a.account_name FROM disbursement_voucher_lines l JOIN coa_accounts a ON a.id = l.account_id WHERE l.dv_id = ?");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();

$historyStmt = $db->prepare("SELECT h.*, u.full_name FROM dv_approval_history h JOIN users u ON u.id = h.actor_id WHERE h.dv_id = ? ORDER BY h.created_at");
$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

$pageTitle = 'Disbursement Voucher ' . $dv['dv_no'];
$pageHelp = [
    ['selector' => '.status-stepper, .status-stepper-stopped',
        'en' => ['title' => 'Stepper at the top', 'body' => 'Shows this voucher\'s stage: Submitted, Approved, or Paid — or a stopped state if Rejected/Voided.'],
        'tl' => ['title' => 'Stepper sa Itaas', 'body' => 'Ipinapakita ang stage ng voucher: Submitted, Approved, o Paid — o stopped state kung Rejected/Voided.']],
];
if ($dv['status'] === 'PendingApproval' && has_permission('disbursement.approve')) {
    $pageHelp[] = ['selector' => 'button[data-confirm="Approve this voucher?"]',
        'en' => ['title' => 'Approve / Reject', 'body' => 'Approver-only. You can\'t approve one you requested yourself.'],
        'tl' => ['title' => 'Approve / Reject', 'body' => 'Approver lamang. Hindi mo puwedeng i-approve ang isinumite mo mismo.']];
}
if ($dv['status'] === 'Approved' && has_permission('disbursement.approve')) {
    $pageHelp[] = ['selector' => 'button[data-confirm="Mark as Paid and post to the GL?"]',
        'en' => ['title' => 'Mark Paid', 'body' => 'Releases the cash, posts the GL entry, and (if bills were selected) auto-creates the matching AP payment.'],
        'tl' => ['title' => 'Mark Paid', 'body' => 'Ilalabas ang cash, ipo-post ang GL entry, at (kung may napiling bills) awtomatikong gagawa ng katugmang AP payment.']];
}
if ($dv['status'] === 'Paid') {
    $pageHelp[] = ['selector' => 'a[href*="voucher-print.php"]',
        'en' => ['title' => 'Print', 'body' => 'A signed voucher slip with Requested By / Approved By lines.'],
        'tl' => ['title' => 'Print', 'body' => 'Isang nilagdaang voucher slip na may Requested By / Approved By lines.']];
}
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($dv['dv_no']) ?> <span class="badge <?= status_badge_class($dv['status']) ?>"><?= e($dv['status']) ?></span></h3>
        <div>
            <?php if ($dv['status'] === 'PendingApproval' && has_permission('disbursement.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button type="submit" class="btn btn-accent" data-confirm="Approve this voucher?">Approve</button></form>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><button type="submit" class="btn btn-danger" data-confirm="Reject this voucher?">Reject</button></form>
            <?php endif; ?>
            <?php if ($dv['status'] === 'Approved' && has_permission('disbursement.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="pay"><button type="submit" class="btn btn-accent" data-confirm="Mark as Paid and post to the GL?">Mark Paid</button></form>
            <?php endif; ?>
            <?php if (in_array($dv['status'], ['Draft','PendingApproval','Approved'], true) && has_permission('disbursement.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="void"><button type="submit" class="btn btn-outline" data-confirm="Void this voucher?">Void</button></form>
            <?php endif; ?>
            <?php if ($dv['status'] === 'Paid'): ?><a href="voucher-print.php?id=<?= $id ?>" class="btn btn-outline" target="_blank">Print</a><?php endif; ?>
            <a href="vouchers.php" class="btn btn-outline">Back</a>
        </div>
    </div>
    <?php
        $stepIndex = ['Draft' => 0, 'PendingApproval' => 0, 'Approved' => 1, 'Paid' => 2][$dv['status']] ?? 0;
        $stopped = $dv['status'] === 'Rejected' ? 'Rejected by the Approver' : ($dv['status'] === 'Void' ? 'Voided' : null);
        echo render_status_stepper(['1. Submitted for Approval', '2. Approved', '3. Paid'], $stepIndex, $stopped);
    ?>
    <div class="form-row">
        <div><span class="text-muted">Payee</span><br><?= e($dv['payee_name']) ?> (<?= e($dv['payee_type']) ?>)</div>
        <div><span class="text-muted">DV Date</span><br><?= format_date($dv['dv_date']) ?></div>
        <div><span class="text-muted">Requested By</span><br><?= e($dv['requested_by_name']) ?></div>
        <div><span class="text-muted">Approved By</span><br><?= e($dv['approved_by_name'] ?? '—') ?></div>
        <div><span class="text-muted">Total Amount</span><br><strong><?= format_currency($dv['amount']) ?></strong></div>
    </div>
    <p><?= e($dv['particulars']) ?></p>
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
    <?php if ($dv['journal_entry_id']): ?><p class="text-muted">Posted as journal entry: <a href="../gl/journal-entry-view.php?id=<?= $dv['journal_entry_id'] ?>">View GL Entry</a></p><?php endif; ?>
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
