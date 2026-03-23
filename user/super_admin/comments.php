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

$status  = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'approved'];
if (!in_array($status, $allowed, true)) $status = 'all';

if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $stmt = $conn->prepare("UPDATE comments SET approved = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute(); $stmt->close();
    header('Location: comments.php?status=' . $status);
    exit();
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute(); $stmt->close();
    header('Location: comments.php?status=' . $status);
    exit();
}

$topbar_title_override    = 'Comments';
$topbar_subtitle_override = 'Moderate comments across the platform';
include 'includes/header.php';

$total = $approved = $pending = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM comments");                             if ($r) $total    = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM comments WHERE approved = 1");          if ($r) $approved = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM comments WHERE approved = 0");          if ($r) $pending  = (int)$r->fetch_assoc()['c'];

$where = '';
if ($status === 'pending')  $where = 'WHERE c.approved = 0';
if ($status === 'approved') $where = 'WHERE c.approved = 1';

$comments = [];
$res = $conn->query("SELECT c.*, p.title AS post_title FROM comments c LEFT JOIN posts p ON c.post_id = p.id $where ORDER BY c.created_at DESC");
if ($res) while ($row = $res->fetch_assoc()) $comments[] = $row;
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS — mirrors opportunities.php
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

  --max-w:      1360px;
  --gutter:     1.5rem;
  --radius:     8px;
  --shadow:     0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── page shell ─── */
.cm-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO
═══════════════════════════════════════════ */
.cm-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}
.cm-hero::before {
  content: 'Cmts.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(6rem, 18vw, 15rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.035);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}
.cm-hero::after {
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
.hero-eyebrow .live-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--accent); box-shadow: 0 0 6px var(--accent);
  animation: blink 2s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

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
  text-align: justify;
}
.hero-stats { display: flex; gap: 2.5rem; flex-wrap: wrap; }
.hero-stat-num {
  font-family: var(--font-serif);
  font-size: 2rem; font-weight: 700; color: #fff; line-height: 1; display: block;
}
.hero-stat-label {
  font-size: .65rem; font-weight: 500; letter-spacing: .1em;
  text-transform: uppercase; color: rgba(255,255,255,.35); display: block; margin-top: .25rem;
}

/* ═══════════════════════════════════════════
   TOOLBAR
═══════════════════════════════════════════ */
.cm-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.cm-toolbar-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .85rem var(--gutter);
  display: flex;
  gap: .75rem;
  align-items: center;
  flex-wrap: wrap;
}
.search-wrap {
  flex: 1; min-width: 200px;
  display: flex; align-items: center; gap: .6rem;
  border: 1.5px solid var(--rule); border-radius: 4px;
  padding: .55rem .9rem; background: var(--bg);
  transition: border-color var(--transition);
}
.search-wrap:focus-within { border-color: var(--ink); background: #fff; }
.search-wrap svg { color: var(--ink-light); flex-shrink: 0; width: 15px; height: 15px; }
.search-wrap input {
  border: none; outline: none; flex: 1;
  font-size: .875rem; font-family: var(--font-sans);
  color: var(--ink); background: transparent;
}
.search-wrap input::placeholder { color: var(--ink-light); }

/* ═══════════════════════════════════════════
   BODY
═══════════════════════════════════════════ */
.cm-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
}

