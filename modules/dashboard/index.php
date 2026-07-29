<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/DecisionEngine.php';
require_once __DIR__ . '/../../includes/Assistant.php';
require_permission('dashboard.view');

$db = get_db();

$totalCash = (float)$db->query("SELECT COALESCE(SUM(current_balance),0) FROM cash_accounts WHERE status='Active'")->fetchColumn();
$arTotal = (float)$db->query("SELECT COALESCE(SUM(total_amount - amount_received),0) FROM ar_invoices WHERE status IN ('Open','PartiallyPaid')")->fetchColumn();
$apTotal = (float)$db->query("SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM ap_bills WHERE status IN ('Open','PartiallyPaid')")->fetchColumn();

$today = date('Y-m-d'); $month = (int)date('n');
$budgetIdsStmt = $db->prepare("SELECT b.id FROM budgets b JOIN budget_periods bp ON bp.id=b.budget_period_id WHERE b.status='Approved' AND ? BETWEEN bp.start_date AND bp.end_date");
$budgetIdsStmt->execute([$today]);
$budgetIds = array_column($budgetIdsStmt->fetchAll(), 'id');
$totalBudgeted = 0; $totalActual = 0;
foreach ($budgetIds as $bid) {
    $lineStmt = $db->prepare("SELECT bl.id, bl.account_id, a.normal_balance FROM budget_lines bl JOIN coa_accounts a ON a.id=bl.account_id WHERE bl.budget_id=?");
    $lineStmt->execute([$bid]);
    foreach ($lineStmt->fetchAll() as $line) {
        $bStmt = $db->prepare("SELECT COALESCE(SUM(budgeted_amount),0) FROM budget_line_monthly WHERE budget_line_id=? AND month<=?");
        $bStmt->execute([$line['id'], $month]);
        $totalBudgeted += (float)$bStmt->fetchColumn();
        $aStmt = $db->prepare("SELECT COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc FROM journal_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=? AND je.status='Posted' AND je.entry_date <= ?");
        $aStmt->execute([$line['account_id'], $today]);
        $a = $aStmt->fetch();
        $totalActual += $line['normal_balance'] === 'Debit' ? ($a['td'] - $a['tc']) : ($a['tc'] - $a['td']);
    }
}
$budgetUtilization = $totalBudgeted != 0 ? round($totalActual / $totalBudgeted * 100, 1) : null;

$recommendations = get_recommendations();
$intents = assistant_intents();

$pageTitle = 'Dashboard';
$pageHelp = [
    ['selector' => '.kpi-grid',
        'en' => ['title' => 'KPI cards', 'body' => 'Cash Position, Accounts Receivable, Accounts Payable, and Budget Utilization — all live totals, click through to the underlying report.'],
        'tl' => ['title' => 'KPI cards', 'body' => 'Cash Position, Accounts Receivable, Accounts Payable, at Budget Utilization — lahat live totals, i-click para makita ang detalyadong report.']],
    ['selector' => '.card.fade-in-up', 'nth' => 0,
        'en' => ['title' => 'Decision Support', 'body' => 'Automatic alert cards that appear only when a threshold is crossed (low cash runway, aging risk, budget overrun, etc.) — configurable under Settings.'],
        'tl' => ['title' => 'Decision Support', 'body' => 'Awtomatikong lalabas ang mga alert card kapag may nalagpasang threshold (mababang cash runway, aging risk, budget overrun, atbp) — naka-configure sa ilalim ng Settings.']],
    ['selector' => '#forecastChart',
        'en' => ['title' => 'Predictive Analysis chart', 'body' => 'A 3-month moving average plus a linear-regression trend line projecting the next 3 months of cash flow from actual history.'],
        'tl' => ['title' => 'Predictive Analysis chart', 'body' => 'Isang 3-buwang moving average kasama ang linear-regression trend line na nagpo-project ng susunod na 3 buwan ng cash flow batay sa aktwal na kasaysayan.']],
];
if (has_permission('assistant.view')) {
    $pageHelp[] = ['selector' => '.assistant-chip', 'nth' => 0,
        'en' => ['title' => 'AI Financial Assistant', 'body' => 'Click a suggested question (or type your own close to one) for a real, computed answer from the current data — not a general chatbot.'],
        'tl' => ['title' => 'AI Financial Assistant', 'body' => 'I-click ang isang suggested na tanong (o mag-type ng sarili mong tanong na malapit dito) para sa tunay, kinalkulang sagot mula sa kasalukuyang data — hindi ito isang pangkalahatang chatbot.']];
}
$pageHelp[] = ['selector' => 'a[href$="modules/help/index.php"]',
    'en' => ['title' => 'New here?', 'body' => 'Click "Getting Started" in the sidebar for a full guided tour of the whole system, with optional English/Tagalog voice-over.'],
    'tl' => ['title' => 'Bago ka lang ba?', 'body' => 'I-click ang "Getting Started" sa sidebar para sa isang buong guided tour ng buong sistema, na may opsyonal na English/Tagalog voice-over.']];
