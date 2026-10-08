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

/**
 * Voids a posted entry and undoes its cash side: books the reversing journal entry,
 * then for every cash register row recorded with that entry, books the opposite
 * movement and restores the cash account balance. Returns the reversal entry id.
 */
function void_posting(int $entryId, int $userId, string $reason): int {
    return db_transaction(function (PDO $db) use ($entryId, $userId, $reason) {
        $reversalId = void_journal_entry($entryId, $userId, $reason);
        $rows = $db->prepare("SELECT * FROM cash_transactions WHERE journal_entry_id = ?");
        $rows->execute([$entryId]);
        $ins = $db->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, type, amount, reference, description, source_module, source_id, cash_flow_category, journal_entry_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $bal = $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?");
        foreach ($rows->fetchAll() as $r) {
            $wasInflow = in_array($r['type'], ['Deposit', 'TransferIn'], true);
            $ins->execute([$r['cash_account_id'], date('Y-m-d'), $wasInflow ? 'Withdrawal' : 'Deposit', $r['amount'], $r['reference'],
                'Void: ' . ($r['description'] ?? ''), $r['source_module'], $r['source_id'], $r['cash_flow_category'], $reversalId, $userId]);
            $bal->execute([$wasInflow ? -(float)$r['amount'] : (float)$r['amount'], $r['cash_account_id']]);
        }
        return $reversalId;
    });
}

/**
 * Voids an AP payment ('ap') or AR receipt ('ar'): takes the applied amounts back off
 * the bills/invoices (they return to Open or PartiallyPaid) and marks it Void.
 *
 * A payment made through a disbursement voucher or collection receipt shares that
 * document's journal entry, so it can only be voided by voiding the voucher/receipt,
 * which calls this with $viaSourceDocument = true and reverses the entry itself.
 * Otherwise this also reverses the payment's own entry, cash movement and any
 * withholding tax still Pending (tax already remitted blocks the void).
 */
function void_payment(string $kind, int $paymentId, int $userId, string $reason, bool $viaSourceDocument = false): void {
    [$table, $noCol, $appTable, $appFk, $docFk, $docTable, $paidCol, $srcTable, $srcFk, $srcNoCol, $srcLabel] = $kind === 'ap'
        ? ['ap_payments', 'payment_no', 'ap_payment_applications', 'payment_id', 'bill_id', 'ap_bills', 'amount_paid', 'disbursement_vouchers', 'ap_payment_id', 'dv_no', 'voucher']
        : ['ar_receipts', 'receipt_no', 'ar_receipt_applications', 'receipt_id', 'invoice_id', 'ar_invoices', 'amount_received', 'collection_receipts', 'ar_receipt_id', 'cr_no', 'collection receipt'];

    db_transaction(function (PDO $db) use ($kind, $paymentId, $userId, $reason, $viaSourceDocument, $table, $noCol, $appTable, $appFk, $docFk, $docTable, $paidCol, $srcTable, $srcFk, $srcNoCol, $srcLabel) {
        $sel = $db->prepare("SELECT * FROM {$table} WHERE id = ? FOR UPDATE");
        $sel->execute([$paymentId]);
        $pay = $sel->fetch();
        if (!$pay) throw new RuntimeException('Payment not found.');
        if ($pay['status'] === 'Void') throw new RuntimeException("{$pay[$noCol]} is already void.");

        if (!$viaSourceDocument) {
            $src = $db->prepare("SELECT {$srcNoCol} FROM {$srcTable} WHERE {$srcFk} = ?");
            $src->execute([$paymentId]);
            if ($srcNo = $src->fetchColumn()) {
                throw new RuntimeException("{$pay[$noCol]} was made through {$srcLabel} {$srcNo}. Void that {$srcLabel} instead.");
            }
            if ($kind === 'ap') {
                $tax = $db->prepare("SELECT status FROM tax_transactions WHERE source_module = 'AP' AND source_id = ? AND direction = 'Withholding'");
                $tax->execute([$paymentId]);
                if (in_array('Remitted', $tax->fetchAll(PDO::FETCH_COLUMN), true)) {
                    throw new RuntimeException("The withholding tax on {$pay[$noCol]} has already been remitted, so the payment can't be voided.");
                }
                $db->prepare("UPDATE tax_transactions SET status = 'Void' WHERE source_module = 'AP' AND source_id = ? AND direction = 'Withholding' AND status = 'Pending'")
                   ->execute([$paymentId]);
            }
            if ($pay['journal_entry_id']) {
                void_posting((int)$pay['journal_entry_id'], $userId, "Void of {$pay[$noCol]}" . ($reason !== '' ? ": {$reason}" : ''));
            }
        }

        $apps = $db->prepare("SELECT {$docFk} AS doc_id, SUM(amount_applied) AS amt FROM {$appTable} WHERE {$appFk} = ? GROUP BY {$docFk}");
        $apps->execute([$paymentId]);
        $lock = $db->prepare("SELECT status, {$paidCol} AS paid FROM {$docTable} WHERE id = ? FOR UPDATE");
        $upd = $db->prepare("UPDATE {$docTable} SET {$paidCol} = ?, status = ? WHERE id = ?");
        foreach ($apps->fetchAll() as $a) {
            $lock->execute([$a['doc_id']]);
            $doc = $lock->fetch();
            $newPaid = max(0, round((float)$doc['paid'] - (float)$a['amt'], 2));
            $newStatus = $doc['status'] === 'Void' ? 'Void' : ($newPaid > 0 ? 'PartiallyPaid' : 'Open');
            $upd->execute([$newPaid, $newStatus, $a['doc_id']]);
        }
        $db->prepare("UPDATE {$table} SET status = 'Void', voided_at = NOW(), void_reason = ? WHERE id = ?")
           ->execute([mb_substr($reason, 0, 255), $paymentId]);
    });
}

