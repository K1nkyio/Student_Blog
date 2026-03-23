<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

if (!is_logged_in()) {
    set_flash('error', 'You must be logged in to view notifications.');
    redirect('login.php');
}

$user_id = get_user_id();

// Pagination setup
$posts_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $posts_per_page;

// Filter setup
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$valid_filters = ['all', 'unread', 'read'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

// Build query based on filter
$where_clauses = ["user_id = ?"];
$params = [$user_id];
$types = "i";

if ($filter === 'unread') {
    $where_clauses[] = "read_at IS NULL";
} elseif ($filter === 'read') {
    $where_clauses[] = "read_at IS NOT NULL";
}

$where_sql = implode(" AND ", $where_clauses);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_notifications = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_notifications / $posts_per_page);

// Fetch notifications with pagination
$sql = "SELECT id, type, message, reference_type, reference_id, created_at, read_at
        FROM notifications
        WHERE $where_sql
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $posts_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Mark all as read if requested
if (isset($_POST['mark_all_read']) && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $update_stmt = $conn->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    set_flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

// Get notification icon based on type
function get_notification_icon($type) {
    $icons = [
        'comment'  => '💬',
        'like'     => '❤️',
        'follow'   => '👤',
        'mention'  => '@',
        'post'     => '📰',
        'system'   => 'ℹ️',
        'bookmark' => '🔖',
    ];
    return $icons[$type] ?? '🔔';
}

// Get notification color key based on type
function get_notification_color_key($type) {
    $map = [
        'comment'  => 'sky',
        'like'     => 'red',
        'follow'   => 'green',
        'mention'  => 'amber',
        'post'     => 'purple',
        'system'   => 'gray',
        'bookmark' => 'accent',
    ];
    return $map[$type] ?? 'gray';
}

$page_title = 'Notifications - ' . SITE_NAME;
include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS  (mirrors opportunities.php)
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
  --gray:         #6b7280;
  --gray-dim:     #f3f4f6;

  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-sans:  'Outfit', sans-serif;

  --max-w: 1360px;
  --gutter: 1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}

@media (max-width: 480px) {
  :root { --gutter: 1rem; }
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── Page shell ─── */
.notif-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO — full-width dark strip
═══════════════════════════════════════════ */
.notif-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

/* big serif watermark */
.notif-hero::before {
  content: 'Notif.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(5rem, 16vw, 13rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.035);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

/* subtle dot grid texture */
.notif-hero::after {
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

/* eyebrow */
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
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  box-shadow: 0 0 6px var(--accent);
  animation: blink 2s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

/* headline */
.hero-headline {
  font-family: var(--font-serif);
  font-size: clamp(2.2rem, 5.5vw, 3.8rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -.015em;
  color: #fff;
  margin-bottom: .8rem;
}
.hero-headline em {
  font-style: italic;
  color: rgba(255,255,255,.45);
}

.hero-sub {
  font-size: .95rem;
  color: rgba(255,255,255,.45);
  font-weight: 300;
  max-width: 480px;
  line-height: 1.7;
  margin-bottom: 2rem;
}

/* stats row */
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
   TOOLBAR — filter tabs + action
═══════════════════════════════════════════ */
.notif-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.notif-toolbar-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--gutter);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

/* filter tabs (tab-bar style) */
.notif-tabs {
  display: flex;
  gap: 0;
  overflow-x: auto;
  scrollbar-width: none;
}
.notif-tabs::-webkit-scrollbar { display: none; }

.notif-tab {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .85rem 1.1rem;
  font-size: .82rem;
  font-weight: 500;
  font-family: var(--font-sans);
  color: var(--ink-light);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  text-decoration: none;
  transition: color var(--transition), border-color var(--transition);
  margin-bottom: -1px;
}
.notif-tab:hover { color: var(--ink-mid); }
.notif-tab.active {
  color: var(--ink);
  border-bottom-color: var(--ink);
  font-weight: 600;
}
.notif-tab-count {
  font-size: .68rem;
  font-weight: 600;
  background: var(--rule);
  color: var(--ink-light);
  border-radius: 99px;
  padding: .08rem .45rem;
}
.notif-tab.active .notif-tab-count {
  background: var(--ink);
  color: #fff;
}

.toolbar-actions {
  padding: .5rem 0;
}

/* ═══════════════════════════════════════════
   BODY
═══════════════════════════════════════════ */
.notif-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
}

/* results bar */
.results-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  font-size: .8rem;
  color: var(--ink-light);
}
.results-bar strong { color: var(--ink-mid); font-weight: 600; }

/* flash messages */
.flash-msg {
  padding: .85rem 1.25rem;
  border-radius: 4px;
  font-size: .875rem;
  font-weight: 500;
  margin-bottom: 1.5rem;
  border-left: 3px solid;
}
.flash-msg.success { background: var(--green-dim); color: var(--green); border-color: var(--green); }
.flash-msg.error   { background: var(--red-dim);   color: var(--red);   border-color: var(--red);   }

/* ═══════════════════════════════════════════
   NOTIFICATION ROWS
═══════════════════════════════════════════ */
.notif-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.notif-row {
  display: grid;
  grid-template-columns: 48px 1fr auto;
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

.notif-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}

@keyframes rowIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* unread indicator — left accent line */
.notif-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: transparent;
}
.notif-row.unread::before { background: var(--sky); }

