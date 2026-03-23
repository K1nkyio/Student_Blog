<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['admin_role'] ?? '';
if ($role !== 'super_admin') {
    header('Location: login.php');
    exit();
}

header('Location: dashboard.php');
exit();
