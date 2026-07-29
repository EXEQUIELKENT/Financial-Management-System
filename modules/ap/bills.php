<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ap.view');

$db = get_db();
$status = $_GET['status'] ?? '';
$sql = "SELECT b.*, v.name AS vendor_name FROM ap_bills b JOIN ap_vendors v ON v.id = b.vendor_id WHERE 1=1";
$params = [];
if ($status !== '') { $sql .= " AND b.status = ?"; $params[] = $status; }
$sql .= " ORDER BY b.bill_date DESC, b.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

$pageTitle = 'Accounts Payable - Bills';
$pageHelp = [
    ['selector' => 'select[name="status"]',
        'en' => ['title' => 'Status filter', 'body' => 'Narrows the list to just one status: Draft, Open, PartiallyPaid, Paid, or Void.'],
        'tl' => ['title' => 'Status Filter', 'body' => 'Ipinapakita lamang ang mga bill na may piniling status: Draft, Open, PartiallyPaid, Paid, o Void.']],
    ['selector' => '.table-toolbar > div:last-child',
        'en' => ['title' => 'Quick links', 'body' => 'Jump to Vendors, Payments, or the Aging Report — the other Accounts Payable screens.'],
        'tl' => ['title' => 'Mabilisang Link', 'body' => 'Dumiretso sa Vendors, Payments, o Aging Report — ang iba pang Accounts Payable na mga screen.']],
];
if (has_permission('ap.create')) {
    $pageHelp[] = ['selector' => 'a[href="bill-form.php"]',
        'en' => ['title' => '+ New Bill', 'body' => 'Create a new bill against a vendor. Saves as Draft — no accounting effect until an Approver posts it.'],
        'tl' => ['title' => '+ Bagong Bill', 'body' => 'Gumawa ng bagong bill laban sa isang vendor. Mase-save bilang Draft — walang epekto sa accounting hangga\'t hindi ito ini-post ng isang Approver.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'The table', 'body' => 'The Status badge shows "Overdue" if a bill is Open/PartiallyPaid and past its due date. Click View to open a bill\'s detail page.'],
    'tl' => ['title' => 'Ang Talahanayan', 'body' => 'Lalabas na "Overdue" ang Status badge kung Open/PartiallyPaid pa ang bill at lagpas na sa due date. I-click ang View para buksan ang detalye ng bill.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['Draft','Open','PartiallyPaid','Paid','Void'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div>
            <a href="vendors.php" class="btn btn-outline">Vendors</a>
            <a href="payments.php" class="btn btn-outline">Payments</a>
            <a href="aging-report.php" class="btn btn-outline">Aging Report</a>
            <?php if (has_permission('ap.create')): ?><a href="bill-form.php" class="btn btn-primary">+ New Bill</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Bill No.</th><th>Vendor</th><th>Bill Date</th><th>Due Date</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bills as $b): $eff = display_status($b['status'], $b['due_date']); ?>
            <tr>
                <td><?= e($b['bill_no']) ?></td>
                <td><?= e($b['vendor_name']) ?></td>
                <td><?= format_date($b['bill_date']) ?></td>
                <td><?= format_date($b['due_date']) ?></td>
                <td class="num"><?= format_currency($b['total_amount']) ?></td>
                <td class="num"><?= format_currency($b['total_amount'] - $b['amount_paid']) ?></td>
                <td><span class="badge <?= status_badge_class($eff) ?>"><?= e($eff) ?></span></td>
                <td><a href="bill-view.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($bills)): ?><tr><td colspan="8" class="empty-state">No bills found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
