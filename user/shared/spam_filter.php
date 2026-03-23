<?php
function check_spam($content, $ip, $email = null) {
    global $conn;
    
    $is_spam = false;
    $reason = '';
    
    // Check for blocked IPs
    $stmt = $conn->prepare("SELECT id FROM blocked_ips WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['is_spam' => true, 'reason' => 'Blocked IP'];
    }
    
    // Check for blocked email domains
    if ($email) {
        $domain = substr(strrchr($email, "@"), 1);
        $stmt = $conn->prepare("SELECT id FROM blocked_domains WHERE domain = ?");
        $stmt->bind_param("s", $domain);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['is_spam' => true, 'reason' => 'Blocked domain'];
        }
    }
    
    // Check for spam patterns
    $spam_patterns = [
        '/\b(viagra|cialis|casino|poker|lottery)\b/i',
        '/https?:\/\/[^\s]{50,}/i', // Very long URLs
        '/(buy now|click here|act now|limited time)/i',
        '/(.)\1{5,}/', // Repeated characters
    ];
    
    foreach ($spam_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return ['is_spam' => true, 'reason' => 'Spam pattern detected'];
        }
    }
    
    // Check for too many links
    $link_count = preg_match_all('/https?:\/\//i', $content);
    if ($link_count > 3) {
        return ['is_spam' => true, 'reason' => 'Too many links'];
    }
    
    // Check duplicate content from same IP
    $stmt = $conn->prepare("SELECT id FROM comments WHERE ip_address = ? AND content = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->bind_param("ss", $ip, $content);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['is_spam' => true, 'reason' => 'Duplicate comment'];
    }
    
    return ['is_spam' => false, 'reason' => ''];
}