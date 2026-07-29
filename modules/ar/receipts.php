<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ar.view');

$db = get_db();
$receipts = $db->query("SELECT r.*, c.name AS customer_name, ca.account_name AS cash_account_name
                         FROM ar_receipts r JOIN ar_customers c ON c.id = r.customer_id JOIN cash_accounts ca ON ca.id = r.cash_account_id
                         ORDER BY r.receipt_date DESC, r.id DESC LIMIT 200")->fetchAll();

$pageTitle = 'Accounts Receivable - Receipts';
$pageHelp = [];
if (has_permission('ar.create')) {
    $pageHelp[] = ['selector' => 'a[href="receipt-form.php"]',
        'en' => ['title' => '+ New Receipt', 'body' => 'Record a payment from a customer, applied against one or more of their open invoices, deposited into a chosen cash/bank account.'],
        'tl' => ['title' => '+ Bagong Receipt', 'body' => 'Itala ang bayad mula sa customer, apply laban sa isa o higit pa nilang open na invoice, ideposito sa piniling cash/bank account.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'The table', 'body' => 'Every receipt ever recorded, with the customer, method, cash account used, and amount.'],
    'tl' => ['title' => 'Ang Talahanayan', 'body' => 'Bawat receipt na naitala, kasama ang customer, method, cash account na ginamit, at halaga.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="invoices.php" class="btn btn-outline">Invoices</a>
            <a href="customers.php" class="btn btn-outline">Customers</a>
            <?php if (has_permission('ar.create')): ?><a href="receipt-form.php" class="btn btn-primary">+ New Receipt</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Receipt No.</th><th>Customer</th><th>Date</th><th>Method</th><th>Cash Account</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr>
                <td><?= e($r['receipt_no']) ?></td>
                <td><?= e($r['customer_name']) ?></td>
                <td><?= format_date($r['receipt_date']) ?></td>
                <td><?= e($r['payment_method']) ?></td>
                <td class="text-muted"><?= e($r['cash_account_name']) ?></td>
                <td class="num"><?= format_currency($r['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($receipts)): ?><tr><td colspan="6" class="empty-state">No receipts recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
