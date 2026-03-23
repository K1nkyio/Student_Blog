<?php
/**
 * Blog System — Explore Page
 * Paginated post listing with filtering, search, bookmarks, likes & share
 */

session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

$user_ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

// ── Pagination & filter setup ──
$posts_per_page  = 9;
$page_num    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset          = ($page_num - 1) * $posts_per_page;

$search          = isset($_GET['search'])   ? trim($_GET['search'])       : '';
$tag_filter      = isset($_GET['tag'])      ? (int)$_GET['tag']           : 0;
$category_filter = isset($_GET['category']) ? (int)$_GET['category']      : 0;
$author_filter   = isset($_GET['author'])   ? (int)$_GET['author']        : 0;
$sort            = isset($_GET['sort'])     ? $_GET['sort']               : 'newest';

$valid_sorts = ['newest', 'oldest', 'comments'];
if (!in_array($sort, $valid_sorts)) $sort = 'newest';

// ── Build WHERE ──
$where_clauses = ["COALESCE(p.visible, 1) = 1", "COALESCE(p.review_status, 'approved') = 'approved'"];
$params = []; $types = "";

if (!empty($search)) {
    $where_clauses[] = "(p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)";
    $sp = "%$search%"; $params[] = $sp; $params[] = $sp; $params[] = $sp; $types .= "sss";
}
if ($tag_filter > 0)      { $where_clauses[] = "EXISTS (SELECT 1 FROM post_tags WHERE post_id = p.id AND tag_id = ?)"; $params[] = $tag_filter;      $types .= "i"; }
if ($category_filter > 0) { $where_clauses[] = "p.category_id = ?";  $params[] = $category_filter; $types .= "i"; }
if ($author_filter > 0)   { $where_clauses[] = "p.author_id = ?";    $params[] = $author_filter;   $types .= "i"; }

$where_sql = implode(" AND ", $where_clauses);

switch ($sort) {
    case 'oldest':   $order_sql = "p.created_at ASC"; break;
    case 'comments': $order_sql = "comment_count DESC, p.created_at DESC"; break;
    default:         $order_sql = "p.created_at DESC";
}

// ── Count ──
$count_stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as total FROM posts p WHERE $where_sql");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_posts = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_posts / $posts_per_page));
$count_stmt->close();

// ── Fetch posts ──
$sql = "SELECT p.id, p.title, p.content, p.excerpt, p.image, p.slug, p.created_at, p.view_count,
        u.id as author_id, u.username as author_name, u.avatar as author_avatar,
        c.id as category_id, c.name as category_name, c.slug as category_slug,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND approved = 1) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND ip_address = ?) as liked_by_ip
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $where_sql ORDER BY $order_sql LIMIT ? OFFSET ?";

$sp2 = $params; $st2 = $types;
array_unshift($sp2, $user_ip); $st2 = 's' . $st2;
$sp2[] = $posts_per_page; $sp2[] = $offset; $st2 .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($sp2)) $stmt->bind_param($st2, ...$sp2);
$stmt->execute();
$posts = $stmt->get_result();

