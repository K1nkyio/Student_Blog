<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__) . '/shared/session.php';
    start_project_session();
}

$role = $_SESSION['admin_role'] ?? '';
if ($role !== 'super_admin') {
    header('Location: login.php');
    exit();
}

require_once dirname(__DIR__) . '/shared/db_connect.php';
require_once dirname(__DIR__) . '/shared/admin_auth.php';
require_admin_access($conn, 'super_admin');

header('Location: dashboard.php');
exit();

