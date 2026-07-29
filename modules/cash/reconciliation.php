<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('cash.view');

$db = get_db();
$recons = $db->query("SELECT r.*, ca.account_name FROM bank_reconciliations r JOIN cash_accounts ca ON ca.id = r.cash_account_id ORDER BY r.statement_date DESC")->fetchAll();

$pageTitle = 'Bank Reconciliation';
$pageHelp = [];
if (has_permission('cash.create')) {
    $pageHelp[] = ['selector' => 'a[href="reconciliation-form.php"]',
        'en' => ['title' => '+ New Reconciliation', 'body' => 'Starts matching an account\'s books against a real bank statement as of a given date.'],
        'tl' => ['title' => '+ Bagong Reconciliation', 'body' => 'Nagsisimula ng pagtutugma ng mga libro ng account laban sa aktwal na bank statement sa isang partikular na petsa.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'Open', 'body' => 'Continue adding reconciling items, or view a completed reconciliation.'],
    'tl' => ['title' => 'Open', 'body' => 'Ipagpatuloy ang pagdaragdag ng reconciling items, o tingnan ang isang tapos nang reconciliation.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="accounts.php" class="btn btn-outline">Accounts</a>
            <?php if (has_permission('cash.create')): ?><a href="reconciliation-form.php" class="btn btn-primary">+ New Reconciliation</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Account</th><th>Statement Date</th><th class="num">Statement Balance</th><th class="num">Book Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recons as $r): ?>
            <tr>
                <td><?= e($r['account_name']) ?></td>
                <td><?= format_date($r['statement_date']) ?></td>
                <td class="num"><?= format_currency($r['statement_balance']) ?></td>
                <td class="num"><?= format_currency($r['book_balance']) ?></td>
                <td><span class="badge <?= status_badge_class($r['status']==='Completed'?'Paid':'Draft') ?>"><?= e($r['status']) ?></span></td>
                <td><a href="reconciliation-form.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recons)): ?><tr><td colspan="6" class="empty-state">No reconciliations yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
