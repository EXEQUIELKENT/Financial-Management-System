<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('users.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$user = ['username' => '', 'email' => '', 'full_name' => '', 'role_id' => '', 'status' => 'Active'];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $user = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user['username'] = trim($_POST['username'] ?? '');
    $user['email'] = trim($_POST['email'] ?? '');
    $user['full_name'] = trim($_POST['full_name'] ?? '');
    $user['role_id'] = (int)($_POST['role_id'] ?? 0);
    $user['status'] = $_POST['status'] ?? 'Active';
    $password = $_POST['password'] ?? '';

    if ($user['username'] === '' || $user['full_name'] === '') $errors[] = 'Username and full name are required.';
    if (!$user['role_id']) $errors[] = 'Role is required.';
    if (!$id && $password === '') $errors[] = 'Password is required for new users.';

    // Usernames are unique, and a clash used to surface as an uncaught database
    // error -- a blank page on a production server. Check up front so the visitor
    // gets a message they can act on instead.
    if (empty($errors)) {
        $dup = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?');
        $dup->execute([$user['username'], $id]);
        if ((int)$dup->fetchColumn() > 0) {
            $errors[] = 'That username is already taken. Please choose a different username.';
        }
    }

    if (empty($errors)) {
        try {
            if ($id) {
                if ($password !== '') {
                    $stmt = $db->prepare("UPDATE users SET username=?, email=?, full_name=?, role_id=?, status=?, password_hash=? WHERE id=?");
                    $stmt->execute([$user['username'], $user['email'], $user['full_name'], $user['role_id'], $user['status'], password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username=?, email=?, full_name=?, role_id=?, status=? WHERE id=?");
                    $stmt->execute([$user['username'], $user['email'], $user['full_name'], $user['role_id'], $user['status'], $id]);
                }
                $roleName = $db->query('SELECT name FROM roles WHERE id = ' . (int)$user['role_id'])->fetchColumn();
                log_audit('update', 'users', $id, sprintf('Updated user %s (role: %s, status: %s)',
                    $user['username'], $roleName, $user['status']));
                flash('success', 'User updated.');
            } else {
                $stmt = $db->prepare("INSERT INTO users (username, email, full_name, role_id, status, password_hash) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$user['username'], $user['email'], $user['full_name'], $user['role_id'], $user['status'], password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int)$db->lastInsertId();
                $roleName = $db->query('SELECT name FROM roles WHERE id = ' . (int)$user['role_id'])->fetchColumn();
                log_audit('create', 'users', $id, sprintf('Created user %s (role: %s, status: %s)',
                    $user['username'], $roleName, $user['status']));
                flash('success', 'User created.');
            }
        } catch (Throwable $e) {
            // A database-level rejection (missing role, transport hiccup, ...) must
            // not blank the page: keep the form open with a readable explanation
            // and leave the detail in the server log.
            error_log('User save failed: ' . $e->getMessage());
            $errors[] = 'Could not save the user. Please try again, and contact your '
                      . 'administrator if it keeps happening.';
        }
        if (empty($errors)) {
            redirect('modules/users/list.php');
        }
    }
}

$roles = $db->query("SELECT * FROM roles ORDER BY name")->fetchAll();

$pageTitle = $id ? 'Edit User' : 'New User';
$pageHelp = [
    ['selector' => 'select[name="role_id"]',
        'en' => ['title' => 'Role', 'body' => 'Decides exactly which sidebar items and buttons this user will see — see the Getting Started guide for what each role can do.'],
        'tl' => ['title' => 'Role', 'body' => 'Ito ang nagpapasya kung anong mga sidebar item at button ang makikita ng user na ito — tingnan ang Getting Started guide para malaman ang kaya ng bawat role.']],
    ['selector' => 'input[name="password"]',
        'en' => ['title' => 'Password', 'body' => 'Required when creating a new user; leave blank when editing an existing one to keep their current password.'],
        'tl' => ['title' => 'Password', 'body' => 'Kailangan kapag gumagawa ng bagong user; iwanang blangko kapag nag-e-edit ng existing user para panatilihin ang kasalukuyang password.']],
    ['selector' => 'select[name="status"]',
        'en' => ['title' => 'Status', 'body' => 'Inactive users can no longer log in.'],
        'tl' => ['title' => 'Status', 'body' => 'Hindi na makaka-login ang mga Inactive na user.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:520px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required></div>
        <div class="form-row">
            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Role</label>
                <select name="role_id" required>
                    <option value="">— Select role —</option>
                    <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= (int)$user['role_id']===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="Active" <?= $user['status']==='Active'?'selected':'' ?>>Active</option>
                    <option value="Inactive" <?= $user['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                    <option value="Pending" <?= $user['status']==='Pending'?'selected':'' ?>>Pending (awaiting approval)</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Password <?= $id ? '(leave blank to keep current)' : '' ?></label><input type="password" name="password" class="form-control"></div>
        <button type="submit" class="btn btn-primary">Save User</button>
        <a href="list.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
