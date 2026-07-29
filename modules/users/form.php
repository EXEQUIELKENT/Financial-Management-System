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

    if (empty($errors)) {
        if ($id) {
            if ($password !== '') {
                $stmt = $db->prepare("UPDATE users SET username=?, email=?, full_name=?, role_id=?, status=?, password_hash=? WHERE id=?");
                $stmt->execute([$user['username'], $user['email'], $user['full_name'], $user['role_id'], $user['status'], password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username=?, email=?, full_name=?, role_id=?, status=? WHERE id=?");
                $stmt->execute([$user['username'], $user['email'], $user['full_name'], $user['role_id'], $user['status'], $id]);
            }
            log_audit('update', 'users', $id, 'Updated user ' . $user['username']);
            flash('success', 'User updated.');
        } else {
            $stmt = $db->prepare("INSERT INTO users (username, email, full_name, role_id, status, password_hash) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$user['username'], $user['email'], $user['full_name'], $user['role_id'], $user['status'], password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int)$db->lastInsertId();
            log_audit('create', 'users', $id, 'Created user ' . $user['username']);
            flash('success', 'User created.');
        }
        redirect('modules/users/list.php');
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
                </select>
            </div>
        </div>
        <div class="form-group"><label>Password <?= $id ? '(leave blank to keep current)' : '' ?></label><input type="password" name="password" class="form-control"></div>
        <button type="submit" class="btn btn-primary">Save User</button>
        <a href="list.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
