<?php
include '../shared/db_connect.php';
require_once dirname(__DIR__) . '/shared/session.php';
    start_project_session();
require_once dirname(__DIR__) . '/shared/admin_auth.php';
require_admin_access($conn);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id) {
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}
header("Location: dashboard.php");
exit();
?>


