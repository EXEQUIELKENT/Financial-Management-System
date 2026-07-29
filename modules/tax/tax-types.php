<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('tax.view');

$db = get_db();
$taxTypes = $db->query("SELECT * FROM tax_types ORDER BY name")->fetchAll();

$pageTitle = 'Tax Types & Rates';
$pageHelp = [];
if (has_permission('tax.create')) {
    $pageHelp[] = ['selector' => 'a[href="tax-type-form.php"]',
        'en' => ['title' => '+ New Tax Type', 'body' => 'Defines a tax (e.g. VAT, Withholding Tax) and its rate, available to pick on Bill/Invoice lines and AP payments.'],
        'tl' => ['title' => '+ Bagong Tax Type', 'body' => 'Nagtatakda ng buwis (hal. VAT, Withholding Tax) at ang rate nito, na puwedeng piliin sa Bill/Invoice lines at AP payments.']];
    $pageHelp[] = ['selector' => 'a.btn-outline.btn-sm',
        'en' => ['title' => 'Edit', 'body' => 'Change the rate or deactivate a tax type.'],
        'tl' => ['title' => 'Edit', 'body' => 'Baguhin ang rate o i-deactivate ang isang tax type.']];
}
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="transactions.php" class="btn btn-outline">Tax Transactions</a>
            <a href="remittances.php" class="btn btn-outline">Remittances</a>
            <a href="compliance-report.php" class="btn btn-outline">Compliance Summary</a>
            <?php if (has_permission('tax.create')): ?><a href="tax-type-form.php" class="btn btn-primary">+ New Tax Type</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Code</th><th>Name</th><th class="num">Rate</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($taxTypes as $t): ?>
            <tr>
                <td><?= e($t['code']) ?></td>
                <td><?= e($t['name']) ?></td>
                <td class="num"><?= e($t['rate_percent']) ?>%</td>
                <td><span class="badge <?= $t['is_active'] ? 'badge-success' : 'badge-void' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td><?php if (has_permission('tax.create')): ?><a href="tax-type-form.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">Edit</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($taxTypes)): ?><tr><td colspan="5" class="empty-state">No tax types configured.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
