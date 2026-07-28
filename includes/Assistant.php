<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Forecast.php';

/**
 * "AI Financial Assistant" -- a self-contained, keyword-matched Q&A engine.
 * No external LLM call: each intent has trigger keywords and a handler that
 * computes a real answer from the database, so it's fully offline and
 * always factually grounded in current data.
 */
function assistant_intents(): array {
    return [
        'cash_position' => ['question' => 'What is our current cash position?', 'keywords' => ['cash position', 'cash balance', 'how much cash', 'bank balance']],
        'ap_total' => ['question' => 'How much do we owe in accounts payable?', 'keywords' => ['payable', 'owe', 'we owe', 'vendors']],
        'ar_total' => ['question' => 'How much are customers owing us?', 'keywords' => ['receivable', 'owing us', 'customers owe', 'collect']],
        'net_income' => ['question' => 'What is our net income this month?', 'keywords' => ['net income', 'profit', 'earnings']],
        'ap_overdue_vendors' => ['question' => 'Which vendors have overdue bills?', 'keywords' => ['overdue vendor', 'late bill', 'overdue bill', 'vendors overdue']],
        'budget_utilization' => ['question' => 'What is our budget utilization?', 'keywords' => ['budget utilization', 'budget usage', 'how much budget', 'spending vs budget']],
        'cash_forecast' => ['question' => 'What is the cash flow forecast for the next 3 months?', 'keywords' => ['forecast', 'predict', 'projection', 'next 3 months', 'next months']],
    ];
}

function assistant_match_intent(string $question): ?string {
    $q = strtolower(trim($question));
    $best = null; $bestScore = 0;
    foreach (assistant_intents() as $key => $intent) {
        $score = 0;
        foreach ($intent['keywords'] as $kw) {
            if (str_contains($q, $kw)) $score++;
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $key; }
    }
    return $bestScore > 0 ? $best : null;
}

