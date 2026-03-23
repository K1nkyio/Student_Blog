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

$topbar_title_override    = 'Analytics';
$topbar_subtitle_override = 'Platform performance and engagement metrics';

include 'includes/header.php';
include '../shared/db_connect.php';

function table_exists(mysqli $conn, string $table): bool {
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function safe_count(mysqli $conn, string $table, string $where = ''): int {
    if (!table_exists($conn, $table)) return 0;
    $t = '`' . str_replace('`', '', $table) . '`';
    $sql = "SELECT COUNT(*) as cnt FROM {$t}" . ($where ? " WHERE {$where}" : '');
    $res = $conn->query($sql);
    if (!$res) return 0;
    return (int)($res->fetch_assoc()['cnt'] ?? 0);
}

function safe_sum(mysqli $conn, string $table, string $column, string $where = ''): int {
    if (!table_exists($conn, $table) || !column_exists($conn, $table, $column)) return 0;
    $t = '`' . str_replace('`', '', $table) . '`';
    $c = '`' . str_replace('`', '', $column) . '`';
    $sql = "SELECT COALESCE(SUM({$c}),0) as total FROM {$t}" . ($where ? " WHERE {$where}" : '');
    $res = $conn->query($sql);
    if (!$res) return 0;
    return (int)($res->fetch_assoc()['total'] ?? 0);
}

function safe_recent(mysqli $conn, string $table, string $dateColumn): int {
    if (!table_exists($conn, $table) || !column_exists($conn, $table, $dateColumn)) return 0;
    $c = '`' . str_replace('`', '', $dateColumn) . '`';
    return safe_count($conn, $table, "{$c} >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
}

function get_monthly_counts(mysqli $conn, string $table, string $dateColumn, int $months = 6): array {
    $months = max(1, $months);
    $keys = []; $labels = [];
    $dt = new DateTime('first day of this month');
    $dt->modify('-' . ($months - 1) . ' months');
    for ($i = 0; $i < $months; $i++) {
        $keys[]   = $dt->format('Y-m');
        $labels[] = $dt->format('M Y');
        $dt->modify('+1 month');
    }
    $counts = array_fill_keys($keys, 0);
    if (!table_exists($conn, $table) || !column_exists($conn, $table, $dateColumn)) {
        return [$labels, array_values($counts)];
    }
    $start = (new DateTime('first day of this month'))->modify('-' . ($months - 1) . ' months')->format('Y-m-01');
    $t = '`' . str_replace('`', '', $table) . '`';
    $c = '`' . str_replace('`', '', $dateColumn) . '`';
    $res = $conn->query("SELECT DATE_FORMAT({$c},'%Y-%m') as ym, COUNT(*) as cnt FROM {$t} WHERE {$c} >= '{$start}' GROUP BY ym");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (isset($counts[$row['ym']])) $counts[$row['ym']] = (int)$row['cnt'];
        }
    }
    return [$labels, array_values($counts)];
}

// ── Metrics ──────────────────────────────────────────
$posts_total   = safe_count($conn, 'posts');
$posts_visible = column_exists($conn, 'posts', 'visible')
    ? safe_count($conn, 'posts', 'COALESCE(visible,1)=1') : $posts_total;
$posts_30d     = safe_recent($conn, 'posts', 'created_at');
$views_total   = safe_sum($conn, 'posts', 'view_count');

$comments_total    = safe_count($conn, 'comments');
$comments_approved = column_exists($conn, 'comments', 'approved')
    ? safe_count($conn, 'comments', 'approved=1') : 0;
$comments_pending  = max(0, $comments_total - $comments_approved);
$comments_30d      = safe_recent($conn, 'comments', 'created_at');

$users_total   = safe_count($conn, 'users');
$users_30d     = safe_recent($conn, 'users', 'created_at');

