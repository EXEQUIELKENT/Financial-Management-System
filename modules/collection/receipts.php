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
$pageHelp = [
    ['selector' => 'select[name="status"]',
        'en' => ['title' => 'Status filter', 'body' => 'Narrows the list to Draft, PendingApproval, Approved, Deposited, Rejected, or Void receipts.'],
        'tl' => ['title' => 'Status Filter', 'body' => 'Ipinapakita lamang ang mga receipt na may piniling status: Draft, PendingApproval, Approved, Deposited, Rejected, o Void.']],
    ['selector' => 'a[href="approval-queue.php"]',
        'en' => ['title' => 'Approval Queue', 'body' => 'Jumps to just the receipts currently waiting on an Approver.'],
        'tl' => ['title' => 'Approval Queue', 'body' => 'Dumiretso sa mga receipt na naghihintay pa sa Approver.']],
];
if (has_permission('collection.create')) {
    $pageHelp[] = ['selector' => 'a[href="receipt-form.php"]',
        'en' => ['title' => '+ New Collection Receipt', 'body' => 'Record money collected (customer payment or misc. income) — goes straight to Pending Approval.'],
        'tl' => ['title' => '+ Bagong Collection Receipt', 'body' => 'Itala ang perang nakolekta (bayad ng customer o miscellaneous income) — direktang mapupunta sa Pending Approval.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'View', 'body' => 'Opens the receipt\'s approval history, and — once Approved — the Mark Deposited action.'],
    'tl' => ['title' => 'View', 'body' => 'Binubuksan ang approval history ng receipt, at — kapag na-Approve na — ang Mark Deposited action.']];
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
