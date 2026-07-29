<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('tax.view');

$db = get_db();
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$sql = "SELECT ty.name AS tax_name, tt.direction,
        SUM(tt.tax_amount) AS total_amount,
        SUM(CASE WHEN tt.status='Remitted' THEN tt.tax_amount ELSE 0 END) AS remitted_amount,
        SUM(CASE WHEN tt.status='Pending' THEN tt.tax_amount ELSE 0 END) AS pending_amount
        FROM tax_transactions tt JOIN tax_types ty ON ty.id = tt.tax_type_id
        WHERE tt.transaction_date BETWEEN ? AND ?
        GROUP BY ty.name, tt.direction ORDER BY ty.name, tt.direction";
$stmt = $db->prepare($sql);
$stmt->execute([$dateFrom, $dateTo]);
$rows = $stmt->fetchAll();

$pageTitle = 'Tax Compliance Summary';
$pageHelp = [
    ['selector' => 'input[name="date_from"]',
        'en' => ['title' => 'Date range', 'body' => 'Summarizes tax activity within this window, grouped by tax type and direction.'],
        'tl' => ['title' => 'Date range', 'body' => 'Ibinubuod ang tax activity sa loob ng window na ito, naka-grupo ayon sa tax type at direction.']],
    ['selector' => 'table.data-table',
        'en' => ['title' => 'Pending badge', 'body' => 'Amber if there\'s still an unremitted amount for that type/direction; green once fully remitted.'],
        'tl' => ['title' => 'Pending badge', 'body' => 'Dilaw kung may hindi pa naremit na halaga para sa type/direction na iyon; berde kapag na-remit na nang buo.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="form-row">
        <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        <div class="form-group" style="display:flex;align-items:flex-end;"><button type="submit" class="btn btn-primary">Run Report</button></div>
    </form>
</div>
<div class="card">
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Tax Type</th><th>Direction</th><th class="num">Total</th><th class="num">Remitted</th><th class="num">Pending</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['tax_name']) ?></td>
                <td><?= e($r['direction']) ?></td>
                <td class="num"><?= format_currency($r['total_amount']) ?></td>
                <td class="num"><?= format_currency($r['remitted_amount']) ?></td>
                <td class="num"><span class="badge <?= $r['pending_amount'] > 0 ? 'badge-pending' : 'badge-success' ?>"><?= format_currency($r['pending_amount']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="5" class="empty-state">No tax activity in this period.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