function generate_entry_no(PDO $db): string {
    return next_document_no('JE', 'journal_entries');
}

/** The column holding each table's document number. */
const DOCUMENT_NO_COLUMNS = [
    'journal_entries' => 'entry_no',
    'ap_bills' => 'bill_no',
    'ap_payments' => 'payment_no',
    'ar_invoices' => 'invoice_no',
    'ar_receipts' => 'receipt_no',
    'collection_receipts' => 'cr_no',
    'disbursement_vouchers' => 'dv_no',
    'cash_transfers' => 'transfer_no',
    'tax_remittances' => 'remittance_no',
];

/**
 * Next number in the PREFIX-YEAR-00001 series. The counter row in document_sequences
 * is locked until the caller's transaction ends, so two saves can't get the same
 * number, and a failed save rolls the counter back, so the series has no gaps (BIR
 * expects invoices and receipts in an unbroken series). The counter never falls below
 * the highest number already in the table, which covers seeded data and older rows
 * numbered before this table existed.
 */
function next_document_no(string $prefix, string $table): string {
    $column = DOCUMENT_NO_COLUMNS[$table] ?? null;
    if ($column === null) throw new InvalidArgumentException("No document number column known for {$table}.");
    return db_transaction(function (PDO $db) use ($prefix, $table, $column) {
        $year = (int)date('Y');
        // ON DUPLICATE KEY takes the row's exclusive lock at once. (INSERT IGNORE would take a
        // shared lock first, and two saves upgrading it to FOR UPDATE deadlock each other.)
        $db->prepare("INSERT INTO document_sequences (prefix, year, last_no) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE last_no = last_no")->execute([$prefix, $year]);
        $sel = $db->prepare("SELECT last_no FROM document_sequences WHERE prefix = ? AND year = ? FOR UPDATE");
        $sel->execute([$prefix, $year]);
        $last = (int)$sel->fetchColumn();

        $max = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX({$column}, '-', -1) AS UNSIGNED)), 0) FROM {$table} WHERE {$column} LIKE ?");
        $max->execute(["{$prefix}-{$year}-%"]);
        $next = max($last, (int)$max->fetchColumn()) + 1;

        $db->prepare("UPDATE document_sequences SET last_no = ? WHERE prefix = ? AND year = ?")->execute([$next, $prefix, $year]);
        return sprintf('%s-%d-%05d', $prefix, $year, $next);
    });
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
