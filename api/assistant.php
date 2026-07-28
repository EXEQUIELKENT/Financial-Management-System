<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/Assistant.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_permission('assistant.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

verify_csrf();
$question = trim($_POST['question'] ?? '');
if ($question === '') {
    echo json_encode(['error' => 'Empty question']);
    exit;
}

$result = assistant_ask($question, current_user()['id']);
echo json_encode($result);
