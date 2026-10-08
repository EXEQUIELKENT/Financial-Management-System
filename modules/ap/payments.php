<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ap.view');

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void') {
    verify_csrf();
    require_permission('ap.approve');
    $voidId = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    try {
        if ($reason === '') throw new RuntimeException('Enter a reason for the void.');
        void_payment('ap', $voidId, current_user()['id'], $reason);
        log_audit('void', 'ap', $voidId, 'Voided payment: ' . $reason);
        flash('success', 'Payment voided. The amounts are back on the open bills and the ledger entry was reversed.');
    } catch (Throwable $e) {
        flash('error', 'Could not void payment: ' . $e->getMessage());
    }
    redirect('modules/ap/payments.php');
}
$from = "FROM ap_payments p JOIN ap_vendors v ON v.id = p.vendor_id JOIN cash_accounts c ON c.id = p.cash_account_id";
$pager = paginate($db,
    "SELECT p.*, v.name AS vendor_name, c.account_name AS cash_account_name $from ORDER BY p.payment_date DESC, p.id DESC",
    "SELECT COUNT(*) $from", [], current_page());
$payments = $pager['data'];

$pageTitle = 'Accounts Payable - Payments';
$pageHelp = [];
if (has_permission('ap.create')) {
    $pageHelp[] = ['selector' => 'a[href="payment-form.php"]',
        'en' => ['title' => '+ New Payment', 'body' => 'Pay one or more of a vendor\'s open bills, in full or partially, from a chosen cash/bank account.'],
        'tl' => ['title' => '+ Bagong Payment', 'body' => 'Bayaran ang isa o higit pang open na bill ng vendor, buo o bahagi, mula sa piniling cash/bank account.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'The table', 'body' => 'Every payment ever recorded, with the vendor, method, cash account used, and amount.'],
    'tl' => ['title' => 'Ang Talahanayan', 'body' => 'Bawat payment na naitala, kasama ang vendor, method, cash account na ginamit, at halaga.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="bills.php" class="btn btn-outline">Bills</a>
            <a href="vendors.php" class="btn btn-outline">Vendors</a>
            <?php if (has_permission('ap.create')): ?><a href="payment-form.php" class="btn btn-primary">+ New Payment</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Payment No.</th><th>Vendor</th><th>Date</th><th>Method</th><th>Cash Account</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e($p['payment_no']) ?></td>
                <td><?= e($p['vendor_name']) ?></td>
                <td><?= format_date($p['payment_date']) ?></td>
                <td><?= e($p['payment_method']) ?></td>
                <td class="text-muted"><?= e($p['cash_account_name']) ?></td>
                <td class="num"><?= format_currency($p['amount']) ?></td>
                <td><span class="badge <?= status_badge_class($p['status']) ?>"><?= e($p['status']) ?></span></td>
                <td>
                    <?php if ($p['status'] !== 'Void' && has_permission('ap.approve')): ?>
                    <form method="post" class="inline-void"><?= csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="text" name="reason" class="form-control" placeholder="Reason" required maxlength="255" style="width:9rem;display:inline-block;">
                        <button type="submit" class="btn btn-outline btn-sm" data-confirm="Void <?= e($p['payment_no']) ?>? The ledger entry and cash movement will be reversed and the amounts go back on the open bills.">Void</button>
                    </form>
                    <?php elseif ($p['status'] === 'Void'): ?><span class="text-muted" title="<?= e($p['void_reason'] ?? '') ?>"><?= e($p['void_reason'] ?? '') ?></span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?><tr><td colspan="8" class="empty-state">No payments recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
