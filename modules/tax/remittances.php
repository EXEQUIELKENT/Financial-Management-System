<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('tax.view');

$db = get_db();
$remittances = $db->query("SELECT r.*, t.name AS tax_name FROM tax_remittances r JOIN tax_types t ON t.id = r.tax_type_id ORDER BY r.period_end DESC")->fetchAll();

$pageTitle = 'Tax Remittances';
$pageHelp = [];
if (has_permission('tax.create')) {
    $pageHelp[] = ['selector' => 'a[href="remittance-form.php"]',
        'en' => ['title' => '+ New Remittance', 'body' => 'Pay the government: aggregates every matching Pending tax transaction for a type/direction/period, marks them Remitted, and posts the GL entry.'],
        'tl' => ['title' => '+ Bagong Remittance', 'body' => 'Pagbabayad sa gobyerno: pinagsasama-sama ang lahat ng tumutugmang Pending na tax transaction para sa isang type/direction/period, minamarkahang Remitted, at nagpo-post ng GL entry.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'Table', 'body' => 'Every remittance ever filed, by tax type and period.'],
    'tl' => ['title' => 'Table', 'body' => 'Lahat ng remittance na na-file na, ayon sa tax type at period.']];
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
