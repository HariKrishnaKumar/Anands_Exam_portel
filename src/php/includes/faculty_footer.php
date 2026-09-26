<?php
/**
 * Faculty layout footer — closes the admin-skin chrome with only the
 * handlers faculty actually needs (sidebar toggle, profile dropdown, theme).
 *
 * Deliberately does NOT include admin_footer.php: that file renders the
 * admin global search over $navSections and polls admin notification APIs,
 * neither of which a read-only faculty session should touch.
 */
?>
        </main>
    </div>
</div>

<script>
// ─── Initialize Lucide Icons ────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

// ─── Sidebar Toggle (desktop collapse / mobile off-canvas) ──
function toggleCollapse() {
    const isMobile = window.innerWidth <= 768;
    if (isMobile) { toggleSidebar(); return; }
    const layout = document.getElementById('appLayout');
    const isCollapsed = layout.classList.toggle('sidebar-collapsed');
    document.cookie = 'sidebar_collapsed=' + (isCollapsed ? '1' : '0') + ';path=/;max-age=31536000';
}

function toggleSidebar(forceState) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isOpen = forceState !== undefined ? forceState : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('sidebar-open', isOpen);
}

function closeSidebar() { toggleSidebar(false); }

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeSidebar();
        document.getElementById('profileMenu')?.classList.remove('open');
    }
});

// ─── Profile Dropdown Toggle ────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const profileMenu = document.getElementById('profileMenu');
    if (profileMenu) {
        profileMenu.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('open');
        });
    }
});

document.addEventListener('click', function (e) {
    const profileMenu = document.getElementById('profileMenu');
    if (profileMenu && !e.target.closest('.topnav-profile')) {
        profileMenu.classList.remove('open');
    }
});

// ─── Theme Switcher (shared cookie with admin skin) ─────
function toggleTheme() {
    const html = document.documentElement;
    const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    document.cookie = 'theme=' + newTheme + ';path=/;max-age=31536000';
}

(function () {
    const saved = document.cookie.split('; ').find(function (r) { return r.indexOf('theme=') === 0; });
    if (saved) {
        const theme = saved.split('=')[1];
        if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    }
})();
</script>
</body>
</html>
