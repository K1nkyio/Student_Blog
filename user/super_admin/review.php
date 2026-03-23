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
include '../shared/functions.php';

$topbar_title_override = 'Post Review';
$topbar_subtitle_override = 'Approve, reject, or remove submitted content';

$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'pending';
}
$search = trim($_GET['q'] ?? '');

if (!isset($_SESSION['review_flash'])) {
    $_SESSION['review_flash'] = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $review_notes = trim($_POST['review_notes'] ?? '');

    if ($post_id > 0 && in_array($action, ['approve', 'reject', 'delete'], true)) {
        $lookup = $conn->prepare("SELECT id, title, author_id, COALESCE(review_status, 'approved') AS review_status FROM posts WHERE id = ? LIMIT 1");
        $lookup->bind_param('i', $post_id);
        $lookup->execute();
        $post = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if ($post) {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE posts SET review_status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, published_at = COALESCE(published_at, NOW()) WHERE id = ?");
                $stmt->bind_param('isi', $current_admin_id, $review_notes, $post_id);
                $stmt->execute();
                $stmt->close();

                if (($post['review_status'] ?? 'approved') !== 'approved') {
                    notify_users_about_post($post_id, $post['title'], $post['author_id'], false);
                }
                $_SESSION['review_flash'] = ['type' => 'success', 'message' => 'Post approved successfully.'];
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE posts SET review_status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?");
                $stmt->bind_param('isi', $current_admin_id, $review_notes, $post_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['review_flash'] = ['type' => 'warning', 'message' => 'Post rejected and returned to the review queue history.'];
            } else {
                $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->bind_param('i', $post_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['review_flash'] = ['type' => 'danger', 'message' => 'Post deleted permanently.'];
            }
        }
    }

    $redirect = 'review.php?status=' . urlencode($status);
    if ($search !== '') {
        $redirect .= '&q=' . urlencode($search);
    }
    header('Location: ' . $redirect);
    exit();
}

$flash = $_SESSION['review_flash'];
$_SESSION['review_flash'] = null;

include 'includes/header.php';

$counts = [
    'all' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];
$count_res = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(COALESCE(review_status, 'approved') = 'pending') AS pending_total,
    SUM(COALESCE(review_status, 'approved') = 'approved') AS approved_total,
    SUM(COALESCE(review_status, 'approved') = 'rejected') AS rejected_total
    FROM posts");
if ($count_res) {
    $row = $count_res->fetch_assoc();
    $counts['all'] = (int)($row['total'] ?? 0);
    $counts['pending'] = (int)($row['pending_total'] ?? 0);
    $counts['approved'] = (int)($row['approved_total'] ?? 0);
    $counts['rejected'] = (int)($row['rejected_total'] ?? 0);
}

$where = [];
$types = '';
$params = [];

if ($status !== 'all') {
    $where[] = "COALESCE(p.review_status, 'approved') = ?";
    $types .= 's';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = "(p.title LIKE ? OR COALESCE(p.excerpt, '') LIKE ? OR COALESCE(p.content, '') LIKE ?)";
    $types .= 'sss';
    $search_like = '%' . $search . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
}

$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
$sql = "SELECT
    p.id,
    p.title,
    p.excerpt,
    p.visible,
    p.created_at,
    p.published_at,
    COALESCE(p.review_status, 'approved') AS review_status,
    p.review_notes,
    p.reviewed_at,
    u.username AS author_name,
    c.name AS category_name,
    a.username AS reviewer_name
    FROM posts p
    LEFT JOIN users u ON p.author_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN admins a ON p.reviewed_by = a.id
    $where_sql
    ORDER BY
      CASE COALESCE(p.review_status, 'approved')
        WHEN 'pending' THEN 0
        WHEN 'rejected' THEN 1
        ELSE 2
      END,
      p.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$posts = [];
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}
$stmt->close();
?>

<style>
.rv-page { animation: rvIn .35s ease both; }
@keyframes rvIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

.rv-hero,
.rv-toolbar,
.rv-card,
.rv-empty {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
}

.rv-hero {
  padding: 1.8rem;
  margin-bottom: 1.25rem;
  position: relative;
  overflow: hidden;
}
.rv-hero::after {
  content: 'Review';
  position: absolute;
  right: 1rem;
  bottom: -1.25rem;
  font-family: var(--font-serif);
  font-size: clamp(3rem, 10vw, 5.5rem);
  font-style: italic;
  color: rgba(24,22,15,.05);
  pointer-events: none;
}
.rv-title {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 3vw, 2.5rem);
  margin-bottom: .4rem;
}
.rv-sub {
  color: var(--ink-light);
  max-width: 760px;
}

