<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('tax.view');

$db = get_db();
$direction = $_GET['direction'] ?? '';
$status = $_GET['status'] ?? '';
$sql = "SELECT tt.*, ty.name AS tax_name, ty.code FROM tax_transactions tt JOIN tax_types ty ON ty.id = tt.tax_type_id WHERE 1=1";
$params = [];
if ($direction !== '') { $sql .= " AND tt.direction = ?"; $params[] = $direction; }
if ($status !== '') { $sql .= " AND tt.status = ?"; $params[] = $status; }
$sql .= " ORDER BY tt.transaction_date DESC, tt.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$pageTitle = 'Tax Transactions';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <select name="direction" onchange="this.form.submit()">
                <option value="">All Directions</option>
                <?php foreach (['Input','Output','Withholding'] as $d): ?><option value="<?= $d ?>" <?= $direction===$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['Pending','Remitted'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
            </select>
        </form>
        <div>
            <a href="tax-types.php" class="btn btn-outline">Tax Types</a>
            <a href="remittances.php" class="btn btn-outline">Remittances</a>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Tax Type</th><th>Source</th><th>Direction</th><th class="num">Taxable Amount</th><th class="num">Tax Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td><?= format_date($t['transaction_date']) ?></td>
                <td><?= e($t['tax_name']) ?></td>
                <td class="text-muted"><?= e($t['source_module']) ?> #<?= (int)$t['source_id'] ?></td>
                <td><?= e($t['direction']) ?></td>
                <td class="num"><?= format_currency($t['taxable_amount']) ?></td>
                <td class="num"><?= format_currency($t['tax_amount']) ?></td>
                <td><span class="badge <?= status_badge_class($t['status']==='Remitted'?'Paid':'Pending') ?>"><?= e($t['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($transactions)): ?><tr><td colspan="7" class="empty-state">No tax transactions recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
