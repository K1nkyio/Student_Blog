<?php
// Simplified notification mark as read handler
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Start session and load includes
    session_start();
    require_once __DIR__ . '/db_connect.php';
    require_once __DIR__ . '/functions.php';

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
        exit;
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data'
        ]);
        exit;
    }

    // Check if user is logged in
    if (!is_logged_in()) {
        echo json_encode([
            'success' => false,
            'message' => 'Please log in to manage notifications'
        ]);
        exit;
    }

    $user_id = get_user_id();
    $notification_id = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;

    // Validate notification_id
    if ($notification_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid notification ID'
        ]);
        exit;
    }

    // Verify notification exists and belongs to user
    $check_stmt = $conn->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $notification_id, $user_id);
    $check_stmt->execute();
    $notification_exists = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();

    if (!$notification_exists) {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found'
        ]);
        exit;
    }

    // Mark notification as read
    $stmt = $conn->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification_id' => $notification_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to mark notification as read'
        ]);
    }

} catch (Exception $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
    exit;
}
?>
