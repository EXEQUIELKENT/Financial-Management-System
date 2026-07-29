<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('ap.view');

$db = get_db();
$payments = $db->query("SELECT p.*, v.name AS vendor_name, c.account_name AS cash_account_name
                         FROM ap_payments p JOIN ap_vendors v ON v.id = p.vendor_id JOIN cash_accounts c ON c.id = p.cash_account_id
                         ORDER BY p.payment_date DESC, p.id DESC LIMIT 200")->fetchAll();

$pageTitle = 'Accounts Payable - Payments';
$pageHelp = [];
if (has_permission('ap.create')) {
    $pageHelp[] = ['selector' => 'a[href="payment-form.php"]',
        'en' => ['title' => '+ New Payment', 'body' => 'Pay one or more of a vendor\'s open bills, in full or partially, from a chosen cash/bank account.'],
        'tl' => ['title' => '+ Bagong Payment', 'body' => 'Bayaran ang isa o higit pang open na bill ng vendor, buo o bahagi, mula sa piniling cash/bank account.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'The table', 'body' => 'Every payment ever recorded, with the vendor, method, cash account used, and amount.'],
    'tl' => ['title' => 'Ang Talahanayan', 'body' => 'Bawat payment na naitala, kasama ang vendor, method, cash account na ginamit, at halaga.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="bills.php" class="btn btn-outline">Bills</a>
            <a href="vendors.php" class="btn btn-outline">Vendors</a>
            <?php if (has_permission('ap.create')): ?><a href="payment-form.php" class="btn btn-primary">+ New Payment</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Payment No.</th><th>Vendor</th><th>Date</th><th>Method</th><th>Cash Account</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e($p['payment_no']) ?></td>
                <td><?= e($p['vendor_name']) ?></td>
                <td><?= format_date($p['payment_date']) ?></td>
                <td><?= e($p['payment_method']) ?></td>
                <td class="text-muted"><?= e($p['cash_account_name']) ?></td>
                <td class="num"><?= format_currency($p['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?><tr><td colspan="6" class="empty-state">No payments recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
