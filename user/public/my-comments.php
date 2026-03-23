<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

if (!is_logged_in()) {
    set_flash('error', 'You must be logged in to view your comments.');
    redirect('login.php');
}

$user_id = get_user_id();

$user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user || !$user['email']) {
    set_flash('error', 'Unable to retrieve user information.');
    redirect('profile.php');
}

$user_email = $user['email'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_comment') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        set_flash('error', 'Invalid security token. Please try again.');
        redirect('my-comments.php?page=' . $page);
    }

    $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
    if ($comment_id <= 0) {
        set_flash('error', 'Invalid comment selected.');
        redirect('my-comments.php?page=' . $page);
    }

    $delete_stmt = $conn->prepare("DELETE FROM comments WHERE id = ? AND email = ?");
    if (!$delete_stmt) {
        set_flash('error', 'Unable to delete comment. Please try again later.');
        redirect('my-comments.php?page=' . $page);
    }

    $delete_stmt->bind_param('is', $comment_id, $user_email);
    $delete_stmt->execute();

    if ($delete_stmt->affected_rows > 0) {
        set_flash('success', 'Comment deleted successfully.');
    } else {
        set_flash('error', 'Comment not found or already removed.');
    }

    $delete_stmt->close();
    redirect('my-comments.php?page=' . $page);
}

// Pagination
$per_page = 10;
$offset   = ($page - 1) * $per_page;

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM comments WHERE email = ?");
$count_stmt->bind_param('s', $user_email);
$count_stmt->execute();
$total_comments = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_comments / $per_page);

$stmt = $conn->prepare("SELECT c.id, c.comment, c.created_at, c.post_id,
                               p.title, p.slug, p.image
                       FROM comments c
                       LEFT JOIN posts p ON c.post_id = p.id
                       WHERE c.email = ? AND COALESCE(p.visible, 1) = 1 AND COALESCE(p.review_status, 'approved') = 'approved'
                       ORDER BY c.created_at DESC
                       LIMIT ? OFFSET ?");
$stmt->bind_param('sii', $user_email, $per_page, $offset);
$stmt->execute();
$comments = $stmt->get_result();
$stmt->close();

$page_title = 'My Comments - ' . SITE_NAME;
include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════ */
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
  --green:        #1a7a4a;
  --green-dim:    #d2edd9;
  --amber:        #b8860b;
  --amber-dim:    #f5edcc;
  --red:          #b03030;
  --red-dim:      #f8e0e0;
  --purple:       #6b3fa0;
  --purple-dim:   #ede6f8;

  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-sans:  'Outfit', sans-serif;

  --max-w:    1000px;
  --gutter:   1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.comments-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO
═══════════════════════════════════════════ */
.comments-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}
.comments-hero::before {
  content: 'Comments.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(4rem, 13vw, 10rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.035);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}
.comments-hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}
.hero-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  position: relative;
  z-index: 1;
  animation: slideUp .6s ease both;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.4);
  padding: .28rem .8rem;
  border-radius: 2px;
  margin-bottom: 1rem;
}
.hero-headline {
  font-family: var(--font-serif);
  font-size: clamp(2.2rem, 5.5vw, 3.8rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -.015em;
  color: #fff;
  margin-bottom: .8rem;
}
.hero-headline em { font-style: italic; color: rgba(255,255,255,.45); }
.hero-sub {
  font-size: .95rem;
  color: rgba(255,255,255,.45);
  font-weight: 300;
  max-width: 480px;
  line-height: 1.7;
  margin-bottom: 2rem;
}
.hero-stats {
  display: flex;
  gap: 2.5rem;
  flex-wrap: wrap;
}
.hero-stat-num {
  font-family: var(--font-serif);
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
  display: block;
}
.hero-stat-label {
  font-size: .65rem;
  font-weight: 500;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.35);
  display: block;
  margin-top: .25rem;
}

/* ═══════════════════════════════════════════
   TOOLBAR
═══════════════════════════════════════════ */
.comments-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.comments-toolbar-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .75rem var(--gutter);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.toolbar-meta {
  font-size: .8rem;
  color: var(--ink-light);
}
.toolbar-meta strong { color: var(--ink-mid); font-weight: 600; }

/* ═══════════════════════════════════════════
   BODY
═══════════════════════════════════════════ */
.comments-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
}

