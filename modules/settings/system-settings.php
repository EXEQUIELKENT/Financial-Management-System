<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_once __DIR__ . '/../../includes/Mailer.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'mail_test') {
    // The hosted container has no shell, so this is the only way to see why OTP and
    // password-reset emails are not arriving.
    require_permission('settings.create');
    verify_csrf();
    $me = $db->prepare('SELECT email, full_name FROM users WHERE id = ?');
    $me->execute([current_user()['id']]);
    $me = $me->fetch() ?: ['email' => '', 'full_name' => ''];
    $to = (string)$me['email'];
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Your account has no valid email address. Add one under My Profile first.');
    } elseif (!mail_is_configured()) {
        flash('error', 'Mail is not configured: set MAIL_USERNAME and MAIL_PASSWORD in the hosting environment, then redeploy.');
    } else {
        $r = send_mail($to, (string)$me['full_name'], APP_SHORT_NAME . ' mail test',
            mail_template('Mail test', '<p>If you can read this, sign-in codes and password-reset codes will be delivered.</p>'));
        if ($r['success']) {
            flash('success', 'Test email sent to ' . $to . '. Check the inbox and spam folder.');
        } else {
            flash('error', 'Test email failed: ' . ($r['detail'] ?? $r['message']));
        }
    }
    log_audit('mail_test', 'settings', null, 'Sent SMTP test email');
    redirect('modules/settings/system-settings.php');
}

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
$pageHelp = [
    ['selector' => '.card', 'nth' => 0,
        'en' => ['title' => 'GL Control Accounts', 'body' => 'Which real Chart of Accounts entry the system posts to automatically for AP, AR, Input Tax, Output Tax, and Withholding Tax Payable. Bill/Invoice approval will error until these are set.'],
        'tl' => ['title' => 'GL Control Accounts', 'body' => 'Kung aling tunay na Chart of Accounts entry ang awtomatikong pinopostan ng sistema para sa AP, AR, Input Tax, Output Tax, at Withholding Tax Payable. Magkakaroon ng error ang pag-approve ng Bill/Invoice hangga\'t hindi ito naka-set.']],
    ['selector' => '.card', 'nth' => 1,
        'en' => ['title' => 'Decision Support Thresholds', 'body' => 'The trigger points for the Dashboard\'s automatic risk alerts — e.g. how many days of cash runway counts as "low," or what AP/AR aging % counts as risky.'],
        'tl' => ['title' => 'Decision Support Thresholds', 'body' => 'Ang mga trigger point para sa awtomatikong risk alerts ng Dashboard — hal. ilang araw ng cash runway ang ituturing na "mababa," o anong AP/AR aging % ang ituturing na mapanganib.']],
];
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

<div class="card">
    <div class="card-header"><h3>Email (sign-in codes &amp; password reset)</h3></div>
    <?php if (mail_is_configured()): ?>
        <p>Mail is configured: <strong><?= e(MAIL_USERNAME) ?></strong> via <?= e(MAIL_HOST . ':' . MAIL_PORT) ?> (<?= e(MAIL_ENCRYPTION) ?>).
        Two-step sign-in and password reset are active.</p>
    <?php else: ?>
        <div class="alert alert-warning">
            <span class="alert-title">Mail is not configured</span>
            Sign-in codes (OTP) are being skipped and password-reset emails cannot be sent.
            Set <code>MAIL_USERNAME</code> and <code>MAIL_PASSWORD</code> (for Gmail, a 16-character App Password)
            in the hosting environment and redeploy.
        </div>
    <?php endif; ?>
    <?php if (has_permission('settings.create')): ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="mail_test">
        <button type="submit" class="btn btn-outline">Send test email to me</button>
    </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
