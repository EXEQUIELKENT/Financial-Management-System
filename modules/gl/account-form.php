<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('gl.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$account = ['account_code' => '', 'account_name' => '', 'account_type' => 'Asset', 'parent_id' => null, 'normal_balance' => 'Debit', 'is_active' => 1];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM coa_accounts WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $account = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $account['account_code'] = trim($_POST['account_code'] ?? '');
    $account['account_name'] = trim($_POST['account_name'] ?? '');
    $account['account_type'] = $_POST['account_type'] ?? 'Asset';
    $account['normal_balance'] = $_POST['normal_balance'] ?? 'Debit';
    $account['parent_id'] = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $account['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    if ($account['account_code'] === '') $errors[] = 'Account code is required.';
    if ($account['account_name'] === '') $errors[] = 'Account name is required.';

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE coa_accounts SET account_code=?, account_name=?, account_type=?, normal_balance=?, parent_id=?, is_active=? WHERE id=?");
            $stmt->execute([$account['account_code'], $account['account_name'], $account['account_type'], $account['normal_balance'], $account['parent_id'], $account['is_active'], $id]);
            log_audit('update', 'gl', $id, 'Updated chart of accounts entry');
            flash('success', 'Account updated.');
        } else {
            $stmt = $db->prepare("INSERT INTO coa_accounts (account_code, account_name, account_type, normal_balance, parent_id, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$account['account_code'], $account['account_name'], $account['account_type'], $account['normal_balance'], $account['parent_id'], $account['is_active']]);
            $id = (int)$db->lastInsertId();
            log_audit('create', 'gl', $id, 'Created chart of accounts entry');
            flash('success', 'Account created.');
        }
        redirect('modules/gl/chart-of-accounts.php');
    }
}

$parents = $db->query("SELECT id, account_code, account_name FROM coa_accounts ORDER BY account_code")->fetchAll();

$pageTitle = $id ? 'Edit Account' : 'New Account';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:640px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Account Code</label>
                <input type="text" name="account_code" class="form-control" value="<?= e($account['account_code']) ?>" required>
            </div>
            <div class="form-group">
                <label>Account Name</label>
                <input type="text" name="account_name" class="form-control" value="<?= e($account['account_name']) ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Account Type</label>
                <select name="account_type" id="account_type" onchange="autoNormalBalance()">
                    <?php foreach (['Asset','Liability','Equity','Revenue','Expense'] as $t): ?>
                        <option value="<?= $t ?>" <?= $account['account_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Normal Balance</label>
                <select name="normal_balance" id="normal_balance">
                    <option value="Debit" <?= $account['normal_balance'] === 'Debit' ? 'selected' : '' ?>>Debit</option>
                    <option value="Credit" <?= $account['normal_balance'] === 'Credit' ? 'selected' : '' ?>>Credit</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Parent Account (optional)</label>
            <select name="parent_id">
                <option value="">— None —</option>
                <?php foreach ($parents as $p): if ($p['id'] == $id) continue; ?>
                    <option value="<?= $p['id'] ?>" <?= (int)$account['parent_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['account_code'] . ' - ' . $p['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" <?= $account['is_active'] ? 'checked' : '' ?> style="width:auto;display:inline;"> Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Save Account</button>
        <a href="chart-of-accounts.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<script>
function autoNormalBalance() {
    var type = document.getElementById('account_type').value;
    var nb = document.getElementById('normal_balance');
    if (['Asset','Expense'].includes(type)) nb.value = 'Debit';
    else nb.value = 'Credit';
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