$bookmarks_total = safe_count($conn, 'bookmarks');
$bookmarks_30d   = safe_recent($conn, 'bookmarks', 'created_at');
$likes_total     = safe_count($conn, 'likes');
$likes_30d       = safe_recent($conn, 'likes', 'created_at');
$reactions_total = safe_count($conn, 'post_reactions');
$reactions_30d   = safe_recent($conn, 'post_reactions', 'created_at');
$events_total    = safe_count($conn, 'events');
$events_30d      = safe_recent($conn, 'events', 'created_at');
$opps_total      = safe_count($conn, 'opportunities');
$opps_30d        = safe_recent($conn, 'opportunities', 'created_at');
$market_total    = safe_count($conn, 'marketplace_items');
$market_30d      = safe_recent($conn, 'marketplace_items', 'created_at');
$safe_total      = safe_count($conn, 'anonymous_reports');
$safe_30d        = safe_recent($conn, 'anonymous_reports', 'created_at');

$approval_rate = $comments_total > 0 ? round(($comments_approved / $comments_total) * 100) : 0;
$visible_rate  = $posts_total    > 0 ? round(($posts_visible    / $posts_total)    * 100) : 0;

// ── Top Posts ──
$top_share_expr = column_exists($conn, 'posts', 'share_count')
    ? 'COALESCE(p.share_count,0)'
    : (table_exists($conn, 'post_shares') ? '(SELECT COUNT(*) FROM post_shares ps WHERE ps.post_id=p.id)' : '0');

$top_posts = [];
if (table_exists($conn, 'posts')) {
    $res = $conn->query("SELECT p.id, p.title,
        COALESCE(p.view_count,0) as views,
        (SELECT COUNT(*) FROM likes l WHERE l.post_id=p.id) as likes,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id AND COALESCE(c.approved,1)=1) as comments,
        {$top_share_expr} as shares, p.created_at
        FROM posts p
        ORDER BY (COALESCE(p.view_count,0)+(SELECT COUNT(*) FROM likes l WHERE l.post_id=p.id)
          +(SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id AND COALESCE(c.approved,1)=1)
          +{$top_share_expr}) DESC, p.created_at DESC LIMIT 5");
    if ($res) while ($row = $res->fetch_assoc()) $top_posts[] = $row;
}

// ── Top Events ──
$top_events = [];
if (table_exists($conn, 'events')) {
    $ev = column_exists($conn,'events','view_count')    ? 'COALESCE(e.view_count,0)'    : '0';
    $el = column_exists($conn,'events','like_count')    ? 'COALESCE(e.like_count,0)'    : '0';
    $ec = column_exists($conn,'events','comment_count') ? 'COALESCE(e.comment_count,0)' : '0';
    $es = column_exists($conn,'events','share_count')   ? 'COALESCE(e.share_count,0)'   : '0';
    $res = $conn->query("SELECT e.id, e.title, e.event_date,
        {$ev} as views, {$el} as likes, {$ec} as comments, {$es} as shares
        FROM events e ORDER BY ({$ev}+{$el}+{$ec}+{$es}) DESC, e.event_date DESC LIMIT 5");
    if ($res) while ($row = $res->fetch_assoc()) $top_events[] = $row;
}

[$user_labels, $user_counts] = get_monthly_counts($conn, 'users', 'created_at', 6);
[$post_labels, $post_counts] = get_monthly_counts($conn, 'posts', 'created_at', 6);
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

  --max-w:    1360px;
  --gutter:   1.5rem;
  --radius:   8px;
  --shadow:   0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── page shell ─── */
.an-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO
═══════════════════════════════════════════ */
.an-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}
.an-hero::before {
  content: 'Stats.';
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
.an-hero::after {
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
   BODY
═══════════════════════════════════════════ */
.an-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
}

/* ── Section label ── */
.section-label {
  display: flex;
  align-items: center;
  gap: .75rem;
  margin: 2.25rem 0 1rem;
}
.section-label::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--rule);
}
.section-label-text {
  font-size: .62rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  white-space: nowrap;
}

/* ── Stat cards grid ── */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}
@media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 540px)  { .stat-grid { grid-template-columns: 1fr; } }

