<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../shared/db_connect.php');
include('includes/admin_utils.php');

$active_tab = 'login';
$login_error = '';
$request_msg = '';
$request_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $active_tab = $action === 'request' ? 'request' : 'login';

    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = md5($_POST['password'] ?? '');

        $stmt = $conn->prepare("SELECT id, username, role, status, password FROM admins WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin) {
            log_admin_audit($conn, 'login_failed', null, null, 'Unknown account: ' . $identifier);
            $login_error = "Invalid username or password.";
        } elseif (!hash_equals($admin['password'] ?? '', $password)) {
            log_admin_audit($conn, 'login_failed', (int)$admin['id'], null, 'Incorrect password');
            $login_error = "Invalid username or password.";
        } elseif (($admin['role'] ?? '') === 'super_admin') {
            log_admin_audit($conn, 'login_redirected', (int)$admin['id'], null, 'Use super admin login portal');
            $login_error = "Super admin accounts must sign in via the super admin portal.";
        } elseif (($admin['status'] ?? 'pending') === 'pending') {
            log_admin_audit($conn, 'login_pending', (int)$admin['id'], null, 'Awaiting approval');
            $login_error = "Your account is awaiting approval by the super admin.";
        } elseif (($admin['status'] ?? '') === 'deactivated') {
            log_admin_audit($conn, 'login_deactivated', (int)$admin['id'], null, 'Account deactivated');
            $login_error = "Your account has been deactivated. Please contact the super admin.";
        } else {
            $_SESSION['admin'] = $admin['username'] ?? $identifier;
            $_SESSION['admin_id'] = (int)($admin['id'] ?? 0);
            $_SESSION['admin_role'] = $admin['role'] ?? 'admin';

            $ip = admin_request_ip();
            $update = $conn->prepare("UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
            $update->bind_param("si", $ip, $_SESSION['admin_id']);
            $update->execute();
            $update->close();

            log_admin_audit($conn, 'login_success', (int)$_SESSION['admin_id'], null, 'Login successful');
            header('Location: dashboard.php');
            exit();
        }
    } elseif ($action === 'request') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $raw_pw   = trim($_POST['password'] ?? '');

        if ($username === '' || $email === '' || $raw_pw === '') {
            $request_msg = 'Please fill out all fields.';
            $request_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $request_msg = 'Please enter a valid email address.';
            $request_type = 'danger';
        } else {
            $check = $conn->prepare("SELECT id, status FROM admins WHERE username = ? OR email = ? LIMIT 1");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existing) {
                $request_msg = 'An account with those details already exists. If you already requested access, please wait for approval.';
                $request_type = 'warning';
            } else {
                $status = 'pending';
                $approved_by = null;
                $approved_at = null;

                if (($_SESSION['admin_role'] ?? '') === 'super_admin') {
                    $status = 'approved';
                    $approved_by = (int)($_SESSION['admin_id'] ?? 0);
                    $approved_at = date('Y-m-d H:i:s');
                }

                $password = md5($raw_pw);
                $stmt = $conn->prepare("INSERT INTO admins (username, email, password, role, status, approved_by, approved_at) VALUES (?, ?, ?, 'admin', ?, ?, ?)");
                $stmt->bind_param("ssssis", $username, $email, $password, $status, $approved_by, $approved_at);

                if ($stmt->execute()) {
                    $new_admin_id = $stmt->insert_id;
                    if ($status === 'approved') {
                        log_admin_audit($conn, 'account_created', $new_admin_id, $approved_by, 'Created and approved by super admin');
                        $request_msg = 'Admin account created and approved.';
                        $request_type = 'success';
                    } else {
                        log_admin_audit($conn, 'account_requested', $new_admin_id, null, 'Awaiting approval');
                        $request_msg = 'Request submitted. Your account is awaiting approval by the super admin.';
                        $request_type = 'success';
                    }
                } else {
                    $request_msg = 'Unable to submit your request. Please try again.';
                    $request_type = 'danger';
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Access — Student Blog</title>
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
      --green:        #1a7a4a;
      --green-dim:    #d2edd9;

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
      width: min(940px, 100%);
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
      content: 'Admin.';
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
    .visual-bottom { position: relative; z-index: 1; }
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
    .visual-features {
      display: flex;
      flex-direction: column;
      gap: .7rem;
    }
    .visual-feature {
      display: flex;
      align-items: flex-start;
      gap: .7rem;
      font-size: .8rem;
      color: rgba(255,255,255,.48);
      line-height: 1.5;
    }
    .feature-dot {
      width: 4px; height: 4px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
      margin-top: .45rem;
    }

    .auth-form {
      background: #fff;
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .tab-switch {
      display: inline-flex;
      gap: .35rem;
      background: var(--bg-warm);
      border: 1px solid var(--rule);
      border-radius: 999px;
      padding: .25rem;
      margin-bottom: 1.5rem;
    }
    .tab-btn {
      border: none;
      background: transparent;
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .05em;
      text-transform: uppercase;
      color: var(--ink-light);
      padding: .4rem .9rem;
      border-radius: 999px;
      cursor: pointer;
      transition: all var(--transition);
    }
    .tab-btn.active {
      background: var(--ink);
      color: #fff;
    }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; animation: fadeIn .2s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

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
    }
    .form-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: .1rem; }
    .form-alert--danger { background: var(--red-dim); border: 1px solid rgba(176,48,48,.18); border-left-color: var(--red); color: var(--red); }
    .form-alert--success { background: var(--green-dim); border: 1px solid rgba(26,122,74,.18); border-left-color: var(--green); color: var(--green); }
    .form-alert--info { background: var(--sky-dim); border: 1px solid rgba(26,95,200,.18); border-left-color: var(--sky); color: var(--sky); }
    .form-alert--warning { background: #fdf4e7; border: 1px solid rgba(184,134,11,.18); border-left-color: #b8860b; color: #b8860b; }

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
      .visual-features { display: none; }
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
          Admin Access
        </div>
        <h2 class="visual-headline">Secure<br>Admin Access</h2>
        <p class="visual-sub">Super admins approve accounts, while daily logins stay smooth for admins.</p>
      </div>
      <div class="visual-bottom">
        <div class="visual-features">
          <div class="visual-feature">
            <span class="feature-dot"></span>
            Approval-based admin onboarding
          </div>
          <div class="visual-feature">
            <span class="feature-dot"></span>
            Centralized admin oversight
          </div>
          <div class="visual-feature">
            <span class="feature-dot"></span>
            Audit logging for accountability
          </div>
        </div>
      </div>
    </div>

    <div class="auth-form">
      <div class="tab-switch" role="tablist">
        <button class="tab-btn <?php echo $active_tab === 'login' ? 'active' : ''; ?>" data-tab="login" type="button">Sign In</button>
        <button class="tab-btn <?php echo $active_tab === 'request' ? 'active' : ''; ?>" data-tab="request" type="button">Request Access</button>
      </div>

      <div class="tab-panel <?php echo $active_tab === 'login' ? 'active' : ''; ?>" id="tab-login">
        <div class="form-eyebrow">Welcome back</div>
        <h1 class="form-headline">Admin Sign In</h1>
        <p class="form-sub">Enter your credentials to access the admin dashboard.</p>

        <?php if ($login_error): ?>
          <div class="form-alert form-alert--danger">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5M8 11v.5" stroke-linecap="round"/>
            </svg>
            <?php echo htmlspecialchars($login_error); ?>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <input type="hidden" name="action" value="login">
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
      </div>

      <div class="tab-panel <?php echo $active_tab === 'request' ? 'active' : ''; ?>" id="tab-request">
        <div class="form-eyebrow">Request access</div>
        <h1 class="form-headline">Admin Access Request</h1>
        <p class="form-sub">Your request will be reviewed by the super admin.</p>

        <?php if ($request_msg): ?>
          <div class="form-alert form-alert--<?php echo htmlspecialchars($request_type); ?>">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="8" cy="8" r="6.5"/><path d="M5 8.5l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php echo htmlspecialchars($request_msg); ?>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <input type="hidden" name="action" value="request">
          <div class="field">
            <label class="field-label" for="req-username">Username</label>
            <input id="req-username" class="field-input" type="text" name="username"
              placeholder="Choose a unique username" required autocomplete="username">
          </div>

          <div class="field">
            <label class="field-label" for="req-email">Email Address</label>
            <input id="req-email" class="field-input" type="email" name="email"
              placeholder="admin@example.com" required autocomplete="email">
          </div>

          <div class="field">
            <label class="field-label" for="req-password">Password</label>
            <input id="req-password" class="field-input" type="password" name="password"
              placeholder="Create a strong password" required autocomplete="new-password">
          </div>

          <button class="btn-submit" type="submit">Request Access</button>
        </form>
      </div>

      <div class="form-footer">
        Need help? Contact the super admin for faster approval.
      </div>
    </div>
  </div>

  <script>
    (function () {
      const buttons = document.querySelectorAll('.tab-btn');
      const panels = document.querySelectorAll('.tab-panel');

      buttons.forEach(btn => {
        btn.addEventListener('click', () => {
          const tab = btn.dataset.tab;
          buttons.forEach(b => b.classList.toggle('active', b === btn));
          panels.forEach(p => p.classList.toggle('active', p.id === `tab-${tab}`));
        });
      });
    })();
  </script>
</body>
</html>

