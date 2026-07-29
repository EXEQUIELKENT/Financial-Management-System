<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('ar.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$customer = ['customer_code' => '', 'name' => '', 'contact_person' => '', 'email' => '', 'phone' => '', 'address' => '', 'tax_id' => '', 'credit_terms_days' => 30, 'status' => 'Active'];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM ar_customers WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $customer = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['customer_code','name','contact_person','email','phone','address','tax_id'] as $f) {
        $customer[$f] = trim($_POST[$f] ?? '');
    }
    $customer['credit_terms_days'] = (int)($_POST['credit_terms_days'] ?? 30);
    $customer['status'] = $_POST['status'] ?? 'Active';

    if ($customer['name'] === '') $errors[] = 'Customer name is required.';
    if ($customer['customer_code'] === '') $customer['customer_code'] = 'C' . str_pad((string)(random_int(1,9999)), 4, '0', STR_PAD_LEFT);

    if (empty($errors)) {
        if ($id) {
            $stmt = $db->prepare("UPDATE ar_customers SET customer_code=?, name=?, contact_person=?, email=?, phone=?, address=?, tax_id=?, credit_terms_days=?, status=? WHERE id=?");
            $stmt->execute([$customer['customer_code'], $customer['name'], $customer['contact_person'], $customer['email'], $customer['phone'], $customer['address'], $customer['tax_id'], $customer['credit_terms_days'], $customer['status'], $id]);
            log_audit('update', 'ar', $id, 'Updated customer ' . $customer['name']);
            flash('success', 'Customer updated.');
        } else {
            $stmt = $db->prepare("INSERT INTO ar_customers (customer_code, name, contact_person, email, phone, address, tax_id, credit_terms_days, status) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$customer['customer_code'], $customer['name'], $customer['contact_person'], $customer['email'], $customer['phone'], $customer['address'], $customer['tax_id'], $customer['credit_terms_days'], $customer['status']]);
            $id = (int)$db->lastInsertId();
            log_audit('create', 'ar', $id, 'Created customer ' . $customer['name']);
            flash('success', 'Customer created.');
        }
        redirect('modules/ar/customers.php');
    }
}

$pageTitle = $id ? 'Edit Customer' : 'New Customer';
$pageHelp = [
    ['selector' => 'input[name="customer_code"]',
        'en' => ['title' => 'Customer Code', 'body' => 'Leave blank to auto-generate one (e.g. C0001).'],
        'tl' => ['title' => 'Customer Code', 'body' => 'Iwanang blangko para awtomatikong makabuo ng isa (hal. C0001).']],
    ['selector' => 'input[name="credit_terms_days"]',
        'en' => ['title' => 'Credit Terms (days)', 'body' => 'Used to auto-suggest a Due Date when you later create an Invoice for this customer.'],
        'tl' => ['title' => 'Credit Terms (days)', 'body' => 'Ginagamit para awtomatikong magmungkahi ng Due Date paggawa mo ng Invoice para sa customer na ito.']],
    ['selector' => 'select[name="status"]',
        'en' => ['title' => 'Status', 'body' => 'Inactive customers no longer appear in the picker on new Invoices, but past history is kept.'],
        'tl' => ['title' => 'Status', 'body' => 'Hindi na lalabas ang mga Inactive na customer sa picker ng bagong Invoices, pero mananatili ang kanilang history.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:640px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-row">
            <div class="form-group"><label>Customer Code</label><input type="text" name="customer_code" class="form-control" value="<?= e($customer['customer_code']) ?>" placeholder="Auto-generated if blank"></div>
            <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" value="<?= e($customer['name']) ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" class="form-control" value="<?= e($customer['contact_person']) ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($customer['email']) ?>"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= e($customer['phone']) ?>"></div>
        </div>
        <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control" value="<?= e($customer['address']) ?>"></div>
        <div class="form-row">
            <div class="form-group"><label>Tax ID</label><input type="text" name="tax_id" class="form-control" value="<?= e($customer['tax_id']) ?>"></div>
            <div class="form-group"><label>Credit Terms (days)</label><input type="number" name="credit_terms_days" class="form-control" value="<?= (int)$customer['credit_terms_days'] ?>"></div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="Active" <?= $customer['status']==='Active'?'selected':'' ?>>Active</option>
                    <option value="Inactive" <?= $customer['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Customer</button>
        <a href="customers.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