.stat-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius);
  padding: 1.3rem 1.4rem;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
  animation: rowIn .35s ease both;
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
}
.stat-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}
@keyframes rowIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}
.stat-card::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px; border-radius: 0 2px 2px 0;
}
.stat-card.sky::before    { background: var(--sky); }
.stat-card.green::before  { background: var(--green); }
.stat-card.amber::before  { background: var(--amber); }
.stat-card.purple::before { background: var(--purple); }
.stat-card.accent::before { background: var(--accent); }
.stat-card.red::before    { background: var(--red); }

.stat-icon {
  width: 36px; height: 36px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; margin-bottom: .75rem;
}
.stat-icon.sky    { background: var(--sky-dim); }
.stat-icon.green  { background: var(--green-dim); }
.stat-icon.amber  { background: var(--amber-dim); }
.stat-icon.purple { background: var(--purple-dim); }
.stat-icon.accent { background: var(--accent-dim); }
.stat-icon.red    { background: var(--red-dim); }

.stat-label {
  font-size: .65rem; font-weight: 600; letter-spacing: .12em;
  text-transform: uppercase; color: var(--ink-light); margin-bottom: .4rem;
}
.stat-value {
  font-family: var(--font-serif);
  font-size: 2.1rem; font-weight: 700;
  color: var(--ink); line-height: 1; margin-bottom: .35rem;
}
.stat-meta { font-size: .78rem; color: var(--ink-light); }
.stat-meta strong { color: var(--ink-mid); font-weight: 600; }

/* ── Section card (chart / table container) ── */
.an-section {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}

.an-section-head {
  display: flex;
  align-items: center;
  gap: .7rem;
  padding: 1rem 1.4rem;
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
}
.an-section-head-icon {
  font-size: 1rem;
}
.an-section-title {
  font-family: var(--font-serif);
  font-size: 1.1rem; font-weight: 700; color: var(--ink);
}
.an-section-body { padding: 1.4rem; }

/* ── Info grid inside sections ── */
.info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .75rem;
  margin-top: 1.1rem;
}
@media (max-width: 860px) { .info-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 540px) { .info-grid { grid-template-columns: 1fr; } }

.info-item {
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  padding: .9rem 1rem;
  background: var(--bg);
  transition: border-color var(--transition);
}
.info-item:hover { border-color: var(--rule); }
.info-item-value {
  font-family: var(--font-serif);
  font-size: 1.3rem; font-weight: 700; color: var(--ink); display: block; margin-bottom: .2rem;
}
.info-item-label { font-size: .78rem; color: var(--ink-light); }

/* ── Bar chart ── */
.chart-wrap {
  background: var(--bg);
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  padding: 1rem 1rem .6rem;
}
.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: .5rem;
  height: 160px;
}
.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  gap: .3rem;
  height: 100%;
}
.bar-fill {
  width: 100%;
  height: var(--h, 4%);
  border-radius: 5px 5px 2px 2px;
  background: var(--sky);
  transition: height .25s ease;
}
.bar-fill.green  { background: var(--green); }
.bar-fill.amber  { background: var(--amber); }
.bar-val   { font-size: .7rem; color: var(--ink-mid); font-weight: 600; }
.bar-label { font-size: .6rem; color: var(--ink-light); text-align: center; line-height: 1.3; }

/* ── SVG line chart ── */
.line-wrap {
  background: var(--bg);
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  padding: .8rem .8rem .4rem;
}
.line-svg { width: 100%; height: 160px; }
.line-legend {
  display: flex;
  justify-content: space-between;
  font-size: .62rem;
  color: var(--ink-light);
  margin-top: .5rem;
  padding: 0 .2rem;
}

/* ── Progress bars ── */
.progress-row { margin-bottom: 1.1rem; }
.progress-label {
  display: flex;
  justify-content: space-between;
  font-size: .82rem;
  color: var(--ink-mid);
  margin-bottom: .35rem;
}
.progress-label span:last-child {
  font-weight: 600;
  font-family: var(--font-serif);
  font-size: .95rem;
  color: var(--ink);
}
.progress-track {
  height: 8px; border-radius: 99px;
  background: var(--rule-light); overflow: hidden;
}
.progress-bar {
  height: 100%; border-radius: 99px;
  background: var(--sky); transition: width .4s ease;
}
.progress-bar.green  { background: var(--green); }
.progress-bar.amber  { background: var(--amber); }
.progress-bar.accent { background: var(--accent); }

