<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ap.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$vendor = ['vendor_code' => '', 'name' => '', 'contact_person' => '', 'email' => '', 'phone' => '', 'address' => '', 'tax_id' => '', 'payment_terms_days' => 30, 'status' => 'Active'];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM ap_vendors WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $vendor = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['vendor_code','name','contact_person','email','phone','address','tax_id'] as $f) {
        $vendor[$f] = trim($_POST[$f] ?? '');
    }
    $vendor['payment_terms_days'] = (int)($_POST['payment_terms_days'] ?? 30);
    $vendor['status'] = $_POST['status'] ?? 'Active';

    if ($vendor['name'] === '') $errors[] = 'Vendor name is required.';
    if ($vendor['vendor_code'] === '') $vendor['vendor_code'] = 'V' . str_pad((string)(random_int(1,9999)), 4, '0', STR_PAD_LEFT);

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE ap_vendors SET vendor_code=?, name=?, contact_person=?, email=?, phone=?, address=?, tax_id=?, payment_terms_days=?, status=? WHERE id=?");
            $stmt->execute([$vendor['vendor_code'], $vendor['name'], $vendor['contact_person'], $vendor['email'], $vendor['phone'], $vendor['address'], $vendor['tax_id'], $vendor['payment_terms_days'], $vendor['status'], $id]);
            log_audit('update', 'ap', $id, 'Updated vendor ' . $vendor['name']);
            flash('success', 'Vendor updated.');
        } else {
            $stmt = $db->prepare("INSERT INTO ap_vendors (vendor_code, name, contact_person, email, phone, address, tax_id, payment_terms_days, status) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$vendor['vendor_code'], $vendor['name'], $vendor['contact_person'], $vendor['email'], $vendor['phone'], $vendor['address'], $vendor['tax_id'], $vendor['payment_terms_days'], $vendor['status']]);
            $id = (int)$db->lastInsertId();
            log_audit('create', 'ap', $id, 'Created vendor ' . $vendor['name']);
            flash('success', 'Vendor created.');
        }
        redirect('modules/ap/vendors.php');
    }
}

$pageTitle = $id ? 'Edit Vendor' : 'New Vendor';
$pageHelp = [
    ['selector' => 'input[name="vendor_code"]',
        'en' => ['title' => 'Vendor Code', 'body' => 'Leave blank to auto-generate one (e.g. V0001).'],
        'tl' => ['title' => 'Vendor Code', 'body' => 'Iwanang blangko para awtomatikong makabuo ng isa (hal. V0001).']],
    ['selector' => 'input[name="payment_terms_days"]',
        'en' => ['title' => 'Payment Terms (days)', 'body' => 'Used to auto-suggest a Due Date when you later create a Bill for this vendor.'],
        'tl' => ['title' => 'Payment Terms (days)', 'body' => 'Ginagamit para awtomatikong magmungkahi ng Due Date paggawa mo ng Bill para sa vendor na ito.']],
    ['selector' => 'select[name="status"]',
        'en' => ['title' => 'Status', 'body' => 'Inactive vendors no longer appear in the vendor picker on new Bills, but past history is kept.'],
        'tl' => ['title' => 'Status', 'body' => 'Hindi na lalabas ang mga Inactive na vendor sa picker ng bagong Bills, pero mananatili ang kanilang history.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:640px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-row">
            <div class="form-group"><label>Vendor Code</label><input type="text" name="vendor_code" class="form-control" value="<?= e($vendor['vendor_code']) ?>" placeholder="Auto-generated if blank"></div>
            <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" value="<?= e($vendor['name']) ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" class="form-control" value="<?= e($vendor['contact_person']) ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($vendor['email']) ?>"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= e($vendor['phone']) ?>"></div>
        </div>
        <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control" value="<?= e($vendor['address']) ?>"></div>
        <div class="form-row">
            <div class="form-group"><label>Tax ID</label><input type="text" name="tax_id" class="form-control" value="<?= e($vendor['tax_id']) ?>"></div>
            <div class="form-group"><label>Payment Terms (days)</label><input type="number" name="payment_terms_days" class="form-control" value="<?= (int)$vendor['payment_terms_days'] ?>"></div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="Active" <?= $vendor['status']==='Active'?'selected':'' ?>>Active</option>
                    <option value="Inactive" <?= $vendor['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Vendor</button>
        <a href="vendors.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
