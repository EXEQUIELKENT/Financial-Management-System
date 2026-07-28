<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_permission('gl.view');

$db = get_db();
$accounts = $db->query("SELECT id, account_code, account_name, normal_balance FROM coa_accounts ORDER BY account_code")->fetchAll();

$accountId = (int)($_GET['account_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$rows = [];
$account = null;
$openingBalance = 0.0;
$closingBalance = 0.0;

if ($accountId) {
    $stmt = $db->prepare("SELECT * FROM coa_accounts WHERE id = ?");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();

    if ($account) {
        $openingStmt = $db->prepare("SELECT COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc
            FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
            WHERE jl.account_id = ? AND je.status = 'Posted' AND je.entry_date < ?");
        $openingStmt->execute([$accountId, $dateFrom]);
        $o = $openingStmt->fetch();
        $openingBalance = $account['normal_balance'] === 'Debit' ? ($o['td'] - $o['tc']) : ($o['tc'] - $o['td']);

        $stmt = $db->prepare("SELECT jl.*, je.entry_no, je.entry_date, je.description, je.source_module, je.id AS je_id
            FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
            WHERE jl.account_id = ? AND je.status = 'Posted' AND je.entry_date BETWEEN ? AND ?
            ORDER BY je.entry_date, je.id");
        $stmt->execute([$accountId, $dateFrom, $dateTo]);
        $rows = $stmt->fetchAll();

        $running = $openingBalance;
        foreach ($rows as &$r) {
            $delta = $account['normal_balance'] === 'Debit' ? ($r['debit'] - $r['credit']) : ($r['credit'] - $r['debit']);
            $running += $delta;
            $r['running_balance'] = $running;
        }
        unset($r);
        $closingBalance = $running;
    }
}

$pageTitle = 'Account Ledger Inquiry';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="form-row">
        <div class="form-group">
            <label>Account</label>
            <select name="account_id" required>
                <option value="">— Select account —</option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $accountId === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['account_code'] . ' - ' . $a['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        <div class="form-group" style="display:flex;align-items:flex-end;"><button type="submit" class="btn btn-primary">View Ledger</button></div>
    </form>
</div>

<?php if ($account): ?>
<div class="card">
    <div class="card-header"><h3><?= e($account['account_code'] . ' - ' . $account['account_name']) ?></h3></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Entry No.</th><th>Source</th><th>Description / Memo</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
        <tbody>
            <tr style="font-weight:600;"><td colspan="6">Opening Balance</td><td class="num"><?= format_currency($openingBalance) ?></td></tr>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= format_date($r['entry_date']) ?></td>
                <td><a href="journal-entry-view.php?id=<?= $r['je_id'] ?>"><?= e($r['entry_no']) ?></a></td>
                <td class="text-muted"><?= e($r['source_module']) ?></td>
                <td><?= e($r['description'] ?: $r['memo']) ?></td>
                <td class="num"><?= $r['debit'] > 0 ? format_currency($r['debit']) : '' ?></td>
                <td class="num"><?= $r['credit'] > 0 ? format_currency($r['credit']) : '' ?></td>
                <td class="num"><?= format_currency($r['running_balance']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="empty-state">No posted activity in this date range.</td></tr>
            <?php endif; ?>
            <tr style="font-weight:600;"><td colspan="6">Closing Balance</td><td class="num"><?= format_currency($closingBalance) ?></td></tr>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
