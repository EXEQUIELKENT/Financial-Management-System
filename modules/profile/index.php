<?php
/**
 * My Profile: the signed-in user's own details, password change, and a window onto
 * their own audit trail.
 *
 * Every role reaches this page -- it asks only for require_login(), not a module
 * permission, since an Auditor has as much right to change their own password as an
 * Admin does. Nothing here can touch another account: the user id always comes from
 * the session, never from the request.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/PasswordReset.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_login();

$db = get_db();
$userId = (int)$_SESSION['user_id'];
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'details') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!$errors) {
            // The address is the only way back in via forgot-password, so it must stay
            // unique -- two accounts sharing one inbox makes the reset target ambiguous.
            $clash = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $clash->execute([$email, $userId]);
            if ($email !== '' && $clash->fetch()) {
                $errors[] = 'That email address is already used by another account.';
            } else {
                $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')
                   ->execute([$fullName, $email !== '' ? $email : null, $userId]);
                $_SESSION['full_name'] = $fullName;
                log_audit('profile_updated', 'profile', $userId, 'Updated own name/email');
                $notice = 'Your details have been saved.';
            }
        }

    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = (string)$stmt->fetchColumn();

        if ($current === '' || $new === '' || $confirm === '') {
            $errors[] = 'Please fill in all three password fields.';
        } elseif (!password_verify($current, $hash)) {
            // Requiring the current password stops someone walking up to an unlocked
            // screen and taking the account over.
            $errors[] = 'Your current password is not correct.';
            log_audit('password_change_failed', 'profile', $userId, 'Wrong current password');
        } elseif ($new !== $confirm) {
            $errors[] = 'The two new passwords do not match.';
        } elseif (($weak = password_strength_error($new)) !== null) {
            $errors[] = $weak;
        } elseif (password_verify($new, $hash)) {
            $errors[] = 'Your new password must be different from your current one.';
        } else {
            password_reset_apply($userId, $new);
            log_audit('password_changed', 'profile', $userId, 'Changed own password');
            $notice = 'Your password has been changed.';
        }
    }
}

// Named $profile, not $user: keeps this fuller record distinct from the signed-in
// user (includes/header.php exposes that one as $currentUser).
$stmt = $db->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$pager = paginate($db,
    'SELECT action, module, details, ip, created_at FROM audit_log WHERE user_id = ? ORDER BY id DESC',
    'SELECT COUNT(*) FROM audit_log WHERE user_id = ?',
    [$userId], current_page(), 10);
$activity = $pager['data'];

$pageTitle = 'My Profile';
$pageHelp = [
    ['selector' => 'form[data-form="details"]',
        'en' => ['title' => 'Your details', 'body' => 'Your name as it appears on documents you create, and the email address password-reset codes are sent to.'],
        'tl' => ['title' => 'Iyong Detalye', 'body' => 'Ang pangalan mong lumalabas sa mga dokumentong ginawa mo, at ang email address kung saan ipinapadala ang reset code.']],
    ['selector' => 'form[data-form="password"]',
        'en' => ['title' => 'Change password', 'body' => 'You must enter your current password first. The new one needs 8+ characters with upper and lower case, a number, and a symbol.'],
        'tl' => ['title' => 'Palitan ang Password', 'body' => 'Kailangan mo munang ilagay ang kasalukuyang password. Ang bago ay dapat 8+ karakter na may malaki at maliit na titik, numero, at simbolo.']],
    ['selector' => '.profile-activity',
        'en' => ['title' => 'Your activity', 'body' => 'The audit trail of your own actions. Admins can see everyone\'s in Audit Log.'],
        'tl' => ['title' => 'Iyong Aktibidad', 'body' => 'Ang audit trail ng sarili mong mga aksyon. Nakikita ng Admin ang sa lahat sa Audit Log.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<?php if ($errors): ?>
    <div class="alert alert-critical">
        <span class="alert-title">Please check the following</span>
        <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($notice): ?>
    <div class="alert alert-success"><?= e($notice) ?></div>
<?php endif; ?>

<div class="profile-grid">
    <div class="card">
        <h3 class="card-title">Account</h3>
        <dl class="profile-facts">
            <dt>Username</dt><dd><?= e($profile['username']) ?></dd>
            <dt>Role</dt><dd><span class="role-pill"><?= e($profile['role_name']) ?></span></dd>
            <dt>Status</dt><dd><span class="badge <?= status_badge_class($profile['status']) ?>"><?= e($profile['status']) ?></span></dd>
            <dt>Last sign-in</dt><dd><?= $profile['last_login'] ? e(format_date($profile['last_login'], 'M d, Y g:i A')) : 'Never' ?></dd>
            <dt>Member since</dt><dd><?= e(format_date($profile['created_at'])) ?></dd>
        </dl>
        <p class="form-hint">
            Your username and role are set by an administrator and cannot be changed here.
        </p>
    </div>

    <div class="card">
        <h3 class="card-title">Your details</h3>
        <form method="post" action="" data-form="details">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="details">
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                       value="<?= e($profile['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($profile['email'] ?? '') ?>" autocomplete="email">
                <span class="form-hint">Password reset codes are sent here. Without it you cannot reset your own password.</span>
            </div>
            <button type="submit" class="btn btn-primary">Save details</button>
        </form>
    </div>

    <div class="card">
        <h3 class="card-title">Change password</h3>
        <form method="post" action="" data-form="password">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="form-group">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password"
                       class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password"
                       class="form-control" required autocomplete="new-password">
                <span class="form-hint">At least 8 characters, with upper and lower case, a number, and a symbol.</span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Change password</button>
        </form>
    </div>
</div>

<div class="card profile-activity">
    <h3 class="card-title">Your recent activity</h3>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>When</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($activity as $a): ?>
            <tr>
                <td><?= e(format_date($a['created_at'], 'M d, Y g:i A')) ?></td>
                <td><?= e($a['action']) ?></td>
                <td><?= e($a['module']) ?></td>
                <td><?= e($a['details']) ?></td>
                <td><?= e($a['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($activity)): ?>
            <tr><td colspan="5" class="empty-state">No activity recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