/* tabs */
.cm-tabs {
  display: flex;
  border-bottom: 1px solid var(--rule);
  margin-bottom: 1.75rem;
  overflow-x: auto;
  scrollbar-width: none;
}
.cm-tabs::-webkit-scrollbar { display: none; }
.cm-tab {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .65rem 1.1rem;
  font-size: .82rem; font-weight: 500; font-family: var(--font-sans);
  color: var(--ink-light); background: transparent;
  border: none; border-bottom: 2px solid transparent;
  text-decoration: none;
  white-space: nowrap;
  transition: color var(--transition), border-color var(--transition);
  margin-bottom: -1px;
}
.cm-tab:hover { color: var(--ink-mid); }
.cm-tab.active { color: var(--ink); border-bottom-color: var(--ink); font-weight: 600; }
.cm-tab-count {
  font-size: .68rem; font-weight: 600;
  background: var(--rule); color: var(--ink-light);
  border-radius: 99px; padding: .08rem .45rem;
}
.cm-tab.active .cm-tab-count { background: var(--ink); color: #fff; }

/* results bar */
.results-bar {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 1.25rem; font-size: .8rem; color: var(--ink-light);
}
.results-bar strong { color: var(--ink-mid); font-weight: 600; }

/* ═══════════════════════════════════════════
   COMMENT ROWS
═══════════════════════════════════════════ */
.cm-list { display: flex; flex-direction: column; gap: 1rem; }

.cm-row {
  display: grid;
  grid-template-columns: 48px 1fr auto;
  gap: 1.25rem;
  align-items: start;
  padding: 1.5rem;
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  position: relative;
  animation: rowIn .35s ease both;
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
}
.cm-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}
@keyframes rowIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}
/* left accent */
.cm-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px; border-radius: 0 2px 2px 0;
}
.cm-row[data-status="approved"]::before { background: var(--green); }
.cm-row[data-status="pending"]::before  { background: var(--amber); }

/* avatar */
.cm-avatar {
  width: 44px; height: 44px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-serif); font-size: 1.25rem; font-weight: 700;
  flex-shrink: 0; margin-top: .1rem; text-transform: uppercase;
}
.cm-avatar.approved { background: var(--green-dim); color: var(--green); }
.cm-avatar.pending  { background: var(--amber-dim); color: var(--amber); }

/* body */
.row-body { min-width: 0; }

.row-badges {
  display: flex; flex-wrap: wrap; gap: .3rem;
  margin-bottom: .45rem; align-items: center;
}
.badge {
  font-size: .62rem; font-weight: 600; letter-spacing: .06em;
  text-transform: uppercase; padding: .18rem .6rem; border-radius: 2px;
}
.badge-approved { background: var(--green-dim); color: var(--green); }
.badge-pending  { background: var(--amber-dim); color: var(--amber); }

.row-post-title {
  font-family: var(--font-serif);
  font-size: clamp(1rem, 2vw, 1.2rem);
  font-weight: 600; color: var(--ink); line-height: 1.25; margin-bottom: .5rem;
}

.row-meta {
  display: flex; flex-wrap: wrap; gap: .4rem 1.25rem;
  font-size: .8rem; color: var(--ink-light); margin-bottom: .55rem; align-items: center;
}
.row-meta-item { display: inline-flex; align-items: center; gap: .3rem; }
.row-meta-item svg { width: 12px; height: 12px; opacity: .7; flex-shrink: 0; }

