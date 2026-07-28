<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('disbursement.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT dv.*, u.full_name AS requested_by_name, u2.full_name AS approved_by_name
                       FROM disbursement_vouchers dv JOIN users u ON u.id = dv.requested_by
                       LEFT JOIN users u2 ON u2.id = dv.approved_by WHERE dv.id = ?");
$stmt->execute([$id]);
$dv = $stmt->fetch();
if (!$dv) { die('Voucher not found.'); }

$lineStmt = $db->prepare("SELECT l.*, a.account_name FROM disbursement_voucher_lines l JOIN coa_accounts a ON a.id = l.account_id WHERE l.dv_id = ?");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();
require_once __DIR__ . '/../../includes/functions.php';
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>DV <?= e($dv['dv_no']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/variables.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
</head><body>
<div class="voucher-print">
    <div class="vp-header">
        <div><h2>Disbursement Voucher</h2><div class="text-muted"><?= e(APP_SHORT_NAME) ?></div></div>
        <div style="text-align:right;"><strong><?= e($dv['dv_no']) ?></strong><br><?= format_date($dv['dv_date']) ?></div>
    </div>
    <p><strong>Pay to:</strong> <?= e($dv['payee_name']) ?> (<?= e($dv['payee_type']) ?>)</p>
    <p><strong>Particulars:</strong> <?= e($dv['particulars']) ?></p>
    <table>
        <thead><tr><th>Description</th><th>Account</th><th style="text-align:right;">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
            <tr><td><?= e($l['description']) ?></td><td><?= e($l['account_name']) ?></td><td style="text-align:right;"><?= format_currency($l['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="font-weight:bold;"><td colspan="2">Total</td><td style="text-align:right;"><?= format_currency($dv['amount']) ?></td></tr>
        </tbody>
    </table>
    <div class="vp-signatures">
        <div>Requested By<br><?= e($dv['requested_by_name']) ?></div>
        <div>Approved By<br><?= e($dv['approved_by_name'] ?? '') ?></div>
    </div>
    <p class="no-print" style="margin-top:24px;"><button onclick="window.print()">Print</button></p>
</div>
</body></html>
