<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.view');

$db = get_db();
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

function cash_balance_asof(PDO $db, string $asOf): float {
    $openingTotal = (float)$db->query("SELECT COALESCE(SUM(opening_balance),0) FROM cash_accounts")->fetchColumn();
    $stmt = $db->prepare("SELECT type, COALESCE(SUM(amount),0) AS amt FROM cash_transactions WHERE transaction_date <= ? GROUP BY type");
    $stmt->execute([$asOf]);
    $net = 0;
    foreach ($stmt->fetchAll() as $r) {
        $net += in_array($r['type'], ['Deposit','TransferIn'], true) ? $r['amt'] : -$r['amt'];
    }
    return $openingTotal + $net;
}

$categories = ['Operating', 'Investing', 'Financing'];
$categoryTotals = [];
foreach ($categories as $cat) {
    $stmt = $db->prepare("SELECT type, COALESCE(SUM(amount),0) AS amt FROM cash_transactions WHERE cash_flow_category = ? AND transaction_date BETWEEN ? AND ? GROUP BY type");
    $stmt->execute([$cat, $dateFrom, $dateTo]);
    $net = 0;
    foreach ($stmt->fetchAll() as $r) {
        $net += in_array($r['type'], ['Deposit','TransferIn'], true) ? $r['amt'] : -$r['amt'];
    }
    $categoryTotals[$cat] = $net;
}
$netChange = array_sum($categoryTotals);
$beginningBalance = cash_balance_asof($db, date('Y-m-d', strtotime($dateFrom . ' -1 day')));
$endingBalance = $beginningBalance + $netChange;

$pageTitle = 'Cash Flow Statement';
$pageHelp = [
    ['selector' => 'input[name="date_from"]',
        'en' => ['title' => 'From / To', 'body' => 'The date range this statement covers.'],
        'tl' => ['title' => 'From / To', 'body' => 'Ang date range na sinasaklaw ng statement na ito.']],
    ['selector' => 'table.data-table',
        'en' => ['title' => 'Operating / Investing / Financing', 'body' => 'Net cash movement in each category, based on how individual cash transactions were tagged when recorded.'],
        'tl' => ['title' => 'Operating / Investing / Financing', 'body' => 'Net cash movement sa bawat category, batay sa kung paano na-tag ang bawat cash transaction noong na-record ito.']],
    ['selector' => '.kpi-grid',
        'en' => ['title' => 'Beginning / Ending Cash Balance', 'body' => 'Total cash across all accounts just before the range, and at its end.'],
        'tl' => ['title' => 'Beginning / Ending Cash Balance', 'body' => 'Kabuuang cash sa lahat ng account bago magsimula ang range, at sa dulo nito.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="table-toolbar">
        <div class="form-row" style="margin:0;">
            <div class="form-group" style="margin:0;"><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
            <div class="form-group" style="margin:0;"><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary">Run</button>
    </form>
</div>
<div class="card">
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Category</th><th class="num">Net Cash Flow</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr><td><?= e($cat) ?> Activities</td><td class="num"><?= format_currency($categoryTotals[$cat]) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:600;"><td>Net Change in Cash</td><td class="num"><?= format_currency($netChange) ?></td></tr>
        </tfoot>
    </table>
    </div>
    <div class="kpi-grid" style="margin-top:16px;">
        <div class="kpi-card primary"><div class="kpi-label">Beginning Cash Balance</div><div class="kpi-value"><?= format_currency($beginningBalance) ?></div><div class="kpi-sub">As of <?= format_date(date('Y-m-d', strtotime($dateFrom . ' -1 day'))) ?></div></div>
        <div class="kpi-card accent"><div class="kpi-label">Ending Cash Balance</div><div class="kpi-value"><?= format_currency($endingBalance) ?></div><div class="kpi-sub">As of <?= format_date($dateTo) ?></div></div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
