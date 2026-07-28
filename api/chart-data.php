<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/Forecast.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_permission('dashboard.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$metric = $_GET['metric'] ?? 'revenue';
if (!in_array($metric, ['revenue', 'expense', 'cash_flow'], true)) $metric = 'revenue';

$forecast = build_forecast($metric, 12, 3);
echo json_encode($forecast);
