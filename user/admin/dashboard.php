<?php
include 'includes/header.php';
include '../shared/db_connect.php';

$postStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM posts");
$postStmt->execute(); $postCount = $postStmt->get_result()->fetch_assoc()['cnt']; $postStmt->close();

$commentStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM comments");
$commentStmt->execute(); $commentCount = $commentStmt->get_result()->fetch_assoc()['cnt']; $commentStmt->close();

$pendingStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM comments WHERE approved = 0");
$pendingStmt->execute(); $pendingCount = $pendingStmt->get_result()->fetch_assoc()['cnt']; $pendingStmt->close();

$postsRes = $conn->query("SELECT id, title, created_at, visible, COALESCE(review_status, 'approved') AS review_status FROM posts ORDER BY created_at DESC");
?>

<style>
/* ═══════════════════════════════════════════
   Uses design tokens from admin-header.php
═══════════════════════════════════════════ */

/* ── PAGE HEADING ── */
.dash-heading {
  margin-bottom: 2rem;
  animation: dashIn .45s ease both;
}
@keyframes dashIn {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}

.dash-eyebrow {
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

.dash-title {
  font-family: var(--font-serif);
  font-size: clamp(1.8rem, 3vw, 2.6rem);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.08;
  letter-spacing: -.015em;
  margin-bottom: .3rem;
}
.dash-title em { font-style: italic; color: var(--ink-light); }

.dash-sub {
  font-size: .855rem;
  color: var(--ink-light);
  font-weight: 300;
}

/* ── STAT CARDS ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1.5rem 1.5rem 1.25rem;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: dashIn .45s ease both;
}
.stat-card:nth-child(1) { animation-delay: .07s; }
.stat-card:nth-child(2) { animation-delay: .14s; }
.stat-card:nth-child(3) { animation-delay: .21s; }

.stat-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}

/* left accent bar — same as .opp-row::before */
.stat-card::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
}
.stat-card--posts::before     { background: var(--sky); }
.stat-card--comments::before  { background: var(--green); }
.stat-card--pending::before   { background: var(--accent); }

.stat-icon {
  width: 40px; height: 40px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1rem;
  flex-shrink: 0;
}
.stat-card--posts    .stat-icon { background: var(--sky-dim); }
.stat-card--comments .stat-icon { background: var(--green-dim); }
.stat-card--pending  .stat-icon { background: var(--accent-dim); }

.stat-icon svg { width: 17px; height: 17px; }
.stat-card--posts    .stat-icon svg { color: var(--sky); }
.stat-card--comments .stat-icon svg { color: var(--green); }
.stat-card--pending  .stat-icon svg { color: var(--accent); }

.stat-label {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .3rem;
}

.stat-value {
  font-family: var(--font-serif);
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  letter-spacing: -.02em;
}

/* ── POSTS TABLE SECTION ── */
.posts-section {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
  animation: dashIn .45s ease .3s both;
}

/* section header — like card-header but serif */
.posts-section-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.posts-section-title {
  font-family: var(--font-serif);
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
  display: flex;
  align-items: center;
  gap: .65rem;
}

.posts-section-title svg {
  width: 14px; height: 14px;
  color: var(--ink-light);
}

.posts-section-meta {
  font-size: .72rem;
  color: var(--ink-light);
  font-weight: 500;
  border: 1px solid var(--rule);
  border-radius: 99px;
  padding: .22rem .7rem;
  background: var(--bg);
}

/* table overrides inherit from admin-header but we refine here */
.posts-table td.post-title-cell {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -.01em;
  max-width: 360px;
}

.posts-table td.post-title-cell a {
  color: inherit;
  text-decoration: none;
  transition: color var(--transition);
}
.posts-table td.post-title-cell a:hover { color: var(--sky); }

.post-date-cell {
  font-size: .78rem;
  color: var(--ink-light);
  white-space: nowrap;
}

/* status badges — reuse badge tokens from admin-header */
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
.status-pill--visible {
  background: var(--green-dim);
  color: var(--green);
}
.status-pill--hidden {
  background: var(--red-dim);
  color: var(--red);
}
.status-pill--pending {
  background: var(--amber-dim);
  color: var(--amber);
}
.status-pill--rejected {
  background: var(--red-dim);
  color: var(--red);
}
.status-pill::before {
  content: '';
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

/* action buttons — reuse btn sizing from admin-header */
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
.action-link svg { width: 11px; height: 11px; }

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

.action-link--toggle {
  background: var(--bg-warm);
  color: var(--ink-mid);
  border-color: var(--rule);
}
.action-link--toggle:hover { background: var(--bg-warmer); border-color: var(--ink-mid); color: var(--ink); }

/* ── EMPTY STATE ── */
.dash-empty {
  text-align: center;
  padding: 5rem 2rem;
  color: var(--ink-light);
}
.dash-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .35; }
.dash-empty h4 {
  font-family: var(--font-serif);
  font-size: 1.2rem;
  color: var(--ink-mid);
  margin-bottom: .35rem;
}
.dash-empty p { font-size: .855rem; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .action-group { flex-direction: column; }
  .action-link { justify-content: center; }
}
@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .dash-heading { margin-bottom: 1.5rem; }
  .dash-title { font-size: 1.6rem; }
  .posts-section-head {
    flex-direction: column;
    align-items: flex-start;
    gap: .5rem;
  }
  .posts-section-meta { align-self: flex-start; }
  .posts-table td.post-title-cell { max-width: none; }
  .post-date-cell { white-space: normal; }
  .action-group { width: 100%; }
  .action-link { width: 100%; justify-content: center; }
  .posts-table td, .posts-table th { padding: 8px 10px; }
}
</style>