/* unread dot */
.notif-row.unread::after {
  content: '';
  position: absolute;
  top: 1.4rem;
  right: 1.4rem;
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--sky);
  box-shadow: 0 0 0 2px rgba(26,95,200,.15);
}

/* type-based accent line colors */
.notif-row.unread[data-type="comment"]::before  { background: var(--sky); }
.notif-row.unread[data-type="like"]::before     { background: var(--red); }
.notif-row.unread[data-type="follow"]::before   { background: var(--green); }
.notif-row.unread[data-type="mention"]::before  { background: var(--amber); }
.notif-row.unread[data-type="post"]::before     { background: var(--purple); }
.notif-row.unread[data-type="bookmark"]::before { background: var(--accent); }

/* type icon */
.notif-icon {
  width: 44px; height: 44px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  margin-top: .1rem;
}
.notif-icon--comment  { background: var(--sky-dim); }
.notif-icon--like     { background: var(--red-dim); }
.notif-icon--follow   { background: var(--green-dim); }
.notif-icon--mention  { background: var(--amber-dim); font-size: .95rem; font-weight: 700; color: var(--amber); font-family: var(--font-sans); }
.notif-icon--post     { background: var(--purple-dim); }
.notif-icon--system   { background: var(--gray-dim); }
.notif-icon--bookmark { background: var(--accent-dim); }

/* row body */
.row-body { min-width: 0; }

.row-badges {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem;
  margin-bottom: .45rem;
  align-items: center;
}
.badge {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .18rem .6rem;
  border-radius: 2px;
}
.badge--comment  { background: var(--sky-dim);    color: var(--sky); }
.badge--like     { background: var(--red-dim);    color: var(--red); }
.badge--follow   { background: var(--green-dim);  color: var(--green); }
.badge--mention  { background: var(--amber-dim);  color: var(--amber); }
.badge--post     { background: var(--purple-dim); color: var(--purple); }
.badge--system   { background: var(--gray-dim);   color: var(--gray); }
.badge--bookmark { background: var(--accent-dim); color: var(--accent); }
.badge--unread   { background: var(--sky-dim);    color: var(--sky); }

/* message */
.notif-message {
  font-family: var(--font-serif);
  font-size: clamp(.95rem, 1.8vw, 1.1rem);
  font-weight: 600;
  color: var(--ink);
  line-height: 1.3;
  margin-bottom: .5rem;
}

/* meta */
.notif-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem 1.25rem;
  font-size: .8rem;
  color: var(--ink-light);
  margin-bottom: .55rem;
  align-items: center;
}
.notif-meta-item {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}
.notif-meta-item svg { width: 12px; height: 12px; opacity: .7; flex-shrink: 0; }
.notif-meta-item a {
  color: var(--sky);
  text-decoration: none;
  font-weight: 500;
  transition: color var(--transition);
}
.notif-meta-item a:hover { color: var(--ink); }

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

