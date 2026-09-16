<?php
/**
 * Shared LIMIT/OFFSET pagination.
 *
 * Adapted from includes/Pagination.php in the LGU IPMS project. The list screens here
 * previously ended in a hard "LIMIT 200", which does not just truncate the view -- it
 * silently hides every record past the two-hundredth, with nothing on screen to say so.
 * For a ledger that grows every day that is a correctness problem, not a cosmetic one.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

define('DEFAULT_PER_PAGE', 25);

/** Page number from the query string, floored at 1. */
function current_page(): int {
    return max(1, (int)($_GET['page'] ?? 1));
}

/**
 * Runs a count query and one page of a select query.
 *
 * $selectSql must NOT carry its own LIMIT/OFFSET -- this appends them. For a GROUP BY
 * or HAVING query, pass $countSql as "SELECT COUNT(*) FROM (<grouped query>) t", since a
 * plain COUNT(*) over a HAVING-filtered query counts the wrong thing.
 *
 * Returns ['data', 'total', 'page', 'per_page', 'last_page', 'from', 'to'].
 */
function paginate(PDO $db, string $selectSql, string $countSql, array $params, int $page, int $perPage = DEFAULT_PER_PAGE): array {
    $perPage = min(200, max(5, $perPage));

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $lastPage = max(1, (int)ceil($total / $perPage));
    // Clamp rather than show an empty page when a stale ?page= outlives the rows.
    $page = min(max(1, $page), $lastPage);
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET are interpolated, never bound: with ATTR_EMULATE_PREPARES off, MySQL
    // rejects placeholders in LIMIT. Both are integers by construction above.
    $stmt = $db->prepare($selectSql . " LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return [
        'data'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => $lastPage,
        'from'      => $total === 0 ? 0 : $offset + 1,
        'to'        => $offset + count($rows),
    ];
}

/**
 * Renders the pager. Every link carries the current query string forward, so filters
 * and sort choices survive a page change instead of silently resetting.
 */
function render_pagination(array $r): string {
    if ($r['total'] === 0) {
        return '';
    }

    $link = function (int $page): string {
        $qs = $_GET;
        $qs['page'] = $page;
        return e(current_page_url() . '?' . http_build_query($qs));
    };

    $html = '<div class="pagination-bar">';
    $html .= '<div class="pagination-summary">Showing <strong>' . number_format($r['from']) . '</strong>'
           . '&ndash;<strong>' . number_format($r['to']) . '</strong> of <strong>'
           . number_format($r['total']) . '</strong></div>';

    if ($r['last_page'] > 1) {
        $html .= '<div class="pagination">';
        $html .= $r['page'] > 1
            ? '<a class="page-link" href="' . $link($r['page'] - 1) . '" rel="prev">&larr; Prev</a>'
            : '<span class="page-link disabled">&larr; Prev</span>';

        // A window around the current page, with first/last always reachable, so the
        // control stays a fixed size whether there are 3 pages or 300.
        $window = 2;
        $start = max(1, $r['page'] - $window);
        $end   = min($r['last_page'], $r['page'] + $window);
        if ($start > 1) {
            $html .= '<a class="page-link" href="' . $link(1) . '">1</a>';
            if ($start > 2) $html .= '<span class="page-gap">&hellip;</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= $i === $r['page']
                ? '<span class="page-link active" aria-current="page">' . $i . '</span>'
                : '<a class="page-link" href="' . $link($i) . '">' . $i . '</a>';
        }
        if ($end < $r['last_page']) {
            if ($end < $r['last_page'] - 1) $html .= '<span class="page-gap">&hellip;</span>';
            $html .= '<a class="page-link" href="' . $link($r['last_page']) . '">' . $r['last_page'] . '</a>';
        }

        $html .= $r['page'] < $r['last_page']
            ? '<a class="page-link" href="' . $link($r['page'] + 1) . '" rel="next">Next &rarr;</a>'
            : '<span class="page-link disabled">Next &rarr;</span>';
        $html .= '</div>';
    }

    return $html . '</div>';
}