/* ── Table ── */
.an-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .85rem;
}
.an-table thead tr {
  border-bottom: 2px solid var(--rule);
}
.an-table th {
  text-align: left;
  padding: .65rem .8rem;
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
}
.an-table td {
  padding: .75rem .8rem;
  border-bottom: 1px solid var(--rule-light);
  color: var(--ink-mid);
  vertical-align: middle;
}
.an-table tbody tr:hover td { background: var(--bg); }
.an-table tbody tr:last-child td { border-bottom: none; }
.an-table .td-title { color: var(--ink); font-weight: 500; }
.an-table .td-num { font-family: var(--font-serif); font-size: 1rem; color: var(--ink); }

.chip {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .68rem; font-weight: 600; padding: .2rem .55rem;
  border-radius: 2px; letter-spacing: .04em;
}
.chip-views   { background: var(--sky-dim);    color: var(--sky); }
.chip-likes   { background: var(--red-dim);    color: var(--red); }
.chip-comments{ background: var(--amber-dim);  color: var(--amber); }
.chip-shares  { background: var(--green-dim);  color: var(--green); }

/* ── Two-col layout for some sections ── */
.two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 860px) { .two-col { grid-template-columns: 1fr; } }

/* ── Empty state ── */
.an-empty {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--ink-light);
}
.an-empty span { font-size: 2rem; display: block; opacity: .35; margin-bottom: .75rem; }
.an-empty p { font-size: .875rem; }
</style>

