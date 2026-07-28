<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('tax.create');

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $taxTypeId = (int)$_POST['tax_type_id'];
    $direction = $_POST['direction'] ?? 'Output';
    $periodStart = $_POST['period_start'] ?? date('Y-m-01');
    $periodEnd = $_POST['period_end'] ?? date('Y-m-d');
    $cashAccountId = (int)$_POST['cash_account_id'];

    $pendingStmt = $db->prepare("SELECT * FROM tax_transactions WHERE tax_type_id = ? AND direction = ? AND status = 'Pending' AND transaction_date BETWEEN ? AND ?");
    $pendingStmt->execute([$taxTypeId, $direction, $periodStart, $periodEnd]);
    $pending = $pendingStmt->fetchAll();
    $totalAmount = array_sum(array_column($pending, 'tax_amount'));

    if (!$cashAccountId) $errors[] = 'Cash/bank account is required.';
    if (empty($pending)) $errors[] = 'No pending tax transactions found for this type/direction/period.';

    if (empty($errors)) {
        $payableAccountId = (int)get_setting($direction === 'Withholding' ? 'withholding_tax_payable_account_id' : 'output_tax_account_id');
        $cashStmt = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ?");
        $cashStmt->execute([$cashAccountId]);
        $cashAcct = $cashStmt->fetch();

        try {
            $remittanceNo = next_document_no('REM', 'tax_remittances');
            $entryId = post_journal_entry([
                'entry_date' => date('Y-m-d'), 'reference' => $remittanceNo, 'source_module' => 'tax', 'source_id' => null,
                'description' => 'Tax remittance ' . $remittanceNo,
                'created_by' => current_user()['id'],
                'lines' => [
                    ['account_id' => $payableAccountId, 'debit' => $totalAmount, 'credit' => 0, 'memo' => 'Tax remittance'],
                    ['account_id' => $cashAcct['gl_account_id'], 'debit' => 0, 'credit' => $totalAmount, 'memo' => 'Cash paid - ' . $cashAcct['account_name']],
                ],
            ]);

            $stmt = $db->prepare("INSERT INTO tax_remittances (remittance_no, tax_type_id, period_start, period_end, total_amount, remittance_date, journal_entry_id, created_by, status) VALUES (?,?,?,?,?,?,?,?,'Remitted')");
            $stmt->execute([$remittanceNo, $taxTypeId, $periodStart, $periodEnd, $totalAmount, date('Y-m-d'), $entryId, current_user()['id']]);
            $remittanceId = (int)$db->lastInsertId();

            $updateStmt = $db->prepare("UPDATE tax_transactions SET status='Remitted', remittance_id=? WHERE id=?");
            foreach ($pending as $p) { $updateStmt->execute([$remittanceId, $p['id']]); }

            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$totalAmount, $cashAccountId]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$cashAccountId, date('Y-m-d'), 'Withdrawal', $totalAmount, $remittanceNo, 'Tax remittance', 'tax', $remittanceId, 'Operating', $entryId, current_user()['id']]);

            log_audit('create', 'tax', $remittanceId, 'Recorded tax remittance ' . $remittanceNo);
            flash('success', 'Tax remittance recorded and posted to the general ledger.');
            redirect('modules/tax/remittances.php');
        } catch (Throwable $e) {
            $errors[] = 'Could not record remittance: ' . $e->getMessage();
        }
    }
}

$taxTypes = $db->query("SELECT id, name FROM tax_types WHERE is_active=1 ORDER BY name")->fetchAll();
$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();

$pageTitle = 'New Tax Remittance';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:640px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label>Tax Type</label>
                <select name="tax_type_id" required><?php foreach ($taxTypes as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Direction</label>
                <select name="direction">
                    <option value="Output">Output (VAT collected on sales)</option>
                    <option value="Withholding">Withholding (withheld from vendor payments)</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Period Start</label><input type="date" name="period_start" class="form-control" value="<?= date('Y-m-01') ?>"></div>
            <div class="form-group"><label>Period End</label><input type="date" name="period_end" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-group"><label>Pay From</label>
            <select name="cash_account_id" required>
                <option value="">— Select cash/bank account —</option>
                <?php foreach ($cashAccounts as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['account_name']) ?> (<?= format_currency($c['current_balance']) ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <p class="form-hint">This will aggregate all Pending tax transactions matching the type, direction, and period, mark them Remitted, and post a journal entry (Dr Tax Payable, Cr Cash).</p>
        <button type="submit" class="btn btn-primary">Record Remittance</button>
        <a href="remittances.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
