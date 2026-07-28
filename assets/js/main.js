function isDarkThemeActive() {
    var current = document.documentElement.getAttribute('data-theme');
    if (current) return current === 'dark';
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function updateThemeToggleUI() {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.classList.toggle('is-dark', isDarkThemeActive());
}

function toggleTheme() {
    var next = isDarkThemeActive() ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    updateThemeToggleUI();
}

function toggleSidebarCollapse() {
    var isMobile = window.matchMedia('(max-width: 900px)').matches;
    if (isMobile) {
        var sidebar = document.getElementById('sidebarNav');
        if (sidebar) sidebar.classList.toggle('open');
        return;
    }
    var collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
}

function updateTopbarClock() {
    var el = document.getElementById('topbarClock');
    if (!el) return;
    var now = new Date();
    var dateStr = now.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
    var timeStr = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    el.textContent = dateStr + ' · ' + timeStr;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    updateThemeToggleUI();
    updateTopbarClock();
    setInterval(updateTopbarClock, 1000);
});