<div class="an-page">

  <!-- ══════════ HERO ══════════ -->
  <div class="an-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="live-dot"></span> Super Admin</span>
      <h1 class="hero-headline">Platform<br><em>Analytics</em></h1>
      <p class="hero-sub">Live performance metrics, engagement trends, and content health — all in one place.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num"><?php echo number_format($users_total); ?></span>
          <span class="hero-stat-label">Users</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?php echo number_format($posts_total); ?></span>
          <span class="hero-stat-label">Posts</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?php echo number_format($views_total); ?></span>
          <span class="hero-stat-label">Views</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num"><?php echo number_format($likes_total); ?></span>
          <span class="hero-stat-label">Likes</span>
        </div>
      </div>
    </div>
  </div>

  <div class="an-body">

    <!-- ═══ PRIMARY STATS ═══ -->
    <div class="section-label"><span class="section-label-text">Core Metrics</span></div>
    <div class="stat-grid">
      <?php
      $cards = [
        ['sky',    '👥', 'Total Users',    $users_total,     $users_30d,     'new users in 30 days'],
        ['green',  '📝', 'Total Posts',    $posts_total,     $posts_30d,     'posts in 30 days'],
        ['accent', '👁', 'Total Views',    $views_total,     null,           'all-time post views'],
        ['amber',  '❤️', 'Total Likes',    $likes_total,     $likes_30d,     'likes in 30 days'],
        ['purple', '💬', 'Total Comments', $comments_total,  $comments_30d,  'comments in 30 days'],
        ['sky',    '🔖', 'Bookmarks',      $bookmarks_total, $bookmarks_30d, 'saved in 30 days'],
        ['green',  '🗓', 'Events',         $events_total,    $events_30d,    'new in 30 days'],
        ['accent', '🎓', 'Opportunities',  $opps_total,      $opps_30d,      'new in 30 days'],
      ];
      foreach ($cards as $i => [$color, $icon, $label, $value, $recent, $meta]):
      ?>
        <div class="stat-card <?php echo $color; ?>" style="animation-delay: <?php echo $i * 40; ?>ms">
          <div class="stat-icon <?php echo $color; ?>"><?php echo $icon; ?></div>
          <div class="stat-label"><?php echo htmlspecialchars($label); ?></div>
          <div class="stat-value"><?php echo number_format($value); ?></div>
          <div class="stat-meta">
            <?php if ($recent !== null): ?>
              <strong><?php echo number_format($recent); ?></strong> <?php echo htmlspecialchars($meta); ?>
            <?php else: ?>
              <?php echo htmlspecialchars($meta); ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ═══ USER GROWTH ═══ -->
    <div class="section-label" style="margin-top:2.5rem"><span class="section-label-text">User Activity</span></div>
    <div class="an-section">
      <div class="an-section-head">
        <span class="an-section-head-icon">👥</span>
        <span class="an-section-title">New Users by Month</span>
      </div>
      <div class="an-section-body">
        <div class="chart-wrap">
          <div class="bar-chart">
            <?php
              $umax = max(array_merge($user_counts, [1]));
              foreach ($user_counts as $i => $val):
                $h = max(4, round(($val / $umax) * 100));
            ?>
              <div class="bar-col" style="animation-delay: <?php echo $i * 60; ?>ms">
                <div class="bar-val"><?php echo number_format($val); ?></div>
                <div class="bar-fill sky" style="--h: <?php echo $h; ?>%"></div>
                <div class="bar-label"><?php echo htmlspecialchars($user_labels[$i]); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-item-value"><?php echo number_format($users_total); ?></span>
            <span class="info-item-label">Total registrations (all-time)</span>
          </div>
          <div class="info-item">
            <span class="info-item-value"><?php echo number_format($users_30d); ?></span>
            <span class="info-item-label">New users in the last 30 days</span>
          </div>
          <div class="info-item">
            <span class="info-item-value"><?php echo number_format($bookmarks_total); ?></span>
            <span class="info-item-label">All-time bookmarks saved</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ POST TRENDS ═══ -->
    <div class="section-label"><span class="section-label-text">Post Trends</span></div>
    <div class="an-section">
      <div class="an-section-head">
        <span class="an-section-head-icon">📝</span>
        <span class="an-section-title">Posts Published by Month</span>
      </div>
      <div class="an-section-body">
        <?php
          $pmax = max(array_merge($post_counts, [1]));
          $w = 560; $h = 130; $pad = 14;
          $n = max(1, count($post_counts));
          $pts = [];
          for ($i = 0; $i < $n; $i++) {
            $x = $pad + ($n === 1 ? 0 : ($i * ($w - 2*$pad) / ($n - 1)));
            $y = $h - $pad - ($post_counts[$i] / $pmax) * ($h - 2*$pad);
            $pts[] = round($x,2) . ',' . round($y,2);
          }
          $areaPoints = $pts;
          $areaPoints[] = round($w - $pad, 2) . ',' . ($h - $pad);
          $areaPoints[] = $pad . ',' . ($h - $pad);
        ?>
        <div class="line-wrap">
          <svg class="line-svg" viewBox="0 0 <?php echo $w; ?> <?php echo $h; ?>" preserveAspectRatio="none">
            <defs>
              <linearGradient id="lineGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--green)" stop-opacity=".25"/>
                <stop offset="100%" stop-color="var(--green)" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <polygon points="<?php echo implode(' ', $areaPoints); ?>" fill="url(#lineGrad)"/>
            <polyline points="<?php echo implode(' ', $pts); ?>"
              fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linejoin="round"/>
            <?php foreach ($pts as $j => $pt):
              [$cx, $cy] = array_map('floatval', explode(',', $pt));
            ?>
              <circle cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="4"
                fill="#fff" stroke="var(--green)" stroke-width="2"/>
            <?php endforeach; ?>
          </svg>
          <div class="line-legend">
            <?php foreach ($post_labels as $lbl): ?>
              <span><?php echo htmlspecialchars($lbl); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-item-value"><?php echo number_format($posts_total); ?></span>
            <span class="info-item-label">Total posts (all-time)</span>
          </div>
          <div class="info-item">
            <span class="info-item-value"><?php echo number_format($views_total); ?></span>
            <span class="info-item-label">All-time post views</span>
          </div>
          <div class="info-item">
            <span class="info-item-value"><?php echo number_format($likes_total); ?></span>
            <span class="info-item-label">All-time post likes</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ ENGAGEMENT (30 DAYS) ═══ -->
    <div class="section-label"><span class="section-label-text">Engagement — Last 30 Days</span></div>
    <div class="an-section">
      <div class="an-section-head">
        <span class="an-section-head-icon">⚡</span>
        <span class="an-section-title">Recent Activity</span>
      </div>
      <div class="an-section-body">
        <?php
          $eng_items = [
            ['Bookmarks',       $bookmarks_30d,  $bookmarks_total,  'sky'],
            ['Likes',           $likes_30d,      $likes_total,      'red'],
            ['Reactions',       $reactions_30d,  $reactions_total,  'amber'],
            ['Marketplace',     $market_30d,     $market_total,     'green'],
            ['Opportunities',   $opps_30d,       $opps_total,       'accent'],
            ['Events',          $events_30d,     $events_total,     'purple'],
            ['SafeSpeak Rpts',  $safe_30d,       $safe_total,       'sky'],
            ['Comments',        $comments_30d,   $comments_total,   'amber'],
          ];
        ?>
        <div class="chart-wrap">
          <div class="bar-chart">
            <?php
              $eng_counts = array_column($eng_items, 1);
              $emax = max(array_merge($eng_counts, [1]));
              foreach ($eng_items as $i => [$elabel, $e30d, $etot, $ecolor]):
                $eh = max(4, round(($e30d / $emax) * 100));
            ?>
              <div class="bar-col">
                <div class="bar-val"><?php echo number_format($e30d); ?></div>
                <div class="bar-fill <?php echo $ecolor; ?>" style="--h: <?php echo $eh; ?>%"></div>
                <div class="bar-label"><?php echo htmlspecialchars($elabel); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="info-grid" style="margin-top:1rem">
          <?php foreach ($eng_items as [$elabel, $e30d, $etot, $ecolor]): ?>
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($e30d); ?></span>
              <span class="info-item-label"><?php echo htmlspecialchars($elabel); ?> in 30 days
                (<?php echo number_format($etot); ?> total)</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ═══ CONTENT HEALTH ═══ -->
    <div class="section-label"><span class="section-label-text">Content Health</span></div>
    <div class="two-col">

      <div class="an-section">
        <div class="an-section-head">
          <span class="an-section-head-icon">📊</span>
          <span class="an-section-title">Moderation Rates</span>
        </div>
        <div class="an-section-body">
          <div class="progress-row">
            <div class="progress-label">
              <span>Comment approval rate</span>
              <span><?php echo $approval_rate; ?>%</span>
            </div>
            <div class="progress-track">
              <div class="progress-bar green" style="width: <?php echo $approval_rate; ?>%"></div>
            </div>
          </div>
          <div class="progress-row">
            <div class="progress-label">
              <span>Visible posts</span>
              <span><?php echo $visible_rate; ?>%</span>
            </div>
            <div class="progress-track">
              <div class="progress-bar sky" style="width: <?php echo $visible_rate; ?>%"></div>
            </div>
          </div>
          <div class="info-grid" style="grid-template-columns:1fr 1fr; margin-top:.85rem">
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($comments_approved); ?></span>
              <span class="info-item-label">Approved comments</span>
            </div>
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($comments_pending); ?></span>
              <span class="info-item-label">Pending moderation</span>
            </div>
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($posts_visible); ?></span>
              <span class="info-item-label">Visible posts</span>
            </div>
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format(max(0, $posts_total - $posts_visible)); ?></span>
              <span class="info-item-label">Hidden / draft</span>
            </div>
          </div>
        </div>
      </div>

      <div class="an-section">
        <div class="an-section-head">
          <span class="an-section-head-icon">🛡</span>
          <span class="an-section-title">SafeSpeak & Safety</span>
        </div>
        <div class="an-section-body">
          <div class="progress-row">
            <div class="progress-label">
              <span>Reports this month</span>
              <span><?php echo $safe_30d; ?></span>
            </div>
            <div class="progress-track">
              <?php $sr = $safe_total > 0 ? round(($safe_30d / max($safe_total, 1)) * 100) : 0; ?>
              <div class="progress-bar accent" style="width: <?php echo min(100, $sr); ?>%"></div>
            </div>
          </div>
          <div class="info-grid" style="grid-template-columns:1fr 1fr; margin-top:.85rem">
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($safe_total); ?></span>
              <span class="info-item-label">Total reports submitted</span>
            </div>
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($safe_30d); ?></span>
              <span class="info-item-label">Reports in last 30 days</span>
            </div>
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($reactions_total); ?></span>
              <span class="info-item-label">Total reactions</span>
            </div>
            <div class="info-item">
              <span class="info-item-value"><?php echo number_format($reactions_30d); ?></span>
              <span class="info-item-label">Reactions in 30 days</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TOP POSTS ═══ -->
    <div class="section-label"><span class="section-label-text">Top Content</span></div>
    <div class="an-section">
      <div class="an-section-head">
        <span class="an-section-head-icon">🏆</span>
        <span class="an-section-title">Top Posts by Engagement</span>
      </div>
      <div class="an-section-body">
        <?php if (!empty($top_posts)): ?>
          <div style="overflow-x:auto">
            <table class="an-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Post</th>
                  <th>Views</th>
                  <th>Likes</th>
                  <th>Comments</th>
                  <th>Shares</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($top_posts as $rank => $row): ?>
                  <tr>
                    <td style="color:var(--ink-light); font-weight:600"><?php echo $rank + 1; ?></td>
                    <td class="td-title"><?php echo htmlspecialchars($row['title'] ?? 'Untitled'); ?></td>
                    <td><span class="chip chip-views"><?php echo number_format((int)($row['views']??0)); ?></span></td>
                    <td><span class="chip chip-likes"><?php echo number_format((int)($row['likes']??0)); ?></span></td>
                    <td><span class="chip chip-comments"><?php echo number_format((int)($row['comments']??0)); ?></span></td>
                    <td><span class="chip chip-shares"><?php echo number_format((int)($row['shares']??0)); ?></span></td>
                    <td style="white-space:nowrap"><?php echo !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '—'; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="an-empty"><span>📭</span><p>No posts found yet.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ═══ TOP EVENTS ═══ -->
    <div class="an-section" style="margin-top:1rem">
      <div class="an-section-head">
        <span class="an-section-head-icon">🗓</span>
        <span class="an-section-title">Top Events by Engagement</span>
      </div>
      <div class="an-section-body">
        <?php if (!empty($top_events)): ?>
          <div style="overflow-x:auto">
            <table class="an-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Event</th>
                  <th>Views</th>
                  <th>Likes</th>
                  <th>Comments</th>
                  <th>Shares</th>
                  <th>Event Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($top_events as $rank => $row): ?>
                  <tr>
                    <td style="color:var(--ink-light); font-weight:600"><?php echo $rank + 1; ?></td>
                    <td class="td-title"><?php echo htmlspecialchars($row['title'] ?? 'Untitled'); ?></td>
                    <td><span class="chip chip-views"><?php echo number_format((int)($row['views']??0)); ?></span></td>
                    <td><span class="chip chip-likes"><?php echo number_format((int)($row['likes']??0)); ?></span></td>
                    <td><span class="chip chip-comments"><?php echo number_format((int)($row['comments']??0)); ?></span></td>
                    <td><span class="chip chip-shares"><?php echo number_format((int)($row['shares']??0)); ?></span></td>
                    <td style="white-space:nowrap"><?php echo !empty($row['event_date']) ? date('M d, Y', strtotime($row['event_date'])) : '—'; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="an-empty"><span>📭</span><p>No events found yet.</p></div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.an-body -->
</div><!-- /.an-page -->

<?php include '../admin/includes/footer.php'; ?>