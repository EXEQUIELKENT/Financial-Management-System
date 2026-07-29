<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('budget.view');

$db = get_db();
$budgets = $db->query("SELECT b.*, bp.name AS period_name, bp.start_date, bp.end_date FROM budgets b JOIN budget_periods bp ON bp.id = b.budget_period_id WHERE b.status='Approved' ORDER BY b.id DESC")->fetchAll();

$budgetId = (int)($_GET['budget_id'] ?? ($budgets[0]['id'] ?? 0));
$asOfMonth = (int)($_GET['as_of_month'] ?? date('n'));

$rows = [];
$selectedBudget = null;
foreach ($budgets as $b) { if ((int)$b['id'] === $budgetId) $selectedBudget = $b; }

if ($selectedBudget) {
    $lineStmt = $db->prepare("SELECT bl.*, a.account_code, a.account_name, a.account_type, a.normal_balance FROM budget_lines bl JOIN coa_accounts a ON a.id = bl.account_id WHERE bl.budget_id = ? ORDER BY a.account_code");
    $lineStmt->execute([$budgetId]);
    $lines = $lineStmt->fetchAll();

    $periodStart = $selectedBudget['start_date'];
    $periodEnd = date('Y-m-t', strtotime(date('Y-' . str_pad($asOfMonth, 2, '0', STR_PAD_LEFT) . '-01', strtotime($periodStart))));

    foreach ($lines as $l) {
        $monthStmt = $db->prepare("SELECT COALESCE(SUM(budgeted_amount),0) FROM budget_line_monthly WHERE budget_line_id = ? AND month <= ?");
        $monthStmt->execute([$l['id'], $asOfMonth]);
        $budgeted = (float)$monthStmt->fetchColumn();

        $actualStmt = $db->prepare("SELECT COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc
                                     FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
                                     WHERE jl.account_id = ? AND je.status='Posted' AND je.entry_date BETWEEN ? AND ?");
        $actualStmt->execute([$l['account_id'], $periodStart, $periodEnd]);
        $a = $actualStmt->fetch();
        $actual = $l['normal_balance'] === 'Debit' ? ($a['td'] - $a['tc']) : ($a['tc'] - $a['td']);

        $variance = $budgeted - $actual;
        $utilization = $budgeted != 0 ? round($actual / $budgeted * 100, 1) : 0;

        $rows[] = [
            'account' => $l['account_code'] . ' - ' . $l['account_name'],
            'type' => $l['account_type'],
            'budgeted' => $budgeted,
            'actual' => $actual,
            'variance' => $variance,
            'utilization' => $utilization,
        ];
    }
}

$pageTitle = 'Budget vs Actual Variance';
$pageHelp = [
    ['selector' => 'select[name="budget_id"]',
        'en' => ['title' => 'Budget picker', 'body' => 'Only Approved budgets appear here — Draft budgets don\'t count toward variance.'],
        'tl' => ['title' => 'Pumili ng Budget', 'body' => 'Ang mga Approved na budget lang ang lalabas dito — hindi kasama ang mga Draft.']],
    ['selector' => 'select[name="as_of_month"]',
        'en' => ['title' => 'As Of Month', 'body' => 'Compares year-to-date Budgeted vs. Actual up through that month.'],
        'tl' => ['title' => 'As Of Month', 'body' => 'Inihahambing ang year-to-date Budgeted kumpara sa Actual hanggang sa buwang iyon.']],
    ['selector' => 'table.data-table',
        'en' => ['title' => 'Utilization badge', 'body' => 'Green under 90%, amber 90-100%, red over 100% of the budgeted amount spent/earned so far.'],
        'tl' => ['title' => 'Utilization badge', 'body' => 'Berde kung mas mababa sa 90%, dilaw sa 90-100%, pula kung lampas 100% ng budgeted amount na nagastos/nakuha.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="form-row">
        <div class="form-group"><label>Budget</label>
            <select name="budget_id" onchange="this.form.submit()">
                <?php foreach ($budgets as $b): ?><option value="<?= $b['id'] ?>" <?= $budgetId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name'] . ' (' . $b['period_name'] . ')') ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>As Of Month</label>
            <select name="as_of_month" onchange="this.form.submit()">
                <?php $mnames=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; foreach ($mnames as $idx => $mn): ?>
                    <option value="<?= $idx+1 ?>" <?= $asOfMonth === $idx+1 ? 'selected' : '' ?>><?= $mn ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>
<?php if (empty($budgets)): ?>
    <div class="card"><p class="empty-state">No approved budgets yet. Approve a budget first.</p></div>
<?php else: ?>
<div class="card">
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Account</th><th>Type</th><th class="num">Budgeted (YTD)</th><th class="num">Actual (YTD)</th><th class="num">Variance</th><th class="num">Utilization</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['account']) ?></td>
                <td class="text-muted"><?= e($r['type']) ?></td>
                <td class="num"><?= format_currency($r['budgeted']) ?></td>
                <td class="num"><?= format_currency($r['actual']) ?></td>
                <td class="num <?= $r['variance'] < 0 ? 'text-danger' : 'text-success' ?>"><?= format_currency($r['variance']) ?></td>
                <td class="num"><span class="badge <?= $r['utilization'] > 100 ? 'badge-danger' : ($r['utilization'] > 90 ? 'badge-pending' : 'badge-success') ?>"><?= $r['utilization'] ?>%</span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="empty-state">No account lines in this budget.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
