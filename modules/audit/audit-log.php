<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('audit.view');

$db = get_db();
$module = $_GET['module'] ?? '';
$sql = "SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE 1=1";
$params = [];
if ($module !== '') { $sql .= " AND a.module = ?"; $params[] = $module; }
$sql .= " ORDER BY a.created_at DESC LIMIT 300";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $db->query("SELECT DISTINCT module FROM audit_log ORDER BY module")->fetchAll();

$pageTitle = 'Audit Log';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <form method="get" class="table-toolbar">
        <select name="module" onchange="this.form.submit()">
            <option value="">All Modules</option>
            <?php foreach ($modules as $m): ?><option value="<?= e($m['module']) ?>" <?= $module===$m['module']?'selected':'' ?>><?= e($m['module']) ?></option><?php endforeach; ?>
        </select>
    </form>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date/Time</th><th>User</th><th>Module</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= format_date($l['created_at'], 'M d, Y g:i A') ?></td>
                <td><?= e($l['full_name'] ?? 'System') ?></td>
                <td class="text-muted"><?= e($l['module']) ?></td>
                <td><?= e($l['action']) ?></td>
                <td class="text-muted">#<?= (int)$l['record_id'] ?></td>
                <td><?= e($l['details']) ?></td>
                <td class="text-muted"><?= e($l['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?><tr><td colspan="7" class="empty-state">No audit activity yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
