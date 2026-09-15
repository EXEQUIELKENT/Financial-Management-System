<?php
/**
 * Password-reset codes for the forgot-password flow.
 *
 * A six-digit code is emailed to the address on file, then exchanged for a new
 * password. Modelled on the OTP flow in the LGU IPMS project, with two deliberate
 * differences:
 *
 *  - Codes are stored as a hash, never in plaintext. Read access to the database is
 *    then not enough to take over an account mid-reset.
 *  - Verification looks up the user's most recent code rather than searching for one
 *    matching the guess. Matching on the guess means a wrong guess finds no row, so
 *    the attempt counter can never increment and the limit does nothing.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Creates the table if it is missing.
 *
 * The deployed database was built from sql/schema.sql before this feature existed, and
 * scripts/migrate.php deliberately does nothing once tables are present, so a released
 * environment would otherwise never get this table. Creating it on demand keeps the
 * feature self-installing; it is also in schema.sql for fresh installs.
 */
function password_reset_ensure_table(): void {
    static $done = false;
    if ($done) return;
    get_db()->exec("
        CREATE TABLE IF NOT EXISTS password_reset_otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
            used TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_pr_user (user_id),
            KEY idx_pr_expires (expires_at),
            CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $done = true;
}

/** The active account for an email address, or null. Status matches attempt_login(). */
function password_reset_find_user(string $email): ?array {
    $stmt = get_db()->prepare(
        "SELECT id, username, email, full_name, status FROM users WHERE email = ? AND status = 'Active' LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/** True when a code was already issued to this user within $withinSeconds. */
function password_reset_recent_exists(int $userId, int $withinSeconds): bool {
    password_reset_ensure_table();
    $stmt = get_db()->prepare(
        'SELECT created_at FROM password_reset_otps WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $createdAt = $stmt->fetchColumn();
    return $createdAt !== false && (time() - strtotime((string)$createdAt)) < $withinSeconds;
}

/**
 * Issues a new code, invalidating any outstanding one so only the newest works.
 * Returns the plaintext code -- the only moment it exists in readable form.
 */
function password_reset_create_code(int $userId): string {
    password_reset_ensure_table();
    $db = get_db();

    $db->prepare('DELETE FROM password_reset_otps WHERE user_id = ? OR expires_at < NOW()')->execute([$userId]);

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + (OTP_VALIDITY_MINUTES * 60));

    $db->prepare(
        'INSERT INTO password_reset_otps (user_id, code_hash, max_attempts, expires_at) VALUES (?, ?, ?, ?)'
    )->execute([$userId, password_hash($code, PASSWORD_DEFAULT), OTP_MAX_ATTEMPTS, $expires]);

    return $code;
}

/**
 * Checks a submitted code against the user's most recent one.
 * Returns ['success' => bool, 'message' => string].
 */
function password_reset_verify_code(int $userId, string $code): array {
    password_reset_ensure_table();
    $db = get_db();

    $stmt = $db->prepare(
        'SELECT id, code_hash, attempts, max_attempts, used, expires_at
           FROM password_reset_otps WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['success' => false, 'message' => 'No code was requested for this account. Please start again.'];
    }
    if ((int)$row['used'] === 1) {
        return ['success' => false, 'message' => 'That code was already used. Please request a new one.'];
    }
    if (strtotime($row['expires_at']) < time()) {
        return ['success' => false, 'message' => 'That code has expired. Please request a new one.'];
    }
    if ((int)$row['attempts'] >= (int)$row['max_attempts']) {
        return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
    }

    if (!password_verify($code, $row['code_hash'])) {
        $db->prepare('UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        $remaining = max(0, (int)$row['max_attempts'] - (int)$row['attempts'] - 1);
        return ['success' => false, 'message' => "Incorrect code. {$remaining} attempt(s) remaining."];
    }

    $db->prepare('UPDATE password_reset_otps SET used = 1, used_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return ['success' => true, 'message' => 'Code verified.'];
}

/** Sets a new password and clears every outstanding code for that user. */
function password_reset_apply(int $userId, string $newPassword): void {
    $db = get_db();
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
       ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
    $db->prepare('DELETE FROM password_reset_otps WHERE user_id = ?')->execute([$userId]);
}

/**
 * Writes an audit row for a not-logged-in actor. log_audit() reads the user id from
 * the session, which is empty during a reset, so the id is passed explicitly here.
 */
function password_reset_audit(?int $userId, string $action, string $details): void {
    try {
        get_db()->prepare(
            'INSERT INTO audit_log (user_id, action, module, details, ip, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$userId, $action, 'auth', $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) {
        // Auditing must never break the reset itself.
        error_log('Password reset audit failed: ' . $e->getMessage());
    }
}

/** Returns an error message when the password is too weak, or null when acceptable. */
function password_strength_error(string $password): ?string {
    if (strlen($password) < 8)                    return 'Password must be at least 8 characters long.';
    if (!preg_match('/[A-Z]/', $password))        return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password))        return 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password))        return 'Password must contain at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return 'Password must contain at least one symbol.';
    return null;
}

/** "accountant@travelcore.test" -> "ac********@travelcore.test" */
function mask_email(string $email): string {
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($name === '' || $domain === '') return $email;
    $visible = substr($name, 0, min(2, strlen($name)));
    return $visible . str_repeat('*', max(1, strlen($name) - strlen($visible))) . '@' . $domain;
}
