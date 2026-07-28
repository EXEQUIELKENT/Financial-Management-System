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
    $db = get_db();
    $lines = $data['lines'] ?? [];
    if (count($lines) < 2) {
        throw new InvalidArgumentException('A journal entry requires at least two lines.');
    }
    $totalDebit = 0.0;
    $totalCredit = 0.0;
    foreach ($lines as $line) {
        $totalDebit += (float)($line['debit'] ?? 0);
        $totalCredit += (float)($line['credit'] ?? 0);
    }
    if (round($totalDebit, 2) !== round($totalCredit, 2)) {
        throw new InvalidArgumentException("Journal entry is not balanced: debits {$totalDebit} vs credits {$totalCredit}.");
    }

    $db->beginTransaction();
    try {
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

        $db->commit();
        return $entryId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/** Manual GL entries start as Draft and go through an explicit approve/post step. */
function create_draft_journal_entry(array $data): int {
    $db = get_db();
    $lines = $data['lines'] ?? [];
    $totalDebit = array_sum(array_column($lines, 'debit'));
    $totalCredit = array_sum(array_column($lines, 'credit'));

    $db->beginTransaction();
    try {
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
        $db->commit();
        return $entryId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
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

    $reversedLines = array_map(function ($l) {
        return [
            'account_id' => $l['account_id'],
            'debit' => (float)$l['credit'],
            'credit' => (float)$l['debit'],
            'department_id' => $l['department_id'],
            'memo' => 'Reversal: ' . $l['memo'],
        ];
    }, $origLines);

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
