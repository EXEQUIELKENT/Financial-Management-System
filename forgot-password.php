<?php
/**
 * Forgot password, step 1 of 2: ask for the email address and send a code.
 *
 * The response is deliberately identical whether or not the address belongs to a real
 * account -- same redirect, same next screen, same wording. Telling a stranger "no such
 * email" turns this page into a way to discover who has an account, so the decision of
 * whether a code was actually sent is kept entirely server-side.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logo-placeholder.php';
require_once __DIR__ . '/includes/PasswordReset.php';
require_once __DIR__ . '/includes/Mailer.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

$error = '';
if (($_GET['error'] ?? '') === 'expired') {
    $error = 'That reset session expired. Please request a new code.';
} elseif (($_GET['error'] ?? '') === 'inactive') {
    $error = 'That account is no longer active. Please contact your administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $user = password_reset_find_user($email);

        // Reset any half-finished attempt, on both the real and the decoy path, so the
        // two are indistinguishable from the outside.
        unset(
            $_SESSION['pending_reset_user_id'],
            $_SESSION['pending_reset_email'],
            $_SESSION['pending_reset_started_at'],
            $_SESSION['pending_reset_last_sent_at'],
            $_SESSION['pending_reset_decoy_attempts'],
            $_SESSION['pending_reset_verified'],
            $_SESSION['dev_code_preview']
        );

        $_SESSION['pending_reset_started_at']  = time();
        $_SESSION['pending_reset_last_sent_at'] = time();
        $_SESSION['pending_reset_decoy_attempts'] = 0;
        $_SESSION['pending_reset_email'] = $user ? $user['email'] : $email;

        if ($user) {
            $_SESSION['pending_reset_user_id'] = (int)$user['id'];

            // Skip sending if one went out moments ago, so repeated submits cannot be
            // used to flood someone's inbox.
            if (!password_reset_recent_exists((int)$user['id'], OTP_RESEND_COOLDOWN_SECONDS)) {
                $code = password_reset_create_code((int)$user['id']);
                $sent = send_mail(
                    $user['email'],
                    $user['full_name'],
                    'Your ' . APP_SHORT_NAME . ' password reset code',
                    mail_template('Password reset code',
                        '<p>Hello <strong>' . e($user['full_name']) . '</strong>,</p>'
                        . '<p>Use this code to reset your ' . e(APP_SHORT_NAME) . ' password:</p>'
                        . '<div style="font-size:30px;font-weight:700;letter-spacing:7px;'
                        . 'text-align:center;padding:16px;background:#f1f5ff;border-radius:8px;'
                        . 'color:#1d4ed8;margin:16px 0;">' . e($code) . '</div>'
                        . '<p>It expires in ' . OTP_VALIDITY_MINUTES . ' minutes. If you did not request '
                        . 'this, you can ignore this email - your password will not change.</p>')
                );

                if (!$sent['success'] && !empty($sent['dev_fallback'])) {
                    // No SMTP configured outside production: show the code on the next
                    // screen so the flow stays usable locally. Never happens in production.
                    $_SESSION['dev_code_preview'] = $code;
                }
                password_reset_audit((int)$user['id'], 'password_reset_requested',
                    'Reset code issued for ' . $user['username']);
            }
        }

        redirect('reset-password.php');
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - <?= e(APP_SHORT_NAME) ?></title>
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
        <div class="login-tagline">Reset your password</div>

        <?php if ($error): ?>
            <div class="alert alert-critical"><?= e($error) ?></div>
        <?php endif; ?>

        <p class="form-hint" style="margin-bottom:16px;">
            Enter the email address on your account. If it matches an active user, we'll
            send a six-digit code you can use to set a new password.
        </p>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= old('email') ?>" required autofocus autocomplete="email">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send reset code</button>
        </form>

        <p style="text-align:center;margin-top:16px;">
            <a href="<?= BASE_URL ?>/login.php">Back to sign in</a>
        </p>
    </div>
</div>
</body>
</html>
