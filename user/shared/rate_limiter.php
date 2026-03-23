<?php
function check_rate_limit($action, $identifier, $max_attempts, $window_seconds) {
    global $conn;
    
    $key = md5($action . $identifier);
    $now = time();
    $window_start = $now - $window_seconds;
    
    // Clean old entries
    $conn->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL $window_seconds SECOND)");
    
    // Count attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM rate_limits WHERE rate_key = ? AND created_at > FROM_UNIXTIME(?)");
    $stmt->bind_param("si", $key, $window_start);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    
    if ($count >= $max_attempts) {
        return false;
    }
    
    // Log this attempt
    $stmt = $conn->prepare("INSERT INTO rate_limits (rate_key, ip_address, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $key, $identifier);
    $stmt->execute();
    
    return true;
}