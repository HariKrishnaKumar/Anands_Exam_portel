<?php
/**
 * Faculty layout header — reuses the admin Fluent 2 skin (admin.css) so the
 * portal looks identical, but:
 *  - guards with requireFaculty() instead of requireAdmin()
 *  - renders a read-only sidebar (Dashboard + Sign Out only)
 *  - exposes NO admin links, notifications, or mutation affordances
 *
 * Call requireFaculty() indirectly via this include, before any HTML.
 */
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
startSession();
requireFaculty();

$currentPage      = basename($_SERVER['PHP_SELF']);
$facultyEmail     = $_SESSION['faculty_email'] ?? 'Faculty';
$facultyName      = $_SESSION['faculty_name'] ?? $facultyEmail;
$facultyCollege   = $_SESSION['faculty_college_name'] ?? 'Your college';
$facultyCollegeId = (int)($_SESSION['faculty_college_id'] ?? 0);
$facultyInitial   = strtoupper(substr($facultyName, 0, 1));

$sidebarCollapsed = ($_COOKIE['sidebar_collapsed'] ?? '') === '1';
?><!DOCTYPE html>
<html lang="en" data-compact="true"<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? ' data-theme="dark"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty — <?= h($pageTitle ?? 'Dashboard') ?> | Yajurvedh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/admin.css">
    <script src="https://unpkg.com/lucide@latest" defer></script>
</head>
<body>
<?= iconSprite() ?>
<div class="admin-layout<?= $sidebarCollapsed ? ' sidebar-collapsed' : '' ?>" id="appLayout">

    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- LEFT SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo-mark" style="background:none !important;box-shadow:none !important;"><img src="<?= ASSETS_URL ?>/img/yajurvedh-logo.png" alt="Yajurvedh" style="width:100%;height:100%;object-fit:contain;"></div>
            <span class="sidebar-logo-text">Yajurvedh</span>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Faculty</div>
            <a href="<?= BASE_URL ?>/faculty/dashboard.php"
               class="nav-item nav-c-dashboard active" aria-current="page" data-tooltip="Dashboard">
                <span class="nav-icon"><?= icon('dashboard', 24) ?></span>
                <span class="nav-label">Dashboard</span>
            </a>

            <div class="nav-section">Scope</div>
            <div class="nav-item nav-c-college" style="cursor:default;opacity:.9;" data-tooltip="<?= h($facultyCollege) ?>">
                <span class="nav-icon"><?= icon('college', 24) ?></span>
                <span class="nav-label"><?= h($facultyCollege) ?></span>
            </div>

            <div class="sidebar-divider"></div>
            <a href="<?= BASE_URL ?>/logout.php" class="nav-item nav-c-signout" style="margin-top:auto;" data-tooltip="Sign Out">
                <span class="nav-icon"><?= icon('logout', 24) ?></span>
                <span class="nav-label">Sign Out</span>
            </a>
        </nav>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="main-content">

        <!-- Top Navigation Bar (read-only: no create/search/notification actions) -->
        <header class="topnav">
            <div class="topnav-left">
                <button class="topnav-toggle" id="sidebarToggle" onclick="toggleCollapse()" aria-label="Toggle sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="topnav-search search-wrapper" style="pointer-events:none;opacity:.75;">
                    <span class="search-icon"><?= icon('search', 16) ?></span>
                    <input type="text" placeholder="Read-only faculty analytics" aria-label="Read-only faculty analytics" readonly tabindex="-1">
                </div>
            </div>
            <div class="topnav-right">
                <span class="topnav-quick-action" style="cursor:default;" aria-hidden="true">
                    <?= icon('chart', 16) ?>
                    <span class="qa-label-text">Read-only</span>
                </span>
                <div class="topnav-profile" id="profileMenu">
                    <div class="topnav-avatar">
                        <?= h($facultyInitial) ?>
                        <span class="online-dot"></span>
                    </div>
                    <div class="topnav-profile-info">
                        <span class="topnav-profile-name"><?= h($facultyName) ?></span>
                        <span class="topnav-profile-role">Faculty — <?= h($facultyCollege) ?></span>
                    </div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="<?= BASE_URL ?>/logout.php" class="profile-dropdown-item profile-dropdown-danger">
                            <?= icon('logout', 16) ?> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="content-area">
