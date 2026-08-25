<?php
/**
 * Student Dashboard Header & Sidebar Include
 * 
 * Shared layout for all student pages: dashboard, results, analytics, profile
 * 
 * Required globals/variables:
 *   $firstName          - Student first name (for display)
 *   $student            - Student data array (for course/batch info if available)
 *   $currentPage        - Current page name for active nav highlighting (e.g., 'dashboard', 'results')
 * 
 * Usage in student pages:
 *   <?php
 *       $firstName = explode(' ', $student['name'])[0];
 *       $currentPage = 'dashboard'; // Set this based on your page
 *       include __DIR__ . '/../../includes/student_header.php';
 *   ?>
 */

if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
}

// Map page file names to nav item names
$pageToNavMap = [
    'dashboard' => 'dashboard',
    'results'   => 'results',
    'analytics' => 'analytics',
    'profile'   => 'profile',
    'test'      => 'dashboard', // test.php doesn't have sidebar, but for safety
];

$currentNav = $pageToNavMap[$currentPage] ?? $currentPage;
?>

<div class="dashboard-layout" id="app">
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- ===== LEFT SIDEBAR ===== -->
    <aside class="dashboard-sidebar" id="sidebar">
        <!-- Logo -->
        <a href="dashboard.php" class="sidebar-logo" style="text-decoration:none;">
            <?php if (!empty($student['college_logo'])): ?>
                <div class="sidebar-logo-icon" style="background:rgba(255,255,255,0.05);padding:4px;">
                    <img src="<?= h($student['college_logo']) ?>" alt="<?= h($student['college_name']) ?>"
                         style="width:36px;height:36px;object-fit:contain;border-radius:8px;"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="sidebar-logo-icon-fallback" style="display:none;">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2.5a.5.5 0 0 1 .28-.46l7-3.5a.5.5 0 0 1 .44 0l7 3.5a.5.5 0 0 1 .28.46v12a.5.5 0 0 1-1 0V3.2l-6.5 3.25V15.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-13z"/></svg>
                    </div>
                </div>
            <?php else: ?>
                <div class="sidebar-logo-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2.5a.5.5 0 0 1 .28-.46l7-3.5a.5.5 0 0 1 .44 0l7 3.5a.5.5 0 0 1 .28.46v12a.5.5 0 0 1-1 0V3.2l-6.5 3.25V15.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-13z"/></svg>
                </div>
            <?php endif; ?>
            <span class="sidebar-logo-text"><?= h($student['college_name'] ?? 'Test Platform') ?></span>
        </a>

        <!-- Primary Navigation -->
        <nav class="sidebar-nav">
            <div class="sidebar-nav-group">
                <a href="dashboard.php" class="sidebar-nav-item <?= $currentNav === 'dashboard' ? 'active' : '' ?>">
                    <?= icon('dashboard', 20) ?>
                    <span>Dashboard</span>
                </a>
                <a href="dashboard.php" class="sidebar-nav-item">
                    <?= icon('test', 20) ?>
                    <span>My Tests</span>
                </a>
                <a href="results.php" class="sidebar-nav-item <?= $currentNav === 'results' ? 'active' : '' ?>">
                    <?= icon('chart', 20) ?>
                    <span>Results</span>
                </a>
                <a href="analytics.php" class="sidebar-nav-item <?= $currentNav === 'analytics' ? 'active' : '' ?>">
                    <?= icon('graph', 20) ?>
                    <span>Analytics</span>
                </a>
                <a href="profile.php" class="sidebar-nav-item <?= $currentNav === 'profile' ? 'active' : '' ?>">
                    <?= icon('student', 20) ?>
                    <span>Profile</span>
                </a>
            </div>

            <!-- Theme Switcher -->
            <div class="sidebar-nav-group">
                <div class="sidebar-nav-label">Appearance</div>
                <button class="sidebar-nav-item theme-toggle" onclick="toggleTheme()" id="themeToggle">
                    <span class="material-symbols-outlined theme-icon">dark_mode</span>
                    <span id="themeLabel">Dark Mode</span>
                </button>
            </div>

            <!-- Sign Out -->
            <div class="sidebar-nav-group" style="margin-top:auto;">
                <a href="<?= BASE_URL ?>/logout.php" class="sidebar-nav-item">
                    <?= icon('logout', 20) ?>
                    <span>Sign Out</span>
                </a>
            </div>
        </nav>

        <!-- Profile Footer -->
        <div class="sidebar-profile">
            <div class="sidebar-profile-avatar">
                <?= strtoupper($firstName[0] ?? '?') ?>
                <span class="online-dot"></span>
            </div>
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name"><?= h($firstName ?? 'Student') ?></div>
                <div class="sidebar-profile-role">Student</div>
            </div>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT AREA ===== -->
    <div class="dashboard-main">
        <!-- Top Navigation -->
        <header class="dashboard-topnav">
            <div class="topnav-left">
                <button class="topnav-hamburger" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <?= icon('menu', 20) ?>
                </button>
                <div class="topnav-brand">Student Portal</div>
            </div>
            <div class="topnav-right">
                <button class="topnav-icon-btn" onclick="toggleTheme()" data-tooltip="Toggle theme">
                    <span class="material-symbols-outlined theme-icon">dark_mode</span>
                </button>
                <div class="topnav-profile">
                    <div class="topnav-avatar">
                        <?= strtoupper($firstName[0] ?? '?') ?>
                        <span class="online-dot"></span>
                    </div>
                    <div class="topnav-profile-info">
                        <div class="topnav-profile-name"><?= h($firstName ?? 'Student') ?></div>
                        <div class="topnav-profile-role">Student</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area (children pages fill this) -->
        <main class="dashboard-content">
            <div class="dashboard-content-inner">
