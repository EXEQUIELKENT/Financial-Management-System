<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Writes an audit row attributed to an explicit user.
 *
 * Most callers want log_audit(), which attributes to whoever is signed in. This
 * variant exists for the auth events -- a failed sign-in, a password reset -- that
 * happen when there is no session to read the actor from, or where the actor is known
 * before the session exists.
 */
function log_audit_as(?int $userId, string $action, string $module, $recordId = null, string $details = ''): void {
    try {
        $stmt = get_db()->prepare(
            "INSERT INTO audit_log (user_id, action, module, record_id, details, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $action, $module, $recordId, $details, client_ip()]);
    } catch (Throwable $e) {
        // An audit failure must never take down the action being audited.
        error_log('Audit write failed: ' . $e->getMessage());
    }
}

/** Audit row for the currently signed-in user. */
function log_audit(string $action, string $module, $recordId = null, string $details = ''): void {
    log_audit_as($_SESSION['user_id'] ?? null, $action, $module, $recordId, $details);
}

/**
 * The caller's address. mod_remoteip already rewrites REMOTE_ADDR from
 * X-Forwarded-For behind the platform proxy (see docker/apache-vhost.conf), so this
 * is the real client IP in production and the direct peer everywhere else.
 */
function client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