.row-comment-text {
  font-size: .855rem; color: var(--ink-mid); line-height: 1.6;
  text-align: justify; text-align-last: left; hyphens: auto;
  font-style: italic;
  background: var(--bg-warm);
  border-left: 3px solid var(--rule);
  padding: .65rem .85rem;
  border-radius: 0 4px 4px 0;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* actions */
.row-actions {
  display: flex; flex-direction: column; gap: .4rem;
  align-items: flex-end; flex-shrink: 0; padding-top: .1rem;
}
.btn {
  display: inline-flex; align-items: center; gap: .4rem;
  font-family: var(--font-sans); font-size: .8rem; font-weight: 600;
  text-decoration: none; padding: .55rem 1.1rem; border-radius: 3px;
  border: none; cursor: pointer; transition: all var(--transition);
  white-space: nowrap; line-height: 1;
}
.btn:active { transform: scale(.97); }
.btn-approve {
  background: var(--green); color: #fff;
}
.btn-approve:hover { background: #155e3a; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,122,74,.25); }
.btn-delete {
  background: transparent; color: var(--red);
  border: 1.5px solid var(--red-dim);
}
.btn-delete:hover { background: var(--red-dim); border-color: var(--red); transform: translateY(-1px); }

/* ═══════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════ */
.cm-empty {
  text-align: center; padding: 5rem 1rem; color: var(--ink-light);
  border-top: 1px solid var(--rule-light);
}
.cm-empty span { font-size: 2.5rem; display: block; opacity: .4; margin-bottom: 1rem; }
.cm-empty h3 { font-family: var(--font-serif); font-size: 1.3rem; color: var(--ink-mid); margin-bottom: .4rem; }
.cm-empty p { font-size: .875rem; }

/* ═══════════════════════════════════════════
   PAGINATION
═══════════════════════════════════════════ */
.cm-pagination {
  display: flex; align-items: center; justify-content: center;
  gap: .6rem; margin-top: 2.5rem;
  border-top: 1px solid var(--rule-light); padding-top: 2rem;
}
.page-btn {
  display: inline-flex; align-items: center; gap: .35rem;
  padding: .55rem 1.1rem; border: 1.5px solid var(--rule); border-radius: 3px;
  background: var(--bg); font-size: .82rem; font-weight: 500;
  font-family: var(--font-sans); color: var(--ink-mid); cursor: pointer;
  transition: all var(--transition);
}
.page-btn:hover:not(:disabled) { border-color: var(--ink); background: var(--bg-warm); }
.page-btn:disabled { opacity: .35; cursor: not-allowed; }
.page-info {
  font-size: .8rem; color: var(--ink-light);
  padding: .55rem 1.1rem; background: var(--bg-warm);
  border-radius: 3px; min-width: 7rem; text-align: center;
}

/* ═══════════════════════════════════════════
   CONFIRM MODAL
═══════════════════════════════════════════ */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(24,22,15,.55); z-index: 200;
  align-items: center; justify-content: center; padding: 1rem;
}
.modal-overlay.active { display: flex; animation: fadeIn .15s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.modal-card {
  background: #fff; border-radius: var(--radius); padding: 2rem;
  max-width: 380px; width: 100%;
  box-shadow: 0 20px 60px rgba(24,22,15,.2);
  animation: scaleIn .2s ease;
}
@keyframes scaleIn { from{transform:scale(.95);opacity:0} to{transform:scale(1);opacity:1} }
.modal-card h4 { font-family: var(--font-serif); font-size: 1.3rem; font-weight: 700; margin-bottom: .5rem; color: var(--ink); }
.modal-card p  { font-size: .875rem; color: var(--ink-mid); margin-bottom: 1.5rem; line-height: 1.6; }
.modal-actions { display: flex; gap: .6rem; justify-content: flex-end; }
.btn-ghost {
  background: transparent; color: var(--ink-mid);
  border: 1.5px solid var(--rule); padding: .55rem 1.1rem; border-radius: 3px;
  font-size: .8rem; font-weight: 600; font-family: var(--font-sans); cursor: pointer;
  transition: all var(--transition);
}
.btn-ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 640px) {
  .cm-row { grid-template-columns: 40px 1fr; grid-template-rows: auto auto; padding: 1.1rem; }
  .row-actions { grid-column: 1 / -1; flex-direction: row; flex-wrap: wrap; align-items: center; }
  .hero-stats { gap: 1.5rem; }
}
@media (max-width: 420px) {
  .cm-toolbar-inner { flex-direction: column; align-items: stretch; }
}
</style>

<div class="cm-page">

  <!-- ══════════ HERO ══════════ -->
  <div class="cm-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="live-dot"></span> Moderation queue</span>
      <h1 class="hero-headline">Manage<br><em>Comments</em></h1>
      <p class="hero-sub">Review, approve, and moderate reader comments across all blog posts. Keep the conversation quality high.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num"><?php echo number_format($total); ?></span>
          <span class="hero-stat-label">Total</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?php echo number_format($approved); ?></span>
          <span class="hero-stat-label">Approved</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?php echo number_format($pending); ?></span>
          <span class="hero-stat-label">Pending</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ STICKY TOOLBAR ══════════ -->
  <div class="cm-toolbar">
    <div class="cm-toolbar-inner">
      <div class="search-wrap">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="9" cy="9" r="6"/><path d="M15 15l3 3" stroke-linecap="round"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Search by name, post, or comment text…">
      </div>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="cm-body">

    <!-- tabs (server-side filter, keeps URL state) -->
    <div class="cm-tabs">
      <a class="cm-tab <?php echo $status === 'all'      ? 'active' : ''; ?>" href="comments.php?status=all">
        All <span class="cm-tab-count"><?php echo $total; ?></span>
      </a>
      <a class="cm-tab <?php echo $status === 'pending'  ? 'active' : ''; ?>" href="comments.php?status=pending">
        Pending <span class="cm-tab-count"><?php echo $pending; ?></span>
      </a>
      <a class="cm-tab <?php echo $status === 'approved' ? 'active' : ''; ?>" href="comments.php?status=approved">
        Approved <span class="cm-tab-count"><?php echo $approved; ?></span>
      </a>
    </div>

    <!-- results bar -->
    <div class="results-bar">
      <span><strong id="resultsCount"><?php echo count($comments); ?></strong> comments found</span>
      <span id="resultsMeta"></span>
    </div>

    <?php if (!empty($comments)): ?>

      <div class="cm-list" id="commentsList">
        <?php foreach ($comments as $i => $row):
          $postTitle  = htmlspecialchars($row['post_title'] ?? 'Unknown Post');
          $name       = trim($row['name'] ?? 'Anonymous');
          $initial    = strtoupper(mb_substr($name, 0, 1)) ?: '?';
          $comment    = trim($row['comment'] ?? '');
          $snippet    = mb_strlen($comment) > 200 ? mb_substr($comment, 0, 200) . '…' : $comment;
          $dateStr    = !empty($row['created_at']) ? date('M d, Y · H:i', strtotime($row['created_at'])) : '';
          $isApproved = !empty($row['approved']);
          $statusKey  = $isApproved ? 'approved' : 'pending';
        ?>
        <div class="cm-row"
          data-status="<?php echo $statusKey; ?>"
          data-name="<?php echo htmlspecialchars(strtolower($name)); ?>"
          data-post="<?php echo htmlspecialchars(strtolower($row['post_title'] ?? '')); ?>"
          data-comment="<?php echo htmlspecialchars(strtolower($comment)); ?>"
          style="animation-delay: <?php echo $i * 35; ?>ms">

          <!-- avatar -->
          <div class="cm-avatar <?php echo $statusKey; ?>"><?php echo htmlspecialchars($initial); ?></div>

          <!-- body -->
          <div class="row-body">
            <div class="row-badges">
              <span class="badge badge-<?php echo $statusKey; ?>">
                <?php echo $isApproved ? '✓ Approved' : '⏳ Pending'; ?>
              </span>
            </div>

            <h3 class="row-post-title"><?php echo $postTitle; ?></h3>

            <div class="row-meta">
              <span class="row-meta-item">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                  <circle cx="8" cy="5" r="3"/>
                  <path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke-linecap="round"/>
                </svg>
                <?php echo htmlspecialchars($name); ?>
              </span>
              <?php if ($dateStr): ?>
              <span class="row-meta-item">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                  <rect x="2" y="3" width="12" height="11" rx="1.5"/>
                  <path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/>
                </svg>
                <?php echo htmlspecialchars($dateStr); ?>
              </span>
              <?php endif; ?>
            </div>

            <p class="row-comment-text"><?php echo nl2br(htmlspecialchars($snippet)); ?></p>
          </div>

          <!-- actions -->
          <div class="row-actions">
            <?php if (!$isApproved): ?>
              <a class="btn btn-approve"
                href="?approve=<?php echo (int)$row['id']; ?>&status=<?php echo htmlspecialchars($status); ?>">
                <svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Approve
              </a>
            <?php endif; ?>
            <button class="btn btn-delete"
              type="button"
              data-delete-id="<?php echo (int)$row['id']; ?>"
              data-delete-name="<?php echo htmlspecialchars($name); ?>"
              data-delete-status="<?php echo htmlspecialchars($status); ?>">
              <svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h8M5 3V2h2v1M4 3v6h4V3" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Delete
            </button>
          </div>

        </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <div class="cm-pagination">
        <button class="page-btn" id="prevPage">
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Prev
        </button>
        <div class="page-info" id="pageIndicator">Page 1 of 1</div>
        <button class="page-btn" id="nextPage">
          Next
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

    <?php else: ?>
      <div class="cm-empty">
        <span>💬</span>
        <h3>No comments found</h3>
        <p>Nothing here for the current filter. Try switching to a different tab.</p>
      </div>
    <?php endif; ?>

  </div><!-- /.cm-body -->
</div><!-- /.cm-page -->

<!-- Delete confirm modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-card">
    <h4>Delete Comment?</h4>
    <p id="modalMsg">This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn-ghost" id="modalCancel" type="button">Cancel</button>
      <a class="btn btn-delete" id="modalConfirm" href="#">Delete</a>
    </div>
  </div>
</div>

<script>
(function () {
  const searchInput   = document.getElementById('searchInput');
  const list          = document.getElementById('commentsList');
  const resultsCount  = document.getElementById('resultsCount');
  const resultsMeta   = document.getElementById('resultsMeta');
  const prevPage      = document.getElementById('prevPage');
  const nextPage      = document.getElementById('nextPage');
  const pageIndicator = document.getElementById('pageIndicator');

  const rows     = list ? Array.from(list.querySelectorAll('.cm-row')) : [];
  const pageSize = 10;
  let currentPage = 1;

  /* ── match ── */
  const matches = (row, query) => {
    if (!query) return true;
    return (row.dataset.name    || '').includes(query)
        || (row.dataset.post    || '').includes(query)
        || (row.dataset.comment || '').includes(query);
  };

  /* ── render ── */
  const render = () => {
    const query    = (searchInput?.value || '').trim().toLowerCase();
    const filtered = rows.filter(r => matches(r, query));
    const total    = filtered.length;
    const pages    = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > pages) currentPage = pages;
    const start = (currentPage - 1) * pageSize;
    const end   = start + pageSize;

    rows.forEach(r => (r.style.display = 'none'));
    filtered.slice(start, end).forEach((r, i) => {
      r.style.display = '';
      r.style.animationDelay = `${i * 35}ms`;
    });

    if (resultsCount)  resultsCount.textContent  = total;
    if (resultsMeta)   resultsMeta.textContent   = total ? `Showing ${Math.min(end, total)} of ${total}` : '';
    if (pageIndicator) pageIndicator.textContent = `Page ${currentPage} of ${pages}`;
    if (prevPage) prevPage.disabled = currentPage === 1;
    if (nextPage) nextPage.disabled = currentPage === pages;
  };

  /* ── search ── */
  if (searchInput) {
    let debounce;
    searchInput.addEventListener('input', () => {
      clearTimeout(debounce);
      debounce = setTimeout(() => { currentPage = 1; render(); }, 280);
    });
  }

  /* ── pagination ── */
  if (prevPage) prevPage.addEventListener('click', () => { currentPage = Math.max(1, currentPage - 1); render(); });
  if (nextPage) nextPage.addEventListener('click', () => { currentPage++; render(); });

  /* ── delete modal ── */
  const modal        = document.getElementById('deleteModal');
  const modalMsg     = document.getElementById('modalMsg');
  const modalCancel  = document.getElementById('modalCancel');
  const modalConfirm = document.getElementById('modalConfirm');

  document.querySelectorAll('[data-delete-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.dataset.deleteId;
      const name = btn.dataset.deleteName   || 'this comment';
      const st   = btn.dataset.deleteStatus || 'all';
      if (modalMsg)     modalMsg.textContent = `Delete the comment by "${name}"? This action cannot be undone.`;
      if (modalConfirm) modalConfirm.href    = `?delete=${id}&status=${st}`;
      modal?.classList.add('active');
    });
  });
  modalCancel?.addEventListener('click', () => modal?.classList.remove('active'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });

  render();
})();
</script>

<?php include '../admin/includes/footer.php'; ?>