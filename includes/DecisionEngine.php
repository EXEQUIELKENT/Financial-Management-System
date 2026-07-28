<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Forecast.php';

const SEVERITY_ORDER = ['Critical' => 0, 'Warning' => 1, 'Info' => 2];

function rec_cash_runway(PDO $db): ?array {
    $totalCash = (float)$db->query("SELECT COALESCE(SUM(current_balance),0) FROM cash_accounts WHERE status='Active'")->fetchColumn();
    $series = get_monthly_series($db, 'cash_flow', 3);
    $avg = count($series) ? array_sum(array_column($series, 'amount')) / count($series) : 0;
    if ($avg >= 0) return null;

    $burnPerDay = abs($avg) / 30;
    if ($burnPerDay <= 0) return null;
    $runwayDays = (int)round($totalCash / $burnPerDay);
    $threshold = (int)get_setting('cash_runway_days_threshold', 60);

    if ($runwayDays < $threshold) {
        return ['severity' => 'Critical', 'title' => 'Low Cash Runway',
            'message' => "At the current average monthly burn rate, cash on hand covers only about {$runwayDays} days of operations (below the {$threshold}-day threshold).",
            'link' => BASE_URL . '/modules/cash/cash-position.php'];
    }
    return null;
}

function rec_ap_overdue_spike(PDO $db): ?array {
    $stmt = $db->query("SELECT due_date, total_amount, amount_paid FROM ap_bills WHERE status IN ('Open','PartiallyPaid')");
    $bills = $stmt->fetchAll();
    $total = 0; $risky = 0;
    foreach ($bills as $b) {
        $balance = $b['total_amount'] - $b['amount_paid'];
        $total += $balance;
        $bucket = aging_bucket($b['due_date']);
        if (in_array($bucket, ['61-90', '90+'], true)) $risky += $balance;
    }
    if ($total <= 0) return null;
    $pct = round($risky / $total * 100, 1);
    $threshold = (float)get_setting('ap_overdue_pct_threshold', 30);
    if ($pct > $threshold) {
        return ['severity' => 'Warning', 'title' => 'Accounts Payable Aging Risk',
            'message' => "{$pct}% of outstanding accounts payable (" . format_currency($risky) . ") is more than 60 days overdue, above the {$threshold}% threshold.",
            'link' => BASE_URL . '/modules/ap/aging-report.php'];
    }
    return null;
}

function rec_ar_aging_risk(PDO $db): ?array {
    $stmt = $db->query("SELECT due_date, total_amount, amount_received FROM ar_invoices WHERE status IN ('Open','PartiallyPaid')");
    $invoices = $stmt->fetchAll();
    $total = 0; $risky = 0;
    foreach ($invoices as $i) {
        $balance = $i['total_amount'] - $i['amount_received'];
        $total += $balance;
        $bucket = aging_bucket($i['due_date']);
        if ($bucket === '90+') $risky += $balance;
    }
    if ($total <= 0) return null;
    $pct = round($risky / $total * 100, 1);
    $threshold = (float)get_setting('ar_aging_pct_threshold', 20);
    if ($pct > $threshold) {
        return ['severity' => 'Warning', 'title' => 'Accounts Receivable Aging Risk',
            'message' => "{$pct}% of outstanding receivables (" . format_currency($risky) . ") is over 90 days past due, above the {$threshold}% threshold.",
            'link' => BASE_URL . '/modules/ar/aging-report.php'];
    }
    return null;
}

