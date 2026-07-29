<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('users.view');

$db = get_db();
$users = $db->query("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.full_name")->fetchAll();

$pageTitle = 'Users';
$pageHelp = [];
if (has_permission('users.create')) {
    $pageHelp[] = ['selector' => 'a[href="form.php"]',
        'en' => ['title' => '+ New User', 'body' => 'Creates a login and assigns one of the 4 roles (Admin, Accountant, Approver, Auditor).'],
        'tl' => ['title' => '+ Bagong User', 'body' => 'Gumagawa ng login at nagtatalaga ng isa sa 4 na tungkulin (Admin, Accountant, Approver, Auditor).']];
    $pageHelp[] = ['selector' => 'a.btn-outline.btn-sm',
        'en' => ['title' => 'Edit', 'body' => 'Change a user\'s name, role, status, or reset their password (leave the password field blank to keep it unchanged).'],
        'tl' => ['title' => 'Edit', 'body' => 'Baguhin ang pangalan, tungkulin, status ng user, o i-reset ang password (iwanang blangko ang password field para hindi ito baguhin).']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'Last Login column', 'body' => 'When that user last signed in — "Never" if they haven\'t yet.'],
    'tl' => ['title' => 'Last Login column', 'body' => 'Kailan huling naglog-in ang user na iyon — "Never" kung hindi pa nila nagagawa.']];
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
