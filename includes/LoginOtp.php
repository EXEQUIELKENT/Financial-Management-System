<?php
/**
 * Two-step sign-in: after the password is accepted, a code is emailed and must be
 * entered before the session becomes usable.
 *
 * The subtle part is that attempt_login() has already populated a fully signed-in
 * session by the time the challenge starts. Leaving it in place would make the code
 * step decorative -- the user could simply type a dashboard URL and skip it. So the
 * authenticated session is moved aside into a pending slot and the live one is cleared;
 * only a correct code restores it.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/PasswordReset.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Audit.php';

const LOGIN_OTP_PURPOSE = 'login';

/** The address a sign-in code can be sent to, or null when the account has none. */
function login_otp_email(int $userId): ?string {
    $stmt = get_db()->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $email = (string)$stmt->fetchColumn();
    return ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
}

/**
 * Suspends the signed-in session and sends a code.
 * Must be called immediately after attempt_login() returns true.
 *
 * Returns true when the challenge is active (a code went out). If the email could
 * not be sent, the authenticated session is put back untouched and false is
 * returned: parking someone with correct credentials behind a code that will
 * never arrive would lock every user out the moment SMTP breaks. The failure is
 * written to the audit log either way.
 */
function start_login_challenge(int $userId, string $email): bool {
    $authenticated = $_SESSION;

    // Drop the live signed-in state, then re-key the session so the pending state is not
    // reachable under the id the browser already held.
    $_SESSION = [];
    session_regenerate_id(true);

    $_SESSION['pending_login']              = $authenticated;
    $_SESSION['pending_login_user_id']      = $userId;
    $_SESSION['pending_login_email']        = $email;
    $_SESSION['pending_login_started_at']   = time();
    $_SESSION['pending_login_last_sent_at'] = time();

    if (!send_login_code($userId, $email)) {
        log_audit_as($userId, 'login_otp_skipped', 'auth', $userId,
            'Sign-in code could not be emailed; two-step sign-in skipped');
        clear_login_challenge();
        foreach ($authenticated as $k => $v) {
            $_SESSION[$k] = $v;
        }
        $_SESSION['last_activity'] = time();
        return false;
    }

    log_audit_as($userId, 'login_otp_sent', 'auth', $userId, 'Sign-in code sent to ' . mask_email($email));
    return true;
}

/** Generates and emails a sign-in code. Returns true when the email actually went out. */
function send_login_code(int $userId, string $email): bool {
    $code = password_reset_create_code($userId, LOGIN_OTP_PURPOSE);

    $stmt = get_db()->prepare('SELECT full_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $name = (string)($stmt->fetchColumn() ?: 'there');

    $sent = send_mail($email, $name,
        'Your ' . APP_SHORT_NAME . ' sign-in code',
        mail_template('Sign-in verification',
            '<p>Hello <strong>' . e($name) . '</strong>,</p>'
            . '<p>Someone signed in to ' . e(APP_SHORT_NAME) . ' with your password. Enter this code to finish:</p>'
            . '<div style="font-size:30px;font-weight:700;letter-spacing:7px;text-align:center;'
            . 'padding:16px;background:#f1f5ff;border-radius:8px;color:#1d4ed8;margin:16px 0;">'
            . e($code) . '</div>'
            . '<p>It expires in ' . OTP_VALIDITY_MINUTES . ' minutes. <strong>If this was not you, '
            . 'your password is known to someone else &mdash; change it as soon as you can.</strong></p>'));

    if (!$sent['success'] && !empty($sent['dev_fallback'])) {
        $_SESSION['dev_login_code'] = $code;
    }
    if (!$sent['success']) {
        // Nobody received a code; make the why visible in the audit log so a broken
        // SMTP configuration can be diagnosed from the admin side.
        log_audit_as($userId, 'login_otp_send_failed', 'auth', $userId,
            'Sign-in code email failed: ' . $sent['message']);
    }
    return (bool)$sent['success'];
}

/** True when a sign-in is waiting on a code. */
function login_challenge_pending(): bool {
    return !empty($_SESSION['pending_login_started_at']) && !empty($_SESSION['pending_login']);
}

/** Restores the suspended session, completing the sign-in. */
function complete_login_challenge(): void {
    $authenticated = $_SESSION['pending_login'] ?? [];
    clear_login_challenge();
    session_regenerate_id(true);
    foreach ($authenticated as $k => $v) {
        $_SESSION[$k] = $v;
    }
    $_SESSION['last_activity'] = time();
}

function clear_login_challenge(): void {
    unset(
        $_SESSION['pending_login'],
        $_SESSION['pending_login_user_id'],
        $_SESSION['pending_login_email'],
        $_SESSION['pending_login_started_at'],
        $_SESSION['pending_login_last_sent_at'],
        $_SESSION['dev_login_code']
    );
}
