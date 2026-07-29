<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ap.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM ap_vendors WHERE id = ?");
$stmt->execute([$id]);
$vendor = $stmt->fetch();
if (!$vendor) { flash('error', 'Vendor not found.'); redirect('modules/ap/vendors.php'); }

$bills = $db->prepare("SELECT * FROM ap_bills WHERE vendor_id = ? ORDER BY bill_date DESC");
$bills->execute([$id]);
$bills = $bills->fetchAll();

$payments = $db->prepare("SELECT * FROM ap_payments WHERE vendor_id = ? ORDER BY payment_date DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

$totalOutstanding = 0;
foreach ($bills as $b) { if ($b['status'] !== 'Void') $totalOutstanding += ($b['total_amount'] - $b['amount_paid']); }

$pageTitle = 'Vendor: ' . $vendor['name'];
$pageHelp = [
    ['selector' => '.form-row',
        'en' => ['title' => 'Outstanding Balance', 'body' => 'Total of every unpaid/partially-paid bill for this vendor, live.'],
        'tl' => ['title' => 'Outstanding Balance', 'body' => 'Kabuuan ng bawat unpaid/partially-paid na bill ng vendor na ito, live.']],
    ['selector' => 'table.data-table', 'nth' => 0,
        'en' => ['title' => 'Bills table', 'body' => 'Every bill ever recorded against this vendor, with balance and status; click a Bill No. to open it.'],
        'tl' => ['title' => 'Talahanayan ng Bills', 'body' => 'Bawat bill na naitala laban sa vendor na ito, kasama ang balance at status; i-click ang Bill No. para buksan.']],
    ['selector' => 'table.data-table', 'nth' => 1,
        'en' => ['title' => 'Payments table', 'body' => 'Every payment made to this vendor.'],
        'tl' => ['title' => 'Talahanayan ng Payments', 'body' => 'Bawat payment na ginawa sa vendor na ito.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($vendor['name']) ?> <span class="text-muted">(<?= e($vendor['vendor_code']) ?>)</span></h3>
        <div>
            <a href="vendors.php" class="btn btn-outline">Back</a>
            <a href="vendor-form.php?id=<?= $id ?>" class="btn btn-outline">Edit</a>
        </div>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Contact</span><br><?= e($vendor['contact_person'] ?: '—') ?></div>
        <div><span class="text-muted">Email</span><br><?= e($vendor['email'] ?: '—') ?></div>
        <div><span class="text-muted">Phone</span><br><?= e($vendor['phone'] ?: '—') ?></div>
        <div><span class="text-muted">Terms</span><br><?= (int)$vendor['payment_terms_days'] ?> days</div>
        <div><span class="text-muted">Outstanding Balance</span><br><strong><?= format_currency($totalOutstanding) ?></strong></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Bills</h3></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Bill No.</th><th>Date</th><th>Due</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($bills as $b): ?>
            <tr>
                <td><a href="bill-view.php?id=<?= $b['id'] ?>"><?= e($b['bill_no']) ?></a></td>
                <td><?= format_date($b['bill_date']) ?></td>
                <td><?= format_date($b['due_date']) ?></td>
                <td class="num"><?= format_currency($b['total_amount']) ?></td>
                <td class="num"><?= format_currency($b['amount_paid']) ?></td>
                <td class="num"><?= format_currency($b['total_amount'] - $b['amount_paid']) ?></td>
                <td><span class="badge <?= status_badge_class($b['status']) ?>"><?= e($b['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($bills)): ?><tr><td colspan="7" class="empty-state">No bills recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Payments</h3></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Payment No.</th><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e($p['payment_no']) ?></td>
                <td><?= format_date($p['payment_date']) ?></td>
                <td><?= e($p['payment_method']) ?></td>
                <td class="num"><?= format_currency($p['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?><tr><td colspan="4" class="empty-state">No payments recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
