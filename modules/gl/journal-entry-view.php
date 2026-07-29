<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/Ledger.php';
require_once __DIR__ . '/../../includes/Audit.php';
require_permission('gl.view');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ?");
    $stmt->execute([$id]);
    $entry = $stmt->fetch();

    if ($action === 'post' && $entry) {
        require_permission('gl.post');
        if ((int)$entry['created_by'] === (int)current_user()['id'] && ($_SESSION['role_name'] ?? '') !== 'Admin') {
            flash('error', 'Segregation of duties: you cannot post an entry you created yourself.');
        } else {
            try {
                approve_and_post_journal_entry($id, current_user()['id']);
                log_audit('post', 'gl', $id, 'Posted journal entry ' . $entry['entry_no']);
                flash('success', 'Journal entry posted to the general ledger.');
            } catch (Throwable $e) {
                flash('error', 'Could not post entry: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'void' && $entry) {
        require_permission('gl.post');
        try {
            void_journal_entry($id, current_user()['id'], $_POST['reason'] ?? '');
            log_audit('void', 'gl', $id, 'Voided journal entry ' . $entry['entry_no']);
            flash('success', 'Journal entry voided with a reversing entry.');
        } catch (Throwable $e) {
            flash('error', 'Could not void entry: ' . $e->getMessage());
        }
    }
    redirect('modules/gl/journal-entry-view.php?id=' . $id);
}

$stmt = $db->prepare("SELECT je.*, u.full_name AS created_by_name, u2.full_name AS approved_by_name
                       FROM journal_entries je JOIN users u ON u.id = je.created_by
                       LEFT JOIN users u2 ON u2.id = je.approved_by WHERE je.id = ?");
$stmt->execute([$id]);
$entry = $stmt->fetch();
if (!$entry) { flash('error', 'Journal entry not found.'); redirect('modules/gl/journal-entries.php'); }

$stmt = $db->prepare("SELECT jl.*, a.account_code, a.account_name FROM journal_lines jl JOIN coa_accounts a ON a.id = jl.account_id WHERE jl.journal_entry_id = ? ORDER BY jl.id");
$stmt->execute([$id]);
$lines = $stmt->fetchAll();
$totalDebit = array_sum(array_column($lines, 'debit'));
$totalCredit = array_sum(array_column($lines, 'credit'));

$pageTitle = 'Journal Entry ' . $entry['entry_no'];
$pageHelp = [
    ['selector' => '.status-stepper, .status-stepper-stopped',
        'en' => ['title' => 'Stepper at the top', 'body' => 'Shows whether this entry is still Drafted or already Posted to the GL — or Voided, if the normal flow was stopped.'],
        'tl' => ['title' => 'Stepper sa Itaas', 'body' => 'Ipinapakita kung Draft pa ang entry na ito o naka-Posted na sa GL — o Voided, kung natigil ang normal na daloy.']],
];
if ($entry['status'] === 'Draft' && has_permission('gl.post')) {
    $pageHelp[] = ['selector' => '.btn-accent',
        'en' => ['title' => 'Approve & Post', 'body' => 'Books it to the ledger permanently. You cannot post an entry you created yourself.'],
        'tl' => ['title' => 'Approve & Post', 'body' => 'Permanenteng ipo-post ito sa ledger. Hindi mo puwedeng i-post ang entry na ikaw mismo ang gumawa.']];
}
if ($entry['status'] === 'Posted' && has_permission('gl.post')) {
    $pageHelp[] = ['selector' => '.btn-danger',
        'en' => ['title' => 'Void Entry', 'body' => 'Books an automatic equal-and-opposite reversing entry rather than deleting anything, preserving the audit trail.'],
        'tl' => ['title' => 'I-void ang Entry', 'body' => 'Awtomatikong magbo-book ng katumbas na reversing entry sa halip na burahin ang kahit ano, para mapanatili ang audit trail.']];
}
include __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3><?= e($entry['entry_no']) ?> <span class="badge <?= status_badge_class($entry['status']) ?>"><?= e($entry['status']) ?></span></h3>
        <div>
            <?php if ($entry['status'] === 'Draft' && has_permission('gl.post')): ?>
                <form method="post" style="display:inline;">
                    <?= csrf_field() ?><input type="hidden" name="action" value="post">
                    <button type="submit" class="btn btn-accent" data-confirm="Post this entry to the general ledger?">Approve &amp; Post</button>
                </form>
            <?php endif; ?>
            <?php if ($entry['status'] === 'Posted' && has_permission('gl.post')): ?>
                <form method="post" style="display:inline;">
                    <?= csrf_field() ?><input type="hidden" name="action" value="void">
                    <button type="submit" class="btn btn-danger" data-confirm="Void this entry? A reversing entry will be booked automatically.">Void Entry</button>
                </form>
            <?php endif; ?>
            <a href="journal-entries.php" class="btn btn-outline">Back to List</a>
        </div>
    </div>
    <?php
        $stepIndex = ['Draft' => 0, 'Posted' => 1][$entry['status']] ?? 0;
        $stopped = $entry['status'] === 'Void' ? 'Voided — a reversing entry was booked' : null;
        echo render_status_stepper(['1. Drafted', '2. Posted to GL'], $stepIndex, $stopped);
    ?>
    <div class="form-row">
        <div><span class="text-muted">Entry Date</span><br><?= format_date($entry['entry_date']) ?></div>
        <div><span class="text-muted">Reference</span><br><?= e($entry['reference'] ?: '—') ?></div>
        <div><span class="text-muted">Source</span><br><?= e($entry['source_module']) ?></div>
        <div><span class="text-muted">Created By</span><br><?= e($entry['created_by_name']) ?></div>
        <div><span class="text-muted">Approved/Posted By</span><br><?= e($entry['approved_by_name'] ?? '—') ?></div>
    </div>
    <p><?= e($entry['description']) ?></p>

    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Account</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
            <tr>
                <td><?= e($l['account_code'] . ' - ' . $l['account_name']) ?></td>
                <td class="text-muted"><?= e($l['memo']) ?></td>
                <td class="num"><?= $l['debit'] > 0 ? format_currency($l['debit']) : '' ?></td>
                <td class="num"><?= $l['credit'] > 0 ? format_currency($l['credit']) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:600;"><td colspan="2">Total</td><td class="num"><?= format_currency($totalDebit) ?></td><td class="num"><?= format_currency($totalCredit) ?></td></tr>
        </tfoot>
    </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
