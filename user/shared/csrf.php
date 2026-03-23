<?php
// ============================================
// FILE: csrf.php
// Simple CSRF protection functions
// ============================================

// Generate a CSRF token and store it in session
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate a CSRF token from POST or GET
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Output a hidden CSRF token field for forms
function csrf_token_field() {
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
// Backwards-compatible alias used in other files
function verify_csrf_token($token) {
    return validate_csrf_token($token);
}
