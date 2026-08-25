<?php
/**
 * Enterprise Icon System — Apple SF Symbols → Lucide → Material Symbols
 * 
 * RENDER STRATEGY (solves the deferred-Lucide-not-loaded problem):
 * 
 * 1. Material Symbols font is loaded in <head> (synchronous CSS, renders immediately)
 * 2. Every icon() call outputs a <span> containing BOTH:
 *    - <i data-lucide="...">  → replaced with SVG when Lucide JS loads
 *    - <span class="icn-fallback material-symbols-outlined"> → visible IMMEDIATELY
 * 3. CSS hides the Material fallback once the Lucide <svg> sibling appears
 * 4. If Lucide never loads (JS fails/deferred), Material Symbols remain visible
 * 
 * This guarantees: NO EMPTY ICON CONTAINERS, EVER.
 * 
 * Priority: Apple SF Symbols naming → Lucide (rendered) → Material (fallback)
 * 
 * Usage:
 *   <?= icon('file-text') ?>              ← size 20, default color
 *   <?= icon('check-circle', 24) ?>        ← size 24
 *   <?= icon('clock', 20, '#F59E0B') ?>    ← custom color
 */

function icon(string $name, int $size = 20, string $color = 'currentColor', string $className = ''): string {
    $def = getIconDef($name);
    $resolvedColor = resolveIconColor($color);
    $sizeAttr = h((string)$size);
    $safeColor = h($resolvedColor);

    // Wrapper — sizes the icon container, holds both Lucide + Material fallback
    $html = '<span class="icn"';
    $html .= ' style="--icn-s:' . $sizeAttr . 'px;--icn-c:' . $safeColor . ';"';
    $html .= ' role="img" aria-label="' . h($def['label']) . '"';
    if ($className) {
        $html .= ' class="' . h($className) . '"';
    }
    $html .= '>';

    // 1) Lucide element — replaced with inline SVG when Lucide JS initializes
    $colorAttr = $resolvedColor !== 'currentColor' ? ' color="' . $safeColor . '"' : '';
    $html .= '<i data-lucide="' . h($def['lucide']) . '"';
    $html .= ' width="' . $sizeAttr . '" height="' . $sizeAttr . '"';
    $html .= $colorAttr;
    $html .= '></i>';

    // 2) Material Symbols fallback — renders immediately via CSS font, no JS needed
    //    Hidden by CSS when Lucide <svg> sibling appears
    $html .= '<span class="icn-fallback material-symbols-outlined" aria-hidden="true">';
    $html .= h($def['material']);
    $html .= '</span>';

    $html .= '</span>';
    return $html;
}

function iconSprite(): string {
    return '';
}

/**
 * Resolve CSS variable colors to hex for inline use.
 */
function resolveIconColor(string $color): string {
    static $map = [
        'var(--green)'  => '#22C55E',
        'var(--red)'    => '#EF4444',
        'var(--accent)' => '#4F8CFF',
        'var(--yellow)' => '#F59E0B',
        'var(--orange)' => '#F97316',
        'var(--purple)' => '#7C3AED',
        'var(--info)'   => '#06B6D4',
        'var(--white)'  => '#FFFFFF',
    ];
    return $map[$color] ?? $color;
}

/**
 * Get icon definition array.
 * Maps friendly/SF Symbols names → Lucide name → Material name → aria-label
 */
function getIconDef(string $name): array {
    static $defs = null;
    if ($defs === null) {
        $defs = getIconDefinitions();
    }
    return $defs[$name] ?? $defs['fallback'];
}

/**
 * Master icon definition table.
 * Key = friendly name used in icon('name') calls.
 * Can be SF Symbols style (doc.text.fill) or short form (file-text).
 */
