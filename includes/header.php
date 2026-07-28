<?php
/**
 * Shared layout header. Every module page sets $pageTitle (and optionally
 * $activeMenu to override the auto-detected active sidebar link) then
 * includes this file, followed by page content, followed by footer.php.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logo-placeholder.php';
require_login();

$pageTitle = $pageTitle ?? APP_SHORT_NAME;
$user = current_user();
$flashes = flash();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){
    var t=localStorage.getItem('theme');
    if(t)document.documentElement.setAttribute('data-theme',t);
    if(localStorage.getItem('sidebarCollapsed')==='1')document.documentElement.classList.add('sidebar-collapsed');
})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> - <?= e(APP_SHORT_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/variables.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-area">
    <div class="topbar">
        <button type="button" class="mobile-menu-btn" onclick="toggleSidebarCollapse()" aria-label="Open menu" title="Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="topbar-title"><?= e($pageTitle) ?></div>
        <div class="topbar-user">
            <span class="topbar-clock" id="topbarClock"></span>
            <button type="button" class="theme-switch" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle dark mode" title="Toggle dark / light mode">
                <span class="theme-switch-track">
                    <span class="theme-switch-knob">
                        <svg class="theme-switch-icon sun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                        <svg class="theme-switch-icon moon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </span>
                </span>
            </button>
            <span class="role-pill"><?= e($user['role_name']) ?></span>
            <span><?= e($user['full_name']) ?></span>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline btn-sm" data-confirm="Are you sure you want to log out?">Logout</a>
        </div>
    </div>
    <div class="content">
    <?php if (!empty($flashes)): foreach ($flashes as $f): ?>
        <div class="alert alert-<?= $f['type'] === 'error' ? 'critical' : e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; endif; ?>
