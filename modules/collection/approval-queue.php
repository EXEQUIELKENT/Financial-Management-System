<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('collection.approve');

$db = get_db();
$receipts = $db->query("SELECT cr.*, u.full_name AS received_by_name FROM collection_receipts cr JOIN users u ON u.id = cr.received_by
                         WHERE cr.status = 'PendingApproval' ORDER BY cr.cr_date")->fetchAll();

$pageTitle = 'Collection Approval Queue';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar"><h3 class="mb-0">Pending Approval (<?= count($receipts) ?>)</h3><a href="receipts.php" class="btn btn-outline">All Receipts</a></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>CR No.</th><th>Date</th><th>Payer</th><th class="num">Amount</th><th>Received By</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr>
                <td><?= e($r['cr_no']) ?></td>
                <td><?= format_date($r['cr_date']) ?></td>
                <td><?= e($r['payer_name']) ?></td>
                <td class="num"><?= format_currency($r['amount']) ?></td>
                <td><?= e($r['received_by_name']) ?></td>
                <td><a href="receipt-view.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">Review</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($receipts)): ?><tr><td colspan="6" class="empty-state">Nothing pending approval.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
