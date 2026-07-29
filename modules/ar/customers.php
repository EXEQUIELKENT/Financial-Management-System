<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ar.view');

$db = get_db();
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM ar_customers WHERE 1=1";
$params = [];
if ($search !== '') { $sql .= " AND (name LIKE ? OR customer_code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customers';
$pageHelp = [
    ['selector' => 'input[name="q"]',
        'en' => ['title' => 'Search box', 'body' => 'Filters by customer name or customer code as you search.'],
        'tl' => ['title' => 'Search Box', 'body' => 'Hinahanap ang customer sa pangalan o customer code habang nagta-type ka.']],
];
if (has_permission('ar.create')) {
    $pageHelp[] = ['selector' => 'a[href="customer-form.php"]',
        'en' => ['title' => '+ New Customer', 'body' => 'Adds a customer record. Leave the code blank to have one generated automatically.'],
        'tl' => ['title' => '+ Bagong Customer', 'body' => 'Magdagdag ng customer record. Iwanang blangko ang code para awtomatikong makabuo ng isa.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'View / Edit', 'body' => 'View opens the customer\'s profile: contact info, outstanding balance, and every invoice and receipt. Edit updates contact details, terms, or marks them Inactive.'],
    'tl' => ['title' => 'View / Edit', 'body' => 'Binubuksan ng View ang profile ng customer: contact info, outstanding balance, at bawat invoice at receipt. Ina-update ng Edit ang contact details, terms, o minamarkahan silang Inactive.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <input type="text" name="q" placeholder="Search customers..." value="<?= e($search) ?>" class="form-control">
            <button type="submit" class="btn btn-outline">Search</button>
        </form>
        <div>
            <a href="invoices.php" class="btn btn-outline">Invoices</a>
            <a href="receipts.php" class="btn btn-outline">Receipts</a>
            <a href="aging-report.php" class="btn btn-outline">Aging Report</a>
            <?php if (has_permission('ar.create')): ?><a href="customer-form.php" class="btn btn-primary">+ New Customer</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Terms</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><?= e($c['customer_code']) ?></td>
                <td><?= e($c['name']) ?></td>
                <td class="text-muted"><?= e($c['contact_person']) ?> <?= $c['email'] ? '· ' . e($c['email']) : '' ?></td>
                <td><?= (int)$c['credit_terms_days'] ?> days</td>
                <td><span class="badge <?= status_badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
                <td>
                    <a href="customer-view.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">View</a>
                    <?php if (has_permission('ar.create')): ?><a href="customer-form.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Edit</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?><tr><td colspan="6" class="empty-state">No customers found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
