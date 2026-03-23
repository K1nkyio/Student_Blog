<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../shared/db_connect.php');
include('../admin/includes/admin_utils.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = md5($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT id, username, role, status, password FROM admins WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$admin) {
        log_admin_audit($conn, 'login_failed', null, null, 'Unknown account: ' . $identifier);
        $error = "Invalid username or password.";
    } elseif (!hash_equals($admin['password'] ?? '', $password)) {
        log_admin_audit($conn, 'login_failed', (int)$admin['id'], null, 'Incorrect password');
        $error = "Invalid username or password.";
    } elseif (($admin['role'] ?? '') !== 'super_admin') {
        log_admin_audit($conn, 'login_denied', (int)$admin['id'], null, 'Not a super admin');
        $error = "This portal is for super admins only. Use the admin access page.";
    } elseif (($admin['status'] ?? 'pending') === 'pending') {
        log_admin_audit($conn, 'login_pending', (int)$admin['id'], null, 'Awaiting approval');
        $error = "Your account is awaiting approval by the super admin.";
    } elseif (($admin['status'] ?? '') === 'deactivated') {
        log_admin_audit($conn, 'login_deactivated', (int)$admin['id'], null, 'Account deactivated');
        $error = "Your account has been deactivated. Please contact the super admin.";
    } else {
        $_SESSION['admin'] = $admin['username'] ?? $identifier;
        $_SESSION['admin_id'] = (int)($admin['id'] ?? 0);
        $_SESSION['admin_role'] = $admin['role'] ?? 'admin';

        $ip = admin_request_ip();
        $update = $conn->prepare("UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
        $update->bind_param("si", $ip, $_SESSION['admin_id']);
        $update->execute();
        $update->close();

        log_admin_audit($conn, 'login_success', (int)$_SESSION['admin_id'], null, 'Super admin login');
        header('Location: users.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Super Admin Sign In — Student Blog</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:           #f5f2ed;
      --bg-warm:      #ede9e1;
      --bg-warmer:    #e5e0d6;
      --ink:          #18160f;
      --ink-mid:      #4a4540;
      --ink-light:    #7a7570;
      --rule:         #d4cfc7;
      --rule-light:   #e8e4dc;
      --accent:       #c8641a;
      --accent-dim:   #f0dece;
      --sky:          #1a5fc8;
      --sky-dim:      #dce9f8;
      --red:          #b03030;
      --red-dim:      #f8e0e0;

      --font-serif: 'Cormorant Garamond', Georgia, serif;
      --font-sans:  'Outfit', sans-serif;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: var(--font-sans);
      background: var(--bg);
      background-image: radial-gradient(var(--rule) 1px, transparent 1px);
      background-size: 22px 22px;
      color: var(--ink);
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 1.5rem;
    }

    .auth-card {
      width: min(920px, 100%);
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-radius: 4px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 16px 48px rgba(24,22,15,.14);
      animation: cardIn .55s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .auth-visual {
      background: var(--ink);
      color: #fff;
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }
    .auth-visual::before {
      content: 'Super.';
      position: absolute;
      right: -1rem;
      bottom: -2rem;
      font-family: var(--font-serif);
      font-size: clamp(5rem, 12vw, 9rem);
      font-weight: 700;
      font-style: italic;
      color: rgba(255,255,255,.035);
      line-height: 1;
      pointer-events: none;
      user-select: none;
    }
    .auth-visual::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
      background-size: 22px 22px;
      pointer-events: none;
    }
    .visual-top { position: relative; z-index: 1; }
    .visual-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      font-size: .62rem;
      font-weight: 600;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--accent);
      border: 1px solid rgba(200,100,26,.4);
      padding: .28rem .8rem;
      border-radius: 2px;
      margin-bottom: 1.25rem;
    }
    .live-dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 6px var(--accent);
      animation: blink 2s infinite;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .visual-headline {
      font-family: var(--font-serif);
      font-size: clamp(1.8rem, 3.2vw, 2.6rem);
      font-weight: 700;
      line-height: 1.08;
      letter-spacing: -.015em;
      color: #fff;
      margin-bottom: .75rem;
    }
    .visual-sub {
      font-size: .88rem;
      color: rgba(255,255,255,.42);
      font-weight: 300;
      line-height: 1.7;
    }

    .auth-form {
      background: #fff;
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .form-eyebrow {
      font-size: .62rem;
      font-weight: 600;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--ink-light);
      margin-bottom: .55rem;
    }
    .form-headline {
      font-family: var(--font-serif);
      font-size: clamp(1.6rem, 2.8vw, 2.1rem);
      font-weight: 700;
      line-height: 1.1;
      color: var(--ink);
      margin-bottom: .35rem;
    }
    .form-sub {
      font-size: .84rem;
      color: var(--ink-light);
      font-weight: 300;
      margin-bottom: 1.75rem;
      padding-bottom: 1.25rem;
      border-bottom: 1px solid var(--rule-light);
    }
    .form-alert {
      display: flex;
      align-items: flex-start;
      gap: .6rem;
      border-radius: 3px;
      padding: .72rem .9rem;
      font-size: .82rem;
      font-weight: 500;
      margin-bottom: 1.5rem;
      border-left-width: 3px;
      border-left-style: solid;
      background: var(--red-dim);
      border: 1px solid rgba(176,48,48,.18);
      border-left-color: var(--red);
      color: var(--red);
    }
    .form-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: .1rem; }

    .field { margin-bottom: 1.15rem; }
    .field-label {
      display: block;
      font-size: .68rem;
      font-weight: 600;
      letter-spacing: .09em;
      text-transform: uppercase;
      color: var(--ink-mid);
      margin-bottom: .5rem;
    }
    .field-input {
      width: 100%;
      font-family: var(--font-sans);
      font-size: .875rem;
      color: var(--ink);
      background: var(--bg);
      border: 1.5px solid var(--rule);
      border-radius: 3px;
      padding: .72rem .9rem;
      outline: none;
      transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
    }
    .field-input::placeholder { color: var(--ink-light); }
    .field-input:focus {
      border-color: var(--ink);
      background: #fff;
      box-shadow: 0 0 0 3px rgba(24,22,15,.06);
    }

    .btn-submit {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      font-family: var(--font-sans);
      font-size: .875rem;
      font-weight: 600;
      letter-spacing: .02em;
      color: #fff;
      background: var(--ink);
      border: none;
      border-radius: 3px;
      padding: .85rem 1.5rem;
      cursor: pointer;
      transition: all var(--transition);
      margin-top: .5rem;
    }
    .btn-submit:hover {
      background: #2c2a22;
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(24,22,15,.22);
    }

    .form-footer {
      margin-top: 1.5rem;
      padding-top: 1.25rem;
      border-top: 1px solid var(--rule-light);
      font-size: .8rem;
      color: var(--ink-light);
      text-align: center;
    }
    .form-footer a {
      color: var(--sky);
      text-decoration: none;
      font-weight: 500;
      transition: color var(--transition);
    }
    .form-footer a:hover { color: var(--ink); }

    @media (max-width: 720px) {
      .auth-card { grid-template-columns: 1fr; }
      .auth-visual { padding: 2rem 1.5rem; }
      .auth-form { padding: 2rem 1.5rem; }
    }
    @media (max-width: 400px) {
      body { padding: 0; }
      .auth-card { border-radius: 0; min-height: 100vh; align-items: start; }
    }
  </style>
