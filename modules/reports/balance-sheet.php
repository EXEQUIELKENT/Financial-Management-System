<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_permission('reports.view');

$db = get_db();
$asOf = $_GET['as_of'] ?? date('Y-m-d');

function bs_section($db, $type, $asOf) {
    $accounts = $db->query("SELECT * FROM coa_accounts WHERE account_type='$type' AND is_active=1 ORDER BY account_code")->fetchAll();
    $rows = []; $total = 0;
    foreach ($accounts as $a) {
        $bal = get_account_balance((int)$a['id'], $asOf);
        if ($bal != 0) { $rows[] = ['name' => $a['account_name'], 'amount' => $bal]; $total += $bal; }
    }
    return [$rows, $total];
}

[$assetRows, $totalAssets] = bs_section($db, 'Asset', $asOf);
[$liabilityRows, $totalLiabilities] = bs_section($db, 'Liability', $asOf);
[$equityRows, $totalEquity] = bs_section($db, 'Equity', $asOf);
[$revenueRows, $totalRevenue] = bs_section($db, 'Revenue', $asOf);
[$expenseRows, $totalExpense] = bs_section($db, 'Expense', $asOf);
$netIncomeToDate = $totalRevenue - $totalExpense;
$totalEquityWithEarnings = $totalEquity + $netIncomeToDate;

$pageTitle = 'Balance Sheet';
$pageHelp = [
    ['selector' => 'input[name="as_of"]',
        'en' => ['title' => 'As Of date', 'body' => 'Every account\'s balance as of this single date (not a range).'],
        'tl' => ['title' => 'As Of date', 'body' => 'Ang balance ng bawat account sa iisang petsang ito (hindi range).']],
    ['selector' => 'table.data-table', 'nth' => 2,
        'en' => ['title' => 'Current Earnings (to date)', 'body' => 'Cumulative Revenue minus Expenses since the start of the ledger — rolled into Equity so the sheet balances.'],
        'tl' => ['title' => 'Current Earnings (to date)', 'body' => 'Kabuuang Revenue bawas Expenses mula nang magsimula ang ledger — isinasama sa Equity para magbalanse ang sheet.']],
    ['selector' => '.kpi-card', 'nth' => 2,
        'en' => ['title' => 'Balance Check card', 'body' => 'Confirms Assets = Liabilities + Equity — should always read "Balanced".'],
        'tl' => ['title' => 'Balance Check card', 'body' => 'Kinukumpirma na Assets = Liabilities + Equity — dapat laging "Balanced" ang nababasa.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="table-toolbar">
        <div class="form-group" style="margin:0;"><label>As Of</label><input type="date" name="as_of" value="<?= e($asOf) ?>" onchange="this.form.submit()"></div>
    </form>
</div>
<div class="card">
    <h3>Assets</h3>
    <div class="table-wrap"><table class="data-table"><tbody>
        <?php foreach ($assetRows as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= format_currency($r['amount']) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot><tr style="font-weight:600;"><td>Total Assets</td><td class="num"><?= format_currency($totalAssets) ?></td></tr></tfoot></table></div>

    <h3 style="margin-top:24px;">Liabilities</h3>
    <div class="table-wrap"><table class="data-table"><tbody>
        <?php foreach ($liabilityRows as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= format_currency($r['amount']) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot><tr style="font-weight:600;"><td>Total Liabilities</td><td class="num"><?= format_currency($totalLiabilities) ?></td></tr></tfoot></table></div>

    <h3 style="margin-top:24px;">Equity</h3>
    <div class="table-wrap"><table class="data-table"><tbody>
        <?php foreach ($equityRows as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= format_currency($r['amount']) ?></td></tr><?php endforeach; ?>
        <tr><td>Current Earnings (to date)</td><td class="num"><?= format_currency($netIncomeToDate) ?></td></tr>
    </tbody><tfoot><tr style="font-weight:600;"><td>Total Equity</td><td class="num"><?= format_currency($totalEquityWithEarnings) ?></td></tr></tfoot></table></div>

    <div class="kpi-grid" style="margin-top:24px;">
        <div class="kpi-card primary"><div class="kpi-label">Total Assets</div><div class="kpi-value"><?= format_currency($totalAssets) ?></div></div>
        <div class="kpi-card primary"><div class="kpi-label">Liabilities + Equity</div><div class="kpi-value"><?= format_currency($totalLiabilities + $totalEquityWithEarnings) ?></div></div>
        <div class="kpi-card <?= abs($totalAssets - ($totalLiabilities + $totalEquityWithEarnings)) < 0.01 ? 'accent' : 'danger' ?>">
            <div class="kpi-label">Balance Check</div>
            <div class="kpi-value"><?= abs($totalAssets - ($totalLiabilities + $totalEquityWithEarnings)) < 0.01 ? 'Balanced ✓' : 'Out of Balance' ?></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
