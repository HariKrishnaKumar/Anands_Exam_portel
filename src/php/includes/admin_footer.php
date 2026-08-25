<?php
/**
 * Admin layout footer — closes content area and includes scripts.
 */
?>
        </main>
    </div>
</div>

<script>
// ─── Initialize Lucide Icons ────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

// ─── Sidebar Toggle (smart: desktop collapse / mobile off-canvas) ─
function toggleCollapse() {
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        toggleSidebar();
        return;
    }
    const layout = document.getElementById('appLayout');
    const isCollapsed = layout.classList.toggle('sidebar-collapsed');
    document.cookie = 'sidebar_collapsed=' + (isCollapsed ? '1' : '0') + ';path=/;max-age=31536000';
}

// ─── Mobile Off-Canvas Sidebar ───────────────────────────
function toggleSidebar(forceState) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isOpen = forceState !== undefined ? forceState : !sidebar.classList.contains('open');

    sidebar.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);

    // Body scroll lock
    document.body.classList.toggle('sidebar-open', isOpen);
}

function closeSidebar() {
    toggleSidebar(false);
}

// Close sidebar on escape key + overlay click + focus trap
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
        // Close profile dropdown
        document.getElementById('profileMenu')?.classList.remove('open');
        // Close notification panel
        const notifBtn = document.getElementById('notifBtn');
        if (notifBtn) notifBtn.classList.remove('notif-open');
    }
});

// Focus trap inside sidebar when open
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    const sidebar = document.getElementById('sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;

    const focusable = sidebar.querySelectorAll(
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
});

// ─── Profile Dropdown Toggle ─────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const profileMenu = document.getElementById('profileMenu');
    if (profileMenu) {
        profileMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            // Close notifications if open
            document.getElementById('notifBtn')?.classList.remove('notif-open');
            this.classList.toggle('open');
        });
    }
});

// ─── Notifications Panel Toggle ─────────────────────────
function toggleNotifications() {
    const btn = document.getElementById('notifBtn');
    if (btn) {
        // Close profile if open
        document.getElementById('profileMenu')?.classList.remove('open');
        btn.classList.toggle('notif-open');
    }
}

// ─── Theme Switcher ─────────────────────────────────────
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    document.cookie = 'theme=' + newTheme + ';path=/;max-age=31536000';
    updateThemeUI(newTheme);
}

function updateThemeUI(theme) {
    const label = document.getElementById('themeLabel');
    if (label) label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
}

// Restore saved theme
(function() {
    const saved = document.cookie.split('; ').find(r => r.startsWith('theme='));
    if (saved) {
        const theme = saved.split('=')[1];
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            updateThemeUI('dark');
        }
    }
})();

// ─── Mobile: Auto-generate data-label for tables ──────
(function() {
    function addTableLabels() {
        document.querySelectorAll('.data-table').forEach(function(table) {
            const headers = [];
            const thead = table.querySelector('thead');
            if (!thead) return;
            thead.querySelectorAll('th').forEach(function(th) {
                headers.push(th.textContent.trim());
            });
            if (headers.length === 0) return;

            table.querySelectorAll('tbody tr').forEach(function(tr) {
                tr.querySelectorAll('td').forEach(function(td, idx) {
                    if (idx < headers.length && !td.hasAttribute('data-label')) {
                        td.setAttribute('data-label', headers[idx]);
                    }
                });
            });
        });
    }
    addTableLabels();
    // Re-run on dynamic content changes
    const observer = new MutationObserver(addTableLabels);
    observer.observe(document.body, { childList: true, subtree: true });
})();

// ─── Mobile: enhance stepper scroll affordance ─────────
(function() {
    function addScrollHint() {
        const steppers = document.querySelectorAll('.studio-stepper');
        if (window.innerWidth <= 767) {
            steppers.forEach(function(s) {
                if (s.scrollWidth > s.clientWidth && !s.dataset.scrollHint) {
                    s.dataset.scrollHint = 'true';
                    s.style.setProperty('--scroll-hint', '1');
                }
            });
        }
    }
    addScrollHint();
    window.addEventListener('resize', addScrollHint);
})();

