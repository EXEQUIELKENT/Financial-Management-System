<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('cash.view');

$db = get_db();
$accountFilter = (int)($_GET['cash_account_id'] ?? 0);
$sql = "SELECT t.*, ca.account_name FROM cash_transactions t JOIN cash_accounts ca ON ca.id = t.cash_account_id WHERE 1=1";
$params = [];
if ($accountFilter) { $sql .= " AND t.cash_account_id = ?"; $params[] = $accountFilter; }
$sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$accounts = $db->query("SELECT id, account_name FROM cash_accounts ORDER BY account_name")->fetchAll();

$pageTitle = 'Cash Transactions';
$pageHelp = [
    ['selector' => 'select[name="cash_account_id"]',
        'en' => ['title' => 'Account filter', 'body' => 'Shows every account\'s transactions, or narrow to just one.'],
        'tl' => ['title' => 'Account filter', 'body' => 'Ipinapakita ang transaksyon ng lahat ng account, o i-narrow sa isa lang.']],
];
if (has_permission('cash.create')) {
    $pageHelp[] = ['selector' => 'a[href="transaction-form.php"]',
        'en' => ['title' => '+ New Transaction', 'body' => 'Record a manual deposit or withdrawal not tied to AP/AR — e.g. a bank charge or interest earned.'],
        'tl' => ['title' => '+ Bagong Transaksyon', 'body' => 'Mag-record ng manual na deposit o withdrawal na hindi kaugnay ng AP/AR — hal. singil ng bangko o kinitang interes.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'Category column', 'body' => 'Operating, Investing, or Financing — how this line is classified on the Cash Flow Statement.'],
    'tl' => ['title' => 'Category column', 'body' => 'Operating, Investing, o Financing — kung paano naka-classify ang linyang ito sa Cash Flow Statement.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <select name="cash_account_id" onchange="this.form.submit()">
                <option value="">All Accounts</option>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= $accountFilter===(int)$a['id']?'selected':'' ?>><?= e($a['account_name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <div>
            <a href="accounts.php" class="btn btn-outline">Accounts</a>
            <?php if (has_permission('cash.create')): ?><a href="transaction-form.php" class="btn btn-primary">+ New Transaction</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date</th><th>Account</th><th>Type</th><th>Description</th><th>Category</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td><?= format_date($t['transaction_date']) ?></td>
                <td><?= e($t['account_name']) ?></td>
                <td><span class="badge <?= in_array($t['type'],['Deposit','TransferIn'],true) ? 'badge-success' : 'badge-danger' ?>"><?= e($t['type']) ?></span></td>
                <td><?= e($t['description']) ?></td>
                <td class="text-muted"><?= e($t['cash_flow_category']) ?></td>
                <td class="num"><?= format_currency($t['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($transactions)): ?><tr><td colspan="6" class="empty-state">No transactions found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
