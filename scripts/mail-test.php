<?php
/**
 * SMTP delivery diagnostic for the OTP / password-reset emails.
 *
 * The login screen deliberately shows only "Unable to send verification code,
 * contact your administrator." when delivery fails, so this script exists to
 * answer the administrator's next question -- WHY did it fail -- with the real
 * SMTP conversation error instead of guesses.
 *
 * Usage (CLI only):
 *   php scripts/mail-test.php you@example.com
 *
 * On the HostForge container there is no shell, so run it locally with the
 * same MAIL_* values the dashboard holds (export them first), or paste the
 * dashboard values into a local .env-style export. For Gmail, MAIL_PASSWORD
 * must be a 16-character App Password from an account with 2-Step
 * Verification enabled -- a normal account password is rejected (SMTP 535).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Mailer.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

$to = $argv[1] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Usage: php scripts/mail-test.php you@example.com\n";
    exit(1);
}

echo 'APP_ENV   : ' . APP_ENV . "\n";
echo 'MAIL_HOST : ' . MAIL_HOST . ':' . MAIL_PORT . ' (' . MAIL_ENCRYPTION . ")\n";
echo 'MAIL_USER : ' . (MAIL_USERNAME !== '' ? MAIL_USERNAME : '(empty)') . "\n";
echo 'MAIL_PASS : ' . (MAIL_PASSWORD !== '' ? str_repeat('*', min(16, strlen(MAIL_PASSWORD))) . ' (' . strlen(MAIL_PASSWORD) . ' chars)' : '(empty)') . "\n";
echo 'MAIL_FROM : ' . MAIL_FROM_EMAIL . ' <' . MAIL_FROM_NAME . ">\n";
echo 'Configured: ' . (mail_is_configured() ? 'yes' : 'NO (MAIL_HOST/MAIL_USERNAME/MAIL_PASSWORD all required)') . "\n\n";

if (!mail_is_configured()) {
    echo "Result: NOT ATTEMPTED -- mail is not configured.\n";
    echo "Set MAIL_USERNAME + MAIL_PASSWORD (Gmail App Password) and retry.\n";
    exit(2);
}

echo "Sending test message to {$to} ...\n";
$result = send_mail($to, 'Mail Test', 'TravelCore FMS mail test',
    mail_template('Mail test', '<p>If you can read this, OTP and password-reset codes will deliver.</p>'));

if ($result['success']) {
    echo "Result: SENT. Check the inbox (and spam) for '{$to}'.\n";
    exit(0);
}

echo "Result: FAILED -- {$result['message']}\n";
if (!empty($result['detail'])) {
    echo "SMTP detail: {$result['detail']}\n";
}
if (stripos((string)($result['detail'] ?? ''), '535') !== false
    || stripos((string)($result['detail'] ?? ''), 'authenticat') !== false) {
    echo "Hint: Gmail SMTP 535 means bad credentials -- use a 16-character App Password\n";
    echo "(Google Account > Security > 2-Step Verification > App passwords), not the\n";
    echo "normal account password, and confirm MAIL_USERNAME matches that account.\n";
}
exit(3);
