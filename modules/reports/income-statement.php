<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.view');

$db = get_db();
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

function is_movement($db, $accountId, $normalBalance, $dateFrom, $dateTo) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc
                           FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
                           WHERE jl.account_id = ? AND je.status='Posted' AND je.entry_date BETWEEN ? AND ?");
    $stmt->execute([$accountId, $dateFrom, $dateTo]);
    $r = $stmt->fetch();
    return $normalBalance === 'Debit' ? ($r['td'] - $r['tc']) : ($r['tc'] - $r['td']);
}

$revenueAccounts = $db->query("SELECT * FROM coa_accounts WHERE account_type='Revenue' AND is_active=1 ORDER BY account_code")->fetchAll();
$expenseAccounts = $db->query("SELECT * FROM coa_accounts WHERE account_type='Expense' AND is_active=1 ORDER BY account_code")->fetchAll();

$totalRevenue = 0; $revenueRows = [];
foreach ($revenueAccounts as $a) {
    $amt = is_movement($db, $a['id'], $a['normal_balance'], $dateFrom, $dateTo);
    if ($amt != 0) { $revenueRows[] = ['name' => $a['account_name'], 'amount' => $amt]; $totalRevenue += $amt; }
}
$totalExpense = 0; $expenseRows = [];
foreach ($expenseAccounts as $a) {
    $amt = is_movement($db, $a['id'], $a['normal_balance'], $dateFrom, $dateTo);
    if ($amt != 0) { $expenseRows[] = ['name' => $a['account_name'], 'amount' => $amt]; $totalExpense += $amt; }
}
$netIncome = $totalRevenue - $totalExpense;

if (isset($_GET['export'])) {
    $csvRows = [];
    $csvRows[] = ['Revenue']; foreach ($revenueRows as $r) $csvRows[] = [$r['name'], $r['amount']];
    $csvRows[] = ['Total Revenue', $totalRevenue];
    $csvRows[] = ['Expenses']; foreach ($expenseRows as $r) $csvRows[] = [$r['name'], $r['amount']];
    $csvRows[] = ['Total Expenses', $totalExpense];
    $csvRows[] = ['Net Income', $netIncome];
    export_to_csv('income-statement-' . $dateFrom . '_to_' . $dateTo . '.csv', ['Line','Amount'], $csvRows);
}

$pageTitle = 'Income Statement';
$pageHelp = [
    ['selector' => 'input[name="date_from"]',
        'en' => ['title' => 'From / To', 'body' => 'The date range this statement covers.'],
        'tl' => ['title' => 'From / To', 'body' => 'Ang date range na sinasaklaw ng statement na ito.']],
    ['selector' => '.kpi-card',
        'en' => ['title' => 'Net Income card', 'body' => 'Total Revenue minus Total Expenses for the range — green if positive, red if a loss.'],
        'tl' => ['title' => 'Net Income card', 'body' => 'Total Revenue bawas Total Expenses para sa range — berde kung positibo, pula kung lugi.']],
    ['selector' => 'a[href*="export=1"]',
        'en' => ['title' => 'Export CSV', 'body' => 'Downloads the full Revenue/Expense breakdown.'],
        'tl' => ['title' => 'Export CSV', 'body' => 'Nagda-download ng buong breakdown ng Revenue/Expense.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="table-toolbar">
        <div class="form-row" style="margin:0;">
            <div class="form-group" style="margin:0;"><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
            <div class="form-group" style="margin:0;"><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Run</button>
            <a href="income-statement.php?date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>&export=1" class="btn btn-outline">Export CSV</a>
        </div>
    </form>
</div>
<div class="card">
    <h3>Revenue</h3>
    <div class="table-wrap"><table class="data-table">
        <tbody>
        <?php foreach ($revenueRows as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= format_currency($r['amount']) ?></td></tr><?php endforeach; ?>
        <?php if (empty($revenueRows)): ?><tr><td class="empty-state" colspan="2">No revenue in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr style="font-weight:600;"><td>Total Revenue</td><td class="num"><?= format_currency($totalRevenue) ?></td></tr></tfoot>
    </table></div>

    <h3 style="margin-top:24px;">Expenses</h3>
    <div class="table-wrap"><table class="data-table">
        <tbody>
        <?php foreach ($expenseRows as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= format_currency($r['amount']) ?></td></tr><?php endforeach; ?>
        <?php if (empty($expenseRows)): ?><tr><td class="empty-state" colspan="2">No expenses in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr style="font-weight:600;"><td>Total Expenses</td><td class="num"><?= format_currency($totalExpense) ?></td></tr></tfoot>
    </table></div>

    <div class="kpi-grid" style="margin-top:24px;">
        <div class="kpi-card <?= $netIncome >= 0 ? 'accent' : 'danger' ?>">
            <div class="kpi-label">Net Income</div>
            <div class="kpi-value"><?= format_currency($netIncome) ?></div>
            <div class="kpi-sub"><?= format_date($dateFrom) ?> to <?= format_date($dateTo) ?></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