.btn-primary {
  background: var(--ink);
  color: #fff;
}
.btn-primary:hover {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}

.btn-ghost {
  background: transparent;
  color: var(--ink-mid);
  border: 1.5px solid var(--rule);
}
.btn-ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); }

.btn-icon {
  padding: .55rem;
  width: 32px; height: 32px;
  border: 1.5px solid var(--rule);
  background: transparent;
  color: var(--ink-light);
  border-radius: 3px;
}
.btn-icon:hover { border-color: var(--ink-mid); color: var(--ink); background: var(--bg-warm); }
.btn-icon svg { width: 13px; height: 13px; }

/* ═══════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════ */
.notif-empty {
  text-align: center;
  padding: 5rem 1rem;
  color: var(--ink-light);
  border-top: 1px solid var(--rule-light);
}
.notif-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .4; }
.notif-empty h3 { font-family: var(--font-serif); font-size: 1.3rem; color: var(--ink-mid); margin-bottom: .4rem; }
.notif-empty p  { font-size: .875rem; }
.notif-empty .btn { margin-top: 1.25rem; }

/* ═══════════════════════════════════════════
   PAGINATION
═══════════════════════════════════════════ */
.notif-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .6rem;
  margin-top: 2.5rem;
  border-top: 1px solid var(--rule-light);
  padding-top: 2rem;
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
.page-info {
  font-size: .8rem;
  color: var(--ink-light);
  padding: .55rem 1.1rem;
  background: var(--bg-warm);
  border-radius: 3px;
  min-width: 7rem;
  text-align: center;
}

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 640px) {
  .notif-row {
    grid-template-columns: 40px 1fr;
    grid-template-rows: auto auto;
    padding: 1.1rem 1.1rem;
  }
  .row-actions {
    grid-column: 1 / -1;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
  }
  .hero-stats { gap: 1.5rem; }
  .notif-toolbar-inner { gap: .5rem; }
}
@media (max-width: 420px) {
  .notif-toolbar-inner { flex-direction: column; align-items: stretch; }
}
</style>

<?php
$stats = [
    'total'  => $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id")->fetch_assoc()['cnt'],
    'unread' => $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND read_at IS NULL")->fetch_assoc()['cnt'],
    'today'  => $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND DATE(created_at) = CURDATE()")->fetch_assoc()['cnt'],
];
?>

