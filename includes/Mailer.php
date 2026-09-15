<?php
/**
 * Outbound email, used by the password-reset flow.
 *
 * Wraps the vendored PHPMailer (lib/PHPMailer) behind two small functions so callers
 * never touch SMTP details. Configuration comes from the MAIL_* environment variables
 * (see config/config.php); when none are set the send fails rather than silently
 * succeeding, and outside production the caller may show the code on screen instead so
 * the flow is still demonstrable without an SMTP account.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function mail_is_configured(): bool {
    return MAIL_HOST !== '' && MAIL_USERNAME !== '' && MAIL_PASSWORD !== '';
}

/**
 * Sends an HTML email.
 *
 * Returns ['success' => bool, 'dev_fallback' => bool, 'message' => string].
 * dev_fallback is true only when mail is unconfigured AND this is not production --
 * it tells the caller it may reveal the code on screen instead.
 */
function send_mail(string $to, string $toName, string $subject, string $bodyHtml): array {
    if (!mail_is_configured()) {
        return [
            'success' => false,
            'dev_fallback' => APP_ENV !== 'production',
            'message' => 'Mail is not configured on this server.',
        ];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->Port = MAIL_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = strtolower(MAIL_ENCRYPTION) === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $bodyHtml)));

        $mail->send();
        return ['success' => true, 'dev_fallback' => false, 'message' => 'Sent.'];
    } catch (Throwable $e) {
        // ErrorInfo carries the SMTP conversation detail; keep it in the server log only.
        error_log('Password reset email failed: ' . ($mail->ErrorInfo ?: $e->getMessage()));
        return [
            'success' => false,
            'dev_fallback' => false,
            'message' => 'Could not send the email. Please try again later.',
        ];
    }
}

/** Wraps body HTML in the app's email shell so every message looks the same. */
function mail_template(string $heading, string $bodyHtml): string {
    $app = htmlspecialchars(APP_SHORT_NAME, ENT_QUOTES, 'UTF-8');
    $year = date('Y');
    return "<!DOCTYPE html><html><body style=\"margin:0;padding:24px;background:#f4f6fb;"
         . "font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1c2430;\">"
         . "<div style=\"max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;"
         . "padding:28px;border:1px solid #e3e8f0;\">"
         . "<div style=\"font-size:20px;font-weight:700;color:#1d4ed8;margin-bottom:6px;\">{$app}</div>"
         . "<div style=\"font-size:16px;font-weight:600;margin-bottom:16px;\">" . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . "</div>"
         . "<div style=\"font-size:14px;line-height:1.6;\">{$bodyHtml}</div>"
         . "<div style=\"margin-top:24px;padding-top:14px;border-top:1px solid #e3e8f0;"
         . "font-size:12px;color:#6b7280;\">This is an automated message - please do not reply."
         . "<br>&copy; {$year} {$app}</div>"
         . "</div></body></html>";
}
