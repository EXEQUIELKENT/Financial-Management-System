<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_permission('ap.view');

$db = get_db();
$asOf = $_GET['as_of'] ?? date('Y-m-d');

$stmt = $db->prepare("SELECT b.*, v.name AS vendor_name FROM ap_bills b JOIN ap_vendors v ON v.id = b.vendor_id
                       WHERE b.status IN ('Open','PartiallyPaid') AND b.bill_date <= ? ORDER BY v.name, b.due_date");
$stmt->execute([$asOf]);
$bills = $stmt->fetchAll();

$buckets = ['Current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$byVendor = [];
foreach ($bills as $b) {
    $balance = $b['total_amount'] - $b['amount_paid'];
    $bucket = aging_bucket($b['due_date'], $asOf);
    $buckets[$bucket] += $balance;
    $byVendor[$b['vendor_name']]['Current'] = ($byVendor[$b['vendor_name']]['Current'] ?? 0);
    $byVendor[$b['vendor_name']][$bucket] = ($byVendor[$b['vendor_name']][$bucket] ?? 0) + $balance;
    $byVendor[$b['vendor_name']]['total'] = ($byVendor[$b['vendor_name']]['total'] ?? 0) + $balance;
}
$grandTotal = array_sum($buckets);

if (isset($_GET['export'])) {
    $rows = [];
    foreach ($byVendor as $vendor => $row) {
        $rows[] = [$vendor, $row['Current'] ?? 0, $row['1-30'] ?? 0, $row['31-60'] ?? 0, $row['61-90'] ?? 0, $row['90+'] ?? 0, $row['total'] ?? 0];
    }
    export_to_csv('ap-aging-' . $asOf . '.csv', ['Vendor','Current','1-30','31-60','61-90','90+','Total'], $rows);
}

$pageTitle = 'AP Aging Report';
$pageHelp = [
    ['selector' => 'input[name="as_of"]',
        'en' => ['title' => 'As Of date', 'body' => 'Recalculates every bucket as if today were this date.'],
        'tl' => ['title' => 'As Of Date', 'body' => 'Kinakalkula ulit ang bawat bucket na parang ngayon ang petsang ito.']],
    ['selector' => '.kpi-grid',
        'en' => ['title' => 'Bucket cards', 'body' => 'Current, 1-30, 31-60, 61-90, and 90+ days past due — how much of your total payables falls into each.'],
        'tl' => ['title' => 'Mga Bucket Card', 'body' => 'Current, 1-30, 31-60, 61-90, at 90+ araw na overdue — magkano sa iyong total payables ang napupunta sa bawat isa.']],
    ['selector' => 'a[href*="export=1"]',
        'en' => ['title' => 'Export CSV', 'body' => 'Downloads the vendor-by-vendor breakdown shown below.'],
        'tl' => ['title' => 'Export CSV', 'body' => 'Ida-download ang breakdown kada-vendor na nasa ibaba.']],
];
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
        <thead><tr><th>Vendor</th><th class="num">Current</th><th class="num">1-30</th><th class="num">31-60</th><th class="num">61-90</th><th class="num">90+</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($byVendor as $vendor => $row): ?>
            <tr>
                <td><?= e($vendor) ?></td>
                <td class="num"><?= format_currency($row['Current'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['1-30'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['31-60'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['61-90'] ?? 0) ?></td>
                <td class="num"><?= format_currency($row['90+'] ?? 0) ?></td>
                <td class="num" style="font-weight:600;"><?= format_currency($row['total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($byVendor)): ?><tr><td colspan="7" class="empty-state">No outstanding bills.</td></tr><?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:600;"><td>Grand Total</td><td class="num"><?= format_currency($buckets['Current']) ?></td><td class="num"><?= format_currency($buckets['1-30']) ?></td><td class="num"><?= format_currency($buckets['31-60']) ?></td><td class="num"><?= format_currency($buckets['61-90']) ?></td><td class="num"><?= format_currency($buckets['90+']) ?></td><td class="num"><?= format_currency($grandTotal) ?></td></tr>
        </tfoot>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
