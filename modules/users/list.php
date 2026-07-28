<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('users.view');

$db = get_db();
$users = $db->query("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.full_name")->fetchAll();

$pageTitle = 'Users';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <?php if (has_permission('users.create')): ?><a href="form.php" class="btn btn-primary">+ New User</a><?php endif; ?>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td class="text-muted"><?= e($u['email']) ?></td>
                <td><?= e($u['role_name']) ?></td>
                <td><span class="badge <?= status_badge_class($u['status']) ?>"><?= e($u['status']) ?></span></td>
                <td class="text-muted"><?= $u['last_login'] ? format_date($u['last_login'], 'M d, Y g:i A') : 'Never' ?></td>
                <td><?php if (has_permission('users.create')): ?><a href="form.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?><tr><td colspan="7" class="empty-state">No users found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