function getIconDefinitions(): array {
    return [

        // ── FALLBACK (when name not found) ────────────────
        'fallback' => [
            'lucide'   => 'square',
            'material' => 'square',
            'label'    => 'icon',
        ],

        // ── SIDEBAR / NAVIGATION ──────────────────────────
        'dashboard' => [
            'lucide'   => 'layout-dashboard',
            'material' => 'dashboard',
            'label'    => 'Dashboard',
        ],
        'layout-dashboard' => [
            'lucide'   => 'layout-dashboard',
            'material' => 'dashboard',
            'label'    => 'Dashboard',
        ],
        'college' => [
            'lucide'   => 'building2',
            'material' => 'business',
            'label'    => 'College',
        ],
        'building2' => [
            'lucide'   => 'building2',
            'material' => 'business',
            'label'    => 'College',
        ],
        'course' => [
            'lucide'   => 'book-open',
            'material' => 'book',
            'label'    => 'Course',
        ],
        'book-open' => [
            'lucide'   => 'book-open',
            'material' => 'book',
            'label'    => 'Course',
        ],
        'batch' => [
            'lucide'   => 'layers',
            'material' => 'layers',
            'label'    => 'Batch',
        ],
        'layers' => [
            'lucide'   => 'layers',
            'material' => 'layers',
            'label'    => 'Batch',
        ],
        'student' => [
            'lucide'   => 'graduation-cap',
            'material' => 'school',
            'label'    => 'Student',
        ],
        'graduation-cap' => [
            'lucide'   => 'graduation-cap',
            'material' => 'school',
            'label'    => 'Student',
        ],
        'test' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'Test',
        ],
        'file-text' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'File',
        ],
        'doc.text.fill' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'Document',
        ],
        'grading' => [
            'lucide'   => 'clipboard-check',
            'material' => 'fact_check',
            'label'    => 'Grading',
        ],
        'clipboard-check' => [
            'lucide'   => 'clipboard-check',
            'material' => 'fact_check',
            'label'    => 'Graded',
        ],
        'reports' => [
            'lucide'   => 'chart-bar-big',
            'material' => 'bar_chart',
            'label'    => 'Reports',
        ],
        'chart-bar-big' => [
            'lucide'   => 'chart-bar-big',
            'material' => 'bar_chart',
            'label'    => 'Chart',
        ],
        'settings' => [
            'lucide'   => 'settings',
            'material' => 'settings',
            'label'    => 'Settings',
        ],
        'notifications' => [
            'lucide'   => 'bell',
            'material' => 'notifications',
            'label'    => 'Notifications',
        ],
        'bell' => [
            'lucide'   => 'bell',
            'material' => 'notifications',
            'label'    => 'Notifications',
        ],
        'search' => [
            'lucide'   => 'search',
            'material' => 'search',
            'label'    => 'Search',
        ],
        'menu' => [
            'lucide'   => 'menu',
            'material' => 'menu',
            'label'    => 'Menu',
        ],

        // ── ACTIONS ───────────────────────────────────────
        'plus' => [
            'lucide'   => 'circle-plus',
            'material' => 'add_circle',
            'label'    => 'Add',
        ],
        'circle-plus' => [
            'lucide'   => 'circle-plus',
            'material' => 'add_circle',
            'label'    => 'Add',
        ],
        'edit' => [
            'lucide'   => 'square-pen',
            'material' => 'edit',
            'label'    => 'Edit',
        ],
        'square-pen' => [
            'lucide'   => 'square-pen',
            'material' => 'edit',
            'label'    => 'Edit',
        ],
        'trash' => [
            'lucide'   => 'trash-2',
            'material' => 'delete',
            'label'    => 'Delete',
        ],
        'trash-2' => [
            'lucide'   => 'trash-2',
            'material' => 'delete',
            'label'    => 'Delete',
        ],
        'copy' => [
            'lucide'   => 'copy',
            'material' => 'content_copy',
            'label'    => 'Copy',
        ],
        'filter' => [
            'lucide'   => 'filter',
            'material' => 'filter_list',
            'label'    => 'Filter',
        ],
        'refresh' => [
            'lucide'   => 'refresh-cw',
            'material' => 'refresh',
            'label'    => 'Refresh',
        ],
        'refresh-cw' => [
            'lucide'   => 'refresh-cw',
            'material' => 'refresh',
            'label'    => 'Refresh',
        ],

        // ── NAVIGATION ────────────────────────────────────
        'arrow-right' => [
            'lucide'   => 'arrow-right',
            'material' => 'arrow_forward',
            'label'    => 'Next',
        ],
        'arrow-left' => [
            'lucide'   => 'arrow-left',
            'material' => 'arrow_back',
            'label'    => 'Back',
        ],
        'chevron-right' => [
            'lucide'   => 'chevron-right',
            'material' => 'chevron_right',
            'label'    => 'Next',
        ],
        'chevron-left' => [
            'lucide'   => 'chevron-left',
            'material' => 'chevron_left',
            'label'    => 'Back',
        ],
        'chevron-down' => [
            'lucide'   => 'chevron-down',
            'material' => 'expand_more',
            'label'    => 'Expand',
        ],
        'external-link' => [
            'lucide'   => 'external-link',
            'material' => 'open_in_new',
            'label'    => 'Open',
        ],
        'logout' => [
            'lucide'   => 'log-out',
            'material' => 'logout',
            'label'    => 'Sign Out',
        ],
        'log-out' => [
            'lucide'   => 'log-out',
            'material' => 'logout',
            'label'    => 'Sign Out',
        ],
        'login' => [
            'lucide'   => 'log-in',
            'material' => 'login',
            'label'    => 'Sign In',
        ],
        'log-in' => [
            'lucide'   => 'log-in',
            'material' => 'login',
            'label'    => 'Sign In',
        ],
        'arrow.right.circle.fill' => [
            'lucide'   => 'arrow-right-circle',
            'material' => 'arrow_forward',
            'label'    => 'Go',
        ],
        'arrow-right-circle' => [
            'lucide'   => 'arrow-right-circle',
            'material' => 'arrow_forward',
            'label'    => 'Go',
        ],

        // ── MEDIA / PLAYER ────────────────────────────────
        'play' => [
            'lucide'   => 'play',
            'material' => 'play_arrow',
            'label'    => 'Play',
        ],
        'pause' => [
            'lucide'   => 'pause',
            'material' => 'pause',
            'label'    => 'Pause',
        ],
        'stop' => [
            'lucide'   => 'square',
            'material' => 'stop',
            'label'    => 'Stop',
        ],

        // ── STATUS / ALERTS ───────────────────────────────
        'check' => [
            'lucide'   => 'check',
            'material' => 'check',
            'label'    => 'Check',
        ],
        'check-circle' => [
            'lucide'   => 'check-circle',
            'material' => 'check_circle',
            'label'    => 'Success',
        ],
        'checkmark.circle.fill' => [
            'lucide'   => 'check-circle',
            'material' => 'check_circle',
            'label'    => 'Completed',
        ],
        'badge-check' => [
            'lucide'   => 'badge-check',
            'material' => 'verified',
            'label'    => 'Verified',
        ],
        'x' => [
            'lucide'   => 'x',
            'material' => 'close',
            'label'    => 'Close',
        ],
        'info' => [
            'lucide'   => 'info',
            'material' => 'info',
            'label'    => 'Info',
        ],
        'warning' => [
            'lucide'   => 'triangle-alert',
            'material' => 'warning',
            'label'    => 'Warning',
        ],
        'triangle-alert' => [
            'lucide'   => 'triangle-alert',
            'material' => 'warning',
            'label'    => 'Warning',
        ],
        'question-circle' => [
            'lucide'   => 'circle-help',
            'material' => 'help',
            'label'    => 'Help',
        ],
        'circle-help' => [
            'lucide'   => 'circle-help',
            'material' => 'help',
            'label'    => 'Help',
        ],
        'help' => [
            'lucide'   => 'circle-help',
            'material' => 'help',
            'label'    => 'Help',
        ],
        'shield' => [
            'lucide'   => 'shield',
            'material' => 'shield',
            'label'    => 'Shield',
        ],

        // ── DATA / CHARTS ─────────────────────────────────
        'chart' => [
            'lucide'   => 'chart-bar-big',
            'material' => 'bar_chart',
            'label'    => 'Chart',
        ],
        'graph' => [
            'lucide'   => 'chart-line',
            'material' => 'show_chart',
            'label'    => 'Graph',
        ],
        'chart-line' => [
            'lucide'   => 'chart-line',
            'material' => 'show_chart',
            'label'    => 'Chart',
        ],
        'activity' => [
            'lucide'   => 'activity',
            'material' => 'pulse',
            'label'    => 'Activity',
        ],
        'status' => [
            'lucide'   => 'circle-dot',
            'material' => 'circle',
            'label'    => 'Status',
        ],
        'circle-dot' => [
            'lucide'   => 'circle-dot',
            'material' => 'circle',
            'label'    => 'Status',
        ],
        'data-usage' => [
            'lucide'   => 'hard-drive',
            'material' => 'storage',
            'label'    => 'Data',
        ],
        'database' => [
            'lucide'   => 'database',
            'material' => 'database',
            'label'    => 'Database',
        ],
        'globe' => [
            'lucide'   => 'globe',
            'material' => 'public',
            'label'    => 'Global',
        ],
        'code' => [
            'lucide'   => 'code',
            'material' => 'code',
            'label'    => 'Code',
        ],
        'flag' => [
            'lucide'   => 'flag',
            'material' => 'flag',
            'label'    => 'Flag',
        ],
        'tag' => [
            'lucide'   => 'tag',
            'material' => 'label',
            'label'    => 'Tag',
        ],
        'folder' => [
            'lucide'   => 'folder',
            'material' => 'folder',
            'label'    => 'Folder',
        ],
        'file' => [
            'lucide'   => 'file',
            'material' => 'insert_drive_file',
            'label'    => 'File',
        ],
        'document' => [
            'lucide'   => 'file-text',
            'material' => 'description',
            'label'    => 'Document',
        ],
        'clipboard' => [
            'lucide'   => 'clipboard',
            'material' => 'content_paste',
            'label'    => 'Clipboard',
        ],
        'timer' => [
            'lucide'   => 'timer',
            'material' => 'timer',
            'label'    => 'Timer',
        ],
        'clock' => [
            'lucide'   => 'clock',
            'material' => 'schedule',
            'label'    => 'Time',
        ],
        'clock3' => [
            'lucide'   => 'clock-3',
            'material' => 'schedule',
            'label'    => 'Clock',
        ],
        'clock.badge.exclamationmark.fill' => [
            'lucide'   => 'clock-3',
            'material' => 'pending_actions',
            'label'    => 'Pending',
        ],
        'pending_actions' => [
            'lucide'   => 'clock-3',
            'material' => 'pending_actions',
            'label'    => 'Pending',
        ],
        'calendar' => [
            'lucide'   => 'calendar',
            'material' => 'calendar_month',
            'label'    => 'Calendar',
        ],
        'calendar-days' => [
            'lucide'   => 'calendar-days',
            'material' => 'calendar_month',
            'label'    => 'Calendar',
        ],
        'calendar.circle.fill' => [
            'lucide'   => 'calendar-days',
            'material' => 'calendar_month',
            'label'    => 'Date',
        ],
        'star' => [
            'lucide'   => 'star',
            'material' => 'star',
            'label'    => 'Star',
        ],

        // ── SYSTEM ────────────────────────────────────────
        'user' => [
            'lucide'   => 'user',
            'material' => 'person',
            'label'    => 'User',
        ],
        'users' => [
            'lucide'   => 'users',
            'material' => 'group',
            'label'    => 'Users',
        ],
        'mail' => [
            'lucide'   => 'mail',
            'material' => 'mail',
            'label'    => 'Email',
        ],
        'phone' => [
            'lucide'   => 'phone',
            'material' => 'phone',
            'label'    => 'Phone',
        ],
        'lock' => [
            'lucide'   => 'lock',
            'material' => 'lock',
            'label'    => 'Lock',
        ],
        'eye' => [
            'lucide'   => 'eye',
            'material' => 'visibility',
            'label'    => 'View',
        ],
        'eye-off' => [
            'lucide'   => 'eye-off',
            'material' => 'visibility_off',
            'label'    => 'Hide',
        ],
        'download' => [
            'lucide'   => 'download',
            'material' => 'download',
            'label'    => 'Download',
        ],
        'upload' => [
            'lucide'   => 'upload',
            'material' => 'upload',
            'label'    => 'Upload',
        ],
        'lightbulb' => [
            'lucide'   => 'lightbulb',
            'material' => 'lightbulb',
            'label'    => 'Tip',
        ],
        'moon' => [
            'lucide'   => 'moon',
            'material' => 'dark_mode',
            'label'    => 'Dark Mode',
        ],

        // ── LAYOUT ────────────────────────────────────────
        'grid' => [
            'lucide'   => 'grid-3x3',
            'material' => 'grid_view',
            'label'    => 'Grid',
        ],
        'grid-3x3' => [
            'lucide'   => 'grid-3x3',
            'material' => 'grid_view',
            'label'    => 'Grid',
        ],
        'list' => [
            'lucide'   => 'list',
            'material' => 'list',
            'label'    => 'List',
        ],

        // ── PROFILE / MISC ────────────────────────────────
        'id-card' => [
            'lucide'   => 'badge-check',
            'material' => 'badge',
            'label'    => 'ID',
        ],
        'badge' => [
            'lucide'   => 'badge',
            'material' => 'badge',
            'label'    => 'Badge',
        ],
        'git-branch' => [
            'lucide'   => 'git-branch',
            'material' => 'alt_route',
            'label'    => 'Branch',
        ],
        'tray' => [
            'lucide'   => 'tray',
            'material' => 'inbox',
            'label'    => 'Inbox',
        ],
        'tray.fill' => [
            'lucide'   => 'tray',
            'material' => 'inbox',
            'label'    => 'Inbox',
        ],

        // ── EXTRA DASHBOARD ICONS ─────────────────────────
        'circle' => [
            'lucide'   => 'circle',
            'material' => 'circle',
            'label'    => 'Circle',
        ],
        'circle.fill' => [
            'lucide'   => 'circle',
            'material' => 'circle',
            'label'    => 'Status',
        ],
        'progress.indicator' => [
            'lucide'   => 'circle-dashed',
            'material' => 'donut_small',
            'label'    => 'Progress',
        ],
        'circle-dashed' => [
            'lucide'   => 'circle-dashed',
            'material' => 'donut_small',
            'label'    => 'In Progress',
        ],
        'donut_small' => [
            'lucide'   => 'circle-dashed',
            'material' => 'donut_small',
            'label'    => 'Progress',
        ],

        // ── TEST TAKING ───────────────────────────────────
        'warning' => [
            'lucide'   => 'triangle-alert',
            'material' => 'warning',
            'label'    => 'Warning',
        ],
    ];
}