<!-- ══ PAGE HEADING ══ -->
<div class="dash-heading">
  <div class="dash-eyebrow">Overview</div>
  <h1 class="dash-title">Dashboard<em>.</em></h1>
  <p class="dash-sub">Welcome back — here's what's happening with your blog today.</p>
</div>

<!-- ══ STATS ══ -->
<div class="stats-grid">

  <div class="stat-card stat-card--posts">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zm-9-4h6v2H9v-2zm0-4h6v2H9v-2zm0-4h4v2H9V8z"/>
      </svg>
    </div>
    <div class="stat-label">Total Posts</div>
    <div class="stat-value"><?php echo $postCount; ?></div>
  </div>

  <div class="stat-card stat-card--comments">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zm-9-5h2v2h-2zm0-6h2v4h-2z"/>
      </svg>
    </div>
    <div class="stat-label">Total Comments</div>
    <div class="stat-value"><?php echo $commentCount; ?></div>
  </div>

  <div class="stat-card stat-card--pending">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
      </svg>
    </div>
    <div class="stat-label">Pending Comments</div>
    <div class="stat-value"><?php echo $pendingCount; ?></div>
  </div>

</div>

<!-- ══ POSTS TABLE ══ -->
<div class="posts-section">
  <div class="posts-section-head">
    <div class="posts-section-title">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
      </svg>
      All Posts
    </div>
    <span class="posts-section-meta"><?php echo $postsRes ? $postsRes->num_rows : 0; ?> entries</span>
  </div>

  <div class="table-responsive">
    <table class="table posts-table mb-0">
      <thead>
        <tr>
          <th>Post Title</th>
          <th>Created</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($postsRes && $postsRes->num_rows > 0): ?>
          <?php while ($p = $postsRes->fetch_assoc()): ?>
          <?php
            $reviewStatus = $p['review_status'] ?? 'approved';
            if ($reviewStatus === 'pending') {
                $statusClass = 'pending';
                $statusLabel = 'Pending Review';
            } elseif ($reviewStatus === 'rejected') {
                $statusClass = 'rejected';
                $statusLabel = 'Rejected';
            } else {
                $statusClass = $p['visible'] ? 'visible' : 'hidden';
                $statusLabel = $p['visible'] ? 'Approved · Live' : 'Approved · Hidden';
            }
          ?>
          <tr>
            <td class="post-title-cell">
              <a href="edit_post.php?id=<?php echo $p['id']; ?>">
                <?php echo htmlspecialchars($p['title']); ?>
              </a>
            </td>
            <td class="post-date-cell">
              <?php echo date('M d, Y', strtotime($p['created_at'])); ?>
            </td>
            <td>
              <span class="status-pill status-pill--<?php echo $statusClass; ?>">
                <?php echo $statusLabel; ?>
              </span>
            </td>
            <td>
              <div class="action-group">
                <a class="action-link action-link--edit"
                   href="edit_post.php?id=<?php echo $p['id']; ?>">
                  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                  Edit
                </a>
                <a class="action-link action-link--delete"
                   href="delete_post.php?id=<?php echo $p['id']; ?>"
                   onclick="return confirm('Delete this post?')">
                  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                  Delete
                </a>
                <?php if ($reviewStatus === 'pending'): ?>
                <a class="action-link action-link--toggle" href="../super_admin/review.php?status=pending">
                  Review Queue
                </a>
                <?php elseif ($reviewStatus === 'approved'): ?>
                <a class="action-link action-link--toggle"
                   href="toggle_visibility.php?id=<?php echo $p['id']; ?>">
                  <svg viewBox="0 0 24 24" fill="currentColor"><?php if ($p['visible']): ?><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/><?php else: ?><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/><?php endif; ?></svg>
                  <?php echo $p['visible'] ? 'Hide' : 'Show'; ?>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="4">
              <div class="dash-empty">
                <span class="dash-empty-icon">📭</span>
                <h4>No posts yet</h4>
                <p>Start creating content for your blog.</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

