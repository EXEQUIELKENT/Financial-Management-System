<?php
require_once __DIR__ . '/../config/db.php';

const FORECAST_MIN_MONTHS = 4;

/**
 * Builds a monthly time series for a given financial metric, most recent
 * $months months up to and including the current month.
 */
function get_monthly_series(PDO $db, string $metric, int $months = 12): array {
    $series = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("first day of -$i month"));
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $label = date('M Y', strtotime($monthStart));
        $amount = 0.0;

        if (in_array($metric, ['revenue', 'expense'], true)) {
            $type = $metric === 'revenue' ? 'Revenue' : 'Expense';
            $stmt = $db->prepare("SELECT a.normal_balance, COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc
                FROM journal_lines jl
                JOIN journal_entries je ON je.id = jl.journal_entry_id
                JOIN coa_accounts a ON a.id = jl.account_id
                WHERE a.account_type = ? AND je.status = 'Posted' AND je.entry_date BETWEEN ? AND ?
                GROUP BY a.normal_balance");
            $stmt->execute([$type, $monthStart, $monthEnd]);
            foreach ($stmt->fetchAll() as $r) {
                $amount += $r['normal_balance'] === 'Debit' ? ($r['td'] - $r['tc']) : ($r['tc'] - $r['td']);
            }
        } elseif ($metric === 'cash_flow') {
            $stmt = $db->prepare("SELECT type, COALESCE(SUM(amount),0) AS amt FROM cash_transactions WHERE transaction_date BETWEEN ? AND ? GROUP BY type");
            $stmt->execute([$monthStart, $monthEnd]);
            foreach ($stmt->fetchAll() as $r) {
                $amount += in_array($r['type'], ['Deposit', 'TransferIn'], true) ? $r['amt'] : -$r['amt'];
            }
        }

        $series[] = ['label' => $label, 'amount' => round($amount, 2)];
    }
    return $series;
}

/** Simple trailing moving average (window months), same length as input, null where insufficient history. */
function moving_average(array $series, int $window = 3): array {
    $result = [];
    foreach ($series as $i => $point) {
        if ($i + 1 < $window) { $result[] = null; continue; }
        $sum = 0;
        for ($j = $i - $window + 1; $j <= $i; $j++) $sum += $series[$j]['amount'];
        $result[] = round($sum / $window, 2);
    }
    return $result;
}

/** Least-squares linear regression over (0..n-1, amount). Returns [slope, intercept]. */
function linear_regression(array $series): array {
    $n = count($series);
    if ($n < 2) return [0, $n ? $series[0]['amount'] : 0];
    $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
    foreach ($series as $i => $point) {
        $x = $i; $y = $point['amount'];
        $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumX2 += $x * $x;
    }
    $denom = ($n * $sumX2 - $sumX * $sumX);
    $slope = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / $denom : 0;
    $intercept = ($sumY - $slope * $sumX) / $n;
    return [$slope, $intercept];
}

/**
 * Full forecast package for the dashboard chart: historical series, moving
 * average baseline, and a linear-regression projection for the next
 * $forwardMonths months. Clips revenue/expense forecasts at zero (can't be
 * negative); leaves cash_flow unclipped since net cash flow can go negative.
 */
function build_forecast(string $metric, int $historyMonths = 12, int $forwardMonths = 3): array {
    $db = get_db();
    $series = get_monthly_series($db, $metric, $historyMonths);
    $nonZeroMonths = count(array_filter($series, fn($p) => $p['amount'] != 0));

    if ($nonZeroMonths < FORECAST_MIN_MONTHS) {
        return ['sufficient' => false, 'history' => $series, 'moving_average' => [], 'forecast' => []];
    }

    $ma = moving_average($series, 3);
    [$slope, $intercept] = linear_regression($series);

    $forecast = [];
    $n = count($series);
    for ($k = 1; $k <= $forwardMonths; $k++) {
        $x = $n - 1 + $k;
        $y = $slope * $x + $intercept;
        if (in_array($metric, ['revenue', 'expense'], true)) $y = max(0, $y);
        $label = date('M Y', strtotime("first day of +$k month"));
        $forecast[] = ['label' => $label, 'amount' => round($y, 2)];
    }

    return ['sufficient' => true, 'history' => $series, 'moving_average' => $ma, 'forecast' => $forecast, 'slope' => $slope];
}
