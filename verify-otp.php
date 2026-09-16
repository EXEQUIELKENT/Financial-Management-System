<?php
/**
 * Two-step sign-in, step 2: enter the emailed code.
 *
 * Reachable only while a sign-in is suspended (see includes/LoginOtp.php). The visitor
 * is NOT signed in at this point -- the authenticated session is parked and is only
 * restored by a correct code.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logo-placeholder.php';
require_once __DIR__ . '/includes/LoginOtp.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}
if (!login_challenge_pending()) {
    redirect('login.php');
}

$userId = (int)$_SESSION['pending_login_user_id'];
$email  = (string)$_SESSION['pending_login_email'];

// The whole step is time-boxed: an abandoned challenge must not sit around as a
// half-authenticated session waiting to be resumed.
if ((time() - (int)$_SESSION['pending_login_started_at']) > PASSWORD_RESET_WINDOW_SECONDS) {
    clear_login_challenge();
    redirect('login.php?expired=1');
}

$error = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'cancel') {
        log_audit_as($userId, 'login_otp_cancelled', 'auth', $userId, 'Abandoned two-step sign-in');
        clear_login_challenge();
        redirect('login.php');

    } elseif ($action === 'resend') {
        $wait = OTP_RESEND_COOLDOWN_SECONDS - (time() - (int)$_SESSION['pending_login_last_sent_at']);
        if ($wait > 0) {
            $error = "Please wait {$wait}s before requesting another code.";
        } else {
            $_SESSION['pending_login_last_sent_at'] = time();
            send_login_code($userId, $email);
            $status = 'A new code has been sent to ' . mask_email($email) . '.';
        }

    } else {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            $error = 'Please enter the six-digit code.';
        } else {
            $result = password_reset_verify_code($userId, $code, LOGIN_OTP_PURPOSE);
            if ($result['success']) {
                complete_login_challenge();
                log_audit('login_otp_verified', 'auth', $userId, 'Completed two-step sign-in');
                redirect('modules/dashboard/index.php');
            }
            $error = $result['message'];
            log_audit_as($userId, 'login_otp_failed', 'auth', $userId, $result['message']);
        }
    }
}

$devCode = $_SESSION['dev_login_code'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_tags() ?>
<title>Verify Sign-in - <?= e(APP_SHORT_NAME) ?></title>
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
        <div class="login-tagline">Verify your sign-in</div>

        <?php if ($error): ?>
            <div class="alert alert-critical"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($status): ?>
            <div class="alert alert-success"><?= e($status) ?></div>
        <?php endif; ?>
        <?php if ($devCode !== null): ?>
            <div class="alert alert-warning">
                <span class="alert-title">Mail is not configured on this server</span>
                Your code is <strong><?= e($devCode) ?></strong>. (Shown only outside production.)
            </div>
        <?php endif; ?>

        <p class="form-hint" style="margin-bottom:16px;">
            Your password was accepted. We sent a six-digit code to
            <strong><?= e(mask_email($email)) ?></strong> &mdash; enter it to finish signing in.
            It expires in <?= (int)OTP_VALIDITY_MINUTES ?> minutes.
        </p>

        <form method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify">
            <div class="form-group">
                <label for="code">Six-digit code</label>
                <input type="text" id="code" name="code" class="form-control otp-input" required autofocus
                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Verify and sign in</button>
        </form>

        <form method="post" action="" style="margin-top:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-outline btn-block">Send a new code</button>
        </form>

        <form method="post" action="" style="margin-top:16px;text-align:center;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline btn-sm">Cancel and sign in again</button>
        </form>
    </div>
</div>
</body>
</html>
