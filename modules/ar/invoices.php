<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ar.view');

$db = get_db();
$status = $_GET['status'] ?? '';
$sql = "SELECT i.*, c.name AS customer_name FROM ar_invoices i JOIN ar_customers c ON c.id = i.customer_id WHERE 1=1";
$params = [];
if ($status !== '') { $sql .= " AND i.status = ?"; $params[] = $status; }
$sql .= " ORDER BY i.invoice_date DESC, i.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$pageTitle = 'Accounts Receivable - Invoices';
$pageHelp = [
    ['selector' => 'select[name="status"]',
        'en' => ['title' => 'Status filter', 'body' => 'Narrows the list to just one status: Draft, Open, PartiallyPaid, Paid, or Void.'],
        'tl' => ['title' => 'Status Filter', 'body' => 'Ipinapakita lamang ang mga invoice na may piniling status: Draft, Open, PartiallyPaid, Paid, o Void.']],
];
if (has_permission('ar.create')) {
    $pageHelp[] = ['selector' => 'a[href="invoice-form.php"]',
        'en' => ['title' => '+ New Invoice', 'body' => 'Create a new invoice against a customer. Saves as Draft — no accounting effect until an Approver posts it.'],
        'tl' => ['title' => '+ Bagong Invoice', 'body' => 'Gumawa ng bagong invoice laban sa isang customer. Mase-save bilang Draft — walang epekto sa accounting hangga\'t hindi ito ini-post ng isang Approver.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'The table', 'body' => 'The Status badge shows "Overdue" if an invoice is Open/PartiallyPaid and past its due date. Click View to open an invoice\'s detail page.'],
    'tl' => ['title' => 'Ang Talahanayan', 'body' => 'Lalabas na "Overdue" ang Status badge kung Open/PartiallyPaid pa ang invoice at lagpas na sa due date. I-click ang View para buksan ang detalye ng invoice.']];
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
            <a href="customers.php" class="btn btn-outline">Customers</a>
            <a href="receipts.php" class="btn btn-outline">Receipts</a>
            <a href="aging-report.php" class="btn btn-outline">Aging Report</a>
            <?php if (has_permission('ar.create')): ?><a href="invoice-form.php" class="btn btn-primary">+ New Invoice</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Invoice No.</th><th>Customer</th><th>Invoice Date</th><th>Due Date</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): $eff = display_status($i['status'], $i['due_date']); ?>
            <tr>
                <td><?= e($i['invoice_no']) ?></td>
                <td><?= e($i['customer_name']) ?></td>
                <td><?= format_date($i['invoice_date']) ?></td>
                <td><?= format_date($i['due_date']) ?></td>
                <td class="num"><?= format_currency($i['total_amount']) ?></td>
                <td class="num"><?= format_currency($i['total_amount'] - $i['amount_received']) ?></td>
                <td><span class="badge <?= status_badge_class($eff) ?>"><?= e($eff) ?></span></td>
                <td><a href="invoice-view.php?id=<?= $i['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($invoices)): ?><tr><td colspan="8" class="empty-state">No invoices found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
