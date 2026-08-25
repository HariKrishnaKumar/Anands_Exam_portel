<?php
$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../../includes/admin_header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-header-left">
        <h1>Activity Logs</h1>
        <p class="dashboard-subtitle">Audit trail of platform activity and administrative actions</p>
    </div>
</div>

<div class="card-flat">
    <div class="empty-state" style="padding:var(--space-8);text-align:center;">
        <div class="empty-icon">
            <svg viewBox="0 0 20 20" fill="currentColor" width="48" height="48"><path d="M17 3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-5l-1.87 1.87a.5.5 0 0 1-.26.13.5.5 0 0 1-.5-.5V15H5a4 4 0 0 1-4-4V6a4 4 0 0 1 4-4h12zM5 4a3 3 0 0 0-3 3v5a3 3 0 0 0 3 3h4v2.07L12.07 15H15a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5z"/></svg>
        </div>
        <h3>Activity Logs — Coming Soon</h3>
        <p>A complete audit trail of all administrative actions, student logins, assessment changes, and system events will be available here.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
