<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('disbursement.view');

$db = get_db();
$status = $_GET['status'] ?? '';
$sql = "SELECT dv.*, u.full_name AS requested_by_name FROM disbursement_vouchers dv JOIN users u ON u.id = dv.requested_by WHERE 1=1";
$params = [];
if ($status !== '') { $sql .= " AND dv.status = ?"; $params[] = $status; }
$sql .= " ORDER BY dv.dv_date DESC, dv.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$vouchers = $stmt->fetchAll();

$pageTitle = 'Disbursement Vouchers';
$pageHelp = [
    ['selector' => 'select[name="status"]',
        'en' => ['title' => 'Status filter', 'body' => 'Narrows the list to Draft, PendingApproval, Approved, Paid, Rejected, or Void vouchers.'],
        'tl' => ['title' => 'Status Filter', 'body' => 'Ipinapakita lamang ang mga voucher na may piniling status: Draft, PendingApproval, Approved, Paid, Rejected, o Void.']],
    ['selector' => 'a[href="approval-queue.php"]',
        'en' => ['title' => 'Approval Queue', 'body' => 'Jumps to just the vouchers currently waiting on an Approver.'],
        'tl' => ['title' => 'Approval Queue', 'body' => 'Dumiretso sa mga voucher na naghihintay pa sa Approver.']],
];
if (has_permission('disbursement.create')) {
    $pageHelp[] = ['selector' => 'a[href="voucher-form.php"]',
        'en' => ['title' => '+ New Voucher', 'body' => 'Submit a payout request (vendor bill settlement, employee cash advance, or ad hoc expense) — goes straight to Pending Approval.'],
        'tl' => ['title' => '+ Bagong Voucher', 'body' => 'Magsumite ng payout request (settlement ng vendor bill, cash advance ng empleyado, o ad hoc na gastos) — direktang mapupunta sa Pending Approval.']];
}
$pageHelp[] = ['selector' => 'table.data-table',
    'en' => ['title' => 'View', 'body' => 'Opens the voucher\'s approval history, and — once Approved — the Mark Paid action.'],
    'tl' => ['title' => 'View', 'body' => 'Binubuksan ang approval history ng voucher, at — kapag na-Approve na — ang Mark Paid action.']];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['Draft','PendingApproval','Approved','Paid','Rejected','Void'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div>
            <a href="approval-queue.php" class="btn btn-outline">Approval Queue</a>
            <?php if (has_permission('disbursement.create')): ?><a href="voucher-form.php" class="btn btn-primary">+ New Voucher</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>DV No.</th><th>Date</th><th>Payee</th><th class="num">Amount</th><th>Requested By</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($vouchers as $v): ?>
            <tr>
                <td><?= e($v['dv_no']) ?></td>
                <td><?= format_date($v['dv_date']) ?></td>
                <td><?= e($v['payee_name']) ?></td>
                <td class="num"><?= format_currency($v['amount']) ?></td>
                <td><?= e($v['requested_by_name']) ?></td>
                <td><span class="badge <?= status_badge_class($v['status']) ?>"><?= e($v['status']) ?></span></td>
                <td><a href="voucher-view.php?id=<?= $v['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($vouchers)): ?><tr><td colspan="7" class="empty-state">No disbursement vouchers found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
