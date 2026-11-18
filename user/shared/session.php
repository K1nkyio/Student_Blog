<?php
/**
 * Start a project session using a writable directory outside the public web root.
 */
function start_project_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Store sessions above user/ so they stay outside both the public and local admin document roots.
    $sessionDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'sessions';

    if (!is_dir($sessionDirectory) && !@mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
        throw new RuntimeException('Unable to create the project session directory.');
    }

    if (!is_writable($sessionDirectory)) {
        throw new RuntimeException('The project session directory is not writable.');
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '1800');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_save_path($sessionDirectory);
    session_start();
}

function destroy_project_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
