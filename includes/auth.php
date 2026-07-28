<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

function attempt_login(string $username, string $password): bool {
    $db = get_db();
    $stmt = $db->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? AND u.status = 'Active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['permissions'] = load_permissions_for_role((int)$user['role_id']);
    $_SESSION['last_activity'] = time();

    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    return true;
}

function load_permissions_for_role(int $roleId): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT p.module_key, p.action FROM role_permissions rp
                           JOIN permissions p ON p.id = rp.permission_id
                           WHERE rp.role_id = ?");
    $stmt->execute([$roleId]);
    $perms = [];
    foreach ($stmt->fetchAll() as $row) {
        $perms[] = $row['module_key'] . '.' . $row['action'];
    }
    return $perms;
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role_id' => $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name'],
    ];
}

function has_permission(string $key): bool {
    if (!is_logged_in()) return false;
    if (($_SESSION['role_name'] ?? '') === 'Admin') return true;
    return in_array($key, $_SESSION['permissions'] ?? [], true);
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_permission(string $key): void {
    require_login();
    if (!has_permission($key)) {
        http_response_code(403);
        echo '<div style="font-family:sans-serif;padding:40px;text-align:center;">';
        echo '<h2>403 - Access Denied</h2><p>You do not have permission to access this page.</p>';
        echo '<a href="' . BASE_URL . '/modules/dashboard/index.php">Return to Dashboard</a>';
        echo '</div>';
        exit;
    }
}

function do_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
