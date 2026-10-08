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
    // Input tax is claimed as credit, never remitted, so only these two are valid here.
    $direction = ($_POST['direction'] ?? 'Output') === 'Withholding' ? 'Withholding' : 'Output';
    $periodStart = $_POST['period_start'] ?? date('Y-m-01');
    $periodEnd = $_POST['period_end'] ?? date('Y-m-d');
    $cashAccountId = (int)$_POST['cash_account_id'];

    $pendingStmt = $db->prepare("SELECT * FROM tax_transactions WHERE tax_type_id = ? AND direction = ? AND status = 'Pending' AND transaction_date BETWEEN ? AND ?");
    $pendingStmt->execute([$taxTypeId, $direction, $periodStart, $periodEnd]);
    $pending = $pendingStmt->fetchAll();
    $totalAmount = round(array_sum(array_column($pending, 'tax_amount')), 2);

    if (!$cashAccountId) $errors[] = 'Cash/bank account is required.';
    if (empty($pending)) $errors[] = 'No pending tax transactions found for this type/direction/period.';
    $payableAccountId = (int)get_setting($direction === 'Withholding' ? 'withholding_tax_payable_account_id' : 'output_tax_account_id');
    if (!$payableAccountId) $errors[] = ($direction === 'Withholding' ? 'Withholding Tax Payable' : 'Output Tax') . ' account is not configured. Ask an Admin to set it in Settings.';
    $cashStmt = $db->prepare("SELECT gl_account_id, account_name FROM cash_accounts WHERE id = ? AND status = 'Active'");
    $cashStmt->execute([$cashAccountId]);
    $cashAcct = $cashStmt->fetch();
    if ($cashAccountId && !$cashAcct) $errors[] = 'Selected cash/bank account was not found or is inactive.';

    if (empty($errors)) {
        $db->beginTransaction();
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

            // Only rows still Pending: two remittances must not claim the same tax.
            $updateStmt = $db->prepare("UPDATE tax_transactions SET status='Remitted', remittance_id=? WHERE id=? AND status='Pending'");
            foreach ($pending as $p) {
                $updateStmt->execute([$remittanceId, $p['id']]);
                if ($updateStmt->rowCount() !== 1) throw new RuntimeException('Some of these tax transactions were already remitted. Reload and try again.');
            }

            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$totalAmount, $cashAccountId]);
            $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$cashAccountId, date('Y-m-d'), 'Withdrawal', $totalAmount, $remittanceNo, 'Tax remittance', 'tax', $remittanceId, 'Operating', $entryId, current_user()['id']]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Could not record remittance: ' . $e->getMessage();
        }
        if (empty($errors)) {
            log_audit('create', 'tax', $remittanceId, 'Recorded tax remittance ' . $remittanceNo);
            flash('success', 'Tax remittance recorded and posted to the general ledger.');
            redirect('modules/tax/remittances.php');
        }
    }
}

$taxTypes = $db->query("SELECT id, name FROM tax_types WHERE is_active=1 ORDER BY name")->fetchAll();
$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY account_name")->fetchAll();

$pageTitle = 'New Tax Remittance';
$pageHelp = [
    ['selector' => 'select[name="direction"]',
        'en' => ['title' => 'Direction', 'body' => 'Output = VAT collected on sales; Withholding = tax withheld from vendor payments. Input tax (VAT paid on purchases) is never remitted, only claimed as credit.'],
        'tl' => ['title' => 'Direction', 'body' => 'Output = VAT na nakolekta sa benta; Withholding = buwis na inihold mula sa bayad sa vendor. Ang Input tax (VAT na binayaran sa pagbili) ay hindi kailanman rini-remit, kredito lang ito.']],
    ['selector' => 'input[name="period_start"]',
        'en' => ['title' => 'Period Start / End', 'body' => 'Only Pending tax transactions dated within this range, matching the type and direction, get aggregated.'],
        'tl' => ['title' => 'Period Start / End', 'body' => 'Ang mga Pending na tax transaction lang na nasa loob ng range na ito, tugma sa type at direction, ang pagsasamahin.']],
    ['selector' => 'select[name="cash_account_id"]',
        'en' => ['title' => 'Pay From', 'body' => 'The cash/bank account the remittance amount is deducted from.'],
        'tl' => ['title' => 'Pay From', 'body' => 'Ang cash/bank account kung saan ibabawas ang halaga ng remittance.']],
];
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
