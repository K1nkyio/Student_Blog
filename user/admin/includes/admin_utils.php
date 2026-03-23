<?php
function admin_request_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return trim($ip) ?: '0.0.0.0';
}

function log_admin_audit(mysqli $conn, string $action, ?int $admin_id = null, ?int $actor_admin_id = null, string $details = ''): void {
    $ip = admin_request_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $conn->prepare(
        "INSERT INTO admin_audit_log (admin_id, actor_admin_id, action, details, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param(
        "iissss",
        $admin_id,
        $actor_admin_id,
        $action,
        $details,
        $ip,
        $user_agent
    );
    $stmt->execute();
    $stmt->close();
}