/* flash */
.flash-msg {
  padding: .85rem 1.25rem;
  border-radius: 4px;
  font-size: .875rem;
  font-weight: 500;
  border-left: 3px solid;
  margin-bottom: 1.5rem;
  font-family: var(--font-sans);
}
.flash-msg.success { background: var(--green-dim); color: var(--green); border-color: var(--green); }
.flash-msg.error   { background: var(--red-dim);   color: var(--red);   border-color: var(--red); }

/* ═══════════════════════════════════════════
   COMMENT ROWS
═══════════════════════════════════════════ */
.comments-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.comment-row {
  display: grid;
  grid-template-columns: 44px 1fr auto;
  gap: 1.25rem;
  align-items: start;
  padding: 1.5rem 1.5rem;
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: rowIn .35s ease both;
  position: relative;
}
.comment-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}
@keyframes rowIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* left accent */
.comment-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: var(--sky);
}

/* icon bubble */
.comment-icon {
  width: 40px; height: 40px;
  border-radius: 8px;
  background: var(--sky-dim);
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
  margin-top: .1rem;
}

/* row body */
.row-body { min-width: 0; }

.row-post-title {
  font-family: var(--font-serif);
  font-size: clamp(.95rem, 2vw, 1.1rem);
  font-weight: 600;
  color: var(--ink);
  line-height: 1.25;
  margin-bottom: .45rem;
  text-decoration: none;
  display: block;
  transition: color var(--transition);
}
.row-post-title:hover { color: var(--sky); }

/* meta */
.row-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem 1.25rem;
  font-size: .8rem;
  color: var(--ink-light);
  margin-bottom: .75rem;
  align-items: center;
}
.row-meta-item {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}
.row-meta-item svg { width: 12px; height: 12px; opacity: .7; flex-shrink: 0; }

/* comment text bubble */
.comment-body-text {
  font-size: .875rem;
  color: var(--ink-mid);
  line-height: 1.65;
  background: var(--bg-warm);
  border: 1px solid var(--rule-light);
  border-radius: 4px;
  padding: .85rem 1rem;
  hyphens: auto;
}

/* row actions */
.row-actions {
  display: flex;
  flex-direction: column;
  gap: .4rem;
  align-items: flex-end;
  flex-shrink: 0;
  padding-top: .1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-family: var(--font-sans);
  font-size: .8rem;
  font-weight: 600;
  text-decoration: none;
  padding: .55rem 1.1rem;
  border-radius: 3px;
  border: none;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
  line-height: 1;
}
.btn:active { transform: scale(.97); }
.btn svg { width: 11px; height: 11px; }

.btn-primary {
  background: var(--ink);
  color: #fff;
}
.btn-primary:hover {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}
.btn-danger {
  background: transparent;
  color: var(--red);
  border: 1.5px solid rgba(176,48,48,.3);
}
.btn-danger:hover {
  background: var(--red-dim);
  border-color: var(--red);
}
.btn-ghost {
  background: transparent;
  color: var(--ink-mid);
  border: 1.5px solid var(--rule);
}
.btn-ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); }

/* ═══════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════ */
.comments-empty {
  text-align: center;
  padding: 5rem 1rem;
  color: var(--ink-light);
  border-top: 1px solid var(--rule-light);
}
.comments-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .4; }
.comments-empty h3 { font-family: var(--font-serif); font-size: 1.3rem; color: var(--ink-mid); margin-bottom: .4rem; }
.comments-empty p  { font-size: .875rem; margin-bottom: 1.5rem; }

/* ═══════════════════════════════════════════
   PAGINATION
═══════════════════════════════════════════ */
.comments-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .6rem;
  margin-top: 2.5rem;
  border-top: 1px solid var(--rule-light);
  padding-top: 2rem;
  flex-wrap: wrap;
}
.page-btn {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .55rem 1.1rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: var(--bg);
  font-size: .82rem;
  font-weight: 500;
  font-family: var(--font-sans);
  color: var(--ink-mid);
  cursor: pointer;
  text-decoration: none;
  transition: all var(--transition);
}
.page-btn:hover:not([aria-disabled="true"]) { border-color: var(--ink); background: var(--bg-warm); }
.page-btn[aria-disabled="true"] { opacity: .35; pointer-events: none; }
.page-btn.active {
  background: var(--ink);
  color: #fff;
  border-color: var(--ink);
}

/* responsive */
@media (max-width: 640px) {
  .comment-row {
    grid-template-columns: 36px 1fr;
    grid-template-rows: auto auto;
    padding: 1.1rem;
  }
  .row-actions {
    grid-column: 1 / -1;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
  }
}
</style>

