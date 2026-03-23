<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

// Make nested relative includes inside the reused admin SafeSpeak page
// resolve against the super_admin directory so it picks up super-admin
// navigation and routes.
chdir(__DIR__);
$safespeak_header_include = __DIR__ . '/includes/header.php';
$safespeak_footer_include = __DIR__ . '/includes/footer.php';
$safespeak_post_endpoint = 'safespeak.php';
$safespeak_report_endpoint = 'ajax/get_report.php';
require __DIR__ . '/../admin/safespeak.php';
