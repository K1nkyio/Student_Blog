<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__) . '/shared/session.php';
    start_project_session();
}

if (!isset($_SESSION['admin'])) {
    header('Location: register.php');
    exit();
}

include '../shared/db_connect.php';
require_once dirname(__DIR__) . '/shared/admin_auth.php';
require_admin_access($conn);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: events.php');
exit();


