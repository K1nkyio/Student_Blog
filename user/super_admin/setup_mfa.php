<?php
require_once dirname(__DIR__) . '/shared/session.php';
start_project_session();
require_once dirname(__DIR__) . '/shared/db_connect.php';
require_once dirname(__DIR__) . '/shared/admin_auth.php';
require_once dirname(__DIR__) . '/admin/includes/admin_utils.php';
ensure_admin_auth_schema($conn);

$pendingId = (int)($_SESSION['pending_super_admin_id'] ?? 0);
$pendingSecret = (string)($_SESSION['pending_super_admin_secret'] ?? '');
$validPending = ($_SESSION['pending_super_admin_stage'] ?? '') === 'enroll'
    && (time() - (int)($_SESSION['pending_super_admin_at'] ?? 0)) <= 300
    && $pendingId > 0 && $pendingSecret !== '';

if (!$validPending) {
    unset($_SESSION['pending_super_admin_id'], $_SESSION['pending_super_admin_stage'], $_SESSION['pending_super_admin_at'], $_SESSION['pending_super_admin_secret']);
    header('Location: login.php');
    exit();
}

$stmt = $conn->prepare("SELECT id, username, role, status FROM admins WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $pendingId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$admin || $admin['role'] !== 'super_admin' || $admin['status'] !== 'approved') {
    unset($_SESSION['pending_super_admin_id'], $_SESSION['pending_super_admin_stage'], $_SESSION['pending_super_admin_at'], $_SESSION['pending_super_admin_secret']);
    header('Location: login.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['mfa_enrollment_attempts'] = (int)($_SESSION['mfa_enrollment_attempts'] ?? 0) + 1;
    if ($_SESSION['mfa_enrollment_attempts'] > 5) {
        $error = 'Too many incorrect codes. Start sign-in again.';
    } elseif (admin_mfa_verify($pendingSecret, (string)($_POST['mfa_code'] ?? ''))) {
        $encryptedSecret = admin_mfa_encrypt($pendingSecret);
        $update = $conn->prepare("UPDATE admins SET mfa_secret = ?, mfa_enabled = 1 WHERE id = ? AND role = 'super_admin' AND status = 'approved'");
        $update->bind_param('si', $encryptedSecret, $pendingId);
        $update->execute();
        $update->close();
        unset($_SESSION['pending_super_admin_id'], $_SESSION['pending_super_admin_stage'], $_SESSION['pending_super_admin_at'], $_SESSION['pending_super_admin_secret'], $_SESSION['mfa_enrollment_attempts']);
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $updateLogin = $conn->prepare('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?');
        $updateLogin->bind_param('si', $ip, $pendingId);
        $updateLogin->execute();
        $updateLogin->close();
        admin_session_login($admin, true);
        log_admin_audit($conn, 'mfa_enabled', $pendingId, $pendingId, 'Super-admin authenticator MFA enrolled');
        header('Location: users.php');
        exit();
    } else {
        $error = 'That code did not verify. Check the authenticator time and try again.';
    }
}

$secret = htmlspecialchars($pendingSecret, ENT_QUOTES, 'UTF-8');
$username = htmlspecialchars((string)$admin['username'], ENT_QUOTES, 'UTF-8');
$otpUri = htmlspecialchars(admin_mfa_uri($pendingSecret, (string)$admin['username']), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set up super-admin verification</title>
  <style>
    body{font:16px/1.5 system-ui,sans-serif;background:#f4f2ed;color:#201d18;margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}
    main{background:#fff;max-width:560px;width:100%;padding:32px;border-radius:16px;box-shadow:0 18px 60px #201d181c}h1{margin-top:0}code{display:block;overflow-wrap:anywhere;padding:14px;background:#f4f2ed;border-radius:8px;font-size:1.15rem;letter-spacing:.08em}label{display:block;font-weight:650;margin:24px 0 8px}input{box-sizing:border-box;width:100%;padding:12px;font:inherit;border:1px solid #aaa;border-radius:8px}button{margin-top:16px;padding:12px 18px;background:#201d18;color:#fff;border:0;border-radius:8px;font:inherit;cursor:pointer}.error{color:#a32222}
  </style>
</head>
<body>
<main>
  <h1>Set up authenticator verification</h1>
  <p>Super-admin accounts must use a time-based authenticator code. Add this account to an authenticator app using the setup key below. Enter the current six-digit code to finish sign-in.</p>
  <p><strong>Account:</strong> <?= $username ?></p>
  <p><strong>Setup key:</strong></p>
  <code><?= $secret ?></code>
  <p>If your authenticator app supports importing an `otpauth` URI, use this value:</p>
  <code><?= $otpUri ?></code>
  <?php if ($error !== ''): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <form method="post" autocomplete="off">
    <label for="mfa_code">6-digit verification code</label>
    <input id="mfa_code" name="mfa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
    <button type="submit">Verify and continue</button>
  </form>
</main>
</body>
</html>
