<?php
header('Content-Type: application/json');
// Start output buffering to catch any accidental HTML output for debugging
ob_start();
// Hide PHP notices/warnings from output; we'll convert errors to JSON below
ini_set('display_errors', '0');
session_start();
define('BLOG_SYSTEM', true);

// Defensive error handling: convert PHP errors/exceptions to JSON
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fatal server error']);
        exit;
    }
});

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limiter.php';

// If any output was produced by included files (accidental HTML or whitespace), log it and clear buffer
$preOutput = ob_get_clean();
if (!empty($preOutput)) {
    $logPath = __DIR__ . '/../logs/ajax_output_debug.log';
    @file_put_contents($logPath, "[" . date('c') . "] like.php pre-output:\n" . $preOutput . "\n---\n", FILE_APPEND);
    // Start a fresh buffer for the rest of the request
    ob_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid post']);
    exit;
}

$ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

// Rate limit: max 20 likes per IP per hour
if (!check_rate_limit('like', $ip, 20, 3600)) {
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded']);
    exit;
}

$check = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND ip_address = ?");
$check->bind_param('is', $post_id, $ip);
$check->execute();
$res = $check->get_result();
$check->close();

$action_taken = '';
$liked = false;

if ($res && $res->num_rows > 0) {
    // User has already liked, so unlike (remove the like)
    $delete = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND ip_address = ?");
    $delete->bind_param('is', $post_id, $ip);
    if ($delete->execute()) {
        $delete->close();
        $action_taken = 'unliked';
        $liked = false;
    } else {
        $delete->close();
        echo json_encode(['success' => false, 'message' => 'Could not remove like']);
        exit;
    }
} else {
    // User hasn't liked yet, so add a like
    $insert = $conn->prepare("INSERT INTO likes (post_id, ip_address) VALUES (?, ?)");
    $insert->bind_param('is', $post_id, $ip);
    if ($insert->execute()) {
        $insert->close();
        $action_taken = 'liked';
        $liked = true;
    } else {
        $insert->close();
        echo json_encode(['success' => false, 'message' => 'Could not save like']);
        exit;
    }
}

// Get updated like count
$countQ = $conn->prepare("SELECT COUNT(*) as total FROM likes WHERE post_id = ?");
$countQ->bind_param('i', $post_id);
$countQ->execute();
$likes = $countQ->get_result()->fetch_assoc()['total'];
$countQ->close();

$message = $liked ? 'Liked!' : 'Unliked';
echo json_encode([
    'success' => true,
    'message' => $message,
    'likes' => (int)$likes,
    'liked' => $liked,
    'action' => $action_taken
]);
exit;
