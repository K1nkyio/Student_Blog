<?php
require_once __DIR__ . '/session.php';

function admin_password_hash(string $password): string
{
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    try {
        return password_hash($password, $algorithm);
    } catch (Throwable $e) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

/** Verify current hashes and transparently upgrade legacy MD5 hashes. */
function admin_password_verify(string $password, string $storedHash, ?string &$upgradedHash = null): bool
{
    $upgradedHash = null;
    if (preg_match('/\A[a-f0-9]{32}\z/i', $storedHash)) {
        if (!hash_equals(strtolower($storedHash), md5($password))) {
            return false;
        }
        $upgradedHash = admin_password_hash($password);
        return true;
    }

    if (!password_verify($password, $storedHash)) {
        return false;
    }
    if (password_needs_rehash($storedHash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        $upgradedHash = admin_password_hash($password);
    }
    return true;
}

function ensure_admin_auth_schema(mysqli $conn): void
{
    $column = $conn->query("SHOW COLUMNS FROM admins LIKE 'password'")->fetch_assoc();
    if (!$column) {
        throw new RuntimeException('The admins table has no password column.');
    }
    if (preg_match('/^(?:var)?char\((\d+)\)/i', (string)$column['Type'], $match) && (int)$match[1] < 255) {
        $nullable = ($column['Null'] ?? 'NO') === 'YES' ? 'NULL' : 'NOT NULL';
        $conn->query("ALTER TABLE admins MODIFY password VARCHAR(255) {$nullable}");
    }
    $mfaSecretColumn = $conn->query("SHOW COLUMNS FROM admins LIKE 'mfa_secret'")->fetch_assoc();
    if (!$mfaSecretColumn) {
        $conn->query('ALTER TABLE admins ADD COLUMN mfa_secret TEXT NULL');
    }
    $mfaEnabledColumn = $conn->query("SHOW COLUMNS FROM admins LIKE 'mfa_enabled'")->fetch_assoc();
    if (!$mfaEnabledColumn) {
        $conn->query('ALTER TABLE admins ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }
    $conn->query("CREATE TABLE IF NOT EXISTS admin_login_attempts (
        attempt_key CHAR(64) NOT NULL PRIMARY KEY,
        attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at DATETIME NOT NULL,
        blocked_until DATETIME NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_admin_login_blocked (blocked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function admin_mfa_key(): string
{
    $configured = getenv('STUDENT_BLOG_MFA_KEY');
    if (is_string($configured) && $configured !== '') {
        $decoded = base64_decode($configured, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }
        throw new RuntimeException('STUDENT_BLOG_MFA_KEY must be base64-encoded 32-byte key material.');
    }

    // Local development fallback is outside the user/ web root. Production should use a secret manager.
    $keyPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.admin_mfa_key';
    if (!is_file($keyPath)) {
        $key = base64_encode(random_bytes(32));
        $handle = @fopen($keyPath, 'x');
        if ($handle !== false) {
            fwrite($handle, $key);
            fclose($handle);
            @chmod($keyPath, 0600);
        }
    }
    $encoded = @file_get_contents($keyPath);
    $decoded = $encoded === false ? false : base64_decode(trim($encoded), true);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new RuntimeException('Unable to load the super-admin MFA encryption key.');
    }
    return $decoded;
}

function admin_mfa_encrypt(string $secret): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', admin_mfa_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Could not protect the MFA secret.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function admin_mfa_decrypt(string $value): string
{
    $data = base64_decode($value, true);
    if ($data === false || strlen($data) < 29) {
        throw new RuntimeException('Stored MFA secret is invalid.');
    }
    $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', admin_mfa_key(), OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16));
    if ($plain === false) {
        throw new RuntimeException('Could not decrypt the MFA secret. Preserve the MFA key and contact the administrator.');
    }
    return $plain;
}

function admin_mfa_base32_encode(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($value) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function admin_mfa_base32_decode(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(rtrim($value, '='))) as $char) {
        $position = strpos($alphabet, $char);
        if ($position === false) return '';
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $output .= chr(bindec($chunk));
    }
    return $output;
}

function admin_mfa_new_secret(): string
{
    return admin_mfa_base32_encode(random_bytes(20));
}

function admin_mfa_verify(string $secret, string $code, ?int $time = null): bool
{
    if (!preg_match('/\A\d{6}\z/', trim($code))) return false;
    $key = admin_mfa_base32_decode($secret);
    if ($key === '') return false;
    $counter = (int)floor(($time ?? time()) / 30);
    for ($offset = -1; $offset <= 1; $offset++) {
        $binaryCounter = pack('N2', 0, $counter + $offset);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $index = ord($hash[19]) & 0x0f;
        $number = ((ord($hash[$index]) & 0x7f) << 24)
            | ((ord($hash[$index + 1]) & 0xff) << 16)
            | ((ord($hash[$index + 2]) & 0xff) << 8)
            | (ord($hash[$index + 3]) & 0xff);
        if (hash_equals(str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT), trim($code))) return true;
    }
    return false;
}

function admin_mfa_uri(string $secret, string $username): string
{
    $label = rawurlencode('Student Blog:' . $username);
    return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=Student%20Blog&algorithm=SHA1&digits=6&period=30';
}

function admin_login_attempt_key(string $identifier): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', strtolower(trim($identifier)) . "\0" . $ip);
}

function admin_login_is_throttled(mysqli $conn, string $key): bool
{
    $stmt = $conn->prepare('SELECT blocked_until FROM admin_login_attempts WHERE attempt_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($row['blocked_until']) && strtotime($row['blocked_until']) > time();
}

function admin_login_record_failure(mysqli $conn, string $key): void
{
    $stmt = $conn->prepare("INSERT INTO admin_login_attempts (attempt_key, attempt_count, window_started_at, blocked_until, updated_at)
        VALUES (?, 1, NOW(), NULL, NOW())
        ON DUPLICATE KEY UPDATE
          attempt_count = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempt_count + 1),
          blocked_until = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NULL,
            IF(attempt_count >= 4, DATE_ADD(NOW(), INTERVAL 15 MINUTE), blocked_until)),
          window_started_at = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started_at),
          updated_at = NOW()");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->close();
}

function admin_login_clear_failures(mysqli $conn, string $key): void
{
    $stmt = $conn->prepare('DELETE FROM admin_login_attempts WHERE attempt_key = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->close();
}

function admin_session_login(array $admin, bool $mfaVerified = false): void
{
    session_regenerate_id(true);
    $_SESSION['admin'] = (string)$admin['username'];
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_role'] = (string)$admin['role'];
    $_SESSION['admin_mfa_verified'] = $admin['role'] === 'super_admin' && $mfaVerified;
    $_SESSION['admin_authenticated_at'] = time();
    $_SESSION['admin_last_activity'] = time();
}

/** Enforce idle/absolute expiry and re-check role/status against the database. */
function require_admin_access(mysqli $conn, ?string $requiredRole = null, bool $redirect = true): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        start_project_session();
    }
    $now = time();
    $idleLimit = 30 * 60;
    $absoluteLimit = 8 * 60 * 60;
    $expired = empty($_SESSION['admin_id'])
        || empty($_SESSION['admin_authenticated_at'])
        || empty($_SESSION['admin_last_activity'])
        || ($now - (int)($_SESSION['admin_last_activity'] ?? 0)) > $idleLimit
        || ($now - (int)($_SESSION['admin_authenticated_at'] ?? 0)) > $absoluteLimit;

    if (!$expired) {
        $id = (int)$_SESSION['admin_id'];
        $stmt = $conn->prepare('SELECT username, role, status FROM admins WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $expired = !$account || ($account['status'] ?? '') !== 'approved';
        if ($account && ($account['role'] ?? '') === 'super_admin' && empty($_SESSION['admin_mfa_verified'])) {
            $expired = true;
        }
        if (!$expired) {
            if (($_SESSION['admin_role'] ?? '') !== (string)$account['role']) {
                session_regenerate_id(true);
            }
            $_SESSION['admin'] = (string)$account['username'];
            $_SESSION['admin_role'] = (string)$account['role'];
            $_SESSION['admin_last_activity'] = $now;
            if ($requiredRole !== null && $_SESSION['admin_role'] !== $requiredRole) {
                if ($redirect) {
                    http_response_code(403);
                    exit('Forbidden');
                }
                return false;
            }
        }
    }

    if ($expired) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'] ?: '/', 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax']);
        }
        session_destroy();
        if ($redirect) {
            $isSuper = strpos((string)($_SERVER['PHP_SELF'] ?? ''), '/super_admin/') !== false;
            header('Location: ' . ($isSuper ? '../super_admin/login.php' : 'register.php'));
            exit();
        }
        return false;
    }
    return true;
}