<div class="notif-page">

  <!-- ══════════ HERO ══════════ -->
  <div class="notif-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="live-dot"></span> Your activity</span>
      <h1 class="hero-headline">Stay in the<br><em>Loop</em></h1>
      <p class="hero-sub">Comments, likes, follows, and mentions — everything that matters to your account, in one place.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $stats['total'] ?></span>
          <span class="hero-stat-label">Total</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $stats['unread'] ?></span>
          <span class="hero-stat-label">Unread</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?= $stats['today'] ?></span>
          <span class="hero-stat-label">Today</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ STICKY TOOLBAR ══════════ -->
  <div class="notif-toolbar">
    <div class="notif-toolbar-inner">
      <nav class="notif-tabs" aria-label="Notification filters">
        <a href="?filter=all"
           class="notif-tab <?= $filter === 'all' ? 'active' : '' ?>">
          All <span class="notif-tab-count"><?= $stats['total'] ?></span>
        </a>
        <a href="?filter=unread"
           class="notif-tab <?= $filter === 'unread' ? 'active' : '' ?>">
          Unread <span class="notif-tab-count"><?= $stats['unread'] ?></span>
        </a>
        <a href="?filter=read"
           class="notif-tab <?= $filter === 'read' ? 'active' : '' ?>">
          Read
        </a>
      </nav>

      <div class="toolbar-actions">
        <?php if ($stats['unread'] > 0): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
          <button type="submit" name="mark_all_read" class="btn btn-ghost">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 7l3 3L12 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Mark all read
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="notif-body">

    <?php display_flash(); ?>

    <!-- Results bar -->
    <div class="results-bar">
      <span><strong><?= $total_notifications ?></strong> notification<?= $total_notifications !== 1 ? 's' : '' ?> found</span>
      <?php if ($total_pages > 1): ?>
        <span>Page <?= $current_page ?> of <?= $total_pages ?></span>
      <?php endif; ?>
    </div>

    <?php if ($notifications->num_rows > 0): ?>

      <div class="notif-list">
        <?php while ($notification = $notifications->fetch_assoc()):
          $is_unread  = is_null($notification['read_at']);
          $type       = $notification['type'] ?? 'system';
          $icon       = get_notification_icon($type);
          $color_key  = get_notification_color_key($type);
        ?>
        <div class="notif-row <?= $is_unread ? 'unread' : '' ?>"
             data-notification-id="<?= (int)$notification['id'] ?>"
             data-type="<?= htmlspecialchars($type) ?>">

          <!-- icon -->
          <div class="notif-icon notif-icon--<?= htmlspecialchars($type) ?>">
            <?= $icon ?>
          </div>

          <!-- body -->
          <div class="row-body">
            <div class="row-badges">
              <span class="badge badge--<?= htmlspecialchars($type) ?>"><?= htmlspecialchars(ucfirst($type)) ?></span>
              <?php if ($is_unread): ?>
                <span class="badge badge--unread">New</span>
              <?php endif; ?>
            </div>

            <div class="notif-message">
              <?= htmlspecialchars($notification['message']) ?>
            </div>

            <div class="notif-meta">
              <span class="notif-meta-item">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?= time_ago($notification['created_at']) ?>
              </span>
              <?php if ($notification['reference_type'] && $notification['reference_id']): ?>
              <span class="notif-meta-item">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 8a3 3 0 004.5.3l1.8-1.8a3 3 0 00-4.2-4.2L5.7 4.7" stroke-linecap="round"/><path d="M11 8a3 3 0 01-4.5-.3L4.7 9.5A3 3 0 008.9 13.7l1.4-1.4" stroke-linecap="round"/></svg>
                <?php
                switch ($notification['reference_type']) {
                    case 'post':
                        echo '<a href="post.php?id=' . (int)$notification['reference_id'] . '">View Post</a>';
                        break;
                    case 'comment':
                        echo '<a href="post.php?id=' . (int)$notification['reference_id'] . '#comments">View Comment</a>';
                        break;
                    case 'user':
                        echo '<a href="profile.php?id=' . (int)$notification['reference_id'] . '">View Profile</a>';
                        break;
                    default:
                        echo '<span>Related Content</span>';
                }
                ?>
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- actions -->
          <div class="row-actions">
            <?php if ($is_unread): ?>
              <button class="btn btn-primary" onclick="markAsRead(<?= (int)$notification['id'] ?>)" data-mark-btn="<?= (int)$notification['id'] ?>">
                <svg width="11" height="11" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M2 6.5l3 3L11 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Mark read
              </button>
            <?php else: ?>
              <span style="font-size:.72rem; color:var(--rule); display:inline-flex; align-items:center; gap:.25rem;">
                <svg width="11" height="11" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M2 6.5l3 3L11 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Read
              </span>
            <?php endif; ?>
            <button class="btn-icon" type="button" title="Dismiss"
              onclick="dismissNotification(<?= (int)$notification['id'] ?>)">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8" stroke-linecap="round"/></svg>
            </button>
          </div>

        </div>
        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <nav class="notif-pagination" role="navigation" aria-label="Notification pages">
        <a href="?filter=<?= $filter ?>&page=<?= max(1, $current_page - 1) ?>"
           class="page-btn"
           <?= $current_page == 1 ? 'aria-disabled="true"' : '' ?>>
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Prev
        </a>

        <?php
        $start_page = max(1, $current_page - 2);
        $end_page   = min($total_pages, $current_page + 2);
        if ($start_page > 1): ?>
          <a href="?filter=<?= $filter ?>&page=1" class="page-btn">1</a>
          <?php if ($start_page > 2): ?><span class="page-info" style="min-width:2rem;padding:.4rem .6rem">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
          <a href="?filter=<?= $filter ?>&page=<?= $i ?>"
             class="page-btn <?= $current_page == $i ? 'active' : '' ?>"
             <?= $current_page == $i ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages):
          if ($end_page < $total_pages - 1): ?><span class="page-info" style="min-width:2rem;padding:.4rem .6rem">…</span><?php endif; ?>
          <a href="?filter=<?= $filter ?>&page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
        <?php endif; ?>

        <a href="?filter=<?= $filter ?>&page=<?= min($total_pages, $current_page + 1) ?>"
           class="page-btn"
           <?= $current_page == $total_pages ? 'aria-disabled="true"' : '' ?>>
          Next
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </nav>
      <?php endif; ?>

    <?php else: ?>
      <!-- Empty State -->
      <div class="notif-empty">
        <span class="notif-empty-icon">🔔</span>
        <h3>
          <?php if ($filter === 'unread'): ?>All caught up<?php elseif ($filter === 'read'): ?>Nothing read yet<?php else: ?>No notifications yet<?php endif; ?>
        </h3>
        <p>
          <?php if ($filter === 'unread'): ?>
            You've read all your notifications — great work staying on top of things.
          <?php elseif ($filter === 'read'): ?>
            You haven't marked any notifications as read yet.
          <?php else: ?>
            Start engaging with posts and other users to receive notifications here.
          <?php endif; ?>
        </p>
        <?php if ($filter !== 'all'): ?>
          <a href="notifications.php" class="btn btn-primary">
            View all notifications
            <svg width="10" height="10" viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div><!-- /.notif-body -->