// ── Sidebar data ──
$categories = $conn->query("SELECT id, name, slug FROM categories ORDER BY name");
$tags = $conn->query("SELECT t.id, t.name, t.slug, COUNT(p.id) as post_count
    FROM tags t
    LEFT JOIN post_tags pt ON t.id = pt.tag_id
    LEFT JOIN posts p ON pt.post_id = p.id
      AND COALESCE(p.visible, 1) = 1
      AND COALESCE(p.review_status, 'approved') = 'approved'
    GROUP BY t.id, t.name, t.slug ORDER BY post_count DESC LIMIT 20");
$authors = $conn->query("SELECT u.id, u.username, COUNT(p.id) as post_count
    FROM users u INNER JOIN posts p ON u.id = p.author_id
    WHERE COALESCE(p.visible, 1) = 1 AND COALESCE(p.review_status, 'approved') = 'approved'
    GROUP BY u.id, u.username ORDER BY post_count DESC");

// ── Session / bookmarks ──
$is_logged_in    = is_logged_in();
$user_id         = get_user_id();
$bookmarked_posts = [];
if ($is_logged_in) {
    $bm = $conn->prepare("SELECT post_id FROM bookmarks WHERE user_id = ?");
    $bm->bind_param("i", $user_id);
    $bm->execute();
    $bmr = $bm->get_result();
    while ($row = $bmr->fetch_assoc()) $bookmarked_posts[] = $row['post_id'];
    $bm->close();
}

$csrf_token      = generate_csrf_token();
$page_title      = 'Explore — ' . SITE_NAME;
$meta_description = 'Explore the latest stories, insights, and student conversations on ' . SITE_NAME;

include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS — exact match opportunities.php
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
  --max-w: 1360px;
  --gutter: 1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  line-height: 1.65;
  -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
img { display: block; max-width: 100%; }
button { font-family: inherit; }

/* ─── page shell ─── */
.ex-page { min-height: 100vh; padding-bottom: 6rem; }

/* ═══════════════════════════════════════════
   HERO — same dark-ink strip as opp-hero
═══════════════════════════════════════════ */
.ex-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

/* watermark */
.ex-hero::before {
  content: 'Explore.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(5rem, 14vw, 11rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.03);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

/* dot grid */
.ex-hero::after {
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
.live-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  box-shadow: 0 0 6px var(--accent);
  animation: blink 2s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

.hero-headline {
  font-family: var(--font-serif);
  font-size: clamp(2.2rem, 5vw, 3.8rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -.015em;
  color: #fff;
  margin-bottom: .75rem;
}
.hero-headline em { font-style: italic; color: rgba(255,255,255,.42); }

.hero-sub {
  font-size: .95rem;
  color: rgba(255,255,255,.42);
  font-weight: 300;
  max-width: 42ch;
  line-height: 1.75;
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
  font-size: .62rem;
  font-weight: 500;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.32);
  display: block;
  margin-top: .22rem;
}

/* ═══════════════════════════════════════════
   STICKY TOOLBAR — mirrors .opp-toolbar
═══════════════════════════════════════════ */
.ex-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.ex-toolbar-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .85rem var(--gutter);
  display: flex;
  gap: .65rem;
  align-items: center;
  flex-wrap: wrap;
}

/* search — mirrors .search-wrap from opp */
.ex-search {
  flex: 1;
  min-width: 200px;
  display: flex;
  align-items: center;
  gap: .55rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .55rem .9rem;
  background: var(--bg);
  transition: border-color var(--transition);
}
.ex-search:focus-within { border-color: var(--ink); background: #fff; }
.ex-search svg { color: var(--ink-light); flex-shrink: 0; width: 14px; height: 14px; }
.ex-search input {
  border: none; outline: none; flex: 1;
  font-size: .875rem; font-family: var(--font-sans);
  color: var(--ink); background: transparent;
}
.ex-search input::placeholder { color: var(--ink-light); }

/* filter selects */
.ex-select {
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .55rem .9rem;
  font-size: .82rem;
  font-family: var(--font-sans);
  color: var(--ink-mid);
  background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237a7570'/%3E%3C/svg%3E") no-repeat right .9rem center;
  background-size: 8px;
  padding-right: 2.25rem;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  transition: border-color var(--transition);
  white-space: nowrap;
}
.ex-select:focus { outline: none; border-color: var(--ink); }

/* search submit btn */
.ex-search-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  border: none;
  border-radius: 3px;
  padding: .57rem 1.1rem;
  background: var(--ink);
  color: #fff;
  font-family: var(--font-sans);
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
}
.ex-search-btn:hover { background: #2c2a22; }
.ex-search-btn svg { width: 13px; height: 13px; }

/* sort tabs — same pill shape as filter-chip */
.ex-sort-wrap { display: flex; gap: .35rem; flex-shrink: 0; }
.ex-sort-tab {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  border: 1.5px solid var(--rule);
  border-radius: 99px;
  padding: .3rem .8rem;
  font-size: .75rem;
  font-weight: 500;
  color: var(--ink-mid);
  text-decoration: none;
  transition: all var(--transition);
  white-space: nowrap;
}
.ex-sort-tab:hover { border-color: var(--ink-mid); color: var(--ink); }
.ex-sort-tab.active { background: var(--ink); color: #fff; border-color: var(--ink); font-weight: 600; }

/* ── filter panel ── */
.ex-filter-panel {
  display: none;
  border-bottom: 1px solid var(--rule);
  background: var(--bg-warm);
}
.ex-filter-panel.open { display: block; animation: panelIn .18s ease; }
@keyframes panelIn { from{opacity:0;transform:translateY(-5px)} to{opacity:1;transform:translateY(0)} }

.ex-filter-panel-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .9rem var(--gutter);
  display: flex;
  flex-wrap: wrap;
  gap: .45rem;
  align-items: center;
}
.filter-strip-label {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-right: .25rem;
}

/* tag chips — mirrors .filter-chip */
.tag-chip {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  border: 1.5px solid var(--rule);
  border-radius: 99px;
  padding: .26rem .75rem;
  font-size: .75rem;
  font-weight: 500;
  color: var(--ink-mid);
  text-decoration: none;
  transition: all var(--transition);
  background: var(--bg);
}
.tag-chip:hover { border-color: var(--ink-mid); color: var(--ink); }
.tag-chip.active { background: var(--ink); color: #fff; border-color: var(--ink); }
.tag-chip-count { font-size: .65rem; opacity: .65; }

/* active filter pills */
.active-pills {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .6rem var(--gutter);
  border-bottom: 1px solid var(--rule-light);
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
  font-size: .75rem;
  color: var(--ink-light);
  background: var(--bg-warm);
}
.active-pill {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  background: var(--accent-dim);
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.2);
  border-radius: 2px;
  padding: .18rem .6rem;
  font-size: .7rem;
  font-weight: 600;
  letter-spacing: .04em;
}
.clear-all-link {
  font-size: .75rem;
  color: var(--ink-light);
  text-decoration: underline;
  text-underline-offset: 2px;
  transition: color var(--transition);
  margin-left: .25rem;
}
.clear-all-link:hover { color: var(--accent); }

/* ═══════════════════════════════════════════
   BODY LAYOUT
═══════════════════════════════════════════ */
.ex-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2rem var(--gutter) 0;
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 2.5rem;
  align-items: start;
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

/* ═══════════════════════════════════════════
   POSTS GRID
═══════════════════════════════════════════ */
.posts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}

/* ── Post card — white card, same shadow + border as opp-row ── */
.post-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  position: relative;
  animation: cardIn .4s ease both;
}
.post-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-3px);
}
/* stagger */
<?php for ($i = 1; $i <= 9; $i++): ?>
.posts-grid .post-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i - 1) * 0.045 ?>s; }
<?php endfor; ?>

