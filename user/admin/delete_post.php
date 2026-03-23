<?php
include '../shared/db_connect.php';
session_start();
if (!isset($_SESSION['admin'])) { header('Location: register.php'); exit(); }

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

