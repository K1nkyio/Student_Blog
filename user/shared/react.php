<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!verify_csrf_token($data['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$post_id = (int)($data['post_id'] ?? 0);
$reaction_type = $data['type'] ?? 'like';
$valid_reactions = ['like', 'love', 'insightful', 'funny'];

if (!in_array($reaction_type, $valid_reactions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reaction']);
    exit;
}

// Use user_id if logged in, otherwise IP-based
$user_id = $_SESSION['user_id'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'];

// Check existing reaction
if ($user_id) {
    $check = $conn->prepare("SELECT id, reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?");
    $check->bind_param("ii", $post_id, $user_id);
} else {
    $check = $conn->prepare("SELECT id, reaction_type FROM post_reactions WHERE post_id = ? AND ip_address = ? AND user_id IS NULL");
    $check->bind_param("is", $post_id, $ip);
}
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    if ($existing['reaction_type'] === $reaction_type) {
        // Remove reaction
        $del = $conn->prepare("DELETE FROM post_reactions WHERE id = ?");
        $del->bind_param("i", $existing['id']);
        $del->execute();
        $action = 'removed';
    } else {
        // Change reaction
        $upd = $conn->prepare("UPDATE post_reactions SET reaction_type = ? WHERE id = ?");
        $upd->bind_param("si", $reaction_type, $existing['id']);
        $upd->execute();
        $action = 'changed';
    }
} else {
    // Add new reaction
    $stmt = $conn->prepare("INSERT INTO post_reactions (post_id, user_id, ip_address, reaction_type, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $post_id, $user_id, $ip, $reaction_type);
    $stmt->execute();
    $action = 'added';
}

// Get updated counts
$counts = [];
foreach ($valid_reactions as $type) {
    $cnt = $conn->prepare("SELECT COUNT(*) as c FROM post_reactions WHERE post_id = ? AND reaction_type = ?");
    $cnt->bind_param("is", $post_id, $type);
    $cnt->execute();
    $counts[$type] = $cnt->get_result()->fetch_assoc()['c'];
}

echo json_encode(['success' => true, 'action' => $action, 'counts' => $counts]);