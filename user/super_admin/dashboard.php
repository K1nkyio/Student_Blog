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

$topbar_title_override = 'Super Admin Dashboard';
$topbar_subtitle_override = 'Approvals, oversight, and platform health';

include 'includes/header.php';
include '../shared/db_connect.php';

$pending_count = 0;
$approved_count = 0;
$deactivated_count = 0;
$recent_login_count = 0;

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM admins WHERE status = 'pending'");
$stmt->execute(); $pending_count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM admins WHERE status = 'approved'");
$stmt->execute(); $approved_count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM admins WHERE status = 'deactivated'");
$stmt->execute(); $deactivated_count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM admin_audit_log WHERE action = 'login_success' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute(); $recent_login_count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0); $stmt->close();

$recent_activity = [];
$activity_stmt = $conn->prepare("SELECT l.action, l.details, l.created_at, a.username AS admin_name, actor.username AS actor_name
                                 FROM admin_audit_log l
                                 LEFT JOIN admins a ON l.admin_id = a.id
                                 LEFT JOIN admins actor ON l.actor_admin_id = actor.id
                                 ORDER BY l.created_at DESC
                                 LIMIT 8");
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();
while ($row = $activity_result->fetch_assoc()) {
    $recent_activity[] = $row;
}
$activity_stmt->close();

$active_tab = 'dashboard';
?>

<style>
.sa-dashboard {
  animation: dashIn .45s ease both;
}
@keyframes dashIn {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}
.sa-hero {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 2rem;
  margin-bottom: 1.5rem;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
}
.sa-hero::before {
  content: 'Super';
  position: absolute;
  right: 1.2rem;
  bottom: -1.6rem;
  font-family: var(--font-serif);
  font-size: clamp(3rem, 12vw, 6rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(24,22,15,.06);
  line-height: 1;
  pointer-events: none;
}
.sa-hero h2 {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 3vw, 2.6rem);
  margin-bottom: .4rem;
}
.sa-hero p {
  color: var(--ink-light);
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
.sa-tab-title {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 700;
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

.sa-kpis {
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
}
.sa-kpi::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
}
.sa-kpi--pending::before { background: var(--amber); }
.sa-kpi--approved::before { background: var(--green); }
.sa-kpi--deactivated::before { background: var(--red); }
.sa-kpi--logins::before { background: var(--sky); }
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

.sa-panel {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.sa-panel-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
  padding: 1rem 1.5rem;
  font-family: var(--font-serif);
  font-size: 1.15rem;
  font-weight: 700;
}
.sa-panel-body {
  padding: 1rem 1.5rem 1.5rem;
}
.activity-item {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: .75rem 0;
  border-bottom: 1px solid var(--rule-light);
  font-size: .85rem;
}
.activity-item:last-child { border-bottom: none; }
.activity-meta {
  color: var(--ink-light);
  font-size: .75rem;
}

@media (max-width: 1024px) {
  .sa-tabs { grid-template-columns: repeat(2, 1fr); }
  .sa-kpis { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .sa-tabs { grid-template-columns: 1fr; }
  .sa-kpis { grid-template-columns: 1fr; }
}
</style>

<div class="sa-dashboard">
  <section class="sa-hero">
    <h2>Super Admin Overview</h2>
    <p>Monitor approval flow, admin access, and recent login activity across the platform.</p>
  </section>

  <section class="sa-kpis">
    <div class="sa-kpi sa-kpi--pending">
      <div class="sa-kpi-label">Pending Approvals</div>
      <div class="sa-kpi-value"><?php echo $pending_count; ?></div>
    </div>
    <div class="sa-kpi sa-kpi--approved">
      <div class="sa-kpi-label">Approved Admins</div>
      <div class="sa-kpi-value"><?php echo $approved_count; ?></div>
    </div>
    <div class="sa-kpi sa-kpi--deactivated">
      <div class="sa-kpi-label">Deactivated Admins</div>
      <div class="sa-kpi-value"><?php echo $deactivated_count; ?></div>
    </div>
    <div class="sa-kpi sa-kpi--logins">
      <div class="sa-kpi-label">Logins (7 days)</div>
      <div class="sa-kpi-value"><?php echo $recent_login_count; ?></div>
    </div>
  </section>

  <section class="sa-panel">
    <div class="sa-panel-head">Recent Admin Activity</div>
    <div class="sa-panel-body">
      <?php if (!empty($recent_activity)): ?>
        <?php foreach ($recent_activity as $row): ?>
          <div class="activity-item">
            <div>
              <strong><?php echo htmlspecialchars($row['action']); ?></strong>
              <?php if (!empty($row['admin_name'])): ?>
                <span> — <?php echo htmlspecialchars($row['admin_name']); ?></span>
              <?php endif; ?>
              <?php if (!empty($row['actor_name'])): ?>
                <span class="activity-meta"> (by <?php echo htmlspecialchars($row['actor_name']); ?>)</span>
              <?php endif; ?>
              <?php if (!empty($row['details'])): ?>
                <div class="activity-meta"><?php echo htmlspecialchars($row['details']); ?></div>
              <?php endif; ?>
            </div>
            <div class="activity-meta">
              <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($row['created_at']))); ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="activity-meta">No recent activity logged yet.</p>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include '../admin/includes/footer.php'; ?>
