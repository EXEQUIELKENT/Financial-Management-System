<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ar.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM ar_customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) { flash('error', 'Customer not found.'); redirect('modules/ar/customers.php'); }

$invoices = $db->prepare("SELECT * FROM ar_invoices WHERE customer_id = ? ORDER BY invoice_date DESC");
$invoices->execute([$id]);
$invoices = $invoices->fetchAll();

$receipts = $db->prepare("SELECT * FROM ar_receipts WHERE customer_id = ? ORDER BY receipt_date DESC");
$receipts->execute([$id]);
$receipts = $receipts->fetchAll();

$totalOutstanding = 0;
foreach ($invoices as $i) { if ($i['status'] !== 'Void') $totalOutstanding += ($i['total_amount'] - $i['amount_received']); }

$pageTitle = 'Customer: ' . $customer['name'];
$pageHelp = [
    ['selector' => '.form-row',
        'en' => ['title' => 'Outstanding Balance', 'body' => 'Total of every unpaid/partially-paid invoice for this customer, live.'],
        'tl' => ['title' => 'Outstanding Balance', 'body' => 'Kabuuan ng bawat unpaid/partially-paid na invoice ng customer na ito, live.']],
    ['selector' => 'table.data-table', 'nth' => 0,
        'en' => ['title' => 'Invoices table', 'body' => 'Every invoice ever billed to this customer, with balance and status; click an Invoice No. to open it.'],
        'tl' => ['title' => 'Talahanayan ng Invoices', 'body' => 'Bawat invoice na na-bill sa customer na ito, kasama ang balance at status; i-click ang Invoice No. para buksan.']],
    ['selector' => 'table.data-table', 'nth' => 1,
        'en' => ['title' => 'Receipts table', 'body' => 'Every payment received from this customer.'],
        'tl' => ['title' => 'Talahanayan ng Receipts', 'body' => 'Bawat payment na natanggap mula sa customer na ito.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($customer['name']) ?> <span class="text-muted">(<?= e($customer['customer_code']) ?>)</span></h3>
        <div>
            <a href="customers.php" class="btn btn-outline">Back</a>
            <a href="customer-form.php?id=<?= $id ?>" class="btn btn-outline">Edit</a>
        </div>
    </div>
    <div class="form-row">
        <div><span class="text-muted">Contact</span><br><?= e($customer['contact_person'] ?: '—') ?></div>
        <div><span class="text-muted">Email</span><br><?= e($customer['email'] ?: '—') ?></div>
        <div><span class="text-muted">Phone</span><br><?= e($customer['phone'] ?: '—') ?></div>
        <div><span class="text-muted">Terms</span><br><?= (int)$customer['credit_terms_days'] ?> days</div>
        <div><span class="text-muted">Outstanding Balance</span><br><strong><?= format_currency($totalOutstanding) ?></strong></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Invoices</h3></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Invoice No.</th><th>Date</th><th>Due</th><th class="num">Total</th><th class="num">Received</th><th class="num">Balance</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): ?>
            <tr>
                <td><a href="invoice-view.php?id=<?= $i['id'] ?>"><?= e($i['invoice_no']) ?></a></td>
                <td><?= format_date($i['invoice_date']) ?></td>
                <td><?= format_date($i['due_date']) ?></td>
                <td class="num"><?= format_currency($i['total_amount']) ?></td>
                <td class="num"><?= format_currency($i['amount_received']) ?></td>
                <td class="num"><?= format_currency($i['total_amount'] - $i['amount_received']) ?></td>
                <td><span class="badge <?= status_badge_class($i['status']) ?>"><?= e($i['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($invoices)): ?><tr><td colspan="7" class="empty-state">No invoices recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Receipts</h3></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Receipt No.</th><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr>
                <td><?= e($r['receipt_no']) ?></td>
                <td><?= format_date($r['receipt_date']) ?></td>
                <td><?= e($r['payment_method']) ?></td>
                <td class="num"><?= format_currency($r['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($receipts)): ?><tr><td colspan="4" class="empty-state">No receipts recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
