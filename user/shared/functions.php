<?php
/**
 * Core Helper Functions
 */

// Prevent direct access
if (!defined('BLOG_SYSTEM')) {
    define('BLOG_SYSTEM', true);
}

// Provide default site constants if not defined by a config file
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Student Blog');
}
if (!defined('SITE_EMAIL')) {
    define('SITE_EMAIL', 'no-reply@studentblog.local');
}

/**
 * Sanitize user input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate a URL-friendly slug
 */
function generate_slug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Generate unique slug for posts
 */
function generate_unique_slug($title, $table = 'posts') {
    global $conn;
    $slug = generate_slug($title);
    $original_slug = $slug;
    $counter = 1;
    
    while (true) {
        $stmt = $conn->prepare("SELECT id FROM $table WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            break;
        }
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    return $slug;
}

/**
 * Format date for display
 */
function format_date($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format date as relative time (e.g., "2 hours ago")
 */
function time_ago($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Truncate text to specified length
 */
function truncate_text($text, $length = 150, $suffix = '...') {
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Calculate reading time
 */
function reading_time($content, $wpm = 200) {
    $word_count = str_word_count(strip_tags($content));
    $minutes = ceil($word_count / $wpm);
    return $minutes . ' min read';
}

/**
 * Get excerpt from content
 */
function get_excerpt($content, $length = 200) {
    $text = strip_tags($content);
    $text = preg_replace('/\s+/', ' ', $text);
    return truncate_text($text, $length);
}

/**
 * Resolve post image paths for public pages.
 * Post images are stored as "assets/images/..." from admin pages, while public
 * pages live under "user/public" and require "../assets/images/...".
 */
function get_post_image_url($path) {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $path);
    if (
        preg_match('#^(https?:)?//#i', $normalized) ||
        strpos($normalized, 'data:') === 0
    ) {
        return $normalized;
    }

    $rootDir = realpath(__DIR__ . '/..');
    $localPath = '';
    $publicPath = $normalized;

    if (strpos($normalized, '../assets/') === 0) {
        $publicPath = $normalized;
        $localPath = $rootDir . '/' . ltrim(substr($normalized, 3), '/');
    } elseif (strpos($normalized, 'assets/') === 0) {
        $publicPath = '../' . $normalized;
        $localPath = $rootDir . '/' . $normalized;
    } elseif (strpos($normalized, 'uploads/') === 0) {
        $publicPath = $normalized;
        $localPath = $rootDir . '/public/' . $normalized;
    }

    if ($localPath !== '' && !file_exists($localPath)) {
        // Return a tiny transparent placeholder to avoid broken image requests.
        return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
    }

    return $publicPath;
}

/**
 * Format number for display (e.g., 1.2K, 3.5M)
 */
function format_number($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return $num;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get logged-in user data
 */
function get_logged_in_user() {
    global $conn;
    if (!is_logged_in()) return null;
    
    $user_id = get_user_id();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Check if user has specific role
 */
function user_has_role($roles) {
    if (!is_logged_in()) return false;
    
    $user = get_logged_in_user();
    if (!$user) return false;
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($user['role'], $roles);
}

/**
 * Check if user is admin
 */
function is_admin() {
    return user_has_role(['admin']);
}

/**
 * Check if user is moderator or higher
 */
function is_moderator() {
    return user_has_role(['admin', 'moderator']);
}

/**
 * Check if user is author or higher
 */
function is_author() {
    return user_has_role(['admin', 'moderator', 'author']);
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message HTML
 */
function display_flash() {
    $flash = get_flash();
    if ($flash) {
        $type = $flash['type'] === 'error' ? 'alert-error' : 'alert-success';
        $icon = $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle';
        echo "<div class='alert $type'><i class='fas fa-$icon'></i> " . htmlspecialchars($flash['message']) . "</div>";
    }
}

/**
 * Get client IP address
 */
function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

/**
 * Validate email format
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL format
 */
function is_valid_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Generate random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password securely
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get post by ID
 */
function get_post($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT p.*, u.username as author_name, u.avatar as author_avatar, c.name as category_name 
                            FROM posts p 
                            LEFT JOIN users u ON p.author_id = u.id 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get post tags
 */
function get_post_tags($post_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT t.* FROM tags t 
                            INNER JOIN post_tags pt ON t.id = pt.tag_id 
                            WHERE pt.post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all categories
 */
function get_categories() {
    global $conn;
    return $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all tags
 */
function get_tags($limit = 20) {
    global $conn;
    $stmt = $conn->prepare("SELECT t.*, COUNT(pt.post_id) as post_count 
                            FROM tags t 
                            LEFT JOIN post_tags pt ON t.id = pt.tag_id 
                            GROUP BY t.id 
                            ORDER BY post_count DESC 
                            LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check if post is bookmarked by user
 */
function is_bookmarked($post_id, $user_id = null) {
    global $conn;
    $user_id = $user_id ?? get_user_id();
    if (!$user_id) return false;
    
    $stmt = $conn->prepare("SELECT id FROM bookmarks WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Get user's bookmarked post IDs
 */
function get_user_bookmarks($user_id = null) {
    global $conn;
    $user_id = $user_id ?? get_user_id();
    if (!$user_id) return [];
    
    $result = $conn->query("SELECT post_id FROM bookmarks WHERE user_id = $user_id");
    $bookmarks = [];
    while ($row = $result->fetch_assoc()) {
        $bookmarks[] = $row['post_id'];
    }
    return $bookmarks;
}

/**
 * Check if user is following author
 */
function is_following($author_id, $follower_id = null) {
    global $conn;
    $follower_id = $follower_id ?? get_user_id();
    if (!$follower_id) return false;
    
    $stmt = $conn->prepare("SELECT id FROM follows WHERE following_id = ? AND follower_id = ?");
    $stmt->bind_param("ii", $author_id, $follower_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Get comment count for post
 */
function get_comment_count($post_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM comments WHERE post_id = ? AND approved = 1");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

/**
 * Get reaction counts for post
 */
function get_reaction_counts($post_id) {
    global $conn;
    $types = ['like', 'love', 'insightful', 'funny'];
    $counts = [];
    foreach ($types as $type) {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM post_reactions WHERE post_id = ? AND reaction_type = ?");
        $stmt->bind_param("is", $post_id, $type);
        $stmt->execute();
        $counts[$type] = $stmt->get_result()->fetch_assoc()['cnt'];
    }
    return $counts;
}

/**
 * Create notification
 */
function create_notification($user_id, $type, $message, $ref_type = null, $ref_id = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssi", $user_id, $type, $message, $ref_type, $ref_id);
    return $stmt->execute();
}

/**
 * Get unread notification count
 */
function get_unread_notifications_count($user_id = null) {
    global $conn;
    $user_id = $user_id ?? get_user_id();
    if (!$user_id) return 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND read_at IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

/**
 * Notify all users about new or updated posts
 */
function notify_users_about_post($post_id, $post_title, $post_author_id = null, $is_update = false) {
    global $conn;

    // Get all users except the post author (for updates, we might want to exclude the author)
    $exclude_author = $is_update && $post_author_id;
    $where_clause = $exclude_author ? "WHERE id != ?" : "";

    $users_query = "SELECT id, username FROM users $where_clause";
    $stmt = $conn->prepare($users_query);

    if ($exclude_author) {
        $stmt->bind_param("i", $post_author_id);
    }

    $stmt->execute();
    $users = $stmt->get_result();

    // Create notification message
    $action = $is_update ? "updated" : "published";
    $message = "New post \"$post_title\" has been $action!";

    // Send notification to each user
    while ($user = $users->fetch_assoc()) {
        create_notification(
            $user['id'],
            'post',
            $message,
            'post',
            $post_id
        );
    }

    $stmt->close();
}

/**
 * Send email (wrapper function)
 */
function send_email($to, $subject, $body, $is_html = true) {
    $headers = "MIME-Version: 1.0\r\n";
    if ($is_html) {
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    }
    $headers .= "From: " . SITE_EMAIL . "\r\n";
    
    return mail($to, $subject, $body, $headers);
}

/**
 * Log activity
 */
function log_activity($action, $details = '', $user_id = null) {
    global $conn;
    $user_id = $user_id ?? get_user_id();
    $ip = get_client_ip();
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    return $stmt->execute();
}

/**
 * Clean old data (for cron jobs)
 */
function cleanup_old_data() {
    global $conn;
    
    // Clean old rate limits (older than 24 hours)
    $conn->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    
    // Clean old unconfirmed subscribers (older than 7 days)
    $conn->query("DELETE FROM subscribers WHERE confirmed = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    
    // Clean old post views (keep only 30 days for analytics)
    $conn->query("DELETE FROM post_views WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
}

/**
 * Build pagination HTML
 */
function pagination($current_page, $total_pages, $base_url, $params = []) {
    if ($total_pages <= 1) return '';
    
    $query_string = http_build_query($params);
    $separator = strpos($base_url, '?') !== false ? '&' : '?';
    
    $html = '<nav class="pagination">';
    
    // Previous
    $prev_class = $current_page == 1 ? 'disabled' : '';
    $prev_page = max(1, $current_page - 1);
    $html .= "<a href='{$base_url}{$separator}page={$prev_page}" . ($query_string ? "&$query_string" : "") . "' class='page-link $prev_class'><i class='fas fa-chevron-left'></i> Prev</a>";
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= "<a href='{$base_url}{$separator}page=1" . ($query_string ? "&$query_string" : "") . "' class='page-link'>1</a>";
        if ($start > 2) $html .= "<span class='page-link' style='border:none;'>...</span>";
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $html .= "<a href='{$base_url}{$separator}page={$i}" . ($query_string ? "&$query_string" : "") . "' class='page-link $active'>$i</a>";
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) $html .= "<span class='page-link' style='border:none;'>...</span>";
        $html .= "<a href='{$base_url}{$separator}page={$total_pages}" . ($query_string ? "&$query_string" : "") . "' class='page-link'>$total_pages</a>";
    }
    
    // Next
    $next_class = $current_page == $total_pages ? 'disabled' : '';
    $next_page = min($total_pages, $current_page + 1);
    $html .= "<a href='{$base_url}{$separator}page={$next_page}" . ($query_string ? "&$query_string" : "") . "' class='page-link $next_class'>Next <i class='fas fa-chevron-right'></i></a>";
    
    $html .= '</nav>';
    return $html;
}

/**
 * JSON response helper
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Success JSON response
 */
function json_success($message, $data = []) {
    json_response(array_merge(['success' => true, 'message' => $message], $data));
}

/**
 * Error JSON response
 */
function json_error($message, $status = 400) {
    json_response(['success' => false, 'message' => $message], $status);
}

require_once __DIR__ . '/db_connect.php';
