<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('budget.create');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? 'create_header';

    if ($action === 'create_header') {
        $periodId = (int)$_POST['budget_period_id'];
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $stmt = $db->prepare("INSERT INTO budgets (budget_period_id, department_id, name, status, created_by) VALUES (?,?,?,'Draft',?)");
        $stmt->execute([$periodId, $departmentId, $name, current_user()['id']]);
        $id = (int)$db->lastInsertId();
        log_audit('create', 'budget', $id, 'Created budget ' . $name);
        flash('success', 'Budget created. Add account lines below.');
        redirect('modules/budget/budget-form.php?id=' . $id);
    } elseif ($action === 'add_line') {
        $accountId = (int)$_POST['account_id'];
        $stmt = $db->prepare("INSERT INTO budget_lines (budget_id, account_id) VALUES (?,?)");
        $stmt->execute([$id, $accountId]);
        $lineId = (int)$db->lastInsertId();
        $monthStmt = $db->prepare("INSERT INTO budget_line_monthly (budget_line_id, month, budgeted_amount) VALUES (?,?,0)");
        for ($m = 1; $m <= 12; $m++) $monthStmt->execute([$lineId, $m]);
        flash('success', 'Account line added.');
        redirect('modules/budget/budget-form.php?id=' . $id);
    } elseif ($action === 'save_amounts') {
        $amounts = $_POST['amount'] ?? []; // [line_id][month] = amount
        $stmt = $db->prepare("UPDATE budget_line_monthly SET budgeted_amount = ? WHERE budget_line_id = ? AND month = ?");
        foreach ($amounts as $lineId => $monthVals) {
            foreach ($monthVals as $month => $amt) {
                $stmt->execute([(float)$amt, (int)$lineId, (int)$month]);
            }
        }
        log_audit('update', 'budget', $id, 'Updated budget monthly amounts');
        flash('success', 'Budget amounts saved.');
        redirect('modules/budget/budget-form.php?id=' . $id);
    } elseif ($action === 'approve') {
        require_permission('budget.approve');
        $db->prepare("UPDATE budgets SET status='Approved', approved_by=? WHERE id=?")->execute([current_user()['id'], $id]);
        log_audit('approve', 'budget', $id, 'Approved budget');
        flash('success', 'Budget approved.');
        redirect('modules/budget/budget-form.php?id=' . $id);
    } elseif ($action === 'delete_line') {
        $lineId = (int)$_POST['line_id'];
        $db->prepare("DELETE FROM budget_lines WHERE id = ? AND budget_id = ?")->execute([$lineId, $id]);
        flash('success', 'Line removed.');
        redirect('modules/budget/budget-form.php?id=' . $id);
    }
}

