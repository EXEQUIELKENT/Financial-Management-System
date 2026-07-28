<?php
require_once __DIR__ . '/../config/db.php';

function log_audit(string $action, string $module, $recordId = null, string $details = ''): void {
    $db = get_db();
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, module, record_id, details, ip, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $action, $module, $recordId, $details, $ip]);
}
