<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('gl.create');

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
    $reference = trim($_POST['reference'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $accountIds = $_POST['account_id'] ?? [];
    $debits = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];
    $memos = $_POST['memo'] ?? [];

    $lines = [];
    $totalDebit = 0; $totalCredit = 0;
    foreach ($accountIds as $i => $accId) {
        if (empty($accId)) continue;
        $d = (float)($debits[$i] ?? 0);
        $c = (float)($credits[$i] ?? 0);
        if ($d == 0 && $c == 0) continue;
        $lines[] = ['account_id' => (int)$accId, 'debit' => $d, 'credit' => $c, 'memo' => $memos[$i] ?? ''];
        $totalDebit += $d;
        $totalCredit += $c;
    }

    if (count($lines) < 2) $errors[] = 'A journal entry needs at least two lines.';
    if (round($totalDebit, 2) !== round($totalCredit, 2)) $errors[] = "Entry is not balanced. Total Debit " . format_currency($totalDebit) . " vs Total Credit " . format_currency($totalCredit) . ".";
    if ($totalDebit == 0) $errors[] = 'Entry amounts cannot all be zero.';

    if (empty($errors)) {
        $entryId = create_draft_journal_entry([
            'entry_date' => $entryDate,
            'reference' => $reference,
            'description' => $description,
            'created_by' => current_user()['id'],
            'lines' => $lines,
        ]);
        log_audit('create_draft', 'gl', $entryId, 'Created manual draft journal entry');
        flash('success', 'Journal entry saved as Draft. Submit for posting when ready.');
        redirect('modules/gl/journal-entry-view.php?id=' . $entryId);
    }
}

$accounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active = 1 ORDER BY account_code")->fetchAll();

$pageTitle = 'New Journal Entry';
$pageHelp = [
    ['selector' => '#lineTable',
        'en' => ['title' => 'Line grid', 'body' => 'Pick an account and enter either a Debit or a Credit per line (not both). Add at least two lines.'],
        'tl' => ['title' => 'Line Grid', 'body' => 'Pumili ng account at maglagay ng Debit o Credit sa bawat linya (hindi pareho). Magdagdag ng hindi bababa sa dalawang linya.']],
    ['selector' => 'button[onclick="addRow()"]',
        'en' => ['title' => '+ Add Line', 'body' => 'Adds another blank Debit/Credit row.'],
        'tl' => ['title' => '+ Magdagdag ng Linya', 'body' => 'Magdaragdag ng isa pang blankong Debit/Credit row.']],
    ['selector' => '#balanceMsg',
        'en' => ['title' => 'Balanced indicator', 'body' => 'Turns green only when Total Debit exactly equals Total Credit — required before you can save.'],
        'tl' => ['title' => 'Balanced Indicator', 'body' => 'Magiging berde lamang kapag eksaktong magkatumbas ang Total Debit at Total Credit — kailangan bago makapag-save.']],
    ['selector' => 'button[type="submit"]',
        'en' => ['title' => 'Save as Draft', 'body' => 'Nothing posts to the ledger yet — an Approver still needs to Approve & Post it from the Journal Entries list.'],
        'tl' => ['title' => 'I-save bilang Draft', 'body' => 'Wala pang mapo-post sa ledger — kailangan pa itong i-Approve & Post ng isang Approver mula sa Journal Entries list.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="jeForm">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label>Entry Date</label>
                <input type="date" name="entry_date" class="form-control" value="<?= e($_POST['entry_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>Reference</label>
                <input type="text" name="reference" class="form-control" value="<?= old('reference') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <input type="text" name="description" class="form-control" value="<?= old('description') ?>">
        </div>

        <div class="table-wrap">
        <table class="data-table" id="lineTable">
            <thead><tr><th>Account</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th><th></th></tr></thead>
            <tbody id="lineBody">
                <?php for ($i = 0; $i < 4; $i++): ?>
                <tr>
                    <td>
                        <select name="account_id[]" class="jeAccount">
                            <option value="">— Select account —</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= e($a['account_code'] . ' - ' . $a['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="memo[]" class="form-control"></td>
                    <td><input type="number" step="0.01" name="debit[]" class="form-control jeDebit num" value="0" onchange="calcTotals()"></td>
                    <td><input type="number" step="0.01" name="credit[]" class="form-control jeCredit num" value="0" onchange="calcTotals()"></td>
                    <td><button type="button" class="btn btn-outline btn-sm" onclick="removeRow(this)">✕</button></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><button type="button" class="btn btn-outline btn-sm" onclick="addRow()">+ Add Line</button></td>
                    <td class="num" id="totalDebit">0.00</td>
                    <td class="num" id="totalCredit">0.00</td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="5" id="balanceMsg" style="text-align:right;font-weight:600;"></td>
                </tr>
            </tfoot>
        </table>
        </div>

        <button type="submit" class="btn btn-primary">Save as Draft</button>
        <a href="journal-entries.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<script>
var accountOptionsHtml = document.querySelector('.jeAccount').innerHTML;
function addRow() {
    var tbody = document.getElementById('lineBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select name="account_id[]" class="jeAccount">' + accountOptionsHtml + '</select></td>' +
        '<td><input type="text" name="memo[]" class="form-control"></td>' +
        '<td><input type="number" step="0.01" name="debit[]" class="form-control jeDebit num" value="0" onchange="calcTotals()"></td>' +
        '<td><input type="number" step="0.01" name="credit[]" class="form-control jeCredit num" value="0" onchange="calcTotals()"></td>' +
        '<td><button type="button" class="btn btn-outline btn-sm" onclick="removeRow(this)">✕</button></td>';
    tbody.appendChild(tr);
}
function removeRow(btn) {
    btn.closest('tr').remove();
    calcTotals();
}
function calcTotals() {
    var debits = document.querySelectorAll('.jeDebit');
    var credits = document.querySelectorAll('.jeCredit');
    var td = 0, tc = 0;
    debits.forEach(function (d) { td += parseFloat(d.value || 0); });
    credits.forEach(function (c) { tc += parseFloat(c.value || 0); });
    document.getElementById('totalDebit').textContent = td.toFixed(2);
    document.getElementById('totalCredit').textContent = tc.toFixed(2);
    var msg = document.getElementById('balanceMsg');
    if (Math.abs(td - tc) < 0.005 && td > 0) {
        msg.textContent = 'Balanced ✓';
        msg.style.color = '#27AE60';
    } else {
        msg.textContent = 'Out of balance by ' + Math.abs(td - tc).toFixed(2);
        msg.style.color = '#EB5757';
    }
}
calcTotals();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
