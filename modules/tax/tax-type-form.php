<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('tax.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$taxType = ['code' => '', 'name' => '', 'rate_percent' => 0, 'is_active' => 1];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM tax_types WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $taxType = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $taxType['code'] = trim($_POST['code'] ?? '');
    $taxType['name'] = trim($_POST['name'] ?? '');
    $taxType['rate_percent'] = (float)($_POST['rate_percent'] ?? 0);
    $taxType['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    if ($taxType['code'] === '' || $taxType['name'] === '') $errors[] = 'Code and name are required.';

    if (empty($errors)) {
        if ($id) {
            $db->prepare("UPDATE tax_types SET code=?, name=?, rate_percent=?, is_active=? WHERE id=?")
               ->execute([$taxType['code'], $taxType['name'], $taxType['rate_percent'], $taxType['is_active'], $id]);
            log_audit('update', 'tax', $id, 'Updated tax type ' . $taxType['name']);
        } else {
            $db->prepare("INSERT INTO tax_types (code, name, rate_percent, is_active) VALUES (?,?,?,?)")
               ->execute([$taxType['code'], $taxType['name'], $taxType['rate_percent'], $taxType['is_active']]);
            log_audit('create', 'tax', (int)$db->lastInsertId(), 'Created tax type ' . $taxType['name']);
        }
        flash('success', 'Tax type saved.');
        redirect('modules/tax/tax-types.php');
    }
}

$pageTitle = $id ? 'Edit Tax Type' : 'New Tax Type';
$pageHelp = [
    ['selector' => 'input[name="rate_percent"]',
        'en' => ['title' => 'Rate (%)', 'body' => 'Applied automatically to any Bill/Invoice line (or AP payment withholding) that selects this tax type.'],
        'tl' => ['title' => 'Rate (%)', 'body' => 'Awtomatikong ilalapat sa anumang Bill/Invoice line (o AP payment withholding) na pumili ng tax type na ito.']],
    ['selector' => 'input[name="is_active"]',
        'en' => ['title' => 'Active', 'body' => 'Inactive tax types no longer appear in the picker on new transactions.'],
        'tl' => ['title' => 'Active', 'body' => 'Hindi na lalabas ang Inactive na tax types sa picker ng mga bagong transaksyon.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:480px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group"><label>Code</label><input type="text" name="code" class="form-control" value="<?= e($taxType['code']) ?>" required></div>
        <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" value="<?= e($taxType['name']) ?>" required></div>
        <div class="form-group"><label>Rate (%)</label><input type="number" step="0.001" name="rate_percent" class="form-control" value="<?= e((string)$taxType['rate_percent']) ?>" required></div>
        <div class="form-group"><label><input type="checkbox" name="is_active" <?= $taxType['is_active'] ? 'checked' : '' ?> style="width:auto;display:inline;"> Active</label></div>
        <button type="submit" class="btn btn-primary">Save Tax Type</button>
        <a href="tax-types.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
