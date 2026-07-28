<?php
/**
 * Permission-gated sidebar navigation. Included by header.php on every page.
 * Each item is only rendered when has_permission() is true for its key, so
 * Auditors see every module (view-only) while Accountants don't see
 * Users/Settings and non-Admins never see Admin-only entries.
 */
$currentPath = strtok($_SERVER['REQUEST_URI'], '?');

$navSections = [
    [
        'items' => [
            ['GL', 'Dashboard', BASE_URL . '/modules/dashboard/index.php', 'dashboard.view'],
        ],
    ],
    [
        'label' => 'Financial Management',
        'items' => [
            ['GL', 'General Ledger', BASE_URL . '/modules/gl/journal-entries.php', 'gl.view'],
            ['AP', 'Accounts Payable', BASE_URL . '/modules/ap/bills.php', 'ap.view'],
            ['AR', 'Accounts Receivable', BASE_URL . '/modules/ar/invoices.php', 'ar.view'],
            ['DV', 'Disbursement Mgmt.', BASE_URL . '/modules/disbursement/vouchers.php', 'disbursement.view'],
            ['CR', 'Collection Mgmt.', BASE_URL . '/modules/collection/receipts.php', 'collection.view'],
            ['BU', 'Budget Management', BASE_URL . '/modules/budget/budgets.php', 'budget.view'],
            ['CA', 'Cash Management', BASE_URL . '/modules/cash/cash-position.php', 'cash.view'],
            ['RP', 'Financial Reports', BASE_URL . '/modules/reports/index.php', 'reports.view'],
            ['TX', 'Tax Management', BASE_URL . '/modules/tax/tax-types.php', 'tax.view'],
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
                <?php foreach ($visibleItems as [$abbr, $label, $url, $perm]): ?>
                    <?php $isActive = str_starts_with($currentPath, parse_url($url, PHP_URL_PATH)); ?>
                    <a href="<?= $url ?>" class="<?= $isActive ? 'active' : '' ?>" title="<?= e($label) ?>">
                        <span class="sidebar-icon"><?= e($abbr) ?></span><span class="sidebar-label"><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
