<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('budget.create');

$db = get_db();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    if ($name === '' || !$start || !$end) $errors[] = 'All fields are required.';
    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO budget_periods (name, start_date, end_date, status) VALUES (?,?,?,'Open')");
        $stmt->execute([$name, $start, $end]);
        log_audit('create', 'budget', (int)$db->lastInsertId(), 'Created budget period ' . $name);
        flash('success', 'Budget period created.');
        redirect('modules/budget/periods.php');
    }
}
$pageTitle = 'New Budget Period';
$pageHelp = [
    ['selector' => 'input[name="name"]',
        'en' => ['title' => 'Name', 'body' => 'A label like "FY2026" — used everywhere else in the system to identify this period.'],
        'tl' => ['title' => 'Name', 'body' => 'Isang label tulad ng "FY2026" — ginagamit sa buong sistema para kilalanin ang period na ito.']],
    ['selector' => 'input[name="start_date"]',
        'en' => ['title' => 'Start / End Date', 'body' => 'The period\'s date range; budgets created inside it use months 1-12 relative to this range.'],
        'tl' => ['title' => 'Start / End Date', 'body' => 'Ang date range ng period; ang mga budget na gagawin dito ay gagamit ng buwan 1-12 batay sa range na ito.']],
];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card" style="max-width:520px;">
    <?php foreach ($errors as $err): ?><div class="alert alert-critical"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" placeholder="e.g. FY2026" required></div>
        <div class="form-row">
            <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?= date('Y-01-01') ?>" required></div>
            <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?= date('Y-12-31') ?>" required></div>
        </div>
        <button type="submit" class="btn btn-primary">Save Period</button>
        <a href="periods.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