@keyframes cardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* image area */
.card-media {
  position: relative;
  aspect-ratio: 16/9;
  overflow: hidden;
  background: var(--bg-warm);
  flex-shrink: 0;
}
.card-media img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform .5s ease;
}
.post-card:hover .card-media img { transform: scale(1.04); }

.card-no-img {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--bg-warm), var(--bg-warmer));
}
.card-no-img svg { width: 28px; height: 28px; color: var(--rule); }

/* category badge — same as .badge from opp */
.card-cat {
  position: absolute;
  top: 10px; left: 10px;
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  padding: .2rem .65rem;
  border-radius: 2px;
  background: var(--ink);
  color: #fff;
  text-decoration: none;
  transition: background var(--transition);
}
.card-cat:hover { background: var(--accent); }

/* hover actions */
.card-actions {
  position: absolute;
  top: 10px; right: 10px;
  display: flex;
  gap: .3rem;
  opacity: 0;
  transform: translateY(-4px);
  transition: opacity var(--transition), transform var(--transition);
}
.post-card:hover .card-actions { opacity: 1; transform: translateY(0); }

.card-action-btn {
  width: 30px; height: 30px;
  border-radius: 3px;
  border: 1px solid rgba(255,255,255,.5);
  background: rgba(255,255,255,.88);
  backdrop-filter: blur(6px);
  color: var(--ink-mid);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition);
  box-shadow: 0 1px 4px rgba(24,22,15,.12);
}
.card-action-btn:hover { background: #fff; color: var(--accent); }
.card-action-btn.is-bookmarked { background: var(--accent); color: #fff; border-color: var(--accent); }
.card-action-btn svg { width: 13px; height: 13px; }

/* card body */
.card-body {
  padding: 1.1rem 1.2rem 1.25rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: .5rem;
}

/* byline */
.card-byline {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .75rem;
  color: var(--ink-light);
}
.card-avatar {
  width: 22px; height: 22px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid var(--rule-light);
}
.card-avatar-placeholder {
  width: 22px; height: 22px;
  border-radius: 50%;
  background: var(--bg-warm);
  border: 1px solid var(--rule-light);
  flex-shrink: 0;
}
.card-author {
  font-weight: 500;
  color: var(--ink-mid);
  transition: color var(--transition);
}
.card-author:hover { color: var(--accent); }
.card-sep { color: var(--rule); font-size: .65rem; }

/* title */
.card-title {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: -.01em;
  color: var(--ink);
}
.card-title a { transition: color var(--transition); }
.card-title a:hover { color: var(--sky); }

/* excerpt */
.card-excerpt {
  font-size: .845rem;
  color: var(--ink-mid);
  line-height: 1.6;
  text-align: justify;
  text-align-last: left;
  hyphens: auto;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* card footer */
.card-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: .85rem;
  margin-top: auto;
  border-top: 1px solid var(--rule-light);
}

.card-stats { display: flex; gap: .75rem; font-size: .75rem; color: var(--ink-light); align-items: center; }
.card-stat  { display: flex; align-items: center; gap: .25rem; }
.card-stat svg { width: 12px; height: 12px; }

.like-btn {
  border: none; background: none; padding: 0;
  color: var(--ink-light); cursor: pointer;
  display: flex; align-items: center; gap: .25rem;
  font-size: .75rem; font-family: var(--font-sans);
  transition: color var(--transition);
}
.like-btn:hover { color: var(--accent); }
.like-btn.is-liked { color: var(--accent); }
.like-btn svg { width: 12px; height: 12px; }

/* "Read" link — same as .btn-ghost small */
.card-read {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: .75rem;
  font-weight: 600;
  color: var(--ink-mid);
  border: 1.5px solid var(--rule);
  padding: .3rem .75rem;
  border-radius: 3px;
  transition: all var(--transition);
  flex-shrink: 0;
}
.card-read:hover { border-color: var(--ink); color: var(--ink); background: var(--bg-warm); }
.card-read svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════ */
.ex-empty {
  grid-column: 1/-1;
  text-align: center;
  padding: 5rem 2rem;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  background: #fff;
  color: var(--ink-light);
}
.ex-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .35; }
.ex-empty h3 { font-family: var(--font-serif); font-size: 1.4rem; color: var(--ink-mid); margin-bottom: .4rem; }
.ex-empty p  { font-size: .875rem; margin-bottom: 1.5rem; }
.ex-empty-link {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .65rem 1.4rem;
  background: var(--ink);
  color: #fff;
  border-radius: 3px;
  font-size: .845rem;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--transition);
}
.ex-empty-link:hover { background: #2c2a22; transform: translateY(-1px); }

/* ═══════════════════════════════════════════
   PAGINATION — matches .opp-pagination
═══════════════════════════════════════════ */
.ex-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  margin-top: 2.5rem;
  border-top: 1px solid var(--rule-light);
  padding-top: 2rem;
  flex-wrap: wrap;
}
.pg-btn {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .52rem 1rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: var(--bg);
  font-size: .8rem;
  font-weight: 500;
  font-family: var(--font-sans);
  color: var(--ink-mid);
  text-decoration: none;
  transition: all var(--transition);
  min-width: 36px;
  justify-content: center;
}
.pg-btn:hover:not(.disabled):not(.dots) { border-color: var(--ink); background: var(--bg-warm); color: var(--ink); }
.pg-btn.active { background: var(--ink); color: #fff; border-color: var(--ink); font-weight: 600; }
.pg-btn.disabled { opacity: .35; pointer-events: none; }
.pg-btn.dots     { pointer-events: none; border-color: transparent; background: transparent; }
.pg-btn svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════ */
.ex-sidebar { display: flex; flex-direction: column; gap: 1.25rem; }

.sb-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
}

