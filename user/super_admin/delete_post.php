<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__) . '/shared/session.php';
    start_project_session();
}

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

include '../shared/db_connect.php';
require_once dirname(__DIR__) . '/shared/admin_auth.php';
require_admin_access($conn, 'super_admin');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id) {
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: post_management.php');
exit();
?>

