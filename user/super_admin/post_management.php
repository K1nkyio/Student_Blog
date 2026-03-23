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

$topbar_title_override = 'Post Management';
$topbar_subtitle_override = 'Edit and delete posts across the platform';

include 'includes/header.php';
include '../shared/db_connect.php';

$posts = [];
$post_res = $conn->query("SELECT id, title, created_at, visible, COALESCE(review_status, 'approved') AS review_status FROM posts ORDER BY created_at DESC");
if ($post_res) {
    while ($row = $post_res->fetch_assoc()) {
        $posts[] = $row;
    }
}

$active_tab = 'posts';
?>

<style>
.pm-heading {
  margin-bottom: 1.5rem;
}
.pm-title {
  font-family: var(--font-serif);
  font-size: clamp(1.6rem, 2.6vw, 2.2rem);
  font-weight: 700;
  margin-bottom: .35rem;
  color: var(--ink);
}
.pm-sub {
  font-size: .85rem;
  color: var(--ink-light);
}

.pm-section {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.pm-section-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
  padding: 1rem 1.5rem;
  font-family: var(--font-serif);
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--ink);
}

.pm-table td.title-cell {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 600;
  color: var(--ink);
}
.pm-table td.title-cell a {
  color: inherit;
  text-decoration: none;
}
.pm-table td.title-cell a:hover { color: var(--sky); }

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .22rem .65rem;
  border-radius: 2px;
}
.status-pill--visible { background: var(--green-dim); color: var(--green); }
.status-pill--hidden  { background: var(--red-dim); color: var(--red); }
.status-pill--pending { background: var(--amber-dim); color: var(--amber); }
.status-pill--rejected { background: var(--red-dim); color: var(--red); }
.status-pill::before {
  content: '';
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.action-group {
  display: flex;
  gap: .35rem;
  flex-wrap: wrap;
}
.action-link {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: .72rem;
  font-weight: 600;
  padding: .32rem .75rem;
  border-radius: 3px;
  text-decoration: none;
  transition: all var(--transition);
  border: 1.5px solid transparent;
  white-space: nowrap;
}
.action-link--edit {
  background: var(--sky-dim);
  color: var(--sky);
  border-color: rgba(26,95,200,.15);
}
.action-link--edit:hover { background: #c5d8f5; color: var(--sky); }
.action-link--delete {
  background: var(--red-dim);
  color: var(--red);
  border-color: rgba(176,48,48,.15);
}
.action-link--delete:hover { background: #f0cccc; color: var(--red); }
.action-link--review {
  background: var(--amber-dim);
  color: var(--amber);
  border-color: rgba(184,134,11,.18);
}
.action-link--review:hover { background: #f4e4b2; color: var(--amber); }

.pm-empty {
  text-align: center;
  padding: 4rem 2rem;
  color: var(--ink-light);
}
.pm-empty h4 {
  font-family: var(--font-serif);
  font-size: 1.2rem;
  color: var(--ink-mid);
  margin-bottom: .35rem;
}

@media (max-width: 640px) {
  .action-group { flex-direction: column; width: 100%; }
  .action-link { justify-content: center; }
}
</style>

<div class="pm-heading">
  <div class="pm-title">Post Management</div>
  <div class="pm-sub">Manage approved, pending, and rejected posts across the platform.</div>
</div>

<div class="pm-section">
  <div class="pm-section-head">All Posts</div>
  <div class="table-responsive">
    <table class="table pm-table mb-0">
      <thead>
        <tr>
          <th>Title</th>
          <th>Created</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($posts)): ?>
          <?php foreach ($posts as $post): ?>
            <?php
              $reviewStatus = $post['review_status'] ?? 'approved';
              if ($reviewStatus === 'pending') {
                  $statusClass = 'pending';
                  $statusLabel = 'Pending Review';
              } elseif ($reviewStatus === 'rejected') {
                  $statusClass = 'rejected';
                  $statusLabel = 'Rejected';
              } else {
                  $statusClass = $post['visible'] ? 'visible' : 'hidden';
                  $statusLabel = $post['visible'] ? 'Approved · Live' : 'Approved · Hidden';
              }
            ?>
            <tr>
              <td class="title-cell">
                <a href="edit_post.php?id=<?php echo (int)$post['id']; ?>">
                  <?php echo htmlspecialchars($post['title']); ?>
                </a>
              </td>
              <td><?php echo !empty($post['created_at']) ? date('M d, Y', strtotime($post['created_at'])) : '&mdash;'; ?></td>
              <td>
                <span class="status-pill status-pill--<?php echo $statusClass; ?>">
                  <?php echo $statusLabel; ?>
                </span>
              </td>
              <td>
                <div class="action-group">
                  <?php if ($reviewStatus !== 'approved'): ?>
                  <a class="action-link action-link--review" href="review.php?status=<?php echo htmlspecialchars($reviewStatus); ?>">
                    Review
                  </a>
                  <?php endif; ?>
                  <a class="action-link action-link--edit" href="edit_post.php?id=<?php echo (int)$post['id']; ?>">
                    Edit
                  </a>
                  <a class="action-link action-link--delete" href="delete_post.php?id=<?php echo (int)$post['id']; ?>"
                     onclick="return confirm('Delete this post?');">
                    Delete
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4">
              <div class="pm-empty">
                <h4>No posts found</h4>
                <p>Create your first post to get started.</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../admin/includes/footer.php'; ?>

