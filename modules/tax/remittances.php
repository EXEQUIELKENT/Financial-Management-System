<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('tax.view');

$db = get_db();
$remittances = $db->query("SELECT r.*, t.name AS tax_name FROM tax_remittances r JOIN tax_types t ON t.id = r.tax_type_id ORDER BY r.period_end DESC")->fetchAll();

$pageTitle = 'Tax Remittances';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="transactions.php" class="btn btn-outline">Tax Transactions</a>
            <?php if (has_permission('tax.create')): ?><a href="remittance-form.php" class="btn btn-primary">+ New Remittance</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Remittance No.</th><th>Tax Type</th><th>Period</th><th class="num">Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($remittances as $r): ?>
            <tr>
                <td><?= e($r['remittance_no']) ?></td>
                <td><?= e($r['tax_name']) ?></td>
                <td><?= format_date($r['period_start']) ?> – <?= format_date($r['period_end']) ?></td>
                <td class="num"><?= format_currency($r['total_amount']) ?></td>
                <td><span class="badge <?= status_badge_class($r['status']==='Remitted'?'Paid':'Draft') ?>"><?= e($r['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($remittances)): ?><tr><td colspan="5" class="empty-state">No remittances recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
