<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: register.php');
    exit();
}

include '../shared/db_connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM opportunities WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: opportunities.php');
exit();

