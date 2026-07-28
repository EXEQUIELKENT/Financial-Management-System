<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('collection.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT cr.*, u.full_name AS received_by_name, u2.full_name AS approved_by_name
                       FROM collection_receipts cr JOIN users u ON u.id = cr.received_by
                       LEFT JOIN users u2 ON u2.id = cr.approved_by WHERE cr.id = ?");
$stmt->execute([$id]);
$cr = $stmt->fetch();
if (!$cr) { die('Receipt not found.'); }

$lineStmt = $db->prepare("SELECT l.*, a.account_name FROM collection_receipt_lines l JOIN coa_accounts a ON a.id = l.account_id WHERE l.cr_id = ?");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();
require_once __DIR__ . '/../../includes/functions.php';
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>CR <?= e($cr['cr_no']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/variables.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
</head><body>
<div class="voucher-print">
    <div class="vp-header">
        <div><h2>Official Collection Receipt</h2><div class="text-muted"><?= e(APP_SHORT_NAME) ?></div></div>
        <div style="text-align:right;"><strong><?= e($cr['cr_no']) ?></strong><br><?= format_date($cr['cr_date']) ?></div>
    </div>
    <p><strong>Received from:</strong> <?= e($cr['payer_name']) ?> (<?= e($cr['payer_type']) ?>)</p>
    <p><strong>Particulars:</strong> <?= e($cr['particulars']) ?></p>
    <table>
        <thead><tr><th>Description</th><th>Account</th><th style="text-align:right;">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
            <tr><td><?= e($l['description']) ?></td><td><?= e($l['account_name']) ?></td><td style="text-align:right;"><?= format_currency($l['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="font-weight:bold;"><td colspan="2">Total</td><td style="text-align:right;"><?= format_currency($cr['amount']) ?></td></tr>
        </tbody>
    </table>
    <div class="vp-signatures">
        <div>Received By<br><?= e($cr['received_by_name']) ?></div>
        <div>Approved By<br><?= e($cr['approved_by_name'] ?? '') ?></div>
    </div>
    <p class="no-print" style="margin-top:24px;"><button onclick="window.print()">Print</button></p>
</div>
</body></html>