.rv-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-top: 1.4rem;
}
.rv-stat {
  background: var(--bg);
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1rem 1.1rem;
}
.rv-stat-label {
  font-size: .65rem;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--ink-light);
  margin-bottom: .35rem;
}
.rv-stat-value {
  font-family: var(--font-serif);
  font-size: 1.8rem;
  line-height: 1;
}

.rv-flash {
  padding: .9rem 1rem;
  border-radius: 3px;
  margin-bottom: 1rem;
  font-size: .84rem;
  border: 1px solid transparent;
}
.rv-flash.success { background: var(--green-dim); color: var(--green); border-color: rgba(26,122,74,.2); }
.rv-flash.warning { background: var(--amber-dim); color: var(--amber); border-color: rgba(184,134,11,.2); }
.rv-flash.danger { background: var(--red-dim); color: var(--red); border-color: rgba(176,48,48,.2); }

.rv-toolbar {
  padding: 1rem 1.1rem;
  margin-bottom: 1rem;
}
.rv-toolbar form {
  display: flex;
  gap: .85rem;
  flex-wrap: wrap;
  align-items: center;
}
.rv-tabs {
  display: flex;
  gap: .55rem;
  flex-wrap: wrap;
}
.rv-tab {
  text-decoration: none;
  padding: .55rem .85rem;
  border-radius: 999px;
  border: 1px solid var(--rule);
  color: var(--ink-mid);
  background: var(--bg);
  font-size: .78rem;
  font-weight: 600;
}
.rv-tab.active {
  background: var(--ink);
  border-color: var(--ink);
  color: #fff;
}
.rv-search {
  flex: 1 1 260px;
  min-width: 220px;
}
.rv-search input {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .7rem .85rem;
  font-size: .86rem;
}
.rv-search input:focus {
  outline: none;
  border-color: var(--ink);
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
}
.rv-toolbar button {
  border: none;
  background: var(--ink);
  color: #fff;
  border-radius: 3px;
  padding: .72rem 1rem;
  font-size: .82rem;
  font-weight: 600;
}

.rv-list {
  display: grid;
  gap: 1rem;
}
.rv-card {
  padding: 1.25rem;
}
.rv-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: .85rem;
}
.rv-card-title {
  font-family: var(--font-serif);
  font-size: 1.3rem;
  margin-bottom: .35rem;
}
.rv-card-meta {
  display: flex;
  gap: .55rem;
  flex-wrap: wrap;
  color: var(--ink-light);
  font-size: .78rem;
}
.rv-chip,
.rv-status {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .28rem .65rem;
  border-radius: 999px;
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
}
.rv-chip { background: var(--bg); color: var(--ink-mid); border: 1px solid var(--rule-light); }
.rv-status.pending { background: var(--amber-dim); color: var(--amber); }
.rv-status.approved { background: var(--green-dim); color: var(--green); }
.rv-status.rejected { background: var(--red-dim); color: var(--red); }

.rv-excerpt {
  color: var(--ink-mid);
  line-height: 1.7;
  margin-bottom: 1rem;
}
.rv-notes {
  margin-bottom: 1rem;
  padding: .85rem 1rem;
  background: var(--bg);
  border: 1px solid var(--rule-light);
  border-radius: 3px;
}
.rv-notes strong {
  display: block;
  margin-bottom: .3rem;
  font-size: .78rem;
}

