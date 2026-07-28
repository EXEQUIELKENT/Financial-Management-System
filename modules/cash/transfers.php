<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('cash.view');

$db = get_db();
$transfers = $db->query("SELECT t.*, f.account_name AS from_name, d.account_name AS to_name
                          FROM cash_transfers t JOIN cash_accounts f ON f.id = t.from_cash_account_id JOIN cash_accounts d ON d.id = t.to_cash_account_id
                          ORDER BY t.transfer_date DESC, t.id DESC")->fetchAll();

$pageTitle = 'Cash Transfers';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="accounts.php" class="btn btn-outline">Accounts</a>
            <?php if (has_permission('cash.create')): ?><a href="transfer-form.php" class="btn btn-primary">+ New Transfer</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Transfer No.</th><th>Date</th><th>From</th><th>To</th><th class="num">Amount</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach ($transfers as $t): ?>
            <tr>
                <td><?= e($t['transfer_no']) ?></td>
                <td><?= format_date($t['transfer_date']) ?></td>
                <td><?= e($t['from_name']) ?></td>
                <td><?= e($t['to_name']) ?></td>
                <td class="num"><?= format_currency($t['amount']) ?></td>
                <td class="text-muted"><?= e($t['description']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($transfers)): ?><tr><td colspan="6" class="empty-state">No transfers recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
