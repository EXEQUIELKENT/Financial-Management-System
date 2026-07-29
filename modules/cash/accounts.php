<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('cash.view');

$db = get_db();
$accounts = $db->query("SELECT ca.*, a.account_code, a.account_name AS gl_account_name FROM cash_accounts ca JOIN coa_accounts a ON a.id = ca.gl_account_id ORDER BY ca.account_name")->fetchAll();

$pageTitle = 'Cash & Bank Accounts';
$pageHelp = [];
if (has_permission('cash.create')) {
    $pageHelp[] = ['selector' => 'a[href="account-form.php"]',
        'en' => ['title' => '+ New Account', 'body' => 'Adds a cash/bank account, linked to a GL asset account, with an opening balance.'],
        'tl' => ['title' => '+ Bagong Account', 'body' => 'Nagdadagdag ng cash/bank account, na naka-link sa isang GL asset account, na may opening balance.']];
}
$pageHelp[] = ['selector' => 'a.btn-outline.btn-sm',
    'en' => ['title' => 'Register', 'body' => 'Opens the full transaction history and running balance for that account.'],
    'tl' => ['title' => 'Register', 'body' => 'Binubuksan ang buong transaction history at running balance ng account na iyon.']];
if (has_permission('cash.create')) {
    $pageHelp[] = ['selector' => 'a.btn-outline.btn-sm', 'nth' => 1,
        'en' => ['title' => 'Edit', 'body' => 'Update the account\'s name, bank details, linked GL account, or status.'],
        'tl' => ['title' => 'Edit', 'body' => 'I-update ang pangalan, detalye ng bangko, naka-link na GL account, o status ng account.']];
}
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