.sb-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule-light);
  padding: .85rem 1.2rem;
  display: flex;
  align-items: center;
  gap: .55rem;
}
.sb-head-icon {
  width: 24px; height: 24px;
  border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sb-head-icon svg { width: 12px; height: 12px; }
.sb-title {
  font-family: var(--font-serif);
  font-size: .95rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}

.sb-body { padding: 1.1rem 1.2rem; }

/* subscribe form */
.sb-desc { font-size: .82rem; color: var(--ink-light); line-height: 1.6; margin-bottom: .85rem; }

.sb-input {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .62rem .85rem;
  font-size: .855rem;
  font-family: var(--font-sans);
  color: var(--ink);
  background: var(--bg);
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition);
  margin-bottom: .7rem;
  -webkit-appearance: none;
}
.sb-input::placeholder { color: var(--ink-light); }
.sb-input:focus { border-color: var(--ink); background: #fff; box-shadow: 0 0 0 3px rgba(24,22,15,.06); }

.sb-checks { display: flex; flex-direction: column; gap: .38rem; margin-bottom: .85rem; }
.sb-checks label {
  display: flex; align-items: center; gap: .5rem;
  font-size: .78rem; color: var(--ink-light); cursor: pointer;
}
.sb-checks input[type="checkbox"] { accent-color: var(--accent); }

/* subscribe button */
.sb-btn {
  width: 100%;
  padding: .68rem;
  border: none;
  border-radius: 3px;
  background: var(--ink);
  color: #fff;
  font-family: var(--font-sans);
  font-size: .855rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition);
  display: flex; align-items: center; justify-content: center; gap: .4rem;
}
.sb-btn:hover { background: #2c2a22; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(24,22,15,.2); }
.sb-btn:active { transform: scale(.98); }

/* ghost button */
.sb-btn-ghost {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  width: 100%;
  margin-top: .85rem;
  padding: .58rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: transparent;
  color: var(--ink-mid);
  font-family: var(--font-sans);
  font-size: .82rem;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--transition);
}
.sb-btn-ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); color: var(--ink); }

/* category list */
.cat-list { padding: 0 1.2rem; }
.cat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .55rem 0;
  border-bottom: 1px solid var(--rule-light);
  font-size: .845rem;
  color: var(--ink-mid);
  text-decoration: none;
  transition: all var(--transition);
  gap: .5rem;
}
.cat-item:last-child { border-bottom: none; }
.cat-item:hover { color: var(--accent); padding-left: .35rem; }
.cat-count {
  font-size: .7rem;
  color: var(--ink-light);
  background: var(--bg-warm);
  padding: .1rem .45rem;
  border-radius: 99px;
  flex-shrink: 0;
  border: 1px solid var(--rule-light);
}

/* bookmarks widget */
.sb-bookmark-text { font-size: .82rem; color: var(--ink-light); line-height: 1.6; }

/* empty widget */
.sb-empty { font-size: .82rem; color: var(--ink-light); text-align: center; padding: .75rem 0; line-height: 1.6; }

