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

include 'includes/header.php';
include '../shared/db_connect.php';
include '../admin/includes/admin_utils.php';

$msg = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $admin_id = (int)($_POST['admin_id'] ?? 0);
    $current_admin_id = (int)($_SESSION['admin_id'] ?? 0);

    $target_stmt = $conn->prepare("SELECT id, username, role, status FROM admins WHERE id = ? LIMIT 1");
    $target_stmt->bind_param("i", $admin_id);
    $target_stmt->execute();
    $target = $target_stmt->get_result()->fetch_assoc();
    $target_stmt->close();

    if (!$target) {
        $msg = 'Admin account not found.';
        $msgType = 'danger';
    } else {
        $target_role = $target['role'] ?? 'admin';
        $target_status = $target['status'] ?? 'pending';

        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM admins WHERE role = 'super_admin' AND status = 'approved'");
        $count_stmt->execute();
        $super_admin_count = (int)($count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $count_stmt->close();

        if ($action === 'update_role') {
            $new_role = trim($_POST['role'] ?? '');
            $allowed_roles = ['super_admin', 'admin', 'editor'];

            if (!in_array($new_role, $allowed_roles, true)) {
                $msg = 'Invalid role update request.';
                $msgType = 'danger';
            } elseif ($admin_id === $current_admin_id && $new_role !== 'super_admin') {
                $msg = 'You cannot remove your own super admin access.';
                $msgType = 'warning';
            } elseif ($target_role === 'super_admin' && $new_role !== 'super_admin' && $super_admin_count <= 1) {
                $msg = 'At least one approved super admin must remain active.';
                $msgType = 'warning';
            } elseif ($target_role === $new_role) {
                $msg = 'No changes made - the role is already set.';
                $msgType = 'info';
            } else {
                $update_stmt = $conn->prepare("UPDATE admins SET role = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_role, $admin_id);
                if ($update_stmt->execute()) {
                    log_admin_audit($conn, 'role_update', $admin_id, $current_admin_id, "Role: {$target_role} -> {$new_role}");
                    $msg = 'Role updated successfully.';
                    $msgType = 'success';
                } else {
                    $msg = 'Failed to update role. Please try again.';
                    $msgType = 'danger';
                }
                $update_stmt->close();
            }
        } elseif ($action === 'approve') {
            if ($target_status !== 'pending') {
                $msg = 'Only pending accounts can be approved.';
                $msgType = 'warning';
            } else {
                $approve_stmt = $conn->prepare("UPDATE admins SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $approve_stmt->bind_param("ii", $current_admin_id, $admin_id);
                $approve_stmt->execute();
                $approve_stmt->close();
                log_admin_audit($conn, 'account_approved', $admin_id, $current_admin_id, 'Approved admin account');
                $msg = 'Admin account approved.';
                $msgType = 'success';
            }
        } elseif ($action === 'deactivate') {
            if ($admin_id === $current_admin_id) {
                $msg = 'You cannot deactivate your own account.';
                $msgType = 'warning';
            } elseif ($target_role === 'super_admin' && $super_admin_count <= 1) {
                $msg = 'At least one approved super admin must remain active.';
                $msgType = 'warning';
            } else {
                $deactivate_stmt = $conn->prepare("UPDATE admins SET status = 'deactivated' WHERE id = ?");
                $deactivate_stmt->bind_param("i", $admin_id);
                $deactivate_stmt->execute();
                $deactivate_stmt->close();
                log_admin_audit($conn, 'account_deactivated', $admin_id, $current_admin_id, 'Deactivated admin account');
                $msg = 'Admin account deactivated.';
                $msgType = 'success';
            }
        } elseif ($action === 'activate') {
            $activate_stmt = $conn->prepare("UPDATE admins SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $activate_stmt->bind_param("ii", $current_admin_id, $admin_id);
            $activate_stmt->execute();
            $activate_stmt->close();
            log_admin_audit($conn, 'account_activated', $admin_id, $current_admin_id, 'Reactivated admin account');
            $msg = 'Admin account reactivated.';
            $msgType = 'success';
        }
    }
}

$admins = [];
$admin_res = $conn->query("SELECT id, username, email, role, status, approved_at, last_login_at FROM admins ORDER BY CASE role WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, username ASC");
if ($admin_res) {
    while ($row = $admin_res->fetch_assoc()) {
        $admins[] = $row;
    }
}

$counts = [
    'total' => count($admins),
    'super_admin' => 0,
    'admin' => 0,
    'editor' => 0,
];
foreach ($admins as $row) {
    $role = $row['role'] ?? 'admin';
    if (isset($counts[$role])) {
        $counts[$role]++;
    }
}

$active_tab = 'users';
?>

<style>
.sa-heading {
  margin-bottom: 2rem;
  animation: dashIn .45s ease both;
}
@keyframes dashIn {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}
.sa-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .6rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .25rem .75rem;
  border-radius: 2px;
  margin-bottom: .7rem;
}
.sa-title {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 3vw, 2.6rem);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.08;
  letter-spacing: -.015em;
  margin-bottom: .35rem;
}
.sa-sub {
  font-size: .855rem;
  color: var(--ink-light);
  font-weight: 300;
  max-width: 620px;
}

.sa-tabs {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}
.sa-tab {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1.25rem;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
}
.sa-tab::before {
  content: '';
  position: absolute;
  left: 0; top: 10px; bottom: 10px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: var(--accent);
}
.sa-tab:hover {
  border-color: var(--rule);
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
}
.sa-tab.active {
  border-color: var(--ink);
  box-shadow: 0 2px 10px rgba(24,22,15,.12), 0 10px 28px rgba(24,22,15,.14);
}
.sa-tab-title {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: .35rem;
}
.sa-tab-desc {
  font-size: .8rem;
  color: var(--ink-light);
  line-height: 1.6;
}
.sa-tab-meta {
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-top: .65rem;
}
.sa-tab:nth-child(2)::before { background: var(--sky); }
.sa-tab:nth-child(3)::before { background: var(--purple); }
.sa-tab:nth-child(4)::before { background: var(--green); }
.sa-tab:nth-child(5)::before { background: var(--amber); }

.sa-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.75rem;
}
.sa-kpi {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1.2rem 1.25rem;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
  animation: dashIn .45s ease both;
}
.sa-kpi:nth-child(1) { animation-delay: .05s; }
.sa-kpi:nth-child(2) { animation-delay: .1s; }
.sa-kpi:nth-child(3) { animation-delay: .15s; }
.sa-kpi:nth-child(4) { animation-delay: .2s; }
.sa-kpi::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
}
.sa-kpi--total::before { background: var(--sky); }
.sa-kpi--super::before { background: var(--accent); }
.sa-kpi--admin::before { background: var(--green); }
.sa-kpi--editor::before { background: var(--purple); }
.sa-kpi-label {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .35rem;
}
.sa-kpi-value {
  font-family: var(--font-serif);
  font-size: 2rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
}

