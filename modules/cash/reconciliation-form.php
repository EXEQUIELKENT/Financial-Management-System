<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('cash.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? 'create';

    if ($formAction === 'create') {
        $cashAccountId = (int)$_POST['cash_account_id'];
        $statementDate = $_POST['statement_date'] ?? date('Y-m-d');
        $statementBalance = (float)($_POST['statement_balance'] ?? 0);
        $bookStmt = $db->prepare("SELECT current_balance FROM cash_accounts WHERE id = ?");
        $bookStmt->execute([$cashAccountId]);
        $bookBalance = (float)$bookStmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO bank_reconciliations (cash_account_id, statement_date, statement_balance, book_balance, status, reconciled_by) VALUES (?,?,?,?,'InProgress',?)");
        $stmt->execute([$cashAccountId, $statementDate, $statementBalance, $bookBalance, current_user()['id']]);
        $id = (int)$db->lastInsertId();
        log_audit('create', 'cash', $id, 'Started bank reconciliation');
        redirect('modules/cash/reconciliation-form.php?id=' . $id);
    } elseif ($formAction === 'add_item') {
        $db->prepare("INSERT INTO bank_reconciliation_items (reconciliation_id, description, amount, item_type) VALUES (?,?,?,?)")
           ->execute([$id, trim($_POST['description'] ?? ''), (float)($_POST['amount'] ?? 0), $_POST['item_type'] ?? 'Error']);
        redirect('modules/cash/reconciliation-form.php?id=' . $id);
    } elseif ($formAction === 'complete') {
        $db->prepare("UPDATE bank_reconciliations SET status='Completed' WHERE id=?")->execute([$id]);
        log_audit('complete', 'cash', $id, 'Completed bank reconciliation');
        flash('success', 'Reconciliation marked as completed.');
        redirect('modules/cash/reconciliation.php');
    }
}

if (!$id) {
    $cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();
    $pageTitle = 'New Bank Reconciliation';
    include __DIR__ . '/../../includes/header.php';
    ?>
    <div class="card" style="max-width:520px;">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="create">
            <div class="form-group"><label>Cash/Bank Account</label>
                <select name="cash_account_id" required>
                    <option value="">— Select account —</option>
                    <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Statement Date</label><input type="date" name="statement_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label>Statement Balance</label><input type="number" step="0.01" name="statement_balance" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-primary">Start Reconciliation</button>
            <a href="reconciliation.php" class="btn btn-outline">Cancel</a>
        </form>
    </div>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    <?php exit;
}

$stmt = $db->prepare("SELECT r.*, ca.account_name FROM bank_reconciliations r JOIN cash_accounts ca ON ca.id = r.cash_account_id WHERE r.id = ?");
$stmt->execute([$id]);
$recon = $stmt->fetch();
if (!$recon) { flash('error', 'Reconciliation not found.'); redirect('modules/cash/reconciliation.php'); }

$itemStmt = $db->prepare("SELECT * FROM bank_reconciliation_items WHERE reconciliation_id = ? ORDER BY id");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

$outstandingChecks = 0; $depositsInTransit = 0; $bankCharges = 0; $interest = 0; $errors = 0;
foreach ($items as $it) {
    switch ($it['item_type']) {
        case 'OutstandingCheck': $outstandingChecks += $it['amount']; break;
        case 'DepositInTransit': $depositsInTransit += $it['amount']; break;
        case 'BankCharge': $bankCharges += $it['amount']; break;
        case 'Interest': $interest += $it['amount']; break;
        case 'Error': $errors += $it['amount']; break;
    }
}
$adjustedBank = $recon['statement_balance'] + $depositsInTransit - $outstandingChecks;
$adjustedBook = $recon['book_balance'] - $bankCharges + $interest + $errors;
$difference = round($adjustedBank - $adjustedBook, 2);

$pageTitle = 'Reconciliation: ' . $recon['account_name'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($recon['account_name']) ?> — <?= format_date($recon['statement_date']) ?> <span class="badge <?= status_badge_class($recon['status']==='Completed'?'Paid':'Draft') ?>"><?= e($recon['status']) ?></span></h3>
        <?php if ($recon['status'] === 'InProgress' && abs($difference) < 0.01): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="form_action" value="complete">
                <button type="submit" class="btn btn-accent">Mark Completed</button></form>
        <?php endif; ?>
    </div>
    <div class="kpi-grid">
        <div class="kpi-card primary"><div class="kpi-label">Statement Balance</div><div class="kpi-value"><?= format_currency($recon['statement_balance']) ?></div></div>
        <div class="kpi-card primary"><div class="kpi-label">Book Balance</div><div class="kpi-value"><?= format_currency($recon['book_balance']) ?></div></div>
        <div class="kpi-card <?= abs($difference) < 0.01 ? 'accent' : 'danger' ?>"><div class="kpi-label">Difference</div><div class="kpi-value"><?= format_currency($difference) ?></div><div class="kpi-sub"><?= abs($difference) < 0.01 ? 'Reconciled ✓' : 'Not yet balanced' ?></div></div>
    </div>

    <?php if ($recon['status'] === 'InProgress'): ?>
    <form method="post" class="form-row" style="align-items:flex-end;">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="form_action" value="add_item">
        <div class="form-group"><label>Item Type</label>
            <select name="item_type">
                <option value="OutstandingCheck">Outstanding Check</option>
                <option value="DepositInTransit">Deposit in Transit</option>
                <option value="BankCharge">Bank Charge</option>
                <option value="Interest">Interest Earned</option>
                <option value="Error">Correction / Error</option>
            </select>
        </div>
        <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
        <div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        <div class="form-group"><button type="submit" class="btn btn-outline">+ Add Item</button></div>
    </form>
    <?php endif; ?>

    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Type</th><th>Description</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr><td><?= e($it['item_type']) ?></td><td><?= e($it['description']) ?></td><td class="num"><?= format_currency($it['amount']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?><tr><td colspan="3" class="empty-state">No reconciling items added yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<a href="reconciliation.php" class="btn btn-outline">Back</a>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
