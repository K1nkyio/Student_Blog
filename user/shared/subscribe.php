<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

// Rate limiting - 5 attempts per hour per IP
$ip = $_SERVER['REMOTE_ADDR'];
if (!check_rate_limit('subscribe', $ip, 5, 3600)) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Try again later.']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$notify_posts = isset($_POST['notify_posts']) ? 1 : 0;
$notify_weekly = isset($_POST['notify_weekly']) ? 1 : 0;

// Check if email exists
$check = $conn->prepare("SELECT id FROM subscribers WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    $stmt = $conn->prepare("UPDATE subscribers SET notify_posts = ?, notify_weekly = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("iii", $notify_posts, $notify_weekly, $existing['id']);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Preferences updated!']);
} else {
    $token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare("INSERT INTO subscribers (email, notify_posts, notify_weekly, confirm_token, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("siis", $email, $notify_posts, $notify_weekly, $token);
    $stmt->execute();
    
    // Send confirmation email (implement send_confirmation_email function)
    // send_confirmation_email($email, $token);
    
    echo json_encode(['success' => true, 'message' => 'Thanks! Check your email to confirm.']);
}
