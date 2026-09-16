<?php
/**
 * Public "request an account" form.
 *
 * Deliberately NOT self-service registration. A finance system has no public role --
 * every account (Admin, Accountant, Approver, Auditor) is staff -- so this creates a
 * dormant record only: status 'Pending', and the lowest-privilege role as a placeholder.
 * Nobody can sign in with it, because attempt_login() requires status 'Active'. An Admin
 * reviews the request in Users and sets the real role when activating it.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logo-placeholder.php';
require_once __DIR__ . '/includes/PasswordReset.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

// The placeholder role. Auditor is view-only, so if an Admin ever activates a request
// without reading it carefully, the account still cannot change a single figure.
const REQUEST_PLACEHOLDER_ROLE = 'Auditor';

$errors = [];
$values = ['full_name' => '', 'email' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db = get_db();

    $values['full_name'] = trim($_POST['full_name'] ?? '');
    $values['email']     = trim($_POST['email'] ?? '');
    $values['username']  = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if ($values['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required - your reset codes are sent there.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $values['username'])) {
        $errors[] = 'Username must be 3-50 characters: letters, numbers, dot, underscore or hyphen.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    } elseif (($weak = password_strength_error($password)) !== null) {
        $errors[] = $weak;
    }

    if (!$errors) {
        // Unlike the password-reset flow, these clashes are reported plainly: the
        // requester has to be told their username is taken or they cannot proceed, and a
        // staff request form is not an anonymous surface worth hiding that from.
        $stmt = $db->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$values['username'], $values['email']]);
        foreach ($stmt->fetchAll() as $clash) {
            if (strcasecmp($clash['username'], $values['username']) === 0) {
                $errors[] = 'That username is already taken.';
            }
            if (strcasecmp((string)$clash['email'], $values['email']) === 0) {
                $errors[] = 'An account already exists for that email address.';
            }
        }
        $errors = array_values(array_unique($errors));
    }

    if (!$errors) {
        $roleStmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
        $roleStmt->execute([REQUEST_PLACEHOLDER_ROLE]);
        $roleId = $roleStmt->fetchColumn();

        $db->prepare(
            'INSERT INTO users (username, email, full_name, role_id, status, password_hash) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $values['username'], $values['email'], $values['full_name'], $roleId, 'Pending',
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $newId = (int)$db->lastInsertId();

        log_audit_as($newId, 'account_requested', 'auth', $newId,
            'Account requested by ' . $values['full_name'] . ' <' . $values['email'] . '>');

        redirect('login.php?requested=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_tags() ?>
<title>Request an Account - <?= e(APP_SHORT_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/css/variables.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="logo-badge" style="flex-direction:column;">
            <?= logo_or_image(72, false) ?>
            <div class="logo-text" style="margin-top:8px;">
                <strong>TravelCore</strong>
                <small>Travel &amp; Tours</small>
            </div>
        </div>
        <div class="login-tagline">Request an account</div>

        <?php if ($errors): ?>
            <div class="alert alert-critical">
                <span class="alert-title">Please check the following</span>
                <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="form-hint" style="margin-bottom:16px;">
            Accounts are approved by an administrator. Submitting this form does not sign
            you in &mdash; you can sign in once your access has been granted.
        </p>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                       value="<?= e($values['full_name']) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="email">Work email address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($values['email']) ?>" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="username">Preferred username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($values['username']) ?>" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       required autocomplete="new-password">
                <span class="form-hint">At least 8 characters, with upper and lower case, a number, and a symbol.</span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Submit request</button>
        </form>

        <p style="text-align:center;margin-top:16px;">
            <a href="<?= BASE_URL ?>/login.php">Back to sign in</a>
        </p>
    </div>
</div>
</body>
</html>