function assistant_answer(string $intent): string {
    $db = get_db();
    switch ($intent) {
        case 'cash_position':
            $accounts = $db->query("SELECT account_name, current_balance FROM cash_accounts WHERE status='Active' ORDER BY current_balance DESC")->fetchAll();
            $total = array_sum(array_column($accounts, 'current_balance'));
            $breakdown = implode(', ', array_map(fn($a) => $a['account_name'] . ': ' . format_currency($a['current_balance']), array_slice($accounts, 0, 4)));
            return "Your current cash position across " . count($accounts) . " account(s) is " . format_currency($total) . " as of " . date('M d, Y') . ". Breakdown: " . ($breakdown ?: 'no active accounts') . ".";

        case 'ap_total':
            $stmt = $db->query("SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM ap_bills WHERE status IN ('Open','PartiallyPaid')");
            $total = (float)$stmt->fetchColumn();
            return "You currently owe " . format_currency($total) . " in outstanding accounts payable across open and partially paid bills.";

        case 'ar_total':
            $stmt = $db->query("SELECT COALESCE(SUM(total_amount - amount_received),0) FROM ar_invoices WHERE status IN ('Open','PartiallyPaid')");
            $total = (float)$stmt->fetchColumn();
            return "Customers currently owe you " . format_currency($total) . " in outstanding accounts receivable.";

        case 'net_income':
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-d');
            $rev = 0; $exp = 0;
            foreach (['Revenue' => 1, 'Expense' => -1] as $type => $sign) {
                $stmt = $db->prepare("SELECT a.normal_balance, COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc
                    FROM journal_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id JOIN coa_accounts a ON a.id=jl.account_id
                    WHERE a.account_type=? AND je.status='Posted' AND je.entry_date BETWEEN ? AND ? GROUP BY a.normal_balance");
                $stmt->execute([$type, $dateFrom, $dateTo]);
                foreach ($stmt->fetchAll() as $r) {
                    $amt = $r['normal_balance'] === 'Debit' ? ($r['td'] - $r['tc']) : ($r['tc'] - $r['td']);
                    if ($type === 'Revenue') $rev += $amt; else $exp += $amt;
                }
            }
            $net = $rev - $exp;
            return "Month-to-date (" . date('M Y') . ") net income is " . format_currency($net) . " (Revenue " . format_currency($rev) . " minus Expenses " . format_currency($exp) . ").";

        case 'ap_overdue_vendors':
            $stmt = $db->query("SELECT v.name, SUM(b.total_amount - b.amount_paid) AS balance, MIN(b.due_date) AS earliest_due
                FROM ap_bills b JOIN ap_vendors v ON v.id = b.vendor_id
                WHERE b.status IN ('Open','PartiallyPaid') AND b.due_date < CURDATE()
                GROUP BY v.name ORDER BY balance DESC LIMIT 5");
            $rows = $stmt->fetchAll();
            if (empty($rows)) return "No vendors currently have overdue bills. Nice work staying current on payables.";
            $list = implode('; ', array_map(fn($r) => $r['name'] . ': ' . format_currency($r['balance']) . ' overdue since ' . format_date($r['earliest_due']), $rows));
            return "Vendors with overdue bills: " . $list . ".";

        case 'budget_utilization':
            $today = date('Y-m-d'); $month = (int)date('n');
            $stmt = $db->prepare("SELECT b.id FROM budgets b JOIN budget_periods bp ON bp.id=b.budget_period_id WHERE b.status='Approved' AND ? BETWEEN bp.start_date AND bp.end_date");
            $stmt->execute([$today]);
            $budgetIds = array_column($stmt->fetchAll(), 'id');
            if (empty($budgetIds)) return "There is no approved budget covering the current period yet.";
            $totalBudgeted = 0; $totalActual = 0;
            foreach ($budgetIds as $bid) {
                $lineStmt = $db->prepare("SELECT bl.id, bl.account_id, a.normal_balance FROM budget_lines bl JOIN coa_accounts a ON a.id=bl.account_id WHERE bl.budget_id=?");
                $lineStmt->execute([$bid]);
                foreach ($lineStmt->fetchAll() as $line) {
                    $bStmt = $db->prepare("SELECT COALESCE(SUM(budgeted_amount),0) FROM budget_line_monthly WHERE budget_line_id=? AND month<=?");
                    $bStmt->execute([$line['id'], $month]);
                    $budgeted = (float)$bStmt->fetchColumn();
                    $aStmt = $db->prepare("SELECT COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc FROM journal_lines jl JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE jl.account_id=? AND je.status='Posted' AND je.entry_date <= ?");
                    $aStmt->execute([$line['account_id'], $today]);
                    $a = $aStmt->fetch();
                    $actual = $line['normal_balance'] === 'Debit' ? ($a['td'] - $a['tc']) : ($a['tc'] - $a['td']);
                    $totalBudgeted += $budgeted; $totalActual += $actual;
                }
            }
            $utilization = $totalBudgeted != 0 ? round($totalActual / $totalBudgeted * 100, 1) : 0;
            return "Year-to-date budget utilization is {$utilization}% (" . format_currency($totalActual) . " actual vs " . format_currency($totalBudgeted) . " budgeted) across active approved budgets.";

        case 'cash_forecast':
            $forecast = build_forecast('cash_flow', 12, 3);
            if (!$forecast['sufficient']) return "There isn't enough posted transaction history yet (need at least 4 months) to produce a reliable forecast.";
            $parts = array_map(fn($f) => $f['label'] . ': ' . format_currency($f['amount']), $forecast['forecast']);
            $trend = ($forecast['slope'] ?? 0) >= 0 ? 'an improving' : 'a declining';
            return "Based on recent monthly trends, projected net cash flow shows " . $trend . " trend: " . implode(', ', $parts) . ".";

        default:
            return "I'm not sure how to answer that yet.";
    }
}

function assistant_ask(string $question, ?int $userId): array {
    $intent = assistant_match_intent($question);
    $answer = $intent ? assistant_answer($intent) : "I can only answer a specific set of financial questions right now. Try one of the suggested questions, e.g. cash position, accounts payable/receivable totals, net income, overdue vendors, budget utilization, or the cash flow forecast.";

    $db = get_db();
    $stmt = $db->prepare("INSERT INTO ai_assistant_query_log (user_id, question_text, matched_intent, answer_text) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $question, $intent, $answer]);

    return ['intent' => $intent, 'answer' => $answer];
}
