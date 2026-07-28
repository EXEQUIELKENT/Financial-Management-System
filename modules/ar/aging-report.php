<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_permission('ar.view');

$db = get_db();
$asOf = $_GET['as_of'] ?? date('Y-m-d');

$stmt = $db->prepare("SELECT i.*, c.name AS customer_name FROM ar_invoices i JOIN ar_customers c ON c.id = i.customer_id
                       WHERE i.status IN ('Open','PartiallyPaid') AND i.invoice_date <= ? ORDER BY c.name, i.due_date");
$stmt->execute([$asOf]);
$invoices = $stmt->fetchAll();

$buckets = ['Current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$byCustomer = [];
foreach ($invoices as $i) {
    $balance = $i['total_amount'] - $i['amount_received'];
    $bucket = aging_bucket($i['due_date'], $asOf);
    $buckets[$bucket] += $balance;
    $byCustomer[$i['customer_name']][$bucket] = ($byCustomer[$i['customer_name']][$bucket] ?? 0) + $balance;
    $byCustomer[$i['customer_name']]['total'] = ($byCustomer[$i['customer_name']]['total'] ?? 0) + $balance;
}
$grandTotal = array_sum($buckets);

if (isset($_GET['export'])) {
    $rows = [];
    foreach ($byCustomer as $customer => $row) {
        $rows[] = [$customer, $row['Current'] ?? 0, $row['1-30'] ?? 0, $row['31-60'] ?? 0, $row['61-90'] ?? 0, $row['90+'] ?? 0, $row['total'] ?? 0];
    }
    export_to_csv('ar-aging-' . $asOf . '.csv', ['Customer','Current','1-30','31-60','61-90','90+','Total'], $rows);
}

$pageTitle = 'AR Aging Report';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="table-toolbar">
        <div class="form-group" style="margin:0;"><label>As Of</label><input type="date" name="as_of" value="<?= e($asOf) ?>" onchange="this.form.submit()"></div>
        <a href="aging-report.php?as_of=<?= e($asOf) ?>&export=1" class="btn btn-outline">Export CSV</a>
    </form>
</div>
<div class="kpi-grid">
    <?php foreach ($buckets as $label => $amt): ?>
    <div class="kpi-card <?= $label === '90+' ? 'danger' : ($label === '61-90' ? 'warning' : 'primary') ?>">
        <div class="kpi-label"><?= e($label) ?></div>
        <div class="kpi-value"><?= format_currency($amt) ?></div>
        <div class="kpi-sub"><?= $grandTotal > 0 ? round($amt / $grandTotal * 100, 1) : 0 ?>% of total</div>
    </div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Customer</th><th class="num">Current</th><th class="num">1-30</th><th class="num">31-60</th><th class="num">61-90</th><th class="num">90+</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($byCustomer as $customer => $row): ?>
            <tr>
                <td><?= e($customer) ?></td>
                <td class="num"><?= format_currency($row['Current'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['1-30'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['31-60'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['61-90'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['90+'] ?? 0) ?></td>
                <td class="num" style="font-weight:600;"><?= format_currency($row['total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($byCustomer)): ?><tr><td colspan="7" class="empty-state">No outstanding invoices.</td></tr><?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:600;"><td>Grand Total</td><td class="num"><?= format_currency($buckets['Current']) ?></td><td class="num"><?= format_currency($buckets['1-30']) ?></td><td class="num"><?= format_currency($buckets['31-60']) ?></td><td class="num"><?= format_currency($buckets['61-90']) ?></td><td class="num"><?= format_currency($buckets['90+']) ?></td><td class="num"><?= format_currency($grandTotal) ?></td></tr>
        </tfoot>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