<div class="comments-page">

  <!-- ══════════ HERO ══════════ -->
  <div class="comments-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow">Your activity</span>
      <h1 class="hero-headline">My<br><em>Comments</em></h1>
      <p class="hero-sub">Everything you've said across the site, in one place. Review, visit, or clean up your contributions.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $total_comments ?></span>
          <span class="hero-stat-label">Total</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $total_pages ?></span>
          <span class="hero-stat-label">Pages</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $per_page ?></span>
          <span class="hero-stat-label">Per page</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ TOOLBAR ══════════ -->
  <div class="comments-toolbar">
    <div class="comments-toolbar-inner">
      <span class="toolbar-meta">
        <strong><?= $total_comments ?></strong> comment<?= $total_comments !== 1 ? 's' : '' ?>
        <?php if ($total_pages > 1): ?> · Page <?= $page ?> of <?= $total_pages ?><?php endif; ?>
      </span>
      <a href="profile.php" class="btn btn-ghost">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Back to Profile
      </a>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="comments-body">

    <?php display_flash(); ?>

    <?php if ($comments && $comments->num_rows > 0): ?>

      <div class="comments-list">
        <?php $i = 0; while ($comment = $comments->fetch_assoc()): $i++; ?>
        <?php
          $post_url = !empty($comment['slug'])
            ? 'post.php?slug=' . urlencode($comment['slug'])
            : 'post.php?id=' . $comment['post_id'];
        ?>
        <div class="comment-row" style="animation-delay:<?= $i * 35 ?>ms">

          <!-- icon -->
          <div class="comment-icon">💬</div>

          <!-- body -->
          <div class="row-body">
            <a href="<?= htmlspecialchars($post_url) ?>" class="row-post-title">
              <?= htmlspecialchars($comment['title'] ?: 'Post #' . $comment['post_id']) ?>
            </a>

            <div class="row-meta">
              <span class="row-meta-item">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?= date('M j, Y · g:i A', strtotime($comment['created_at'])) ?>
              </span>
            </div>

            <div class="comment-body-text">
              <?= nl2br(htmlspecialchars($comment['comment'])) ?>
            </div>
          </div>

          <!-- actions -->
          <div class="row-actions">
            <a href="<?= htmlspecialchars($post_url) ?>#comments" class="btn btn-primary">
              View
              <svg viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <form method="POST" action="my-comments.php?page=<?= $page ?>"
                  onsubmit="return confirm('Delete this comment?');" style="margin:0">
              <?php csrf_token_field(); ?>
              <input type="hidden" name="action" value="delete_comment">
              <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
              <button type="submit" class="btn btn-danger">
                <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 4h10M5 4V2.5h4V4M5.5 6.5v4M8.5 6.5v4M3 4l.8 7.5h6.4L11 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Delete
              </button>
            </form>
          </div>

        </div>
        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <nav class="comments-pagination" aria-label="Comment pages">

        <a href="my-comments.php?page=<?= max(1, $page - 1) ?>"
           class="page-btn"
           <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>>
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Prev
        </a>

        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1): ?>
          <a href="my-comments.php?page=1" class="page-btn">1</a>
          <?php if ($start > 2): ?><span class="page-btn" style="pointer-events:none;opacity:.4;border:none;padding:.4rem .5rem">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <a href="my-comments.php?page=<?= $i ?>"
             class="page-btn <?= $i == $page ? 'active' : '' ?>"
             <?= $i == $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($end < $total_pages):
          if ($end < $total_pages - 1): ?><span class="page-btn" style="pointer-events:none;opacity:.4;border:none;padding:.4rem .5rem">…</span><?php endif; ?>
          <a href="my-comments.php?page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
        <?php endif; ?>

        <a href="my-comments.php?page=<?= min($total_pages, $page + 1) ?>"
           class="page-btn"
           <?= $page >= $total_pages ? 'aria-disabled="true"' : '' ?>>
          Next
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>

      </nav>
      <?php endif; ?>

    <?php else: ?>
      <div class="comments-empty">
        <span class="comments-empty-icon">💬</span>
        <h3>No comments yet</h3>
        <p>You haven't posted any comments yet. Start engaging with the community!</p>
        <a href="index.php" class="btn btn-primary">
          Browse Posts
          <svg viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
    <?php endif; ?>

  </div><!-- /.comments-body -->
</div><!-- /.comments-page -->

<?php include '../shared/footer.php'; ?>
