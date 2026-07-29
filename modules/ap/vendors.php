<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ap.view');

$db = get_db();
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM ap_vendors WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR vendor_code LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

$pageTitle = 'Vendors';
$pageHelp = [
    ['selector' => 'input[name="q"]',
        'en' => ['title' => 'Search box', 'body' => 'Filters by vendor name or vendor code as you search.'],
        'tl' => ['title' => 'Search Box', 'body' => 'Hinahanap ang vendor sa pangalan o vendor code habang nagta-type ka.']],
];
if (has_permission('ap.create')) {
    $pageHelp[] = ['selector' => 'a[href="vendor-form.php"]',
        'en' => ['title' => '+ New Vendor', 'body' => 'Adds a vendor record. Leave the code blank to have one generated automatically.'],
        'tl' => ['title' => '+ Bagong Vendor', 'body' => 'Magdagdag ng vendor record. Iwanang blangko ang code para awtomatikong makabuo ng isa.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'View / Edit', 'body' => 'View opens the vendor\'s profile: contact info, outstanding balance, and every bill and payment. Edit updates contact details, terms, or marks them Inactive.'],
    'tl' => ['title' => 'View / Edit', 'body' => 'Binubuksan ng View ang profile ng vendor: contact info, outstanding balance, at bawat bill at payment. Ina-update ng Edit ang contact details, terms, o minamarkahan silang Inactive.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <input type="text" name="q" placeholder="Search vendors..." value="<?= e($search) ?>" class="form-control">
            <button type="submit" class="btn btn-outline">Search</button>
        </form>
        <div>
            <a href="bills.php" class="btn btn-outline">Bills</a>
            <a href="payments.php" class="btn btn-outline">Payments</a>
            <a href="aging-report.php" class="btn btn-outline">Aging Report</a>
            <?php if (has_permission('ap.create')): ?>
                <a href="vendor-form.php" class="btn btn-primary">+ New Vendor</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Terms</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($vendors as $v): ?>
            <tr>
                <td><?= e($v['vendor_code']) ?></td>
                <td><?= e($v['name']) ?></td>
                <td class="text-muted"><?= e($v['contact_person']) ?> <?= $v['email'] ? '· ' . e($v['email']) : '' ?></td>
                <td><?= (int)$v['payment_terms_days'] ?> days</td>
                <td><span class="badge <?= status_badge_class($v['status']) ?>"><?= e($v['status']) ?></span></td>
                <td>
                    <a href="vendor-view.php?id=<?= $v['id'] ?>" class="btn btn-outline btn-sm">View</a>
                    <?php if (has_permission('ap.create')): ?><a href="vendor-form.php?id=<?= $v['id'] ?>" class="btn btn-outline btn-sm">Edit</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($vendors)): ?><tr><td colspan="6" class="empty-state">No vendors found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
