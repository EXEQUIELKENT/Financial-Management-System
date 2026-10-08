<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Central posting engine. Every module (AP, AR, Disbursement, Collection,
 * Cash, Tax) creates its journal entries through post_journal_entry() so the
 * general ledger stays the single source of truth for every dollar recorded.
 *
 * $data = [
 *   'entry_date' => 'Y-m-d',
 *   'reference' => string,
 *   'source_module' => string,   // e.g. 'ap', 'ar', 'disbursement'
 *   'source_id' => int|null,
 *   'description' => string,
 *   'created_by' => int,
 *   'lines' => [ ['account_id'=>int, 'debit'=>float, 'credit'=>float, 'department_id'=>int|null, 'memo'=>string], ... ],
 * ]
 */
function post_journal_entry(array $data): int {
    $lines = $data['lines'] ?? [];
    if (count($lines) < 2) {
        throw new InvalidArgumentException('A journal entry requires at least two lines.');
    }
    validate_journal_lines($lines);

    return db_transaction(function (PDO $db) use ($data, $lines) {
        $entryNo = generate_entry_no($db);
        $stmt = $db->prepare("INSERT INTO journal_entries
            (entry_no, entry_date, reference, source_module, source_id, description, status, created_by, approved_by, posted_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'Posted', ?, ?, NOW(), NOW())");
        $stmt->execute([
            $entryNo,
            $data['entry_date'],
            $data['reference'] ?? '',
            $data['source_module'] ?? 'manual',
            $data['source_id'] ?? null,
            $data['description'] ?? '',
            $data['created_by'],
            $data['created_by'],
        ]);
        $entryId = (int)$db->lastInsertId();

        $lineStmt = $db->prepare("INSERT INTO journal_lines
            (journal_entry_id, account_id, debit, credit, department_id, memo)
            VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($lines as $line) {
            $lineStmt->execute([
                $entryId,
                $line['account_id'],
                (float)($line['debit'] ?? 0),
                (float)($line['credit'] ?? 0),
                $line['department_id'] ?? null,
                $line['memo'] ?? '',
            ]);
        }
        return $entryId;
    });
}

/**
 * Runs $fn(PDO) inside a database transaction and returns its result, rolling back on
 * any exception. If a transaction is already open, $fn joins it, so a handler can wrap
 * several writes (document rows, ledger entry, cash balance) and have them commit or
 * roll back together. Never redirect() inside $fn: redirect() exits before the commit.
 */
function db_transaction(callable $fn) {
    $db = get_db();
    if ($db->inTransaction()) {
        return $fn($db);
    }
    $db->beginTransaction();
    try {
        $result = $fn($db);
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/**
 * Every line needs an account and a single non-negative side, and the entry must
 * balance. A missing account usually means a control account isn't set in Settings.
 */
function validate_journal_lines(array $lines): void {
    $totalDebit = 0.0;
    $totalCredit = 0.0;
    foreach ($lines as $line) {
        $d = round((float)($line['debit'] ?? 0), 2);
        $c = round((float)($line['credit'] ?? 0), 2);
        if (empty($line['account_id'])) {
            throw new InvalidArgumentException('A journal line has no account. Check that the control accounts are set in Settings.');
        }
        if ($d < 0 || $c < 0) {
            throw new InvalidArgumentException('Journal lines cannot have negative amounts.');
        }
        if ($d > 0 && $c > 0) {
            throw new InvalidArgumentException('A journal line cannot have both a debit and a credit.');
        }
        $totalDebit += $d;
        $totalCredit += $c;
    }
    if (round($totalDebit, 2) !== round($totalCredit, 2)) {
        throw new InvalidArgumentException("Journal entry is not balanced: debits {$totalDebit} vs credits {$totalCredit}.");
    }
}

/**
 * Applies amounts to one vendor's AP bills ('ap') or one customer's AR invoices ('ar')
 * and updates their paid amount and status. Each document is locked and must belong to
 * $partyId, be Open or PartiallyPaid, and have enough unpaid balance; otherwise this
 * throws and the caller's transaction rolls back. $applications is [document id => amount].
 * With $dryRun it only checks and returns the problems, for form validation.
 */
function apply_to_open_documents(string $kind, int $partyId, array $applications, bool $dryRun = false): array {
    [$table, $partyCol, $paidCol, $noCol, $label] = $kind === 'ap'
        ? ['ap_bills', 'vendor_id', 'amount_paid', 'bill_no', 'Bill']
        : ['ar_invoices', 'customer_id', 'amount_received', 'invoice_no', 'Invoice'];
    $db = get_db();
    $sel = $db->prepare("SELECT {$noCol} AS doc_no, {$partyCol} AS party_id, status, total_amount, {$paidCol} AS paid
                         FROM {$table} WHERE id = ?" . ($dryRun ? '' : ' FOR UPDATE'));
    $upd = $db->prepare("UPDATE {$table} SET {$paidCol} = ?, status = ? WHERE id = ?");
    $problems = [];
    foreach ($applications as $docId => $amt) {
        $amt = round((float)$amt, 2);
        $sel->execute([(int)$docId]);
        $doc = $sel->fetch();
        if (!$doc || (int)$doc['party_id'] !== $partyId) {
            $problems[] = "{$label} #{$docId} does not belong to the selected " . ($kind === 'ap' ? 'vendor.' : 'customer.');
            continue;
        }
        if (!in_array($doc['status'], ['Open', 'PartiallyPaid'], true)) {
            $problems[] = "{$label} {$doc['doc_no']} is not open (status: {$doc['status']}).";
            continue;
        }
        $balance = round((float)$doc['total_amount'] - (float)$doc['paid'], 2);
        if ($amt <= 0 || $amt > $balance) {
            $problems[] = "Amount for {$label} {$doc['doc_no']} must be more than zero and at most its unpaid balance of " . format_currency($balance) . '.';
            continue;
        }
        if (!$dryRun) {
            $newPaid = round((float)$doc['paid'] + $amt, 2);
            $newStatus = $newPaid >= (float)$doc['total_amount'] - 0.005 ? 'Paid' : 'PartiallyPaid';
            $upd->execute([$newPaid, $newStatus, (int)$docId]);
        }
    }
    if ($problems && !$dryRun) {
        throw new RuntimeException(implode(' ', $problems));
    }
    return $problems;
}

/** Manual GL entries start as Draft and go through an explicit approve/post step. */
function create_draft_journal_entry(array $data): int {
    $lines = $data['lines'] ?? [];
    validate_journal_lines($lines);

    return db_transaction(function (PDO $db) use ($data, $lines) {
        $entryNo = generate_entry_no($db);
        $stmt = $db->prepare("INSERT INTO journal_entries
            (entry_no, entry_date, reference, source_module, source_id, description, status, created_by, created_at)
            VALUES (?, ?, ?, 'manual', NULL, ?, 'Draft', ?, NOW())");
        $stmt->execute([$entryNo, $data['entry_date'], $data['reference'] ?? '', $data['description'] ?? '', $data['created_by']]);
        $entryId = (int)$db->lastInsertId();

        $lineStmt = $db->prepare("INSERT INTO journal_lines
            (journal_entry_id, account_id, debit, credit, department_id, memo)
            VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($lines as $line) {
            $lineStmt->execute([
                $entryId,
                $line['account_id'],
                (float)($line['debit'] ?? 0),
                (float)($line['credit'] ?? 0),
                $line['department_id'] ?? null,
                $line['memo'] ?? '',
            ]);
        }
        return $entryId;
    });
}

function approve_and_post_journal_entry(int $entryId, int $userId): void {
    $db = get_db();
    $stmt = $db->prepare("SELECT je.*, COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc
                           FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id = je.id
                           WHERE je.id = ? GROUP BY je.id");
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();
    if (!$entry) throw new RuntimeException('Journal entry not found.');
    if ($entry['status'] !== 'Draft') throw new RuntimeException('Only Draft entries can be posted.');
    if (round((float)$entry['td'], 2) !== round((float)$entry['tc'], 2)) {
        throw new RuntimeException('Cannot post an unbalanced journal entry.');
    }
    $lineStmt = $db->prepare("SELECT account_id, debit, credit FROM journal_lines WHERE journal_entry_id = ?");
    $lineStmt->execute([$entryId]);
    validate_journal_lines($lineStmt->fetchAll());
    $db->prepare("UPDATE journal_entries SET status='Posted', approved_by=?, posted_at=NOW() WHERE id=?")
       ->execute([$userId, $entryId]);
}

/** Voiding never deletes — it books an equal-and-opposite reversing entry for audit-trail integrity. */
function void_journal_entry(int $entryId, int $userId, string $reason = ''): int {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ?");
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();
    if (!$entry) throw new RuntimeException('Journal entry not found.');
    if ($entry['status'] === 'Void') throw new RuntimeException('Journal entry is already void.');

    $lineStmt = $db->prepare("SELECT * FROM journal_lines WHERE journal_entry_id = ?");
    $lineStmt->execute([$entryId]);
    $origLines = $lineStmt->fetchAll();

    // Net each line so the reversal has one non-negative side even if an older entry
    // was saved with a negative amount or with both a debit and a credit.
    $reversedLines = array_map(function ($l) {
        $net = round((float)$l['debit'] - (float)$l['credit'], 2);
        return [
            'account_id' => $l['account_id'],
            'debit' => $net < 0 ? -$net : 0,
            'credit' => $net > 0 ? $net : 0,
            'department_id' => $l['department_id'],
            'memo' => 'Reversal: ' . $l['memo'],
        ];
    }, $origLines);

    return db_transaction(function (PDO $db) use ($entry, $entryId, $userId, $reason, $reversedLines) {
        $reversalId = post_journal_entry([
            'entry_date' => date('Y-m-d'),
            'reference' => $entry['entry_no'] . '-VOID',
            'source_module' => $entry['source_module'],
            'source_id' => $entry['source_id'],
            'description' => 'Reversal of ' . $entry['entry_no'] . ($reason ? ': ' . $reason : ''),
            'created_by' => $userId,
            'lines' => $reversedLines,
        ]);
        $db->prepare("UPDATE journal_entries SET status='Void' WHERE id=?")->execute([$entryId]);
        return $reversalId;
    });
}

function generate_entry_no(PDO $db): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM journal_entries WHERE entry_no LIKE ?");
    $stmt->execute(["JE-{$year}-%"]);
    $cnt = (int)$stmt->fetch()['cnt'] + 1;
    return sprintf('JE-%s-%05d', $year, $cnt);
}

function next_document_no(string $prefix, string $table, string $column = 'created_at'): string {
    $db = get_db();
    $year = date('Y');
    $col = $column === 'created_at' ? '' : '';
    $stmt = $db->query("SELECT COUNT(*) AS cnt FROM {$table} WHERE YEAR(created_at) = {$year}");
    $cnt = (int)$stmt->fetch()['cnt'] + 1;
    return sprintf('%s-%s-%05d', $prefix, $year, $cnt);
}

/** Signed balance per the account's normal_balance convention (Debit or Credit). */
function get_account_balance(int $accountId, ?string $asOfDate = null): float {
    $db = get_db();
    $sql = "SELECT a.normal_balance, COALESCE(SUM(jl.debit),0) AS td, COALESCE(SUM(jl.credit),0) AS tc
            FROM coa_accounts a
            LEFT JOIN journal_lines jl ON jl.account_id = a.id
            LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.status = 'Posted'";
    $params = [$accountId];
    $sql .= " WHERE a.id = ?";
    if ($asOfDate) {
        $sql .= " AND (je.entry_date <= ? OR je.entry_date IS NULL)";
        $params[] = $asOfDate;
    }
    $sql .= " GROUP BY a.id, a.normal_balance";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) return 0.0;
    $debit = (float)$row['td'];
    $credit = (float)$row['tc'];
    return $row['normal_balance'] === 'Debit' ? ($debit - $credit) : ($credit - $debit);
}

/** Trial balance: every account with its total debit/credit movement, as of an optional date. */
function get_trial_balance(?string $asOfDate = null): array {
    $db = get_db();
    $sql = "SELECT a.id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                   COALESCE(SUM(jl.debit),0) AS total_debit, COALESCE(SUM(jl.credit),0) AS total_credit
            FROM coa_accounts a
            LEFT JOIN journal_lines jl ON jl.account_id = a.id
            LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.status = 'Posted'";
    $params = [];
    if ($asOfDate) {
        $sql .= " AND je.entry_date <= ?";
        $params[] = $asOfDate;
    }
    $sql .= " WHERE a.is_active = 1 GROUP BY a.id ORDER BY a.account_code";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
