<?php
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/functions.php';
$db = get_db();

$totalCash = (float)$db->query("SELECT COALESCE(SUM(current_balance),0) FROM cash_accounts WHERE status='Active'")->fetchColumn();
$arTotal = (float)$db->query("SELECT COALESCE(SUM(total_amount - amount_received),0) FROM ar_invoices WHERE status IN ('Open','PartiallyPaid')")->fetchColumn();
$apTotal = (float)$db->query("SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM ap_bills WHERE status IN ('Open','PartiallyPaid')")->fetchColumn();

var_dump($totalCash, format_currency($totalCash));
var_dump($arTotal, format_currency($arTotal));
var_dump($apTotal, format_currency($apTotal));

require_once __DIR__ . '/../includes/Ledger.php';
// Simulate a fresh bill posting like bill-view.php does
$bill = $db->query("SELECT * FROM ap_bills WHERE id = 9")->fetch();
var_dump($bill['total_amount'], format_currency($bill['total_amount']));
