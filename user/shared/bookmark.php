<?php
// Simplified bookmark handler with proper error handling
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Start session and load includes
    session_start();
    require_once __DIR__ . '/db_connect.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/csrf.php';

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
            'message' => 'Please log in to bookmark posts'
        ]);
        exit;
    }

    $user_id = get_user_id();
    $post_id = isset($data['post_id']) ? (int)$data['post_id'] : 0;
    $action = isset($data['action']) ? $data['action'] : 'toggle';

    // Validate post_id
    if ($post_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid post ID'
        ]);
        exit;
    }

    // Verify post exists and is visible
    $check_post = $conn->prepare("SELECT id, title, author_id FROM posts WHERE id = ? AND COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved'");
    $check_post->bind_param("i", $post_id);
    $check_post->execute();
    $post_result = $check_post->get_result();
    if ($post_result->num_rows === 0) {
        $check_post->close();
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
        exit;
    }
    $post_data = $post_result->fetch_assoc();
    $check_post->close();

    // Check if already bookmarked
    $check_bookmark = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND post_id = ?");
    $check_bookmark->bind_param("ii", $user_id, $post_id);
    $check_bookmark->execute();
    $bookmark_result = $check_bookmark->get_result();
    $is_bookmarked = $bookmark_result->num_rows > 0;
    $check_bookmark->close();

    if ($action === 'remove') {
        if (!$is_bookmarked) {
            echo json_encode([
                'success' => false,
                'message' => 'Bookmark not found',
                'bookmarked' => false
            ]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND post_id = ?");
        $stmt->bind_param("ii", $user_id, $post_id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode([
                'success' => true,
                'message' => 'Bookmark removed successfully',
                'bookmarked' => false,
                'action' => 'removed'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to remove bookmark'
            ]);
        }
        exit;

    } elseif ($action === 'add') {
        if ($is_bookmarked) {
            echo json_encode([
                'success' => false,
                'message' => 'Already bookmarked',
                'bookmarked' => true
            ]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, post_id, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user_id, $post_id);
        if ($stmt->execute()) {
            $stmt->close();

            // Create notification for post author (if not bookmarking own post)
            if ($post_data && $post_data['author_id'] != $user_id) {
                create_notification(
                    $post_data['author_id'],
                    'bookmark',
                    'Someone bookmarked your post "' . substr($post_data['title'], 0, 50) . (strlen($post_data['title']) > 50 ? '...' : '') . '"',
                    'post',
                    $post_id
                );
            }

            echo json_encode([
                'success' => true,
                'message' => 'Post bookmarked successfully!',
                'bookmarked' => true,
                'action' => 'added'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add bookmark'
            ]);
        }
        exit;

    } else {
        // toggle action
        if ($is_bookmarked) {
            // Remove bookmark
            $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND post_id = ?");
            $stmt->bind_param("ii", $user_id, $post_id);
            if ($stmt->execute()) {
                $stmt->close();
                echo json_encode([
                    'success' => true,
                    'message' => 'Bookmark removed successfully',
                    'bookmarked' => false,
                    'action' => 'removed'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to remove bookmark'
                ]);
            }
        } else {
            // Add bookmark
            $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, post_id, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $user_id, $post_id);
            if ($stmt->execute()) {
                $stmt->close();

                // Create notification for post author (if not bookmarking own post)
                if ($post_data && $post_data['author_id'] != $user_id) {
                    create_notification(
                        $post_data['author_id'],
                        'bookmark',
                        'Someone bookmarked your post "' . substr($post_data['title'], 0, 50) . (strlen($post_data['title']) > 50 ? '...' : '') . '"',
                        'post',
                        $post_id
                    );
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Post bookmarked successfully!',
                    'bookmarked' => true,
                    'action' => 'added'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add bookmark'
                ]);
            }
        }
        exit;
    }

} catch (Exception $e) {
    error_log("Bookmark Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
    exit;
}
?>