/* ═══════════════════════════════════════════
   SHARE MODAL
═══════════════════════════════════════════ */
.modal-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(24,22,15,.5);
  backdrop-filter: blur(4px);
  z-index: 9000;
  align-items: center; justify-content: center;
  padding: 1.5rem;
}
.modal-overlay.open { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

.modal-box {
  background: #fff;
  border: 1px solid var(--rule);
  border-radius: 6px;
  max-width: 440px; width: 100%;
  box-shadow: 0 8px 32px rgba(24,22,15,.18), 0 32px 80px rgba(24,22,15,.14);
  animation: modalIn .3s ease;
}
@keyframes modalIn {
  from { opacity: 0; transform: scale(.96) translateY(12px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.1rem 1.4rem;
  border-bottom: 1px solid var(--rule);
  background: var(--bg-warm);
}
.modal-title {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}
.modal-close {
  width: 28px; height: 28px;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: var(--bg);
  color: var(--ink-light);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all var(--transition);
}
.modal-close:hover { border-color: var(--ink); color: var(--ink); background: var(--bg-warm); }
.modal-close svg { width: 12px; height: 12px; }

.modal-body { padding: 1.25rem 1.4rem; }

.share-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .6rem;
}
.share-btn-item {
  display: flex; align-items: center; gap: .5rem;
  padding: .72rem .9rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: transparent;
  font-family: var(--font-sans);
  font-size: .82rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition);
  color: var(--ink-mid);
}
.share-btn-item:hover { transform: translateY(-1px); }
.share-btn-item svg { width: 15px; height: 15px; flex-shrink: 0; }

.s-facebook { color: #1877f2; } .s-facebook:hover { background: rgba(24,119,242,.06); border-color: #1877f2; }
.s-twitter  { color: #1da1f2; } .s-twitter:hover  { background: rgba(29,161,242,.06); border-color: #1da1f2; }
.s-linkedin { color: #0a66c2; } .s-linkedin:hover { background: rgba(10,102,194,.06); border-color: #0a66c2; }
.s-whatsapp { color: #25d366; } .s-whatsapp:hover { background: rgba(37,211,102,.06); border-color: #25d366; }
.s-copy { grid-column: 1/-1; color: var(--ink-mid); }
.s-copy:hover { background: var(--bg-warm); border-color: var(--ink-mid); }

/* ═══════════════════════════════════════════
   TOAST
═══════════════════════════════════════════ */
.toast-dock {
  position: fixed; bottom: 1.5rem; right: 1.5rem;
  z-index: 10000; display: flex; flex-direction: column; gap: .4rem;
  pointer-events: none;
}
.toast {
  background: var(--ink); color: #fff;
  padding: .7rem 1.1rem;
  border-radius: 3px;
  font-size: .82rem;
  font-family: var(--font-sans);
  box-shadow: 0 4px 16px rgba(24,22,15,.18);
  animation: toastIn .28s ease;
  pointer-events: auto;
  max-width: 280px;
}
@keyframes toastIn { from{opacity:0;transform:translateX(12px)} to{opacity:1;transform:translateX(0)} }
.toast-success { background: var(--green); }
.toast-error   { background: var(--red); }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 1080px) {
  .ex-body { grid-template-columns: 1fr; }
  .ex-sidebar { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
}
@media (max-width: 640px) {
  .posts-grid { grid-template-columns: 1fr; }
  .ex-toolbar-inner { flex-wrap: wrap; }
  .ex-sort-wrap { display: none; }
  .toast-dock { right: 1rem; left: 1rem; }
  .toast { max-width: none; }
  .share-grid { grid-template-columns: 1fr; }
  .s-copy { grid-column: auto; }
  .ex-hero::before { display: none; }
}
</style>

<div class="ex-page">

  <!-- ══════ HERO ══════ -->
  <div class="ex-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="live-dot"></span> Community Journal</span>
      <h1 class="hero-headline">Stories Worth<br><em>Reading</em></h1>
      <p class="hero-sub">Discover the latest writing, insights, and conversations from the campus community.</p>
      <div class="hero-stats">
        <div>
          <span class="hero-stat-num"><?= number_format($total_posts) ?></span>
          <span class="hero-stat-label">Posts</span>
        </div>
        <div>
          <span class="hero-stat-num">Page <?= $page_num ?> / <?= $total_pages ?></span>
          <span class="hero-stat-label">Current</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ STICKY TOOLBAR ══════ -->
  <div class="ex-toolbar">
    <form method="GET" action="" id="filterForm">
      <div class="ex-toolbar-inner">
        <!-- search -->
        <div class="ex-search">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="9" cy="9" r="6"/><path d="M15 15l3 3" stroke-linecap="round"/>
          </svg>
          <input type="text" name="search"
                 placeholder="Search posts…"
                 value="<?= htmlspecialchars($search) ?>"
                 autocomplete="off">
        </div>

        <!-- category select -->
        <select name="category" class="ex-select" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
          <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
          </option>
          <?php endwhile; ?>
        </select>

        <!-- author select -->
        <select name="author" class="ex-select" onchange="this.form.submit()">
          <option value="">All Authors</option>
          <?php while ($author = $authors->fetch_assoc()): ?>
          <option value="<?= $author['id'] ?>" <?= $author_filter == $author['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($author['username']) ?> (<?= $author['post_count'] ?>)
          </option>
          <?php endwhile; ?>
        </select>

        <?php if ($tag_filter): ?>
          <input type="hidden" name="tag" value="<?= $tag_filter ?>">
        <?php endif; ?>

        <!-- search button -->
        <button type="submit" class="ex-search-btn">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="9" cy="9" r="6"/><path d="M15 15l3 3" stroke-linecap="round"/>
          </svg>
          Search
        </button>

        <!-- sort tabs -->
        <div class="ex-sort-wrap">
          <?php
          $sl = ['newest' => 'Newest', 'oldest' => 'Oldest', 'comments' => 'Top'];
          foreach ($sl as $val => $label):
            $q = http_build_query(array_filter(['search' => $search, 'category' => $category_filter ?: null,
              'tag' => $tag_filter ?: null, 'author' => $author_filter ?: null, 'sort' => $val]));
          ?>
          <a href="?<?= $q ?>" class="ex-sort-tab <?= $sort === $val ? 'active' : '' ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </form>
  </div>

  <!-- tag cloud panel -->
  <?php if ($tags->num_rows > 0): ?>
  <div class="ex-filter-panel open" id="tagPanel">
    <div class="ex-filter-panel-inner">
      <span class="filter-strip-label">Tags</span>
      <?php $tags->data_seek(0); while ($tag = $tags->fetch_assoc()):
        $q = http_build_query(array_filter(['search' => $search, 'tag' => $tag['id'], 'category' => $category_filter ?: null]));
      ?>
      <a href="?<?= $q ?>" class="tag-chip <?= $tag_filter == $tag['id'] ? 'active' : '' ?>">
        <?= htmlspecialchars($tag['name']) ?>
        <span class="tag-chip-count"><?= $tag['post_count'] ?></span>
      </a>
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- active filter pills -->
  <?php if (!empty($search) || $tag_filter || $category_filter || $author_filter): ?>
  <div class="active-pills">
    <span>Filtered by:</span>
    <?php if (!empty($search)): ?>
      <span class="active-pill">"<?= htmlspecialchars($search) ?>"</span>
    <?php endif; ?>
    <?php if ($category_filter): ?>
      <span class="active-pill">Category #<?= $category_filter ?></span>
    <?php endif; ?>
    <?php if ($tag_filter): ?>
      <span class="active-pill">Tag #<?= $tag_filter ?></span>
    <?php endif; ?>
    <?php if ($author_filter): ?>
      <span class="active-pill">Author #<?= $author_filter ?></span>
    <?php endif; ?>
    <a href="explore.php" class="clear-all-link">Clear all</a>
  </div>
  <?php endif; ?>

  <!-- ══════ BODY ══════ -->
  <div class="ex-body">

    <!-- posts + pagination -->
    <div>
      <div class="results-bar">
        <span><strong><?= number_format($total_posts) ?></strong> post<?= $total_posts !== 1 ? 's' : '' ?> found</span>
        <span>Page <?= $page_num ?> of <?= $total_pages ?></span>
      </div>

      <?php if ($posts->num_rows > 0): ?>
      <div class="posts-grid" data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
        <?php while ($post = $posts->fetch_assoc()):
          $excerpt     = !empty($post['excerpt']) ? $post['excerpt'] : (function_exists('get_excerpt') ? get_excerpt($post['content'], 150) : substr(strip_tags($post['content']), 0, 150) . '…');
          $is_bm       = in_array($post['id'], $bookmarked_posts);
          $is_liked    = !empty($post['liked_by_ip']);
          $post_url    = !empty($post['slug']) ? "post.php?slug=" . urlencode($post['slug']) : "post.php?id=" . $post['id'];
          $full_url    = "https://{$_SERVER['HTTP_HOST']}/{$post_url}";
        ?>
        <article class="post-card" data-post-id="<?= $post['id'] ?>">

          <!-- image -->
          <div class="card-media">
            <?php if (!empty($post['image'])): ?>
              <img src="<?= htmlspecialchars(get_post_image_url($post['image'])) ?>"
                   alt="<?= htmlspecialchars($post['title']) ?>"
                   loading="lazy">
            <?php else: ?>
              <div class="card-no-img">
                <svg fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                  <rect x="3" y="3" width="18" height="18" rx="2"/>
                  <circle cx="8.5" cy="8.5" r="1.5"/>
                  <polyline points="21 15 16 10 5 21"/>
                </svg>
              </div>
            <?php endif; ?>

            <?php if (!empty($post['category_name'])): ?>
            <a href="?category=<?= $post['category_id'] ?>" class="card-cat">
              <?= htmlspecialchars($post['category_name']) ?>
            </a>
            <?php endif; ?>

            <div class="card-actions">
              <?php if ($is_logged_in): ?>
              <button class="card-action-btn bookmark-btn <?= $is_bm ? 'is-bookmarked' : '' ?>"
                      data-post-id="<?= $post['id'] ?>"
                      title="<?= $is_bm ? 'Remove bookmark' : 'Bookmark' ?>">
                <svg fill="<?= $is_bm ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
                </svg>
              </button>
              <?php endif; ?>
              <button class="card-action-btn share-open-btn"
                      data-post-id="<?= $post['id'] ?>"
                      data-post-title="<?= htmlspecialchars($post['title']) ?>"
                      data-post-url="<?= htmlspecialchars($full_url) ?>"
                      title="Share">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- body -->
          <div class="card-body">
            <div class="card-byline">
              <?php if (!empty($post['author_avatar'])): ?>
                <img src="<?= htmlspecialchars($post['author_avatar']) ?>"
                     alt="<?= htmlspecialchars($post['author_name']) ?>"
                     class="card-avatar">
              <?php else: ?>
                <span class="card-avatar-placeholder"></span>
              <?php endif; ?>
              <a href="?author=<?= $post['author_id'] ?>" class="card-author">
                <?= htmlspecialchars($post['author_name'] ?? 'Anonymous') ?>
              </a>
              <span class="card-sep">·</span>
              <span title="<?= date('F j, Y \a\t g:i A', strtotime($post['created_at'])) ?>">
                <?= function_exists('time_ago') ? time_ago($post['created_at']) : date('M d, Y', strtotime($post['created_at'])) ?>
              </span>
            </div>

            <h2 class="card-title">
              <a href="<?= $post_url ?>"><?= htmlspecialchars($post['title']) ?></a>
            </h2>

            <p class="card-excerpt"><?= htmlspecialchars($excerpt) ?></p>

            <div class="card-foot">
              <div class="card-stats">
                <span class="card-stat" title="<?= number_format($post['view_count']) ?> views">
                  <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                  <?= function_exists('format_number') ? format_number($post['view_count'] ?? 0) : number_format($post['view_count'] ?? 0) ?>
                </span>
                <a class="card-stat" href="<?= $post_url ?>#commentForm" title="<?= $post['comment_count'] ?> comments">
                  <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                  </svg>
                  <?= $post['comment_count'] ?>
                </a>
                <button class="like-btn <?= $is_liked ? 'is-liked' : '' ?>"
                        type="button"
                        data-post-id="<?= $post['id'] ?>"
                        aria-pressed="<?= $is_liked ? 'true' : 'false' ?>"
                        title="<?= $post['like_count'] ?> likes">
                  <svg fill="<?= $is_liked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                  </svg>
                  <span class="like-count"><?= $post['like_count'] ?></span>
                </button>
              </div>
              <a href="<?= $post_url ?>" class="card-read">
                Read
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
              </a>
            </div>
          </div>

        </article>
        <?php endwhile; ?>
      </div>

      <!-- pagination -->
      <?php if ($total_pages > 1):
        $qs = http_build_query(array_filter(['search' => $search, 'category' => $category_filter ?: null,
          'tag' => $tag_filter ?: null, 'author' => $author_filter ?: null, 'sort' => $sort]));
      ?>
      <nav class="ex-pagination">
        <a href="?page=<?= max(1, $page_num-1) ?>&<?= $qs ?>"
           class="pg-btn <?= $page_num==1 ? 'disabled' : '' ?>">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Prev
        </a>

        <?php
        $ps = max(1, $page_num-2); $pe = min($total_pages, $page_num+2);
        if ($ps > 1):
        ?><a href="?page=1&<?= $qs ?>" class="pg-btn">1</a><?php
        if ($ps > 2) echo '<span class="pg-btn dots">…</span>';
        endif;

        for ($i = $ps; $i <= $pe; $i++):
        ?><a href="?page=<?= $i ?>&<?= $qs ?>"
             class="pg-btn <?= $page_num==$i ? 'active' : '' ?>"><?= $i ?></a><?php
        endfor;

        if ($pe < $total_pages):
          if ($pe < $total_pages-1) echo '<span class="pg-btn dots">…</span>';
        ?><a href="?page=<?= $total_pages ?>&<?= $qs ?>" class="pg-btn"><?= $total_pages ?></a><?php
        endif;
        ?>

        <a href="?page=<?= min($total_pages, $page_num+1) ?>&<?= $qs ?>"
           class="pg-btn <?= $page_num==$total_pages ? 'disabled' : '' ?>">
          Next
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </nav>
      <?php endif; ?>

      <?php else: ?>
      <div class="posts-grid">
        <div class="ex-empty">
          <span class="ex-empty-icon">🔍</span>
          <h3>Nothing found</h3>
          <p>No posts match your current filters. Try broadening your search or clearing the filters.</p>
          <a href="explore.php" class="ex-empty-link">
            View all posts
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══════ SIDEBAR ══════ -->
    <aside class="ex-sidebar">

      <!-- Subscribe -->
      <div class="sb-card">
        <div class="sb-head">
          <span class="sb-head-icon" style="background:var(--accent-dim)">
            <svg fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
            </svg>
          </span>
          <span class="sb-title">Stay in the Loop</span>
        </div>
        <div class="sb-body">
          <p class="sb-desc">Fresh stories delivered to your inbox. No spam, ever.</p>
          <form id="subscribeForm" method="POST" action="ajax/subscribe.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="email" name="email" class="sb-input"
                   placeholder="you@example.com" required>
            <div class="sb-checks">
              <label><input type="checkbox" name="notify_posts" checked> New posts</label>
              <label><input type="checkbox" name="notify_weekly"> Weekly digest</label>
            </div>
            <button type="submit" class="sb-btn" id="subBtn">Subscribe</button>
          </form>
        </div>
      </div>

      <!-- Categories -->
      <div class="sb-card">
        <div class="sb-head">
          <span class="sb-head-icon" style="background:var(--sky-dim)">
            <svg fill="none" stroke="var(--sky)" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
            </svg>
          </span>
          <span class="sb-title">Categories</span>
        </div>
        <nav class="cat-list">
          <?php
          $categories->data_seek(0);
          if ($categories->num_rows > 0):
            while ($cat = $categories->fetch_assoc()):
              $cnt = $conn->query("SELECT COUNT(*) as c FROM posts WHERE category_id = {$cat['id']} AND COALESCE(visible, 1) = 1 AND COALESCE(review_status, 'approved') = 'approved'")->fetch_assoc()['c'];
          ?>
          <a href="?category=<?= $cat['id'] ?>" class="cat-item">
            <span><?= htmlspecialchars($cat['name']) ?></span>
            <span class="cat-count"><?= $cnt ?></span>
          </a>
          <?php endwhile; else: ?>
          <p class="sb-empty" style="padding:1rem">No categories yet.</p>
          <?php endif; ?>
        </nav>
        <div style="padding:.85rem 1.2rem">
          <a href="categories.php" class="sb-btn-ghost">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14" width="12" height="12"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Browse all categories
          </a>
        </div>
      </div>

      <!-- Bookmarks (logged in) -->
      <?php if ($is_logged_in): ?>
      <div class="sb-card">
        <div class="sb-head">
          <span class="sb-head-icon" style="background:var(--amber-dim)">
            <svg fill="none" stroke="var(--amber)" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
            </svg>
          </span>
          <span class="sb-title">Your Bookmarks</span>
        </div>
        <div class="sb-body">
          <?php if (count($bookmarked_posts) > 0): ?>
          <p class="sb-bookmark-text">
            You've saved <strong><?= count($bookmarked_posts) ?></strong> post<?= count($bookmarked_posts) !== 1 ? 's' : '' ?>.
          </p>
          <a href="bookmarks.php" class="sb-btn-ghost" style="margin-top:.6rem">View all bookmarks</a>
          <?php else: ?>
          <p class="sb-empty">Bookmark any post using the bookmark icon — it'll appear here.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </aside>
  </div><!-- /.ex-body -->
</div><!-- /.ex-page -->

<!-- ══════ SHARE MODAL ══════ -->
<div class="modal-overlay" id="shareModal">
  <div class="modal-box">
    <div class="modal-head">
      <span class="modal-title">Share Post</span>
      <button class="modal-close" onclick="closeModal()" aria-label="Close">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
          <path d="M2 2l10 10M12 2L2 12" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="share-grid">
        <button class="share-btn-item s-facebook" onclick="sharePost('facebook')">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          Facebook
        </button>
        <button class="share-btn-item s-twitter" onclick="sharePost('twitter')">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          Twitter / X
        </button>
        <button class="share-btn-item s-linkedin" onclick="sharePost('linkedin')">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
          LinkedIn
        </button>
        <button class="share-btn-item s-whatsapp" onclick="sharePost('whatsapp')">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          WhatsApp
        </button>
        <button class="share-btn-item s-copy" onclick="copyLink()">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
          </svg>
          Copy link
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast dock -->
<div class="toast-dock" id="toastDock"></div>

<script>
/* ── State ── */
let _share = { postId: null, title: '', url: '' };

/* ── Modal ── */
function openModal() { document.getElementById('shareModal').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal() { document.getElementById('shareModal').classList.remove('open'); document.body.style.overflow = ''; }

/* ── Share ── */
function sharePost(platform) {
  const t = encodeURIComponent(_share.title), u = encodeURIComponent(_share.url);
  const map = {
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${u}`,
    twitter:  `https://twitter.com/intent/tweet?text=${t}&url=${u}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${u}`,
    whatsapp: `https://wa.me/?text=${t}%20${u}`
  };
  if (map[platform]) window.open(map[platform], '_blank', 'width=600,height=400,noopener,noreferrer');
}

function copyLink() {
  const url = _share.url;
  const write = () => navigator.clipboard.writeText(url).then(() => { toast('Link copied!', 'success'); closeModal(); });
  navigator.clipboard?.writeText ? write().catch(() => fallback(url)) : fallback(url);
}

function fallback(text) {
  const ta = Object.assign(document.createElement('textarea'), { value: text, style: 'position:fixed;opacity:0' });
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); toast('Link copied!', 'success'); closeModal(); }
  catch { toast('Could not copy link', 'error'); }
  ta.remove();
}

/* ── Toast ── */
function toast(msg, type = 'info') {
  const dock = document.getElementById('toastDock');
  const el = Object.assign(document.createElement('div'), { className: `toast toast-${type}`, textContent: msg });
  dock.appendChild(el);
  setTimeout(() => { el.style.cssText += 'opacity:0;transition:opacity .3s'; setTimeout(() => el.remove(), 300); }, 3200);
}

/* ── Subscribe ── */
document.getElementById('subscribeForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('subBtn'), orig = btn.textContent;
  btn.disabled = true; btn.textContent = 'Subscribing…';
  fetch(this.action, { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(d => { toast(d.message || (d.success ? 'Subscribed!' : 'Failed'), d.success ? 'success' : 'error'); if (d.success) this.reset(); })
    .catch(() => toast('Network error', 'error'))
    .finally(() => { btn.disabled = false; btn.textContent = orig; });
});

/* ── Wire up ── */
document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('.posts-grid');
  const csrf = grid?.dataset.csrf || '';

  /* bookmarks */
  document.querySelectorAll('.bookmark-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      const wasBm = btn.classList.contains('is-bookmarked');
      const svg   = btn.querySelector('svg');
      btn.classList.toggle('is-bookmarked', !wasBm);
      svg.setAttribute('fill', wasBm ? 'none' : 'currentColor');
      btn.title = wasBm ? 'Bookmark' : 'Remove bookmark';
      fetch('ajax/bookmark.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: btn.dataset.postId, action: wasBm ? 'remove' : 'add' })
      })
      .then(r => r.text()).then(text => {
        if (!text.trim()) throw new Error('Empty response');
        const d = JSON.parse(text);
        if (d.success) { toast(d.message, 'success'); }
        else { btn.classList.toggle('is-bookmarked', wasBm); svg.setAttribute('fill', wasBm ? 'currentColor' : 'none'); toast(d.message || 'Failed', 'error'); }
      })
      .catch(err => { btn.classList.toggle('is-bookmarked', wasBm); svg.setAttribute('fill', wasBm ? 'currentColor' : 'none'); toast(err.message || 'Network error', 'error'); });
    });
  });

  /* likes */
  document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', async e => {
      e.preventDefault(); e.stopPropagation();
      if (!csrf) { toast('Session expired. Refresh and try again.', 'error'); return; }
      const fd = new FormData();
      fd.append('post_id', btn.dataset.postId);
      fd.append('csrf_token', csrf);
      try {
        const res = await fetch('ajax/like.php', { method: 'POST', body: fd });
        const d = await res.json();
        if (!d.success) throw new Error(d.message || 'Like failed');
        btn.classList.toggle('is-liked', d.liked);
        btn.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
        btn.querySelector('svg')?.setAttribute('fill', d.liked ? 'currentColor' : 'none');
        const ct = btn.querySelector('.like-count');
        if (ct) ct.textContent = d.likes;
        toast(d.message || (d.liked ? 'Liked!' : 'Unliked'), 'success');
      } catch(err) { toast(err.message || 'Network error', 'error'); }
    });
  });

  /* share open */
  document.querySelectorAll('.share-open-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      _share = { postId: btn.dataset.postId, title: btn.dataset.postTitle, url: btn.dataset.postUrl };
      openModal();
    });
  });

  /* modal dismiss */
  document.getElementById('shareModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
});
</script>

<?php
$stmt->close();
$conn->close();
include '../shared/footer.php';
?>

