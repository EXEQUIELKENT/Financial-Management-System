<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ar.view');

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void') {
    verify_csrf();
    require_permission('ar.approve');
    $voidId = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    try {
        if ($reason === '') throw new RuntimeException('Enter a reason for the void.');
        void_payment('ar', $voidId, current_user()['id'], $reason);
        log_audit('void', 'ar', $voidId, 'Voided receipt: ' . $reason);
        flash('success', 'Receipt voided. The amounts are back on the open invoices and the ledger entry was reversed.');
    } catch (Throwable $e) {
        flash('error', 'Could not void receipt: ' . $e->getMessage());
    }
    redirect('modules/ar/receipts.php');
}
$from = "FROM ar_receipts r JOIN ar_customers c ON c.id = r.customer_id JOIN cash_accounts ca ON ca.id = r.cash_account_id";
$pager = paginate($db,
    "SELECT r.*, c.name AS customer_name, ca.account_name AS cash_account_name $from ORDER BY r.receipt_date DESC, r.id DESC",
    "SELECT COUNT(*) $from", [], current_page());
$receipts = $pager['data'];

$pageTitle = 'Accounts Receivable - Receipts';
$pageHelp = [];
if (has_permission('ar.create')) {
    $pageHelp[] = ['selector' => 'a[href="receipt-form.php"]',
        'en' => ['title' => '+ New Receipt', 'body' => 'Record a payment from a customer, applied against one or more of their open invoices, deposited into a chosen cash/bank account.'],
        'tl' => ['title' => '+ Bagong Receipt', 'body' => 'Itala ang bayad mula sa customer, apply laban sa isa o higit pa nilang open na invoice, ideposito sa piniling cash/bank account.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'The table', 'body' => 'Every receipt ever recorded, with the customer, method, cash account used, and amount.'],
    'tl' => ['title' => 'Ang Talahanayan', 'body' => 'Bawat receipt na naitala, kasama ang customer, method, cash account na ginamit, at halaga.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="invoices.php" class="btn btn-outline">Invoices</a>
            <a href="customers.php" class="btn btn-outline">Customers</a>
            <?php if (has_permission('ar.create')): ?><a href="receipt-form.php" class="btn btn-primary">+ New Receipt</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Receipt No.</th><th>Customer</th><th>Date</th><th>Method</th><th>Cash Account</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr>
                <td><?= e($r['receipt_no']) ?></td>
                <td><?= e($r['customer_name']) ?></td>
                <td><?= format_date($r['receipt_date']) ?></td>
                <td><?= e($r['payment_method']) ?></td>
                <td class="text-muted"><?= e($r['cash_account_name']) ?></td>
                <td class="num"><?= format_currency($r['amount']) ?></td>
                <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                <td>
                    <?php if ($r['status'] !== 'Void' && has_permission('ar.approve')): ?>
                    <form method="post" class="inline-void"><?= csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="text" name="reason" class="form-control" placeholder="Reason" required maxlength="255" style="width:9rem;display:inline-block;">
                        <button type="submit" class="btn btn-outline btn-sm" data-confirm="Void <?= e($r['receipt_no']) ?>? The ledger entry and cash movement will be reversed and the amounts go back on the open invoices.">Void</button>
                    </form>
                    <?php elseif ($r['status'] === 'Void'): ?><span class="text-muted" title="<?= e($r['void_reason'] ?? '') ?>"><?= e($r['void_reason'] ?? '') ?></span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($receipts)): ?><tr><td colspan="8" class="empty-state">No receipts recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
