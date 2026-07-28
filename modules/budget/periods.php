<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('budget.view');

$db = get_db();
$periods = $db->query("SELECT * FROM budget_periods ORDER BY start_date DESC")->fetchAll();

$pageTitle = 'Budget Periods';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="budgets.php" class="btn btn-outline">Budgets</a>
            <a href="variance-report.php" class="btn btn-outline">Variance Report</a>
            <?php if (has_permission('budget.create')): ?><a href="period-form.php" class="btn btn-primary">+ New Period</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($periods as $p): ?>
            <tr><td><?= e($p['name']) ?></td><td><?= format_date($p['start_date']) ?></td><td><?= format_date($p['end_date']) ?></td><td><span class="badge <?= status_badge_class($p['status']==='Open'?'Open':'Void') ?>"><?= e($p['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (empty($periods)): ?><tr><td colspan="4" class="empty-state">No budget periods yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
