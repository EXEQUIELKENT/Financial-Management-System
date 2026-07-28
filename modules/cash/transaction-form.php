<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('cash.create');

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cashAccountId = (int)$_POST['cash_account_id'];
    $offsetAccountId = (int)$_POST['offset_account_id'];
    $type = $_POST['type'] ?? 'Deposit';
    $amount = (float)($_POST['amount'] ?? 0);
    $txDate = $_POST['transaction_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['cash_flow_category'] ?? 'Operating';

    if (!$cashAccountId) $errors[] = 'Cash account is required.';
    if (!$offsetAccountId) $errors[] = 'Offset account is required.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

    if (empty($errors)) {
        $cashStmt = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ?");
        $cashStmt->execute([$cashAccountId]);
        $cashAcct = $cashStmt->fetch();

        $isInflow = $type === 'Deposit';
        $jeLines = $isInflow
            ? [['account_id' => $cashAcct['gl_account_id'], 'debit' => $amount, 'credit' => 0, 'memo' => $description], ['account_id' => $offsetAccountId, 'debit' => 0, 'credit' => $amount, 'memo' => $description]]
            : [['account_id' => $offsetAccountId, 'debit' => $amount, 'credit' => 0, 'memo' => $description], ['account_id' => $cashAcct['gl_account_id'], 'debit' => 0, 'credit' => $amount, 'memo' => $description]];

        try {
            $entryId = post_journal_entry([
                'entry_date' => $txDate, 'reference' => '', 'source_module' => 'cash', 'source_id' => null,
                'description' => $description ?: ($type . ' - ' . $cashAcct['account_name']),
                'created_by' => current_user()['id'], 'lines' => $jeLines,
            ]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, description, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$cashAccountId, $txDate, $type, $amount, $description, $category, $entryId, current_user()['id']]);
            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance " . ($isInflow ? '+' : '-') . " ? WHERE id = ?")->execute([$amount, $cashAccountId]);
            log_audit('create', 'cash', $cashAccountId, "Recorded $type of $amount");
            flash('success', 'Cash transaction recorded and posted to the general ledger.');
            redirect('modules/cash/account-register.php?id=' . $cashAccountId);
        } catch (Throwable $e) {
            $errors[] = 'Could not record transaction: ' . $e->getMessage();
        }
    }
}

$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();
$offsetAccounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active=1 AND account_type IN ('Expense','Revenue','Liability','Asset') ORDER BY account_code")->fetchAll();

$pageTitle = 'New Cash Transaction';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:640px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label>Cash/Bank Account</label>
            <select name="cash_account_id" required>
                <option value="">— Select account —</option>
                <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?> (<?= format_currency($c['current_balance']) ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Type</label>
                <select name="type">
                    <option value="Deposit">Deposit (money in)</option>
                    <option value="Withdrawal">Withdrawal (money out)</option>
                </select>
            </div>
            <div class="form-group"><label>Date</label><input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-group"><label>Offset Account</label>
            <select name="offset_account_id" required>
                <option value="">— e.g. Bank Charges, Interest Income —</option>
                <?php foreach ($offsetAccounts as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['account_code'].' - '.$a['account_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="form-group"><label>Cash Flow Category</label>
                <select name="cash_flow_category">
                    <?php foreach (['Operating','Investing','Financing'] as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
        <button type="submit" class="btn btn-primary">Record Transaction</button>
        <a href="transactions.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