</div><!-- /.notif-page -->

<script>
// ── Mark as read ──
function markAsRead(notificationId) {
  const row = document.querySelector(`[data-notification-id="${notificationId}"]`);
  if (!row) return;

  // Optimistic UI
  row.classList.remove('unread');
  const btn = document.querySelector(`[data-mark-btn="${notificationId}"]`);
  if (btn) btn.style.opacity = '.4';

  fetch('ajax/mark_notification_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notification_id: notificationId })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      row.classList.add('unread');
      if (btn) btn.style.opacity = '';
      showToast(data.message || 'Failed to mark as read', 'error');
    } else {
      if (btn) {
        btn.outerHTML = `<span style="font-size:.72rem;color:var(--rule);display:inline-flex;align-items:center;gap:.25rem;">
          <svg width="11" height="11" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M2 6.5l3 3L11 2" stroke-linecap="round" stroke-linejoin="round"/></svg>Read</span>`;
      }
      showToast('Marked as read', 'success');
    }
  })
  .catch(() => {
    row.classList.add('unread');
    if (btn) btn.style.opacity = '';
    showToast('Network error. Please try again.', 'error');
  });
}

// ── Dismiss ──
function dismissNotification(notificationId) {
  const row = document.querySelector(`[data-notification-id="${notificationId}"]`);
  if (!row) return;
  row.style.transition = 'opacity .25s ease, transform .25s ease';
  row.style.opacity    = '0';
  row.style.transform  = 'translateX(20px)';
  setTimeout(() => row.remove(), 260);
}

// ── Toast ──
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  const bg = type === 'success' ? 'var(--green)' : type === 'error' ? 'var(--red)' : 'var(--sky)';
  toast.style.cssText = `
    position:fixed;top:1.25rem;right:1.25rem;
    background:${bg};color:#fff;
    padding:.7rem 1.1rem;border-radius:3px;
    box-shadow:0 4px 20px rgba(24,22,15,.18);
    z-index:10000;font-size:.82rem;font-weight:600;
    font-family:'Outfit',sans-serif;
    opacity:0;transition:opacity .25s ease;`;
  toast.textContent = message;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.style.opacity = '1');
  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 260);
  }, 3000);
}

// ── Auto-mark on scroll (debounced) ──
let markTimeout;
window.addEventListener('scroll', function () {
  clearTimeout(markTimeout);
  markTimeout = setTimeout(function () {
    document.querySelectorAll('.notif-row.unread').forEach(item => {
      const rect = item.getBoundingClientRect();
      if (rect.top >= 0 && rect.bottom <= window.innerHeight) {
        markAsRead(parseInt(item.dataset.notificationId, 10));
      }
    });
  }, 1200);
});
</script>

<?php
$notifications->close();
$conn->close();
include '../shared/footer.php';
?>