<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logo-placeholder.php';
require_once __DIR__ . '/includes/PasswordReset.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/includes/LoginOtp.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (attempt_login($username, $password)) {
        // Two-step sign-in, mirroring the LGU IPMS staff flow: the password step
        // parks the session and a code must be entered before it is restored.
        // The challenge starts whenever the account has an email that can
        // receive the code; without mail configured the code is shown on the
        // verification screen outside production (dev preview), and in
        // production an undeliverable code refuses the sign-in instead of
        // silently skipping the second factor.
        $uid = (int)$_SESSION['user_id'];
        $email = login_otp_email($uid);
        if ($email !== null) {
            if (start_login_challenge($uid, $email)) {
                redirect('verify-otp.php');
            }
            $error = 'Unable to send verification code, contact your administrator.';
        } else {
            // No address on file means no way to receive a code. Rather than lock the
            // account out, let them in and record it for an administrator to fix.
            log_audit('login_otp_skipped', 'auth', $uid, 'No email address on file for two-step sign-in');
            redirect('modules/dashboard/index.php');
        }
    } else {
        $error = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_tags() ?>
<title>Login - <?= e(APP_SHORT_NAME) ?></title>
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
        <div class="login-tagline"><?= e(APP_FULL_TITLE) ?></div>

        <?php if ($error): ?>
            <div class="alert alert-critical"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['expired'])): ?>
            <div class="alert alert-warning">Your sign-in verification timed out. Please sign in again.</div>
        <?php endif; ?>
        <?php if (isset($_GET['requested'])): ?>
            <div class="alert alert-success">Your request has been submitted. An administrator will review it before you can sign in.</div>
        <?php endif; ?>
        <?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success">Your password has been changed. Sign in with your new password.</div>
        <?php endif; ?>
        <?php if (isset($_GET['inactive'])): ?>
            <div class="alert alert-warning">That account is no longer active. Please contact your administrator.</div>
        <?php endif; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username or email</label>
                <input type="text" id="username" name="username" class="form-control" value="<?= old('username') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <p style="text-align:center;margin-top:14px;">
            <a href="<?= BASE_URL ?>/forgot-password.php">Forgot your password?</a>
            &nbsp;&middot;&nbsp;
            <a href="<?= BASE_URL ?>/request-account.php">Request an account</a>
        </p>

        <?php if (APP_ENV !== 'production'): // Never publish demo credentials on a public URL. ?>
        <div class="login-demo">
            <strong>Demo accounts</strong> (after running <code>sql/seed.php</code>):<br>
            admin / accountant / approver / auditor — password: <code>Passw0rd!</code>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