</head>
<body>
  <div class="auth-card">
    <div class="auth-visual">
      <div class="visual-top">
        <div class="visual-eyebrow">
          <span class="live-dot"></span>
          Super Admin
        </div>
        <h2 class="visual-headline">Super Admin<br>Sign In</h2>
        <p class="visual-sub">High-privilege access for platform governance and approvals.</p>
      </div>
    </div>

    <div class="auth-form">
      <div class="form-eyebrow">Restricted access</div>
      <h1 class="form-headline">Super Admin Login</h1>
      <p class="form-sub">Use your super admin credentials to continue.</p>

      <?php if ($error): ?>
        <div class="form-alert">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5M8 11v.5" stroke-linecap="round"/>
          </svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <div class="field">
          <label class="field-label" for="identifier">Username or Email</label>
          <input id="identifier" class="field-input" type="text" name="identifier"
            placeholder="Enter your username or email" required autocomplete="username">
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <input id="password" class="field-input" type="password" name="password"
            placeholder="Enter your password" required autocomplete="current-password">
        </div>

        <button class="btn-submit" type="submit">Sign In</button>
      </form>

      <div class="form-footer">
        Admin login? <a href="../admin/register.php">Use the admin access page</a>.
      </div>
    </div>
  </div>
</body>
</html>
