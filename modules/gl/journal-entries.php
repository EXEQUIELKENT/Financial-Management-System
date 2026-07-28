<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('gl.view');

$db = get_db();
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = "SELECT je.*, u.full_name AS created_by_name,
        (SELECT COALESCE(SUM(debit),0) FROM journal_lines WHERE journal_entry_id = je.id) AS total_amount
        FROM journal_entries je JOIN users u ON u.id = je.created_by WHERE 1=1";
$params = [];
if ($status !== '') { $sql .= " AND je.status = ?"; $params[] = $status; }
if ($dateFrom !== '') { $sql .= " AND je.entry_date >= ?"; $params[] = $dateFrom; }
if ($dateTo !== '') { $sql .= " AND je.entry_date <= ?"; $params[] = $dateTo; }
$sql .= " ORDER BY je.entry_date DESC, je.id DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$pageTitle = 'Journal Entries';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="table-toolbar">
        <form method="get" class="table-filters">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['Draft','Posted','Void'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" onchange="this.form.submit()">
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" onchange="this.form.submit()">
        </form>
        <div>
            <a href="chart-of-accounts.php" class="btn btn-outline">Chart of Accounts</a>
            <a href="account-ledger.php" class="btn btn-outline">Account Ledger</a>
            <?php if (has_permission('gl.create')): ?>
                <a href="journal-entry-form.php" class="btn btn-primary">+ New Journal Entry</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Entry No.</th><th>Date</th><th>Source</th><th>Description</th><th>Created By</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($entries as $je): ?>
            <tr>
                <td><?= e($je['entry_no']) ?></td>
                <td><?= format_date($je['entry_date']) ?></td>
                <td class="text-muted"><?= e($je['source_module']) ?></td>
                <td><?= e($je['description']) ?></td>
                <td><?= e($je['created_by_name']) ?></td>
                <td class="num"><?= format_currency($je['total_amount']) ?></td>
                <td><span class="badge <?= status_badge_class($je['status']) ?>"><?= e($je['status']) ?></span></td>
                <td><a href="journal-entry-view.php?id=<?= $je['id'] ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
            <tr><td colspan="8" class="empty-state">No journal entries found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