.sa-section {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.sa-section-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.sa-section-title {
  font-family: var(--font-serif);
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}
.sa-section-note {
  font-size: .75rem;
  color: var(--ink-light);
}

.role-pill {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .22rem .6rem;
  border-radius: 2px;
}
.role-pill--super_admin { background: var(--accent-dim); color: var(--accent); }
.role-pill--admin { background: var(--green-dim); color: var(--green); }
.role-pill--editor { background: var(--purple-dim); color: var(--purple); }
.role-pill::before {
  content: '';
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .22rem .6rem;
  border-radius: 2px;
}
.status-pill--approved { background: var(--green-dim); color: var(--green); }
.status-pill--pending { background: var(--amber-dim); color: var(--amber); }
.status-pill--deactivated { background: var(--red-dim); color: var(--red); }
.status-pill::before {
  content: '';
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.admin-name {
  font-weight: 600;
  color: var(--ink);
}
.admin-email {
  font-size: .78rem;
  color: var(--ink-light);
}
.admin-you {
  font-size: .6rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .15rem .4rem;
  border-radius: 99px;
  margin-left: .35rem;
}

.role-form {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
}
.role-form select {
  min-width: 140px;
}
.access-actions {
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
}
.access-actions .btn {
  font-size: .72rem;
  padding: .35rem .75rem;
}

.sa-empty {
  text-align: center;
  padding: 4rem 2rem;
  color: var(--ink-light);
}
.sa-empty h4 {
  font-family: var(--font-serif);
  font-size: 1.2rem;
  color: var(--ink-mid);
  margin-bottom: .35rem;
}

@media (max-width: 1024px) {
  .sa-kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .sa-tabs { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .sa-kpi-grid { grid-template-columns: 1fr; }
  .sa-tabs { grid-template-columns: 1fr; }
  .sa-section-head { flex-direction: column; align-items: flex-start; }
  .role-form { width: 100%; }
  .role-form select, .role-form button { width: 100%; }
  .access-actions { width: 100%; }
}
</style>

<div class="sa-heading">
  <div class="sa-eyebrow">Super Admin</div>
  <h1 class="sa-title">Admin Control Center</h1>
  <p class="sa-sub">Approve, audit, and manage admin and user access across the platform.</p>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?php echo $msgType === 'success' ? 'success' : ($msgType === 'danger' ? 'danger' : ($msgType === 'warning' ? 'warning' : 'info')); ?>">
    <?php echo htmlspecialchars($msg); ?>
  </div>
<?php endif; ?>

<div class="sa-kpi-grid">
  <div class="sa-kpi sa-kpi--total">
    <div class="sa-kpi-label">Total Admins</div>
    <div class="sa-kpi-value"><?php echo $counts['total']; ?></div>
  </div>
  <div class="sa-kpi sa-kpi--super">
    <div class="sa-kpi-label">Super Admins</div>
    <div class="sa-kpi-value"><?php echo $counts['super_admin']; ?></div>
  </div>
  <div class="sa-kpi sa-kpi--admin">
    <div class="sa-kpi-label">Admins</div>
    <div class="sa-kpi-value"><?php echo $counts['admin']; ?></div>
  </div>
  <div class="sa-kpi sa-kpi--editor">
    <div class="sa-kpi-label">Editors</div>
    <div class="sa-kpi-value"><?php echo $counts['editor']; ?></div>
  </div>
</div>

<div class="sa-section">
  <div class="sa-section-head">
    <div>
      <div class="sa-section-title">Admin Accounts</div>
      <div class="sa-section-note">Approve, deactivate, or change roles for admin accounts.</div>
    </div>
    <a href="../admin/register.php" class="btn btn-primary btn-sm">Request / Create Admin</a>
  </div>

  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Admin</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Update Role</th>
          <th>Access</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($admins)): ?>
          <?php
            $super_admin_count = $counts['super_admin'];
            $current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
          ?>
          <?php foreach ($admins as $admin): ?>
            <?php
              $role = $admin['role'] ?? 'admin';
              $status = $admin['status'] ?? 'pending';
              $is_current = (int)($admin['id'] ?? 0) === $current_admin_id;
              $is_last_super = $role === 'super_admin' && $super_admin_count <= 1;
              $disable_role = $is_last_super && !$is_current;
              $last_login = !empty($admin['last_login_at']) ? date('M d, Y H:i', strtotime($admin['last_login_at'])) : '—';
            ?>
            <tr>
              <td>
                <div class="admin-name">
                  <?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?>
                  <?php if ($is_current): ?><span class="admin-you">You</span><?php endif; ?>
                </div>
                <div class="admin-email"><?php echo htmlspecialchars($admin['email'] ?? ''); ?></div>
              </td>
              <td>
                <span class="role-pill role-pill--<?php echo htmlspecialchars($role); ?>">
                  <?php echo htmlspecialchars(str_replace('_', ' ', strtoupper($role))); ?>
                </span>
              </td>
              <td>
                <span class="status-pill status-pill--<?php echo htmlspecialchars($status); ?>">
                  <?php echo htmlspecialchars(strtoupper($status)); ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars($last_login); ?></td>
              <td>
                <form class="role-form" method="post">
                  <input type="hidden" name="action" value="update_role">
                  <input type="hidden" name="admin_id" value="<?php echo (int)($admin['id'] ?? 0); ?>">
                  <select name="role" class="form-select form-select-sm" <?php echo $disable_role ? 'disabled' : ''; ?>>
                    <option value="super_admin" <?php echo $role === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="editor" <?php echo $role === 'editor' ? 'selected' : ''; ?>>Editor</option>
                  </select>
                  <button class="btn btn-primary btn-sm" type="submit" <?php echo $disable_role ? 'disabled' : ''; ?>>Update</button>
                </form>
              </td>
              <td>
                <div class="access-actions">
                  <?php if ($status === 'pending'): ?>
                    <form method="post">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="admin_id" value="<?php echo (int)($admin['id'] ?? 0); ?>">
                      <button class="btn btn-primary btn-sm" type="submit">Approve</button>
                    </form>
                  <?php elseif ($status === 'approved'): ?>
                    <form method="post">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="admin_id" value="<?php echo (int)($admin['id'] ?? 0); ?>">
                      <button class="btn btn-danger btn-sm" type="submit" <?php echo $is_current ? 'disabled' : ''; ?>>Deactivate</button>
                    </form>
                  <?php else: ?>
                    <form method="post">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="admin_id" value="<?php echo (int)($admin['id'] ?? 0); ?>">
                      <button class="btn btn-secondary btn-sm" type="submit">Activate</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6">
              <div class="sa-empty">
                <h4>No admins found</h4>
                <p>Create your first admin account to get started.</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../admin/includes/footer.php'; ?>
