<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('budget.view');

$db = get_db();
$budgets = $db->query("SELECT b.*, bp.name AS period_name, d.name AS department_name, u.full_name AS created_by_name
                        FROM budgets b JOIN budget_periods bp ON bp.id = b.budget_period_id
                        LEFT JOIN departments d ON d.id = b.department_id
                        JOIN users u ON u.id = b.created_by ORDER BY b.id DESC")->fetchAll();

$pageTitle = 'Budgets';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <div></div>
        <div>
            <a href="periods.php" class="btn btn-outline">Budget Periods</a>
            <a href="variance-report.php" class="btn btn-outline">Variance Report</a>
            <?php if (has_permission('budget.create')): ?><a href="budget-form.php" class="btn btn-primary">+ New Budget</a><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Period</th><th>Department</th><th>Created By</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($budgets as $b): ?>
            <tr>
                <td><?= e($b['name']) ?></td>
                <td><?= e($b['period_name']) ?></td>
                <td class="text-muted"><?= e($b['department_name'] ?? 'All Departments') ?></td>
                <td><?= e($b['created_by_name']) ?></td>
                <td><span class="badge <?= status_badge_class($b['status'] === 'Approved' ? 'Paid' : 'Draft') ?>"><?= e($b['status']) ?></span></td>
                <td><a href="budget-form.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($budgets)): ?><tr><td colspan="6" class="empty-state">No budgets created yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
