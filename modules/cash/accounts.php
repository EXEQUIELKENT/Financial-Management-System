<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('cash.view');

$db = get_db();
$accounts = $db->query("SELECT ca.*, a.account_code, a.account_name AS gl_account_name FROM cash_accounts ca JOIN coa_accounts a ON a.id = ca.gl_account_id ORDER BY ca.account_name")->fetchAll();

$pageTitle = 'Cash & Bank Accounts';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="cash-position.php" class="btn btn-outline">Cash Position</a>
            <a href="transactions.php" class="btn btn-outline">Transactions</a>
            <a href="transfers.php" class="btn btn-outline">Transfers</a>
            <a href="reconciliation.php" class="btn btn-outline">Reconciliation</a>
            <?php if (has_permission('cash.create')): ?><a href="account-form.php" class="btn btn-primary">+ New Account</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Account Name</th><th>Type</th><th>Bank</th><th>GL Account</th><th class="num">Current Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($accounts as $a): ?>
            <tr>
                <td><?= e($a['account_name']) ?></td>
                <td><?= e($a['account_type']) ?></td>
                <td class="text-muted"><?= e($a['bank_name'] ?: '—') ?> <?= $a['account_no'] ? '· '.e($a['account_no']) : '' ?></td>
                <td class="text-muted"><?= e($a['account_code'].' - '.$a['gl_account_name']) ?></td>
                <td class="num"><?= format_currency($a['current_balance']) ?></td>
                <td><span class="badge <?= status_badge_class($a['status']) ?>"><?= e($a['status']) ?></span></td>
                <td>
                    <a href="account-register.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">Register</a>
                    <?php if (has_permission('cash.create')): ?><a href="account-form.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">Edit</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($accounts)): ?><tr><td colspan="7" class="empty-state">No cash/bank accounts yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
