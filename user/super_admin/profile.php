<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

include '../shared/db_connect.php';

$topbar_title_override = 'Profile';
$topbar_subtitle_override = 'Manage your super admin account details';
include 'includes/header.php';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$error = '';
$success = '';

$stmt = $conn->prepare("SELECT id, username, email, role, status, created_at, approved_at, last_login_at, last_login_ip FROM admins WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    echo '<div class="alert alert-danger">Unable to load your profile.</div>';
    include '../admin/includes/footer.php';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($username === '' || $email === '') {
        $error = 'Username and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif ($new_password !== '' && $new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        $check = $conn->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        $check->bind_param('ssi', $username, $email, $admin_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $error = 'Another admin account already uses that username or email.';
        } else {
            if ($new_password !== '') {
                $password = md5($new_password);
                $update = $conn->prepare("UPDATE admins SET username = ?, email = ?, password = ? WHERE id = ?");
                $update->bind_param('sssi', $username, $email, $password, $admin_id);
            } else {
                $update = $conn->prepare("UPDATE admins SET username = ?, email = ? WHERE id = ?");
                $update->bind_param('ssi', $username, $email, $admin_id);
            }

            if ($update->execute()) {
                $_SESSION['admin'] = $username;
                $success = 'Profile updated successfully.';
                $admin['username'] = $username;
                $admin['email'] = $email;
            } else {
                $error = 'Unable to update your profile right now.';
            }
            $update->close();
        }
    }
}
?>

<style>
.pf-shell {
  display: grid;
  grid-template-columns: 340px 1fr;
  gap: 1.25rem;
}
.pf-panel,
.pf-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
}
.pf-panel {
  padding: 1.5rem;
  position: sticky;
  top: 84px;
  height: fit-content;
}
.pf-avatar {
  width: 72px;
  height: 72px;
  border-radius: 12px;
  background: var(--ink);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-serif);
  font-size: 1.8rem;
  margin-bottom: 1rem;
}
.pf-name {
  font-family: var(--font-serif);
  font-size: 1.8rem;
  margin-bottom: .35rem;
}
.pf-muted {
  color: var(--ink-light);
  font-size: .88rem;
}
.pf-badges {
  display: flex;
  gap: .55rem;
  flex-wrap: wrap;
  margin: 1rem 0 1.2rem;
}
.pf-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .35rem .7rem;
  border-radius: 999px;
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  background: var(--bg);
  border: 1px solid var(--rule);
}
.pf-meta {
  display: grid;
  gap: .85rem;
}
.pf-meta-item strong {
  display: block;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--ink-light);
  margin-bottom: .2rem;
}

.pf-card {
  overflow: hidden;
}
.pf-card-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
  padding: 1rem 1.25rem;
  font-family: var(--font-serif);
  font-size: 1.1rem;
  font-weight: 700;
}
.pf-card-body {
  padding: 1.35rem 1.25rem 1.45rem;
}
.pf-alert {
  padding: .85rem .95rem;
  border-radius: 3px;
  margin-bottom: 1rem;
  font-size: .84rem;
}
.pf-alert.error { background: var(--red-dim); color: var(--red); }
.pf-alert.success { background: var(--green-dim); color: var(--green); }
.pf-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}
.pf-field {
  margin-bottom: 1rem;
}
.pf-field label {
  display: block;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .45rem;
}
.pf-field input {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .75rem .85rem;
  font-size: .88rem;
}
.pf-field input:focus {
  outline: none;
  border-color: var(--ink);
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
}
.pf-actions {
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
  margin-top: .4rem;
}
.pf-btn {
  border: none;
  border-radius: 3px;
  padding: .75rem 1.1rem;
  font-size: .84rem;
  font-weight: 700;
  cursor: pointer;
}
.pf-btn.primary {
  background: var(--ink);
  color: #fff;
}
.pf-btn.secondary {
  background: var(--bg);
  color: var(--ink-mid);
  border: 1px solid var(--rule);
}

@media (max-width: 900px) {
  .pf-shell { grid-template-columns: 1fr; }
  .pf-panel { position: static; }
}
@media (max-width: 640px) {
  .pf-grid { grid-template-columns: 1fr; }
  .pf-actions > * { width: 100%; }
}
</style>

<div class="pf-shell">
  <aside class="pf-panel">
    <div class="pf-avatar"><?php echo htmlspecialchars(strtoupper(substr($admin['username'], 0, 2))); ?></div>
    <div class="pf-name"><?php echo htmlspecialchars($admin['username']); ?></div>
    <div class="pf-muted"><?php echo htmlspecialchars($admin['email']); ?></div>

    <div class="pf-badges">
      <span class="pf-badge"><?php echo htmlspecialchars(str_replace('_', ' ', $admin['role'])); ?></span>
      <span class="pf-badge"><?php echo htmlspecialchars($admin['status']); ?></span>
    </div>

    <div class="pf-meta">
      <div class="pf-meta-item">
        <strong>Approved At</strong>
        <span><?php echo !empty($admin['approved_at']) ? date('M d, Y g:i A', strtotime($admin['approved_at'])) : 'Not recorded'; ?></span>
      </div>
      <div class="pf-meta-item">
        <strong>Last Login</strong>
        <span><?php echo !empty($admin['last_login_at']) ? date('M d, Y g:i A', strtotime($admin['last_login_at'])) : 'Not recorded'; ?></span>
      </div>
      <div class="pf-meta-item">
        <strong>Last Login IP</strong>
        <span><?php echo htmlspecialchars($admin['last_login_ip'] ?: 'Not recorded'); ?></span>
      </div>
      <div class="pf-meta-item">
        <strong>Member Since</strong>
        <span><?php echo !empty($admin['created_at']) ? date('M d, Y', strtotime($admin['created_at'])) : 'Not recorded'; ?></span>
      </div>
    </div>
  </aside>

  <section class="pf-card">
    <div class="pf-card-head">Update Profile</div>
    <div class="pf-card-body">
      <?php if ($error !== ''): ?>
        <div class="pf-alert error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success !== ''): ?>
        <div class="pf-alert success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="pf-grid">
          <div class="pf-field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required value="<?php echo htmlspecialchars($admin['username']); ?>">
          </div>
          <div class="pf-field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($admin['email']); ?>">
          </div>
          <div class="pf-field">
            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" placeholder="Leave blank to keep your current password">
          </div>
          <div class="pf-field">
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" placeholder="Repeat the new password">
          </div>
        </div>

        <div class="pf-actions">
          <button class="pf-btn primary" type="submit">Save Changes</button>
          <a class="pf-btn secondary" href="dashboard.php" style="text-decoration:none;text-align:center;">Back to Dashboard</a>
        </div>
      </form>
    </div>
  </section>
</div>

<?php include '../admin/includes/footer.php'; ?>
