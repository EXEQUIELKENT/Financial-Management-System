<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('cash.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$account = ['account_name' => '', 'account_type' => 'Bank', 'bank_name' => '', 'account_no' => '', 'gl_account_id' => '', 'opening_balance' => 0, 'status' => 'Active'];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM cash_accounts WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $account = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $account['account_name'] = trim($_POST['account_name'] ?? '');
    $account['account_type'] = $_POST['account_type'] ?? 'Bank';
    $account['bank_name'] = trim($_POST['bank_name'] ?? '');
    $account['account_no'] = trim($_POST['account_no'] ?? '');
    $account['gl_account_id'] = (int)($_POST['gl_account_id'] ?? 0);
    $account['opening_balance'] = (float)($_POST['opening_balance'] ?? 0);
    $account['status'] = $_POST['status'] ?? 'Active';

    if ($account['account_name'] === '') $errors[] = 'Account name is required.';
    if (!$account['gl_account_id']) $errors[] = 'Linked GL account is required.';

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE cash_accounts SET account_name=?, account_type=?, bank_name=?, account_no=?, gl_account_id=?, status=? WHERE id=?");
            $stmt->execute([$account['account_name'], $account['account_type'], $account['bank_name'], $account['account_no'], $account['gl_account_id'], $account['status'], $id]);
            log_audit('update', 'cash', $id, 'Updated cash account ' . $account['account_name']);
            flash('success', 'Cash account updated.');
        } else {
            $stmt = $db->prepare("INSERT INTO cash_accounts (account_name, account_type, bank_name, account_no, gl_account_id, opening_balance, current_balance, status) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$account['account_name'], $account['account_type'], $account['bank_name'], $account['account_no'], $account['gl_account_id'], $account['opening_balance'], $account['opening_balance'], $account['status']]);
            $id = (int)$db->lastInsertId();
            log_audit('create', 'cash', $id, 'Created cash account ' . $account['account_name']);
            flash('success', 'Cash account created.');
        }
        redirect('modules/cash/accounts.php');
    }
}

$glAccounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active=1 AND account_type='Asset' ORDER BY account_code")->fetchAll();

$pageTitle = $id ? 'Edit Cash Account' : 'New Cash Account';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:640px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-row">
            <div class="form-group"><label>Account Name</label><input type="text" name="account_name" class="form-control" value="<?= e($account['account_name']) ?>" required></div>
            <div class="form-group"><label>Type</label>
                <select name="account_type">
                    <?php foreach (['Cash on Hand','Bank','Petty Cash'] as $t): ?><option value="<?= $t ?>" <?= $account['account_type']===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e($account['bank_name']) ?>"></div>
            <div class="form-group"><label>Account No.</label><input type="text" name="account_no" class="form-control" value="<?= e($account['account_no']) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Linked GL Account</label>
                <select name="gl_account_id" required>
                    <option value="">— Select GL account —</option>
                    <?php foreach ($glAccounts as $g): ?><option value="<?= $g['id'] ?>" <?= (int)$account['gl_account_id']===(int)$g['id']?'selected':'' ?>><?= e($g['account_code'].' - '.$g['account_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php if (!$id): ?>
            <div class="form-group"><label>Opening Balance</label><input type="number" step="0.01" name="opening_balance" class="form-control" value="<?= e((string)$account['opening_balance']) ?>"></div>
            <?php endif; ?>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="Active" <?= $account['status']==='Active'?'selected':'' ?>>Active</option>
                    <option value="Inactive" <?= $account['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Account</button>
        <a href="accounts.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
