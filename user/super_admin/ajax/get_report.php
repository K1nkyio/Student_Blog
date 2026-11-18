<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__, 2) . '/shared/session.php';
    start_project_session();
}

if (empty($_SESSION['admin']) || (($_SESSION['admin_role'] ?? '') !== 'super_admin')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once dirname(__DIR__, 2) . '/shared/db_connect.php';
require_once dirname(__DIR__, 2) . '/shared/admin_auth.php';
if (!require_admin_access($conn, 'super_admin', false)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

chdir(__DIR__);
$safespeak_form_action = 'safespeak.php';
require __DIR__ . '/../../admin/ajax/get_report.php';

