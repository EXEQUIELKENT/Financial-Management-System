<?php
/**
 * Permission-gated sidebar navigation. Included by header.php on every page.
 * Each item is only rendered when has_permission() is true for its key, so
 * Auditors see every module (view-only) while Accountants don't see
 * Users/Settings and non-Admins never see Admin-only entries.
 */
// REQUEST_URI is URL-encoded by the browser (spaces become %20), but BASE_URL/href
// values are built from the literal filesystem folder name -- decode before comparing,
// otherwise "Financial Management System" (with real spaces) never matches
// "Financial%20Management%20System" and no sidebar item is ever marked active.
$currentPath = urldecode(strtok($_SERVER['REQUEST_URI'], '?'));

// Each item is [abbr, label, url, permission, children?]. children (when present) is a
// flat list of [label, filename] pairs for the other pages that live inside that same
// module folder -- e.g. Cash Management's hub is cash-position.php, and its children are
// the sibling pages (accounts.php, transactions.php, ...) that used to only be reachable
// via in-page "Back"/toolbar buttons. Rendered as an indented sub-list directly under the
// parent, but only while a page inside that module is open, so the sidebar doesn't grow to
//9 modules x every sub-page all the time -- it expands exactly where you currently are.
$navSections = [
    [
        'items' => [
            ['?', 'Getting Started', BASE_URL . '/modules/help/index.php', 'dashboard.view'],
            ['GL', 'Dashboard', BASE_URL . '/modules/dashboard/index.php', 'dashboard.view'],
        ],
    ],
    [
        'label' => 'Financial Management',
        'items' => [
            ['GL', 'General Ledger', BASE_URL . '/modules/gl/journal-entries.php', 'gl.view', [
                ['Chart of Accounts', 'chart-of-accounts.php'],
                ['Account Ledger', 'account-ledger.php'],
            ]],
            ['AP', 'Accounts Payable', BASE_URL . '/modules/ap/bills.php', 'ap.view', [
                ['Vendors', 'vendors.php'],
                ['Payments', 'payments.php'],
                ['Aging Report', 'aging-report.php'],
            ]],
            ['AR', 'Accounts Receivable', BASE_URL . '/modules/ar/invoices.php', 'ar.view', [
                ['Customers', 'customers.php'],
                ['Receipts', 'receipts.php'],
                ['Aging Report', 'aging-report.php'],
            ]],
            ['DV', 'Disbursement Mgmt.', BASE_URL . '/modules/disbursement/vouchers.php', 'disbursement.view', [
                ['Approval Queue', 'approval-queue.php', 'disbursement.approve'],
            ]],
            ['CR', 'Collection Mgmt.', BASE_URL . '/modules/collection/receipts.php', 'collection.view', [
                ['Approval Queue', 'approval-queue.php', 'collection.approve'],
            ]],
            ['BU', 'Budget Management', BASE_URL . '/modules/budget/budgets.php', 'budget.view', [
                ['Budget Periods', 'periods.php'],
                ['Variance Report', 'variance-report.php'],
            ]],
            ['CA', 'Cash Management', BASE_URL . '/modules/cash/cash-position.php', 'cash.view', [
                ['Accounts', 'accounts.php'],
                ['Transactions', 'transactions.php'],
                ['Transfers', 'transfers.php'],
                ['Reconciliation', 'reconciliation.php'],
            ]],
            ['RP', 'Financial Reports', BASE_URL . '/modules/reports/index.php', 'reports.view', [
                ['Trial Balance', 'trial-balance.php'],
                ['Income Statement', 'income-statement.php'],
                ['Balance Sheet', 'balance-sheet.php'],
                ['Cash Flow', 'cash-flow.php'],
                ['Custom Report', 'custom-report.php'],
            ]],
            ['TX', 'Tax Management', BASE_URL . '/modules/tax/tax-types.php', 'tax.view', [
                ['Tax Transactions', 'transactions.php'],
                ['Remittances', 'remittances.php'],
                ['Compliance Summary', 'compliance-report.php'],
            ]],
        ],
    ],
    [
        'label' => 'Administration',
        'items' => [
            ['US', 'Users', BASE_URL . '/modules/users/list.php', 'users.view'],
            ['ST', 'Settings', BASE_URL . '/modules/settings/system-settings.php', 'settings.view'],
            ['AU', 'Audit Log', BASE_URL . '/modules/audit/audit-log.php', 'audit.view'],
        ],
    ],
];
?>
<div class="sidebar" id="sidebarNav">
    <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar" title="Collapse sidebar">
        <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <div class="sidebar-inner">
        <div class="sidebar-brand">
            <?= logo_or_image(40, true) ?>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($navSections as $section): ?>
                <?php
                    $visibleItems = array_filter($section['items'], fn($i) => has_permission($i[3]));
                    if (empty($visibleItems)) continue;
                ?>
                <?php if (!empty($section['label'])): ?>
                    <div class="sidebar-section"><span class="sidebar-label"><?= e($section['label']) ?></span></div>
                <?php endif; ?>
                <?php foreach ($visibleItems as $item): ?>
                    <?php
                        [$abbr, $label, $url, $perm] = $item;
                        $children = $item[4] ?? [];
                        // Match on the module's folder (e.g. ".../modules/ap/"), not the exact
                        // file -- otherwise navigating to bill-view.php, vendor-view.php,
                        // aging-report.php etc. (every page besides the one literal sidebar
                        // link) would silently lose the active highlight.
                        $urlPath = parse_url($url, PHP_URL_PATH);
                        $moduleDir = substr($urlPath, 0, strrpos($urlPath, '/') + 1);
                        $isActive = str_starts_with($currentPath, $moduleDir);
                    ?>
                    <a href="<?= $url ?>" class="<?= $isActive ? 'active' : '' ?>" title="<?= e($label) ?>">
                        <span class="sidebar-icon"><?= e($abbr) ?></span><span class="sidebar-label"><?= e($label) ?></span>
                    </a>
                    <?php if ($isActive && !empty($children)): ?>
                        <?php $visibleChildren = array_filter($children, fn($c) => has_permission($c[2] ?? $perm)); ?>
                        <?php if (!empty($visibleChildren)): ?>
                        <div class="sidebar-subnav">
                            <?php foreach ($visibleChildren as $child): ?>
                                <?php
                                    [$childLabel, $childFile] = $child;
                                    $childActive = $currentPath === $moduleDir . $childFile;
                                ?>
                                <a href="<?= $moduleDir . $childFile ?>" class="<?= $childActive ? 'active' : '' ?>"><?= e($childLabel) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
