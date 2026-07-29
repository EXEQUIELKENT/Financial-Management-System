<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.view');

$db = get_db();
$accounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts ORDER BY account_code")->fetchAll();
$selectedAccounts = array_map('intval', $_GET['account_ids'] ?? []);
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$rows = [];
if (!empty($selectedAccounts)) {
    $in = implode(',', array_fill(0, count($selectedAccounts), '?'));
    $sql = "SELECT jl.*, je.entry_no, je.entry_date, je.description, je.source_module, a.account_code, a.account_name
            FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id JOIN coa_accounts a ON a.id = jl.account_id
            WHERE jl.account_id IN ($in) AND je.status='Posted' AND je.entry_date BETWEEN ? AND ?
            ORDER BY je.entry_date, je.id";
    $params = array_merge($selectedAccounts, [$dateFrom, $dateTo]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if (isset($_GET['export']) && !empty($rows)) {
    $csvRows = [];
    foreach ($rows as $r) { $csvRows[] = [$r['entry_date'], $r['entry_no'], $r['account_code'].' - '.$r['account_name'], $r['description'], $r['debit'], $r['credit']]; }
    export_to_csv('gl-detail-' . $dateFrom . '_to_' . $dateTo . '.csv', ['Date','Entry No.','Account','Description','Debit','Credit'], $csvRows);
}

$pageTitle = 'Custom GL Detail Report';
$pageHelp = [
    ['selector' => 'select[name="account_ids[]"]',
        'en' => ['title' => 'Accounts multi-select', 'body' => 'Ctrl/Cmd+click to pick more than one account.'],
        'tl' => ['title' => 'Accounts multi-select', 'body' => 'Ctrl/Cmd+click para pumili ng higit sa isang account.']],
    ['selector' => 'button[type="submit"].btn-primary',
        'en' => ['title' => 'Run Report', 'body' => 'Lists every posted journal line for the selected accounts within the date range.'],
        'tl' => ['title' => 'Run Report', 'body' => 'Ilinilista ang bawat naka-post na journal line para sa mga napiling account sa loob ng date range.']],
];
if (!empty($rows)) {
    $pageHelp[] = ['selector' => 'table.data-table a',
        'en' => ['title' => 'Entry No. link', 'body' => 'Opens the full journal entry that line belongs to.'],
        'tl' => ['title' => 'Entry No. link', 'body' => 'Binubuksan ang buong journal entry na kinabibilangan ng linyang iyon.']];
}
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get">
        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <label>Accounts (Ctrl/Cmd+click to select multiple)</label>
                <select name="account_ids[]" multiple size="8">
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= in_array((int)$a['id'], $selectedAccounts, true) ? 'selected' : '' ?>><?= e($a['account_code'].' - '.$a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
            <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary">Run Report</button>
        <?php if (!empty($rows)): ?>
            <button type="submit" name="export" value="1" class="btn btn-outline">Export CSV</button>
        <?php endif; ?>
    </form>
</div>
<?php if (!empty($selectedAccounts)): ?>
<div class="card">
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Entry No.</th><th>Account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= format_date($r['entry_date']) ?></td>
                <td><a href="../gl/journal-entry-view.php?id=<?= $r['journal_entry_id'] ?>"><?= e($r['entry_no']) ?></a></td>
                <td><?= e($r['account_code'].' - '.$r['account_name']) ?></td>
                <td class="text-muted"><?= e($r['description'] ?: $r['memo']) ?></td>
                <td class="num"><?= $r['debit'] > 0 ? format_currency($r['debit']) : '' ?></td>
                <td class="num"><?= $r['credit'] > 0 ? format_currency($r['credit']) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="empty-state">No activity found for the selected accounts/range.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
