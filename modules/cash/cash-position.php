<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('cash.view');

$db = get_db();
$accounts = $db->query("SELECT * FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();
$totalCash = array_sum(array_column($accounts, 'current_balance'));

$recentStmt = $db->query("SELECT t.*, ca.account_name FROM cash_transactions t JOIN cash_accounts ca ON ca.id = t.cash_account_id ORDER BY t.transaction_date DESC, t.id DESC LIMIT 15");
$recent = $recentStmt->fetchAll();

$pageTitle = 'Cash Position';
include __DIR__ . '/../../includes/header.php';
?>
<div class="kpi-grid">
    <div class="kpi-card primary">
        <div class="kpi-label">Total Cash Position</div>
        <div class="kpi-value"><?= format_currency($totalCash) ?></div>
        <div class="kpi-sub">Across <?= count($accounts) ?> active accounts</div>
    </div>
    <?php foreach ($accounts as $a): ?>
    <div class="kpi-card accent">
        <div class="kpi-label"><?= e($a['account_name']) ?></div>
        <div class="kpi-value"><?= format_currency($a['current_balance']) ?></div>
        <div class="kpi-sub"><?= e($a['account_type']) ?><?= $a['bank_name'] ? ' · ' . e($a['bank_name']) : '' ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-toolbar">
        <h3 class="mb-0">Recent Activity</h3>
        <div>
            <a href="accounts.php" class="btn btn-outline">Accounts</a>
            <a href="transactions.php" class="btn btn-outline">All Transactions</a>
            <a href="transfers.php" class="btn btn-outline">Transfers</a>
            <a href="reconciliation.php" class="btn btn-outline">Reconciliation</a>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Account</th><th>Type</th><th>Description</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $t): ?>
            <tr>
                <td><?= format_date($t['transaction_date']) ?></td>
                <td><?= e($t['account_name']) ?></td>
                <td><span class="badge <?= in_array($t['type'],['Deposit','TransferIn'],true) ? 'badge-success' : 'badge-danger' ?>"><?= e($t['type']) ?></span></td>
                <td><?= e($t['description']) ?></td>
                <td class="num"><?= format_currency($t['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?><tr><td colspan="5" class="empty-state">No recent activity.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
