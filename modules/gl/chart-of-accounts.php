<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('gl.view');

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    require_permission('gl.create');
    verify_csrf();
    $id = (int)$_POST['toggle_id'];
    $db->prepare("UPDATE coa_accounts SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    log_audit('toggle_active', 'gl', $id, 'Toggled chart of accounts active status');
    flash('success', 'Account status updated.');
    redirect('modules/gl/chart-of-accounts.php');
}

$typeFilter = $_GET['type'] ?? '';
$sql = "SELECT a.*, p.account_name AS parent_name FROM coa_accounts a LEFT JOIN coa_accounts p ON p.id = a.parent_id WHERE 1=1";
$params = [];
if ($typeFilter !== '') {
    $sql .= " AND a.account_type = ?";
    $params[] = $typeFilter;
}
$sql .= " ORDER BY a.account_code";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$pageTitle = 'Chart of Accounts';
$pageHelp = [
    ['selector' => 'select[name="type"]',
        'en' => ['title' => 'Type filter', 'body' => 'Shows only accounts of one type: Asset, Liability, Equity, Revenue, or Expense.'],
        'tl' => ['title' => 'Type Filter', 'body' => 'Ipinapakita lamang ang mga account ng isang uri: Asset, Liability, Equity, Revenue, o Expense.']],
];
if (has_permission('gl.create')) {
    $pageHelp[] = ['selector' => 'a[href="account-form.php"]',
        'en' => ['title' => '+ New Account', 'body' => 'Adds a new GL account with a code, name, type, normal balance (Debit/Credit), and an optional parent for hierarchy.'],
        'tl' => ['title' => '+ Bagong Account', 'body' => 'Magdagdag ng bagong GL account na may code, pangalan, uri, normal balance (Debit/Credit), at opsyonal na parent para sa hierarchy.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'The table', 'body' => 'Ledger opens that account\'s full transaction history. Accounts are never deleted (it would break historical GL entries) — Deactivate just hides it from new transactions while keeping history intact.'],
    'tl' => ['title' => 'Ang Talahanayan', 'body' => 'Binubuksan ng Ledger ang buong transaction history ng account na iyon. Hindi kailanman binubura ang mga account (masisira ang lumang GL entries) — ang Deactivate lang ang magtatago nito sa mga bagong transaksyon habang buo pa rin ang history.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div class="table-filters">
            <form method="get" style="display:flex; gap:8px;">
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach (['Asset','Liability','Equity','Revenue','Expense'] as $t): ?>
                        <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (has_permission('gl.create')): ?>
            <a href="account-form.php" class="btn btn-primary">+ New Account</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Code</th><th>Account Name</th><th>Type</th><th>Normal Balance</th><th>Parent</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($accounts as $a): ?>
            <tr>
                <td><?= e($a['account_code']) ?></td>
                <td><?= e($a['account_name']) ?></td>
                <td><?= e($a['account_type']) ?></td>
                <td><?= e($a['normal_balance']) ?></td>
                <td class="text-muted"><?= e($a['parent_name'] ?? '—') ?></td>
                <td><span class="badge <?= $a['is_active'] ? 'badge-success' : 'badge-void' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <a href="account-ledger.php?account_id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">Ledger</a>
                    <?php if (has_permission('gl.create')): ?>
                        <a href="account-form.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="toggle_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" data-confirm="Toggle active status for this account?"><?= $a['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($accounts)): ?>
            <tr><td colspan="7" class="empty-state">No accounts found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
