<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

if (isAdmin()) {
    redirect('/admin/dashboard.php');
} elseif (isStudent()) {
    redirect('/student/dashboard.php');
} elseif (isset($_SESSION['guest_token'])) {
    redirect('/student/test.php');
}
// Otherwise show login
redirect('/login.php');
