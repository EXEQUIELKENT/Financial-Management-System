<?php
require_once __DIR__ . '/../config/config.php';

function format_currency($amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function format_date($date, string $fmt = 'M d, Y'): string {
    if (empty($date)) return '';
    $ts = is_numeric($date) ? (int)$date : strtotime($date);
    return $ts ? date($fmt, $ts) : '';
}

/**
 * Appends a cache-busting ?v=<file mtime> to a static asset URL, so browsers
 * pick up edited CSS/JS immediately instead of serving a stale cached copy
 * (which is otherwise very easy to hit during active development, since none
 * of these asset tags had any versioning before).
 */
function asset_url(string $relativePath): string {
    $diskPath = __DIR__ . '/../' . ltrim($relativePath, '/');
    $v = file_exists($diskPath) ? filemtime($diskPath) : time();
    return rtrim(BASE_URL, '/') . '/' . ltrim($relativePath, '/') . '?v=' . $v;
}

function redirect(string $path): void {
    $url = str_starts_with($path, 'http') ? $path : rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function flash(string $type = null, string $message = null) {
    if ($type !== null && $message !== null) {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
        return null;
    }
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function old(string $key, $default = '') {
    return e($_POST[$key] ?? $default);
}

function export_to_csv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function get_setting(string $key, $default = null) {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = get_db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    $cache[$key] = $val !== false ? $val : $default;
    return $cache[$key];
}

function set_setting(string $key, string $value): void {
    $stmt = get_db()->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

function current_page_url(): string {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    return $path;
}

function aging_bucket(string $dueDate, ?string $asOf = null): string {
    $asOfTs = $asOf ? strtotime($asOf) : time();
    $dueTs = strtotime($dueDate);
    $daysPastDue = (int)floor(($asOfTs - $dueTs) / 86400);
    if ($daysPastDue <= 0) return 'Current';
    if ($daysPastDue <= 30) return '1-30';
    if ($daysPastDue <= 60) return '31-60';
    if ($daysPastDue <= 90) return '61-90';
    return '90+';
}

function display_status(string $status, ?string $dueDate = null): string {
    if (in_array($status, ['Open', 'PartiallyPaid'], true) && $dueDate && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        return 'Overdue';
    }
    return $status;
}

/**
 * Renders a horizontal "you are here" progress stepper for a document's
 * lifecycle (Draft -> Approved -> Paid, etc). $labels is the ordered list of
 * stage names; $currentIndex is which one the record is currently at; pass
 * $stoppedLabel (e.g. "Voided", "Rejected") when the normal flow was
 * interrupted, which replaces the stepper with a single stopped-state pill.
 */
function render_status_stepper(array $labels, int $currentIndex, ?string $stoppedLabel = null): string {
    if ($stoppedLabel !== null) {
        return '<div class="status-stepper-stopped">' . e($stoppedLabel) . '</div>';
    }
    $lastIndex = count($labels) - 1;
    $html = '<div class="status-stepper">';
    foreach ($labels as $i => $label) {
        // The final stage is shown as complete (checkmark) rather than "active" once
        // reached -- there's nothing left to progress toward, so it should read as done.
        $isDone = $i < $currentIndex || ($i === $currentIndex && $currentIndex === $lastIndex);
        $state = $isDone ? 'done' : ($i === $currentIndex ? 'active' : 'upcoming');
        $icon = $isDone ? '&#10003;' : (string)($i + 1);
        $html .= '<div class="status-step ' . $state . '">'
               . '<span class="status-step-dot">' . $icon . '</span>'
               . '<span class="status-step-label">' . e($label) . '</span>'
               . '</div>';
    }
    $html .= '</div>';
    return $html;
}

function status_badge_class(string $status): string {
    $map = [
        'Draft' => 'badge-draft',
        'Open' => 'badge-open',
        'PartiallyPaid' => 'badge-partial',
        'Paid' => 'badge-success',
        'Overdue' => 'badge-danger',
        'Void' => 'badge-void',
        'PendingApproval' => 'badge-pending',
        'Approved' => 'badge-success',
        'Rejected' => 'badge-danger',
        'Posted' => 'badge-success',
        'Deposited' => 'badge-success',
        'Remitted' => 'badge-success',
        'Pending' => 'badge-pending',
        'Active' => 'badge-success',
        'Inactive' => 'badge-void',
    ];
    return $map[$status] ?? 'badge-draft';
}
