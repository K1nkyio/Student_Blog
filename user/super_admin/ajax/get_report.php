<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin']) || (($_SESSION['admin_role'] ?? '') !== 'super_admin')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

chdir(__DIR__);
$safespeak_form_action = 'safespeak.php';
require __DIR__ . '/../../admin/ajax/get_report.php';
