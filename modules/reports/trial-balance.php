<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_permission('reports.view');

$asOf = $_GET['as_of'] ?? date('Y-m-d');
$rows = get_trial_balance($asOf);

$totalDebit = 0; $totalCredit = 0;
foreach ($rows as &$r) {
    if ($r['normal_balance'] === 'Debit') {
        $bal = $r['total_debit'] - $r['total_credit'];
        $r['debit_col'] = $bal >= 0 ? $bal : 0;
        $r['credit_col'] = $bal < 0 ? -$bal : 0;
    } else {
        $bal = $r['total_credit'] - $r['total_debit'];
        $r['credit_col'] = $bal >= 0 ? $bal : 0;
        $r['debit_col'] = $bal < 0 ? -$bal : 0;
    }
    $totalDebit += $r['debit_col'];
    $totalCredit += $r['credit_col'];
}
unset($r);

if (isset($_GET['export'])) {
    $csvRows = [];
    foreach ($rows as $r) { $csvRows[] = [$r['account_code'], $r['account_name'], $r['debit_col'], $r['credit_col']]; }
    export_to_csv('trial-balance-' . $asOf . '.csv', ['Code','Account','Debit','Credit'], $csvRows);
}

$pageTitle = 'Trial Balance';
$pageHelp = [
    ['selector' => 'input[name="as_of"]',
        'en' => ['title' => 'As Of date', 'body' => 'Shows every account\'s Debit/Credit movement up through this date.'],
        'tl' => ['title' => 'As Of date', 'body' => 'Ipinapakita ang Debit/Credit movement ng bawat account hanggang sa petsang ito.']],
    ['selector' => '.alert-success, .alert-critical',
        'en' => ['title' => 'Bottom banner', 'body' => 'Green "Books are in balance" when Total Debit equals Total Credit — should always be true unless a document was edited outside the normal workflow.'],
        'tl' => ['title' => 'Bottom banner', 'body' => 'Berdeng "Books are in balance" kapag magkatumbas ang Total Debit at Total Credit — dapat laging totoo maliban kung may na-edit na dokumento sa labas ng normal na workflow.']],
    ['selector' => 'a[href*="export=1"]',
        'en' => ['title' => 'Export CSV', 'body' => 'Downloads exactly what\'s shown in the table.'],
        'tl' => ['title' => 'Export CSV', 'body' => 'Nagda-download ng eksaktong nakikita sa table.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="table-toolbar">
        <div class="form-group" style="margin:0;"><label>As Of</label><input type="date" name="as_of" value="<?= e($asOf) ?>" onchange="this.form.submit()"></div>
        <a href="trial-balance.php?as_of=<?= e($asOf) ?>&export=1" class="btn btn-outline">Export CSV</a>
    </form>
</div>
<div class="card">
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Code</th><th>Account</th><th>Type</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): if ($r['debit_col'] == 0 && $r['credit_col'] == 0) continue; ?>
            <tr>
                <td><?= e($r['account_code']) ?></td>
                <td><?= e($r['account_name']) ?></td>
                <td class="text-muted"><?= e($r['account_type']) ?></td>
                <td class="num"><?= $r['debit_col'] > 0 ? format_currency($r['debit_col']) : '' ?></td>
                <td class="num"><?= $r['credit_col'] > 0 ? format_currency($r['credit_col']) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:600;"><td colspan="3">Total</td><td class="num"><?= format_currency($totalDebit) ?></td><td class="num"><?= format_currency($totalCredit) ?></td></tr>
        </tfoot>
    </table>
    </div>
    <?php if (round($totalDebit, 2) === round($totalCredit, 2)): ?>
        <div class="alert alert-success" style="margin-top:16px;">Books are in balance.</div>
    <?php else: ?>
        <div class="alert alert-critical" style="margin-top:16px;">Out of balance by <?= format_currency(abs($totalDebit - $totalCredit)) ?>.</div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
