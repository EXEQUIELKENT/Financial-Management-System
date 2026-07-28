<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('settings.view');

$db = get_db();

$controlAccountKeys = [
    'ap_control_account_id' => 'Accounts Payable Control Account',
    'ar_control_account_id' => 'Accounts Receivable Control Account',
    'input_tax_account_id' => 'Input Tax (VAT) Account',
    'output_tax_account_id' => 'Output Tax (VAT) Account',
    'withholding_tax_payable_account_id' => 'Withholding Tax Payable Account',
];
$thresholdKeys = [
    'cash_runway_days_threshold' => 'Cash Runway Warning Threshold (days)',
    'ap_overdue_pct_threshold' => 'AP Overdue Warning Threshold (%)',
    'ar_aging_pct_threshold' => 'AR Aging Warning Threshold (%)',
    'budget_warning_pct' => 'Budget Utilization Warning Threshold (%)',
    'budget_critical_pct' => 'Budget Utilization Critical Threshold (%)',
    'upcoming_payable_days' => 'Upcoming Payables Look-ahead (days)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('settings.create');
    verify_csrf();
    foreach (array_merge(array_keys($controlAccountKeys), array_keys($thresholdKeys)) as $key) {
        if (isset($_POST[$key])) set_setting($key, (string)$_POST[$key]);
    }
    log_audit('update', 'settings', null, 'Updated system settings');
    flash('success', 'Settings saved.');
    redirect('modules/settings/system-settings.php');
}

$accounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active=1 ORDER BY account_code")->fetchAll();

$pageTitle = 'System Settings';
include __DIR__ . '/../../includes/header.php';
?>
<form method="post">
<?= csrf_field() ?>
<div class="card">
    <div class="card-header"><h3>GL Control Accounts</h3></div>
    <p class="text-muted">These accounts are used when the system automatically posts journal entries for AP, AR, and tax activity.</p>
    <div class="form-row">
        <?php foreach ($controlAccountKeys as $key => $label): $current = (int)get_setting($key); ?>
        <div class="form-group">
            <label><?= e($label) ?></label>
            <select name="<?= $key ?>">
                <option value="">— Not set —</option>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= $current===(int)$a['id']?'selected':'' ?>><?= e($a['account_code'].' - '.$a['account_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3>Decision Support Thresholds</h3></div>
    <div class="form-row">
        <?php foreach ($thresholdKeys as $key => $label): ?>
        <div class="form-group">
            <label><?= e($label) ?></label>
            <input type="number" step="1" name="<?= $key ?>" class="form-control" value="<?= e(get_setting($key, '0')) ?>">
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php if (has_permission('settings.create')): ?>
    <button type="submit" class="btn btn-primary">Save Settings</button>
<?php endif; ?>
</form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