// ─── Close dropdowns on outside click ───────────────────
document.addEventListener('click', function(e) {
    // Profile dropdown
    const profileMenu = document.getElementById('profileMenu');
    if (profileMenu && !e.target.closest('.topnav-profile')) {
        profileMenu.classList.remove('open');
    }
    // Notification panel
    const notifBtn = document.getElementById('notifBtn');
    if (notifBtn && !e.target.closest('.topnav-btn')) {
        notifBtn.classList.remove('notif-open');
    }
    // General overflow menus
    document.querySelectorAll('.overflow-menu.open').forEach(m => m.classList.remove('open'));
});

// ─── Global Search ───────────────────────────────────────
(function() {
    const searchData = [
        <?php foreach ($navSections as $section): ?>
            <?php foreach ($section['items'] as $item): ?>
                { label: '<?= h($item['label']) ?>', section: '<?= h($section['label']) ?>', url: '<?= BASE_URL ?>/admin/<?= $item['url'] ?>', icon: '<?= $item['icon'] ?>' },
            <?php endforeach; ?>
        <?php endforeach; ?>
        { label: 'Dashboard', section: 'General', url: '<?= BASE_URL ?>/admin/dashboard.php', icon: 'dashboard' },
    ];

    const input = document.getElementById('globalSearch');
    const dropdown = document.getElementById('searchDropdown');
    let activeIdx = -1;
    let results = [];

    function fuzzyMatch(query, text) {
        const q = query.toLowerCase();
        const t = text.toLowerCase();
        let qi = 0;
        for (let ti = 0; ti < t.length && qi < q.length; ti++) {
            if (q[qi] === t[ti]) qi++;
        }
        return qi === q.length;
    }

    function buildResults() {
        const q = input.value.trim();
        if (!q) { dropdown.classList.remove('open'); return; }

        results = searchData.filter(item =>
            fuzzyMatch(q, item.label) || fuzzyMatch(q, item.section)
        );

        if (results.length === 0) {
            dropdown.innerHTML = '<div class="search-dropdown-empty">No results found</div>';
            dropdown.classList.add('open');
            activeIdx = -1;
            return;
        }

        // Group by section
        const grouped = {};
        results.forEach(r => {
            if (!grouped[r.section]) grouped[r.section] = [];
            grouped[r.section].push(r);
        });

        let html = '';
        let idx = 0;
        for (const [section, items] of Object.entries(grouped)) {
            html += '<div class="search-section-label">' + section + '</div>';
            items.forEach(item => {
                const re = new RegExp('(' + input.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                const highlighted = item.label.replace(re, '<em>$1</em>');
                html += '<a href="' + item.url + '" class="search-dropdown-item" data-index="' + idx + '">'
                    + '<span class="sdi-icon">' + getSearchIcon(item.icon) + '</span>'
                    + '<span class="sdi-label">' + highlighted + '</span>'
                    + '<span class="sdi-meta">' + item.section + '</span>'
                    + '</a>';
                idx++;
            });
        }
        dropdown.innerHTML = html;
        dropdown.classList.add('open');
        activeIdx = -1;

        // Click handler on items
        dropdown.querySelectorAll('.search-dropdown-item').forEach(el => {
            el.addEventListener('click', function(e) {
                // Let the native link handle navigation
            });
        });
    }

    function getSearchIcon(name) {
        // Map old icon names to Lucide names (matches the PHP getLucideMap())
        const lucideMap = {
            dashboard: 'layout-dashboard',
            college: 'building2',
            course: 'book-open',
            batch: 'layers',
            student: 'graduation-cap',
            test: 'file-text',
            clock: 'clock',
            external: 'external-link',
            plus: 'circle-plus',
            document: 'file-text',
            database: 'database',
            status: 'circle-dot',
            pulse: 'activity',
            grading: 'clipboard-check',
            chart: 'chart-bar-big',
            reports: 'chart-bar-big',
            activity: 'activity',
            warning: 'triangle-alert',
            settings: 'settings',
            help: 'circle-help',
        };
        const lucideName = lucideMap[name] || 'layout-dashboard';
        return '<i data-lucide="' + lucideName + '" width="16" height="16"></i>';
    }

    // Input handler
    input.addEventListener('input', buildResults);

    // Focus handler
    input.addEventListener('focus', function() {
        if (input.value.trim()) buildResults();
    });

    // Keyboard navigation
    input.addEventListener('keydown', function(e) {
        const items = dropdown.querySelectorAll('.search-dropdown-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
            items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
            if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
            items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
            if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) {
                window.location.href = items[activeIdx].href;
            } else if (results.length === 1) {
                window.location.href = items[0].href;
            }
        } else if (e.key === 'Escape') {
            input.blur();
            dropdown.classList.remove('open');
        }
    });

    // Close on blur (delayed to allow click)
    input.addEventListener('blur', function() {
        setTimeout(function() { dropdown.classList.remove('open'); }, 200);
    });

    // Ctrl+K shortcut
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
</script>

<script>
// ─── Notification Bell: live feed (poll + mark read) ───────────
// Lives in the footer so it survives soft-nav (only main.content-area swaps).
(function() {
    'use strict';
    var NOTIF_API = <?= json_encode(BASE_URL . '/admin/notifications.php') ?>;
    var CSRF      = <?= json_encode(getCsrfToken()) ?>;

    function setBadge(count) {
        var badge = document.getElementById('notifBadge');
        if (!badge) return;
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.style.display = count > 0 ? '' : 'none';
        var ma = document.getElementById('notifMarkAll');
        if (ma) ma.style.display = count > 0 ? '' : 'none';
        var h3 = document.querySelector('.notif-panel-header h3');
        var unreadSpan = document.getElementById('notifUnreadText');
        if (unreadSpan) {
            if (count > 0) unreadSpan.textContent = '(' + count + ' unread)';
            else unreadSpan.remove();
        }
    }

    function refreshNotifs() {
        fetch(NOTIF_API + '?action=poll', { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function(r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
            .then(function(d) {
                setBadge(d.count || 0);
                var body = document.getElementById('notifPanelBody');
                if (body && d.html) body.innerHTML = d.html;
                if (window.lucide && lucide.createIcons) { try { lucide.createIcons(); } catch (e) {} }
            })
            .catch(function() { /* server unreachable — keep current feed */ });
    }

    document.addEventListener('click', function(e) {
        var ma = e.target.closest('#notifMarkAll');
        if (ma) {
            e.preventDefault();
            var fd = new FormData();
            fd.append('csrf_token', CSRF);
            fetch(NOTIF_API + '?action=mark_all', { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r) { return r.json(); })
                .then(function() { refreshNotifs(); })
                .catch(function() {});
            return;
        }
        var item = e.target.closest('.notif-item[data-id]');
        if (item && !item.classList.contains('is-read')) {
            var fd2 = new FormData();
            fd2.append('csrf_token', CSRF);
            fd2.append('id', item.getAttribute('data-id'));
            fetch(NOTIF_API + '?action=mark_one', { method: 'POST', credentials: 'same-origin', body: fd2 })
                .then(function(r) { return r.json(); })
                .then(function() { item.classList.add('is-read'); refreshNotifs(); })
                .catch(function() {});
        }
    });

    // Initial sync shortly after load, then poll every 30s
    setTimeout(refreshNotifs, 1200);
    setInterval(refreshNotifs, 30000);
})();
</script>

<script>
// ─── Soft Navigation (left sidebar) ────────────────────────────
// Intercepts clicks on left-nav items and swaps only the main
// content area via fetch(). The sidebar DOM is never touched, so
// its scroll position, active item and collapse state persist.
(function() {
    'use strict';
    if (!window.history || !history.pushState) return; // fallback: default full navigation

    var main = document.querySelector('main.content-area');
    if (!main) return;

    var pendingNav = false;
    var trackedTimers = []; // intervals/timeouts created by page scripts (cleared on next nav)

    function isSoftLink(a) {
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#') return false;
        if (!a.closest('.sidebar-nav')) return false;      // left navigation links only
        if (a.target && a.target !== '_self') return false; // keep new-tab links native
        if (href.indexOf('logout') !== -1) return false;    // logout must stay a real request
        return true;
    }

    // Mirror of PHP isNavActive(): match by page name + optional ?tab=
    function isActive(a, url) {
        var aUrl = new URL(a.getAttribute('href'), location.href);
        var u = new URL(url, location.href);
        if (aUrl.pathname.replace(/\/+$/, '') !== u.pathname.replace(/\/+$/, '')) return false;
        var aTab = aUrl.searchParams.get('tab') || '';
        var uTab = u.searchParams.get('tab') || '';
        if (!aTab && !uTab) return true;
        return !!aTab && aTab === uTab;
    }

    function setActive(url) {
        document.querySelectorAll('.sidebar-nav a.nav-item').forEach(function(a) {
            var on = isActive(a, url);
            a.classList.toggle('active', on);
            if (on) a.setAttribute('aria-current', 'page');
            else a.removeAttribute('aria-current');
        });
    }

    function clearTrackedTimers() {
        trackedTimers.forEach(function(t) { clearInterval(t); });
        trackedTimers = [];
    }

    // Execute one inline script. While it runs:
    //  - DOMContentLoaded registrations are collected and invoked immediately
    //  - setInterval/setTimeout are intercepted so they can be cleared on next nav
    function execInline(text, container, done) {
        var dclQueue = [];
        var created = [];
        var origAdd = document.addEventListener;
        var origSI = window.setInterval;
        var origST = window.setTimeout;

        document.addEventListener = function(type, fn, opts) {
            if (type === 'DOMContentLoaded') { dclQueue.push(fn); return; }
            return origAdd.call(this, type, fn, opts);
        };
        window.setInterval = function(fn, ms) { var id = origSI(fn, ms); created.push(id); return id; };
        window.setTimeout = function(fn, ms) { var id = origST(fn, ms); created.push(id); return id; };

        var s = document.createElement('script');
        s.textContent = text;
        container.appendChild(s);

        document.addEventListener = origAdd;
        window.setInterval = origSI;
        window.setTimeout = origST;

        trackedTimers = trackedTimers.concat(created);
        dclQueue.forEach(function(fn) { try { fn(); } catch (e) { /* page script error — keep going */ } });
        done();
    }

    function execExternal(src, container, done) {
        var s = document.createElement('script');
        s.src = src;
        s.async = false;
        s.onload = done;
        s.onerror = done;
        container.appendChild(s);
    }

    // Re-run page scripts in original order (external -> inline) so
    // libs like Chart.js are available to the inline init code.
    function injectScripts(specs, container, done) {
        var i = 0;
        function next() {
            if (i >= specs.length) { done(); return; }
            var spec = specs[i++];
            if (spec.src) execExternal(spec.src, container, next);
            else execInline(spec.text, container, next);
        }
        next();
    }

    function navigate(url) {
        if (pendingNav) return;
        pendingNav = true;

        fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function(res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.text(); })
            .then(function(html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newMain = doc.querySelector('main.content-area');
                if (!newMain) { window.location.href = url; return; } // session expired / error page → full nav

                var t = doc.title;
                if (t) document.title = t;

                var specs = Array.from(newMain.querySelectorAll('script')).map(function(s) {
                    return { src: s.getAttribute('src') || '', text: s.textContent || '' };
                });

                clearTrackedTimers();
                main.innerHTML = newMain.innerHTML;
                setActive(url);
                if (url !== location.href) history.pushState({ url: url }, '', url);

                injectScripts(specs, main, function() {
                    if (window.lucide && lucide.createIcons) { try { lucide.createIcons(); } catch (e) {} }
                    pendingNav = false;
                    // Mobile: close the off-canvas drawer after navigating
                    if (window.innerWidth <= 768 && window.closeSidebar) try { closeSidebar(); } catch (e) {}
                });
            })
            .catch(function() {
                pendingNav = false;
                window.location.href = url; // network error → fallback to full navigation
            });
    }

    // Intercept left-nav clicks (primary button, no modifier keys)
    document.addEventListener('click', function(e) {
        if (e.defaultPrevented || e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return; // preserve shortcuts / new tab
        var a = e.target.closest('a.nav-item');
        if (!a || !isSoftLink(a)) return;

        var url = a.href;
        if (url === location.href) { e.preventDefault(); return; } // same page → never reload
        e.preventDefault();
        navigate(url);
    }, true);

    // Back / forward through the in-app history
    window.addEventListener('popstate', function() {
        pendingNav = false;
        navigate(location.href);
    });

    // No browser scroll-restoration jumps on popstate
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
})();
</script>
</body>
</html>
