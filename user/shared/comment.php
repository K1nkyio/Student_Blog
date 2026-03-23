<?php
header('Content-Type: application/json');
// Start output buffering to catch any accidental HTML output for debugging
ob_start();
// Hide PHP notices/warnings from output; we'll convert errors to JSON below
ini_set('display_errors', '0');
session_start();

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
    @file_put_contents($logPath, "[" . date('c') . "] comment.php pre-output:\n" . $preOutput . "\n---\n", FILE_APPEND);
    // Start a fresh buffer for the rest of the request
    ob_start();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get client IP for rate limiting
$ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

// Rate limit: max 5 comments per IP per 10 minutes
if (!check_rate_limit('comment', $ip, 5, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many comments. Please wait before posting again']);
    exit;
}

// Get and sanitize input
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$name = sanitize_input($_POST['name'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
$comment_text = sanitize_input($_POST['comment_text'] ?? '');

// Validate all required fields
if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit;
}

if (empty($name) || strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid name']);
    exit;
}

if (empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide an email']);
    exit;
}

if (!is_valid_email($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

if (empty($comment_text) || strlen($comment_text) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Comment must be at least 3 characters']);
    exit;
}

if (strlen($comment_text) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Comment cannot exceed 2000 characters']);
    exit;
}

// Verify post exists and is visible
$postCheck = $conn->prepare("SELECT id FROM posts WHERE id = ? AND COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved' LIMIT 1");
if (!$postCheck) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$postCheck->bind_param("i", $post_id);
$postCheck->execute();
$postResult = $postCheck->get_result();

if ($postResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Post not found']);
    exit;
}
$postCheck->close();

// Insert comment
$insertComment = $conn->prepare("INSERT INTO comments (post_id, name, email, comment, created_at) VALUES (?, ?, ?, ?, NOW())");

if (!$insertComment) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$insertComment->bind_param("isss", $post_id, $name, $email, $comment_text);

if ($insertComment->execute()) {
    $comment_id = $conn->insert_id;
    $insertComment->close();

    // Create notification for post author
    $post_info = $conn->prepare("SELECT title, author_id FROM posts WHERE id = ?");
    $post_info->bind_param("i", $post_id);
    $post_info->execute();
    $post_data = $post_info->get_result()->fetch_assoc();
    $post_info->close();

    if ($post_data) {
        create_notification(
            $post_data['author_id'],
            'comment',
            $name . ' commented on your post "' . substr($post_data['title'], 0, 50) . (strlen($post_data['title']) > 50 ? '...' : '') . '"',
            'post',
            $post_id
        );
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Comment posted successfully!',
        'comment' => [
            'id' => (int)$comment_id,
            'post_id' => (int)$post_id,
            'name' => $name,
            'email' => $email,
            'comment' => $comment_text,
            'created_at' => date('M j, Y')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error posting comment: ' . $insertComment->error]);
    $insertComment->close();
}

$conn->close();
exit;
?>