function rec_budget_overrun(PDO $db): ?array {
    $today = date('Y-m-d');
    $month = (int)date('n');
    $stmt = $db->prepare("SELECT b.id, bp.start_date, bp.end_date FROM budgets b JOIN budget_periods bp ON bp.id = b.budget_period_id
                           WHERE b.status='Approved' AND ? BETWEEN bp.start_date AND bp.end_date");
    $stmt->execute([$today]);
    $activeBudgets = $stmt->fetchAll();

    $worst = null;
    foreach ($activeBudgets as $bud) {
        $lineStmt = $db->prepare("SELECT bl.id, bl.account_id, a.account_name, a.normal_balance FROM budget_lines bl JOIN coa_accounts a ON a.id = bl.account_id WHERE bl.budget_id = ?");
        $lineStmt->execute([$bud['id']]);
        foreach ($lineStmt->fetchAll() as $line) {
            $budgetedStmt = $db->prepare("SELECT COALESCE(SUM(budgeted_amount),0) FROM budget_line_monthly WHERE budget_line_id = ? AND month <= ?");
            $budgetedStmt->execute([$line['id'], $month]);
            $budgeted = (float)$budgetedStmt->fetchColumn();
            if ($budgeted <= 0) continue;

            $actualStmt = $db->prepare("SELECT COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc FROM journal_lines jl
                                         JOIN journal_entries je ON je.id = jl.journal_entry_id
                                         WHERE jl.account_id = ? AND je.status='Posted' AND je.entry_date BETWEEN ? AND ?");
            $actualStmt->execute([$line['account_id'], $bud['start_date'], $today]);
            $a = $actualStmt->fetch();
            $actual = $line['normal_balance'] === 'Debit' ? ($a['td'] - $a['tc']) : ($a['tc'] - $a['td']);
            $utilization = round($actual / $budgeted * 100, 1);

            if ($worst === null || $utilization > $worst['utilization']) {
                $worst = ['utilization' => $utilization, 'account_name' => $line['account_name']];
            }
        }
    }
    if (!$worst) return null;
    $warnPct = (float)get_setting('budget_warning_pct', 90);
    $critPct = (float)get_setting('budget_critical_pct', 100);

    if ($worst['utilization'] >= $critPct) {
        return ['severity' => 'Critical', 'title' => 'Budget Exceeded',
            'message' => "\"{$worst['account_name']}\" has used {$worst['utilization']}% of its year-to-date budget allocation.",
            'link' => BASE_URL . '/modules/budget/variance-report.php'];
    }
    if ($worst['utilization'] >= $warnPct) {
        return ['severity' => 'Warning', 'title' => 'Budget Nearing Limit',
            'message' => "\"{$worst['account_name']}\" has used {$worst['utilization']}% of its year-to-date budget allocation.",
            'link' => BASE_URL . '/modules/budget/variance-report.php'];
    }
    return null;
}

function rec_insufficient_cash_for_payables(PDO $db): ?array {
    $days = (int)get_setting('upcoming_payable_days', 30);
    $lookAhead = date('Y-m-d', strtotime("+{$days} days"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM ap_bills WHERE status IN ('Open','PartiallyPaid') AND due_date <= ?");
    $stmt->execute([$lookAhead]);
    $duePayables = (float)$stmt->fetchColumn();
    $totalCash = (float)$db->query("SELECT COALESCE(SUM(current_balance),0) FROM cash_accounts WHERE status='Active'")->fetchColumn();

    if ($duePayables > $totalCash && $duePayables > 0) {
        return ['severity' => 'Critical', 'title' => 'Insufficient Cash for Upcoming Payables',
            'message' => format_currency($duePayables) . " in bills is due within {$days} days, which exceeds current cash on hand of " . format_currency($totalCash) . ".",
            'link' => BASE_URL . '/modules/ap/aging-report.php'];
    }
    return null;
}

function rec_revenue_decline(PDO $db): ?array {
    $forecast = build_forecast('revenue', 6, 1);
    if (!$forecast['sufficient']) return null;
    if (($forecast['slope'] ?? 0) < 0) {
        return ['severity' => 'Info', 'title' => 'Revenue Trend Declining',
            'message' => 'Revenue has shown a declining trend over the recent months based on posted GL activity.',
            'link' => BASE_URL . '/modules/dashboard/index.php'];
    }
    return null;
}

function rec_tax_remittance_due(PDO $db): ?array {
    $cutoff = date('Y-m-d', strtotime('-30 days'));
    $stmt = $db->prepare("SELECT COALESCE(SUM(tax_amount),0) AS amt, COUNT(*) AS cnt FROM tax_transactions WHERE status='Pending' AND direction IN ('Output','Withholding') AND transaction_date <= ?");
    $stmt->execute([$cutoff]);
    $r = $stmt->fetch();
    if ((float)$r['amt'] > 0) {
        return ['severity' => 'Warning', 'title' => 'Tax Remittance Due',
            'message' => (int)$r['cnt'] . ' tax transaction(s) totaling ' . format_currency($r['amt']) . ' have been pending remittance for over 30 days.',
            'link' => BASE_URL . '/modules/tax/remittances.php'];
    }
    return null;
}

function get_recommendations(): array {
    $db = get_db();
    $rules = [
        'rec_cash_runway', 'rec_insufficient_cash_for_payables', 'rec_budget_overrun',
        'rec_ap_overdue_spike', 'rec_ar_aging_risk', 'rec_tax_remittance_due', 'rec_revenue_decline',
    ];
    $results = [];
    foreach ($rules as $fn) {
        $r = $fn($db);
        if ($r) $results[] = $r;
    }
    usort($results, fn($a, $b) => SEVERITY_ORDER[$a['severity']] <=> SEVERITY_ORDER[$b['severity']]);
    return $results;
}
