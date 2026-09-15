<?php
/**
 * Forgot password, step 2 of 2: verify the emailed code, then set a new password.
 *
 * Reachable only with a reset started by forgot-password.php. When that page was given
 * an address with no matching account it still sends the visitor here, with no user id
 * in the session -- the "decoy" path below mirrors every response of the real one so the
 * two cannot be told apart, and can never reach the password form.
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

function clear_reset_session(): void {
    unset(
        $_SESSION['pending_reset_user_id'],
        $_SESSION['pending_reset_email'],
        $_SESSION['pending_reset_started_at'],
        $_SESSION['pending_reset_last_sent_at'],
        $_SESSION['pending_reset_decoy_attempts'],
        $_SESSION['pending_reset_verified'],
        $_SESSION['dev_code_preview']
    );
}

if (empty($_SESSION['pending_reset_started_at'])) {
    redirect('forgot-password.php');
}
if ((time() - (int)$_SESSION['pending_reset_started_at']) > PASSWORD_RESET_WINDOW_SECONDS) {
    clear_reset_session();
    redirect('forgot-password.php?error=expired');
}

$userId       = isset($_SESSION['pending_reset_user_id']) ? (int)$_SESSION['pending_reset_user_id'] : null;
$pendingEmail = (string)($_SESSION['pending_reset_email'] ?? '');
$error = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'cancel') {
        clear_reset_session();
        redirect('login.php');

    } elseif ($action === 'resend') {
        $wait = OTP_RESEND_COOLDOWN_SECONDS - (time() - (int)($_SESSION['pending_reset_last_sent_at'] ?? 0));
        if ($wait > 0) {
            $error = "Please wait {$wait}s before requesting another code.";
        } else {
            $_SESSION['pending_reset_last_sent_at'] = time();
            unset($_SESSION['pending_reset_verified'], $_SESSION['dev_code_preview']);

            if ($userId !== null) {
                $stmt = get_db()->prepare("SELECT full_name, email FROM users WHERE id = ? AND status = 'Active'");
                $stmt->execute([$userId]);
                $fresh = $stmt->fetch();
                if (!$fresh) {
                    clear_reset_session();
                    redirect('forgot-password.php?error=inactive');
                }
                $code = password_reset_create_code($userId);
                $sent = send_mail($fresh['email'], $fresh['full_name'],
                    'Your ' . APP_SHORT_NAME . ' password reset code',
                    mail_template('Password reset code',
                        '<p>Hello <strong>' . e($fresh['full_name']) . '</strong>,</p>'
                        . '<p>Your new code is:</p>'
                        . '<div style="font-size:30px;font-weight:700;letter-spacing:7px;'
                        . 'text-align:center;padding:16px;background:#f1f5ff;border-radius:8px;'
                        . 'color:#1d4ed8;margin:16px 0;">' . e($code) . '</div>'
                        . '<p>It expires in ' . OTP_VALIDITY_MINUTES . ' minutes.</p>'));
                if (!$sent['success'] && !empty($sent['dev_fallback'])) {
                    $_SESSION['dev_code_preview'] = $code;
                }
                password_reset_audit($userId, 'password_reset_code_resent', 'Reset code resent');
            } else {
                // Decoy path: no database or mail work, same visible outcome.
                $_SESSION['pending_reset_decoy_attempts'] = 0;
            }
            $status = 'A new code has been sent to ' . mask_email($pendingEmail) . '.';
        }

    } elseif ($action === 'verify') {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            $error = 'Please enter the six-digit code.';
        } elseif ($userId !== null) {
            $result = password_reset_verify_code($userId, $code);
            if ($result['success']) {
                $_SESSION['pending_reset_verified'] = true;
            } else {
                $error = $result['message'];
                password_reset_audit($userId, 'password_reset_code_rejected', $result['message']);
            }
        } else {
            // Decoy path: counts down like the real one, never succeeds.
            $attempts = (int)($_SESSION['pending_reset_decoy_attempts'] ?? 0);
            $_SESSION['pending_reset_decoy_attempts'] = $attempts + 1;
            $remaining = max(0, OTP_MAX_ATTEMPTS - $attempts - 1);
            $error = $remaining > 0
                ? "Incorrect code. {$remaining} attempt(s) remaining."
                : 'Too many incorrect attempts. Please request a new code.';
        }

    } elseif ($action === 'set_password') {
        if (empty($_SESSION['pending_reset_verified'])) {
            // The form only renders after verification; this catches a forged POST.
            $error = 'Please verify your code first.';
        } else {
            $new     = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if ($new === '' || $confirm === '') {
                $error = 'Please fill in both password fields.';
            } elseif ($new !== $confirm) {
                $error = 'The two passwords do not match.';
            } elseif (($weak = password_strength_error($new)) !== null) {
                $error = $weak;
            } elseif ($userId !== null) {
                $stmt = get_db()->prepare("SELECT id, username FROM users WHERE id = ? AND status = 'Active'");
                $stmt->execute([$userId]);
                $fresh = $stmt->fetch();
                if (!$fresh) {
                    clear_reset_session();
                    redirect('forgot-password.php?error=inactive');
                }
                password_reset_apply($userId, $new);
                password_reset_audit($userId, 'password_reset_completed',
                    'Password reset for ' . $fresh['username']);
                clear_reset_session();
                redirect('login.php?reset=1');
            }
        }
    }
}

$verified = !empty($_SESSION['pending_reset_verified']);
$devCode  = $_SESSION['dev_code_preview'] ?? null;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - <?= e(APP_SHORT_NAME) ?></title>
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
        <div class="login-tagline"><?= $verified ? 'Choose a new password' : 'Enter your reset code' ?></div>

        <?php if ($error): ?>
            <div class="alert alert-critical"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($status): ?>
            <div class="alert alert-success"><?= e($status) ?></div>
        <?php endif; ?>
        <?php if ($devCode !== null): ?>
            <div class="alert alert-warning">
                <span class="alert-title">Mail is not configured on this server</span>
                Your code is <strong><?= e($devCode) ?></strong>. Set the MAIL_* environment
                variables to have codes emailed instead. (Shown only outside production.)
            </div>
        <?php endif; ?>

        <?php if (!$verified): ?>
            <p class="form-hint" style="margin-bottom:16px;">
                If <strong><?= e(mask_email($pendingEmail)) ?></strong> belongs to an active
                account, a six-digit code is on its way. It expires in
                <?= (int)OTP_VALIDITY_MINUTES ?> minutes.
            </p>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <label for="code">Six-digit code</label>
                    <input type="text" id="code" name="code" class="form-control" required autofocus
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                           style="letter-spacing:6px;text-align:center;font-size:20px;">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Verify code</button>
            </form>
            <form method="post" action="" style="margin-top:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn btn-outline btn-block">Send a new code</button>
            </form>
        <?php else: ?>
            <p class="form-hint" style="margin-bottom:16px;">
                Code accepted. Choose a new password: at least 8 characters, with an
                uppercase letter, a lowercase letter, a number and a symbol.
            </p>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_password">
                <div class="form-group">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control"
                           required autofocus autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Set new password</button>
            </form>
        <?php endif; ?>

        <form method="post" action="" style="margin-top:16px;text-align:center;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline btn-sm">Cancel and return to sign in</button>
        </form>
    </div>
</div>
</body>
</html>
