<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('cash.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM cash_accounts WHERE id = ?");
$stmt->execute([$id]);
$account = $stmt->fetch();
if (!$account) { flash('error', 'Cash account not found.'); redirect('modules/cash/accounts.php'); }

$txStmt = $db->prepare("SELECT * FROM cash_transactions WHERE cash_account_id = ? ORDER BY transaction_date, id");
$txStmt->execute([$id]);
$transactions = $txStmt->fetchAll();

$running = (float)$account['opening_balance'];
foreach ($transactions as &$t) {
    $delta = in_array($t['type'], ['Deposit','TransferIn'], true) ? $t['amount'] : -$t['amount'];
    if ($t['type'] === 'Adjustment') $delta = $t['amount'];
    $running += $delta;
    $t['running_balance'] = $running;
}
unset($t);
$transactions = array_reverse($transactions);

$pageTitle = 'Register: ' . $account['account_name'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($account['account_name']) ?></h3>
        <div class="kpi-value"><?= format_currency($account['current_balance']) ?></div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Reference</th><th>Source</th><th class="num">Amount</th><th class="num">Balance</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td><?= format_date($t['transaction_date']) ?></td>
                <td><span class="badge <?= in_array($t['type'],['Deposit','TransferIn'],true) ? 'badge-success' : 'badge-danger' ?>"><?= e($t['type']) ?></span></td>
                <td><?= e($t['description']) ?></td>
                <td class="text-muted"><?= e($t['reference']) ?></td>
                <td class="text-muted"><?= e($t['source_module'] ?: 'manual') ?></td>
                <td class="num"><?= format_currency($t['amount']) ?></td>
                <td class="num"><?= format_currency($t['running_balance']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($transactions)): ?><tr><td colspan="7" class="empty-state">No transactions recorded yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<a href="accounts.php" class="btn btn-outline">Back to Accounts</a>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