$extraScripts = ['https://cdn.jsdelivr.net/npm/chart.js', BASE_URL . '/assets/js/dashboard.js', BASE_URL . '/assets/js/assistant.js'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="kpi-grid">
    <div class="kpi-card primary fade-in-up" style="animation-delay:0.05s">
        <div class="kpi-label">Cash Position</div>
        <div class="kpi-value"><?= format_currency($totalCash) ?></div>
        <div class="kpi-sub"><a href="../cash/cash-position.php">View accounts →</a></div>
    </div>
    <div class="kpi-card accent fade-in-up" style="animation-delay:0.1s">
        <div class="kpi-label">Accounts Receivable</div>
        <div class="kpi-value"><?= format_currency($arTotal) ?></div>
        <div class="kpi-sub"><a href="../ar/aging-report.php">View aging →</a></div>
    </div>
    <div class="kpi-card warning fade-in-up" style="animation-delay:0.15s">
        <div class="kpi-label">Accounts Payable</div>
        <div class="kpi-value"><?= format_currency($apTotal) ?></div>
        <div class="kpi-sub"><a href="../ap/aging-report.php">View aging →</a></div>
    </div>
    <div class="kpi-card fade-in-up <?= $budgetUtilization === null ? 'primary' : ($budgetUtilization > 100 ? 'danger' : ($budgetUtilization > 90 ? 'warning' : 'accent')) ?>" style="animation-delay:0.2s">
        <div class="kpi-label">Budget Utilization (YTD)</div>
        <div class="kpi-value"><?= $budgetUtilization === null ? '—' : $budgetUtilization . '%' ?></div>
        <div class="kpi-sub"><a href="../budget/variance-report.php">View variance →</a></div>
    </div>
</div>

<div class="card fade-in-up" style="animation-delay:0.25s">
    <div class="card-header"><h3>Decision Support</h3></div>
    <?php if (empty($recommendations)): ?>
        <div class="alert alert-success"><span class="alert-title">All clear</span> No threshold-based risks detected right now.</div>
    <?php else: foreach ($recommendations as $r): ?>
        <div class="alert alert-<?= strtolower($r['severity']) === 'critical' ? 'critical' : (strtolower($r['severity']) === 'warning' ? 'warning' : 'info') ?>">
            <div class="alert-title"><?= e($r['title']) ?></div>
            <?= e($r['message']) ?> <a href="<?= $r['link'] ?>">Details →</a>
        </div>
    <?php endforeach; endif; ?>
</div>

<div class="form-row">
    <div class="card fade-in-up" style="flex:2;min-width:400px;animation-delay:0.3s">
        <div class="card-header"><h3>Revenue &amp; Expense Trend</h3></div>
        <div class="chart-box"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="card fade-in-up" style="flex:2;min-width:400px;animation-delay:0.35s">
        <div class="card-header"><h3>Cash Flow — Predictive Analysis</h3></div>
        <div class="chart-box"><canvas id="forecastChart"></canvas></div>
        <p class="form-hint" id="forecastNote"></p>
    </div>
</div>

<?php if (has_permission('assistant.view')): ?>
<div class="card fade-in-up" style="animation-delay:0.4s">
    <div class="card-header"><h3>AI Financial Assistant</h3></div>
    <div>
        <?php foreach ($intents as $intent): ?>
            <span class="assistant-chip" onclick="assistantAsk('<?= e($intent['question']) ?>')"><?= e($intent['question']) ?></span>
        <?php endforeach; ?>
    </div>
    <div class="assistant-log" id="assistantLog" style="margin-top:16px;"></div>
    <form id="assistantForm" onsubmit="return assistantSubmit(event)" style="display:flex;gap:8px;">
        <input type="text" id="assistantInput" class="form-control" placeholder="Ask a question about your finances...">
        <button type="submit" class="btn btn-primary">Ask</button>
    </form>
</div>
<?php endif; ?>

<script>
window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
window.API_BASE = <?= json_encode(BASE_URL) ?>;
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
