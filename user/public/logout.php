<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

// Only allow POST logout to prevent accidental GET logouts
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    // invalid token: redirect with message
    set_flash('error', 'Invalid logout request.');
    redirect('index.php');
}

// Capture user id for logging before clearing session
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    log_activity('logout', 'User logged out', $user_id);
}

// Clear session data
$_SESSION = [];

// Delete session cookie if present
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Start a fresh session to set a flash message
session_start();
set_flash('success', 'You have been logged out.');

// Redirect to homepage
redirect('index.php');
