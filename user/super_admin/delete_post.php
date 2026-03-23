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

include '../shared/db_connect.php';

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
