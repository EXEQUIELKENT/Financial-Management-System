<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('disbursement.approve');

$db = get_db();
$vouchers = $db->query("SELECT dv.*, u.full_name AS requested_by_name FROM disbursement_vouchers dv JOIN users u ON u.id = dv.requested_by
                         WHERE dv.status = 'PendingApproval' ORDER BY dv.dv_date")->fetchAll();

$pageTitle = 'Disbursement Approval Queue';
$pageHelp = [
    ['selector' => 'table.data-table',
        'en' => ['title' => 'This list', 'body' => 'Only vouchers currently in PendingApproval status — everything here needs your decision. Review opens it so you can Approve or Reject with an optional comment.'],
        'tl' => ['title' => 'Ang Listahang Ito', 'body' => 'Mga voucher lamang na PendingApproval — lahat dito ay kailangan ng iyong desisyon. Binubuksan ng Review para i-Approve o i-Reject na may opsyonal na komento.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar"><h3 class="mb-0">Pending Approval (<?= count($vouchers) ?>)</h3><a href="vouchers.php" class="btn btn-outline">All Vouchers</a></div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>DV No.</th><th>Date</th><th>Payee</th><th class="num">Amount</th><th>Requested By</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($vouchers as $v): ?>
            <tr>
                <td><?= e($v['dv_no']) ?></td>
                <td><?= format_date($v['dv_date']) ?></td>
                <td><?= e($v['payee_name']) ?></td>
                <td class="num"><?= format_currency($v['amount']) ?></td>
                <td><?= e($v['requested_by_name']) ?></td>
                <td><a href="voucher-view.php?id=<?= $v['id'] ?>" class="btn btn-primary btn-sm">Review</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($vouchers)): ?><tr><td colspan="6" class="empty-state">Nothing pending approval.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
