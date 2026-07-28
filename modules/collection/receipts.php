<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('collection.view');

$db = get_db();
$status = $_GET['status'] ?? '';
$sql = "SELECT cr.*, u.full_name AS received_by_name FROM collection_receipts cr JOIN users u ON u.id = cr.received_by WHERE 1=1";
$params = [];
if ($status !== '') { $sql .= " AND cr.status = ?"; $params[] = $status; }
$sql .= " ORDER BY cr.cr_date DESC, cr.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

$pageTitle = 'Collection Receipts';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['Draft','PendingApproval','Approved','Deposited','Rejected','Void'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div>
            <a href="approval-queue.php" class="btn btn-outline">Approval Queue</a>
            <?php if (has_permission('collection.create')): ?><a href="receipt-form.php" class="btn btn-primary">+ New Collection Receipt</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>CR No.</th><th>Date</th><th>Payer</th><th class="num">Amount</th><th>Received By</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr>
                <td><?= e($r['cr_no']) ?></td>
                <td><?= format_date($r['cr_date']) ?></td>
                <td><?= e($r['payer_name']) ?></td>
                <td class="num"><?= format_currency($r['amount']) ?></td>
                <td><?= e($r['received_by_name']) ?></td>
                <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                <td><a href="receipt-view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($receipts)): ?><tr><td colspan="7" class="empty-state">No collection receipts found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