.rv-form textarea {
  width: 100%;
  min-height: 88px;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .8rem .9rem;
  font-size: .86rem;
  resize: vertical;
}
.rv-form textarea:focus {
  outline: none;
  border-color: var(--ink);
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
}
.rv-actions {
  display: flex;
  gap: .7rem;
  flex-wrap: wrap;
  margin-top: .8rem;
}
.rv-btn {
  border: none;
  border-radius: 3px;
  padding: .72rem 1rem;
  font-size: .82rem;
  font-weight: 700;
  cursor: pointer;
}
.rv-btn.approve { background: var(--green); color: #fff; }
.rv-btn.reject { background: var(--amber); color: #fff; }
.rv-btn.delete { background: var(--red); color: #fff; }

.rv-empty {
  padding: 3.5rem 1.5rem;
  text-align: center;
  color: var(--ink-light);
}
.rv-empty h3 {
  font-family: var(--font-serif);
  color: var(--ink);
  margin-bottom: .4rem;
}

@media (max-width: 900px) {
  .rv-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .rv-stats { grid-template-columns: 1fr; }
  .rv-card-head { flex-direction: column; }
  .rv-actions > * { width: 100%; }
}
</style>

<div class="rv-page">
  <section class="rv-hero">
    <div class="rv-title">Content Review Queue</div>
    <div class="rv-sub">Posts created from the admin content form appear here first. Approve them to publish, reject them with notes, or delete them entirely.</div>

    <div class="rv-stats">
      <div class="rv-stat">
        <div class="rv-stat-label">All Posts</div>
        <div class="rv-stat-value"><?php echo number_format($counts['all']); ?></div>
      </div>
      <div class="rv-stat">
        <div class="rv-stat-label">Pending</div>
        <div class="rv-stat-value"><?php echo number_format($counts['pending']); ?></div>
      </div>
      <div class="rv-stat">
        <div class="rv-stat-label">Approved</div>
        <div class="rv-stat-value"><?php echo number_format($counts['approved']); ?></div>
      </div>
      <div class="rv-stat">
        <div class="rv-stat-label">Rejected</div>
        <div class="rv-stat-value"><?php echo number_format($counts['rejected']); ?></div>
      </div>
    </div>
  </section>

  <?php if (!empty($flash['message'])): ?>
    <div class="rv-flash <?php echo htmlspecialchars($flash['type'] ?? 'success'); ?>">
      <?php echo htmlspecialchars($flash['message']); ?>
    </div>
  <?php endif; ?>

  <section class="rv-toolbar">
    <form method="get">
      <div class="rv-tabs">
        <?php foreach ($allowed_statuses as $tab): ?>
          <a class="rv-tab <?php echo $status === $tab ? 'active' : ''; ?>"
             href="review.php?status=<?php echo urlencode($tab); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
            <?php echo ucfirst($tab); ?> (<?php echo number_format($counts[$tab] ?? 0); ?>)
          </a>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
      <div class="rv-search">
        <input type="search" name="q" placeholder="Search title, excerpt, or content" value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <button type="submit">Filter</button>
    </form>
  </section>

  <?php if (!empty($posts)): ?>
    <div class="rv-list">
      <?php foreach ($posts as $post): ?>
        <article class="rv-card">
          <div class="rv-card-head">
            <div>
              <div class="rv-card-title"><?php echo htmlspecialchars($post['title']); ?></div>
              <div class="rv-card-meta">
                <span class="rv-chip"><?php echo htmlspecialchars($post['category_name'] ?: 'Uncategorized'); ?></span>
                <span class="rv-chip">Author: <?php echo htmlspecialchars($post['author_name'] ?: 'Unknown'); ?></span>
                <span class="rv-chip">Created: <?php echo !empty($post['created_at']) ? date('M d, Y g:i A', strtotime($post['created_at'])) : 'N/A'; ?></span>
                <span class="rv-chip"><?php echo !empty($post['visible']) ? 'Will be public after approval' : 'Will stay hidden after approval'; ?></span>
                <?php if (!empty($post['reviewer_name'])): ?>
                  <span class="rv-chip">Reviewed by: <?php echo htmlspecialchars($post['reviewer_name']); ?></span>
                <?php endif; ?>
              </div>
            </div>
            <span class="rv-status <?php echo htmlspecialchars($post['review_status']); ?>">
              <?php echo htmlspecialchars($post['review_status']); ?>
            </span>
          </div>

          <?php if (!empty($post['excerpt'])): ?>
            <div class="rv-excerpt"><?php echo nl2br(htmlspecialchars($post['excerpt'])); ?></div>
          <?php endif; ?>

          <?php if (!empty($post['review_notes']) || !empty($post['reviewed_at']) || !empty($post['published_at'])): ?>
            <div class="rv-notes">
              <?php if (!empty($post['review_notes'])): ?>
                <strong>Review Notes</strong>
                <div><?php echo nl2br(htmlspecialchars($post['review_notes'])); ?></div>
              <?php endif; ?>
              <?php if (!empty($post['reviewed_at'])): ?>
                <div style="margin-top:.45rem;font-size:.8rem;color:var(--ink-light);">Reviewed <?php echo date('M d, Y g:i A', strtotime($post['reviewed_at'])); ?></div>
              <?php endif; ?>
              <?php if (!empty($post['published_at']) && $post['review_status'] === 'approved'): ?>
                <div style="margin-top:.25rem;font-size:.8rem;color:var(--ink-light);">Published <?php echo date('M d, Y g:i A', strtotime($post['published_at'])); ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <form method="post" class="rv-form">
            <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
            <textarea name="review_notes" placeholder="Add moderation notes for the author or for internal context"><?php echo htmlspecialchars($post['review_notes'] ?? ''); ?></textarea>
            <div class="rv-actions">
              <button class="rv-btn approve" type="submit" name="action" value="approve">Approve</button>
              <button class="rv-btn reject" type="submit" name="action" value="reject">Reject</button>
              <button class="rv-btn delete" type="submit" name="action" value="delete" onclick="return confirm('Delete this post permanently?');">Delete</button>
            </div>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="rv-empty">
      <h3>No posts found</h3>
      <p>The selected review state has no matching posts right now.</p>
    </div>
  <?php endif; ?>
</div>

<?php include '../admin/includes/footer.php'; ?>
