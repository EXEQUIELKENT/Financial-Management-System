<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.view');

$pageTitle = 'Financial Reports & Analytics';
include __DIR__ . '/../../includes/header.php';

$reports = [
    ['Trial Balance', 'All accounts with debit/credit movement as of a date. Must balance.', 'trial-balance.php'],
    ['Income Statement', 'Revenue less Expenses for a date range, optionally by department.', 'income-statement.php'],
    ['Balance Sheet', 'Assets, Liabilities and Equity as of a date.', 'balance-sheet.php'],
    ['Cash Flow Statement', 'Operating, Investing and Financing cash movement for a date range.', 'cash-flow.php'],
    ['Custom / GL Detail Report', 'Pick specific accounts and a date range for a detailed transaction listing.', 'custom-report.php'],
];
?>
<div class="kpi-grid">
<?php foreach ($reports as [$title, $desc, $link]): ?>
    <div class="card" style="margin-bottom:0;">
        <h3><?= e($title) ?></h3>
        <p class="text-muted"><?= e($desc) ?></p>
        <a href="<?= $link ?>" class="btn btn-primary btn-sm">Open Report</a>
    </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
