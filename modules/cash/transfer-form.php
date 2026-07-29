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
    $fromId = (int)$_POST['from_cash_account_id'];
    $toId = (int)$_POST['to_cash_account_id'];
    $amount = (float)($_POST['amount'] ?? 0);
    $transferDate = $_POST['transfer_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');

    if (!$fromId || !$toId) $errors[] = 'Both source and destination accounts are required.';
    if ($fromId === $toId) $errors[] = 'Source and destination accounts must be different.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

    if (empty($errors)) {
        $fromAcct = $db->prepare("SELECT gl_account_id, account_name, current_balance FROM cash_accounts WHERE id = ?");
        $fromAcct->execute([$fromId]); $from = $fromAcct->fetch();
        $toAcct = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ?");
        $toAcct->execute([$toId]); $to = $toAcct->fetch();

        try {
            $transferNo = next_document_no('XFER', 'cash_transfers');
            $entryId = post_journal_entry([
                'entry_date' => $transferDate, 'reference' => $transferNo, 'source_module' => 'cash', 'source_id' => null,
                'description' => 'Cash transfer ' . $transferNo . ': ' . $from['account_name'] . ' -> ' . $to['account_name'],
                'created_by' => current_user()['id'],
                'lines' => [
                    ['account_id' => $to['gl_account_id'], 'debit' => $amount, 'credit' => 0, 'memo' => 'Transfer in'],
                    ['account_id' => $from['gl_account_id'], 'debit' => 0, 'credit' => $amount, 'memo' => 'Transfer out'],
                ],
            ]);

            $stmt = $db->prepare("INSERT INTO cash_transfers (transfer_no, from_cash_account_id, to_cash_account_id, transfer_date, amount, description, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$transferNo, $fromId, $toId, $transferDate, $amount, $description, $entryId, current_user()['id']]);
            $transferId = (int)$db->lastInsertId();

            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $fromId]);
            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $toId]);

            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$fromId, $transferDate, 'TransferOut', $amount, $transferNo, $description, 'cash', $transferId, 'Financing', $entryId, current_user()['id']]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$toId, $transferDate, 'TransferIn', $amount, $transferNo, $description, 'cash', $transferId, 'Financing', $entryId, current_user()['id']]);

            log_audit('create', 'cash', $transferId, 'Recorded cash transfer ' . $transferNo);
            flash('success', 'Transfer recorded and posted to the general ledger.');
            redirect('modules/cash/transfers.php');
        } catch (Throwable $e) {
            $errors[] = 'Could not record transfer: ' . $e->getMessage();
        }
    }
}

$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();

$pageTitle = 'New Cash Transfer';
$pageHelp = [
    ['selector' => 'select[name="from_cash_account_id"]',
        'en' => ['title' => 'From / To', 'body' => 'Must be two different cash/bank accounts. Posts Dr. the destination\'s GL account / Cr. the source\'s.'],
        'tl' => ['title' => 'From / To', 'body' => 'Kailangan dalawang magkaibang cash/bank account. Nagpo-post ng Dr. sa GL account ng destination / Cr. sa source.']],
    ['selector' => 'input[name="amount"]',
        'en' => ['title' => 'Amount', 'body' => 'Deducted from From and added to To — total cash across the organization stays the same, only the split between accounts changes.'],
        'tl' => ['title' => 'Amount', 'body' => 'Ibinabawas sa From at idinadagdag sa To — pareho pa rin ang total cash ng organisasyon, ang paghahati lang sa pagitan ng accounts ang nagbabago.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:640px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label>From</label>
                <select name="from_cash_account_id" required>
                    <option value="">— Select account —</option>
                    <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?> (<?= format_currency($c['current_balance']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>To</label>
                <select name="to_cash_account_id" required>
                    <option value="">— Select account —</option>
                    <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="form-group"><label>Date</label><input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
        <button type="submit" class="btn btn-primary">Record Transfer</button>
        <a href="transfers.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