if (!$id) {
    $periods = $db->query("SELECT * FROM budget_periods WHERE status='Open' ORDER BY start_date DESC")->fetchAll();
    $departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
    $pageTitle = 'New Budget';
    include __DIR__ . '/../../includes/header.php';
    ?>
    <div class="card" style="max-width:520px;">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="create_header">
            <div class="form-group"><label>Budget Period</label>
                <select name="budget_period_id" required>
                    <?php foreach ($periods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Department (optional)</label>
                <select name="department_id"><option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Budget Name</label><input type="text" name="name" class="form-control" placeholder="e.g. Operating Budget" required></div>
            <button type="submit" class="btn btn-primary">Create Budget</button>
            <a href="budgets.php" class="btn btn-outline">Cancel</a>
        </form>
    </div>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    <?php
    exit;
}

$stmt = $db->prepare("SELECT b.*, bp.name AS period_name FROM budgets b JOIN budget_periods bp ON bp.id = b.budget_period_id WHERE b.id = ?");
$stmt->execute([$id]);
$budget = $stmt->fetch();
if (!$budget) { flash('error', 'Budget not found.'); redirect('modules/budget/budgets.php'); }

$lineStmt = $db->prepare("SELECT bl.*, a.account_code, a.account_name FROM budget_lines bl JOIN coa_accounts a ON a.id = bl.account_id WHERE bl.budget_id = ? ORDER BY a.account_code");
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();

$monthlyByLine = [];
if (!empty($lines)) {
    $lineIds = array_column($lines, 'id');
    $in = implode(',', array_fill(0, count($lineIds), '?'));
    $mStmt = $db->prepare("SELECT * FROM budget_line_monthly WHERE budget_line_id IN ($in)");
    $mStmt->execute($lineIds);
    foreach ($mStmt->fetchAll() as $m) {
        $monthlyByLine[$m['budget_line_id']][$m['month']] = $m['budgeted_amount'];
    }
}

$existingAccountIds = array_column($lines, 'account_id');
$accounts = $db->query("SELECT id, account_code, account_name FROM coa_accounts WHERE is_active=1 AND account_type IN ('Expense','Revenue') ORDER BY account_code")->fetchAll();

$pageTitle = 'Budget: ' . $budget['name'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($budget['name']) ?> <span class="badge <?= status_badge_class($budget['status']==='Approved'?'Paid':'Draft') ?>"><?= e($budget['status']) ?></span></h3>
        <div>
            <?php if ($budget['status'] === 'Draft' && has_permission('budget.approve')): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="form_action" value="approve">
                    <button type="submit" class="btn btn-accent" data-confirm="Approve this budget?">Approve</button></form>
            <?php endif; ?>
            <a href="budgets.php" class="btn btn-outline">Back</a>
        </div>
    </div>
    <p class="text-muted">Period: <?= e($budget['period_name']) ?></p>

    <?php if ($budget['status'] === 'Draft'): ?>
    <form method="post" style="margin-bottom:20px;display:flex;gap:8px;align-items:flex-end;">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="form_action" value="add_line">
        <div class="form-group" style="margin:0;min-width:280px;">
            <label>Add Account Line</label>
            <select name="account_id" required>
                <option value="">— Select account —</option>
                <?php foreach ($accounts as $a): if (in_array($a['id'], $existingAccountIds)) continue; ?>
                    <option value="<?= $a['id'] ?>"><?= e($a['account_code'].' - '.$a['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">+ Add</button>
    </form>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="form_action" value="save_amounts">
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Account</th><?php foreach ($months as $m): ?><th class="num"><?= $m ?></th><?php endforeach; ?><th class="num">Total</th><?php if ($budget['status']==='Draft'): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($lines as $l): $total = array_sum($monthlyByLine[$l['id']] ?? []); ?>
                <tr>
                    <td><?= e($l['account_code'].' - '.$l['account_name']) ?></td>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <td><input type="number" step="0.01" name="amount[<?= $l['id'] ?>][<?= $m ?>]" class="form-control" style="min-width:80px;" value="<?= e((string)($monthlyByLine[$l['id']][$m] ?? 0)) ?>" <?= $budget['status'] !== 'Draft' ? 'readonly' : '' ?>></td>
                    <?php endfor; ?>
                    <td class="num" style="font-weight:600;"><?= number_format($total, 2) ?></td>
                    <?php if ($budget['status'] === 'Draft'): ?>
                    <td>
                        <button form="deleteLineForm<?= $l['id'] ?>" type="submit" class="btn btn-outline btn-sm" data-confirm="Remove this line?">✕</button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($lines)): ?><tr><td colspan="15" class="empty-state">No account lines yet. Add one above.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <?php if ($budget['status'] === 'Draft' && !empty($lines)): ?>
            <button type="submit" class="btn btn-primary">Save Amounts</button>
        <?php endif; ?>
    </form>
    <?php foreach ($lines as $l): ?>
        <form id="deleteLineForm<?= $l['id'] ?>" method="post" style="display:none;">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="form_action" value="delete_line"><input type="hidden" name="line_id" value="<?= $l['id'] ?>">
        </form>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
