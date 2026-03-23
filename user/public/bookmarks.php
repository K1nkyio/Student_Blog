<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

if (!is_logged_in()) {
    set_flash('error', 'You must be logged in to view bookmarks.');
    redirect('login.php');
}

$user_id = get_user_id();

$posts_per_page  = 12;
$page_num    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset          = ($page_num - 1) * $posts_per_page;

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookmarks WHERE user_id = ?");
$count_stmt->bind_param('i', $user_id);
$count_stmt->execute();
$total_bookmarks = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_bookmarks / $posts_per_page));

$stmt = $conn->prepare("SELECT p.id, p.title, p.content, p.excerpt, p.image, p.slug, p.created_at, p.view_count,
        u.id as author_id, u.username as author_name, u.avatar as author_avatar,
        c.id as category_id, c.name as category_name, c.slug as category_slug,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND approved = 1) as comment_count,
        (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reaction_count,
        b.created_at as bookmarked_at
        FROM posts p
        INNER JOIN bookmarks b ON b.post_id = p.id
        LEFT JOIN users u ON p.author_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE b.user_id = ? AND COALESCE(p.visible, 1) = 1 AND COALESCE(p.review_status, 'approved') = 'approved'
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?");

$stmt->bind_param('iii', $user_id, $posts_per_page, $offset);
$stmt->execute();
$bookmarked = $stmt->get_result();
$stmt->close();

$page_title = 'Bookmarks — ' . SITE_NAME;
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
  --max-w:      1360px;
  --gutter:     1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
  --shadow:     0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  line-height: 1.65;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
  padding-bottom: 6rem;
}
a { color: inherit; text-decoration: none; }
img { display: block; max-width: 100%; }
button { font-family: inherit; }

/* ═══════════════════════════════════════════
   HERO STRIP — dark ink, same as all other pages
═══════════════════════════════════════════ */
.bm-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

/* serif watermark */
.bm-hero::before {
  content: 'Saved.';
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
.bm-hero::after {
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
@keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

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
  max-width: 40ch;
  line-height: 1.75;
  margin-bottom: 2rem;
}

/* hero stats row */
.hero-stats { display: flex; gap: 2.5rem; flex-wrap: wrap; }
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
   BODY
═══════════════════════════════════════════ */
.bm-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2rem var(--gutter) 3rem;
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
   POSTS GRID — white cards, same as explore.php
═══════════════════════════════════════════ */
.posts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 1rem;
}

.post-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  position: relative;
  animation: cardIn .4s ease both;
}
.post-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-3px);
}
<?php for ($i = 1; $i <= 12; $i++): ?>
.posts-grid .post-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i - 1) * 0.04 ?>s; }
<?php endfor; ?>

@keyframes cardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── card image ── */
.card-media {
  position: relative;
  width: 100%;
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

/* category badge */
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

/* saved-at badge — bottom-left */
.card-saved-at {
  position: absolute;
  bottom: 10px; left: 10px;
  background: rgba(24,22,15,.58);
  backdrop-filter: blur(4px);
  color: rgba(255,255,255,.82);
  border-radius: 2px;
  padding: .2rem .6rem;
  font-size: .65rem;
  font-weight: 500;
  display: flex; align-items: center; gap: .28rem;
  letter-spacing: .03em;
}
.card-saved-at svg { width: 10px; height: 10px; }

/* hover action overlay */
.card-actions {
  position: absolute;
  top: 10px; right: 10px;
  display: flex; gap: .3rem;
  opacity: 0;
  transform: translateY(-4px);
  transition: opacity var(--transition), transform var(--transition);
}
.post-card:hover .card-actions { opacity: 1; transform: translateY(0); }

.card-action-btn {
  width: 30px; height: 30px;
  border-radius: 3px;
  border: 1px solid rgba(255,255,255,.4);
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
.card-action-btn.share-open-btn:hover { color: var(--sky); }
.card-action-btn svg { width: 13px; height: 13px; }

/* ── card body ── */
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
  border: 1px solid var(--rule-light);
  flex-shrink: 0;
}
.card-avatar-ph {
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
.card-sep { color: var(--rule); font-size: .6rem; }

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
.bm-empty {
  text-align: center;
  padding: 5rem 2rem;
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  box-shadow: var(--shadow);
  color: var(--ink-light);
}
.bm-empty-icon { font-size: 2.5rem; display: block; opacity: .3; margin-bottom: 1rem; }
.bm-empty h3 {
  font-family: var(--font-serif);
  font-size: 1.4rem;
  color: var(--ink-mid);
  margin-bottom: .4rem;
}
.bm-empty p { font-size: .845rem; margin-bottom: 1.5rem; max-width: 36ch; margin-inline: auto; }
.btn-browse {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .68rem 1.5rem;
  background: var(--ink);
  color: #fff;
  border-radius: 3px;
  font-size: .845rem;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--transition);
}
.btn-browse:hover { background: #2c2a22; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(24,22,15,.2); color: #fff; }
.btn-browse svg { width: 12px; height: 12px; }

/* ═══════════════════════════════════════════
   PAGINATION — matches .opp-pagination
═══════════════════════════════════════════ */
.bm-pagination {
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
  padding: .5rem 1rem;
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
.pg-btn.active   { background: var(--ink); color: #fff; border-color: var(--ink); font-weight: 600; }
.pg-btn.disabled { opacity: .35; pointer-events: none; }
.pg-btn.dots     { pointer-events: none; border-color: transparent; background: transparent; }
.pg-btn svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   SHARE MODAL — same as explore.php
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
  from { opacity:0; transform:scale(.96) translateY(12px); }
  to   { opacity:1; transform:scale(1) translateY(0); }
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
.share-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }

.share-btn-item {
  display: flex; align-items: center; gap: .5rem;
  padding: .72rem .9rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: transparent;
  font-family: var(--font-sans);
  font-size: .82rem; font-weight: 500;
  cursor: pointer;
  transition: all var(--transition);
  color: var(--ink-mid);
}
.share-btn-item:hover { transform: translateY(-1px); }
.share-btn-item svg { width: 15px; height: 15px; flex-shrink: 0; }

.s-facebook { color:#1877f2; } .s-facebook:hover { background:rgba(24,119,242,.06); border-color:#1877f2; }
.s-twitter  { color:#1da1f2; } .s-twitter:hover  { background:rgba(29,161,242,.06); border-color:#1da1f2; }
.s-linkedin { color:#0a66c2; } .s-linkedin:hover { background:rgba(10,102,194,.06); border-color:#0a66c2; }
.s-whatsapp { color:#25d366; } .s-whatsapp:hover { background:rgba(37,211,102,.06); border-color:#25d366; }
.s-copy { grid-column:1/-1; color:var(--ink-mid); }
.s-copy:hover { background:var(--bg-warm); border-color:var(--ink-mid); }

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
  font-size: .82rem; font-family: var(--font-sans);
  box-shadow: 0 4px 16px rgba(24,22,15,.18);
  animation: toastIn .28s ease;
  pointer-events: auto; max-width: 280px;
}
@keyframes toastIn { from{opacity:0;transform:translateX(12px)} to{opacity:1;transform:translateX(0)} }
.toast-success { background: var(--green); }
.toast-error   { background: var(--red); }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 640px) {
  .posts-grid { grid-template-columns: 1fr; }
  .share-grid { grid-template-columns: 1fr; }
  .s-copy { grid-column: auto; }
  .toast-dock { right: 1rem; left: 1rem; }
  .toast { max-width: none; }
}
</style>

<!-- ══════ HERO ══════ -->
<div class="bm-hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">
      <span class="live-dot"></span>
      Reading List
    </div>
    <h1 class="hero-headline">Your <em>Bookmarks</em></h1>
    <p class="hero-sub">Posts you've saved for later — pick up right where you left off.</p>
    <div class="hero-stats">
      <div>
        <span class="hero-stat-num"><?php echo number_format($total_bookmarks); ?></span>
        <span class="hero-stat-label">Saved Posts</span>
      </div>
      <div>
        <span class="hero-stat-num">Page <?php echo $page_num; ?> / <?php echo $total_pages; ?></span>
        <span class="hero-stat-label">Current</span>
      </div>
    </div>
  </div>
</div>

<!-- ══════ BODY ══════ -->
<div class="bm-body">

  <?php display_flash(); ?>

  <?php if ($bookmarked && $bookmarked->num_rows > 0): ?>

    <div class="results-bar">
      <span><strong><?php echo number_format($total_bookmarks); ?></strong> saved post<?php echo $total_bookmarks !== 1 ? 's' : ''; ?></span>
      <?php if ($total_pages > 1): ?>
        <span>Page <?php echo $page_num; ?> of <?php echo $total_pages; ?></span>
      <?php endif; ?>
    </div>

    <div class="posts-grid">
      <?php while ($post = $bookmarked->fetch_assoc()):
        $excerpt  = !empty($post['excerpt']) ? $post['excerpt'] : (function_exists('get_excerpt') ? get_excerpt($post['content'], 150) : substr(strip_tags($post['content']), 0, 150) . '…');
        $post_url = !empty($post['slug']) ? "post.php?slug=" . urlencode($post['slug']) : "post.php?id=" . $post['id'];
        $full_url = "https://{$_SERVER['HTTP_HOST']}/{$post_url}";
      ?>
      <article class="post-card" data-post-id="<?= $post['id'] ?>">

        <!-- image -->
        <div class="card-media">
          <?php if (!empty($post['image'])): ?>
            <img src="<?= htmlspecialchars(function_exists('get_post_image_url') ? get_post_image_url($post['image']) : $post['image']) ?>"
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

          <!-- category -->
          <?php if (!empty($post['category_name'])): ?>
          <a href="explore.php?category=<?= $post['category_id'] ?>" class="card-cat">
            <?= htmlspecialchars($post['category_name']) ?>
          </a>
          <?php endif; ?>

          <!-- saved-at pill -->
          <span class="card-saved-at">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
            </svg>
            Saved <?= function_exists('time_ago') ? time_ago($post['bookmarked_at']) : date('M d', strtotime($post['bookmarked_at'])) ?>
          </span>

          <!-- hover actions -->
          <div class="card-actions">
            <button class="card-action-btn is-bookmarked bookmark-btn"
                    data-post-id="<?= $post['id'] ?>"
                    title="Remove bookmark">
              <svg fill="currentColor" stroke="currentColor" stroke-width="0" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
              </svg>
            </button>
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
              <span class="card-avatar-ph"></span>
            <?php endif; ?>
            <a href="explore.php?author=<?= $post['author_id'] ?>" class="card-author">
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
              <span class="card-stat" title="<?= $post['comment_count'] ?> comments">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
                <?= $post['comment_count'] ?>
              </span>
              <span class="card-stat" title="<?= $post['reaction_count'] ?> reactions">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                </svg>
                <?= $post['reaction_count'] ?>
              </span>
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
    <?php if ($total_pages > 1): ?>
    <nav class="bm-pagination">
      <a href="?page=<?= max(1, $page_num-1) ?>" class="pg-btn <?= $page_num==1 ? 'disabled' : '' ?>">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Prev
      </a>

      <?php
      $ps = max(1, $page_num-2); $pe = min($total_pages, $page_num+2);
      if ($ps > 1):
      ?><a href="?page=1" class="pg-btn">1</a><?php
      if ($ps > 2) echo '<span class="pg-btn dots">…</span>';
      endif;

      for ($i = $ps; $i <= $pe; $i++):
      ?><a href="?page=<?= $i ?>" class="pg-btn <?= $page_num==$i ? 'active' : '' ?>"><?= $i ?></a><?php
      endfor;

      if ($pe < $total_pages):
        if ($pe < $total_pages-1) echo '<span class="pg-btn dots">…</span>';
      ?><a href="?page=<?= $total_pages ?>" class="pg-btn"><?= $total_pages ?></a><?php
      endif;
      ?>

      <a href="?page=<?= min($total_pages, $page_num+1) ?>" class="pg-btn <?= $page_num==$total_pages ? 'disabled' : '' ?>">
        Next
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </nav>
    <?php endif; ?>

  <?php else: ?>
    <div class="bm-empty">
      <span class="bm-empty-icon">🔖</span>
      <h3>No bookmarks yet</h3>
      <p>Browse the blog and tap the bookmark icon on posts you want to save for later.</p>
      <a class="btn-browse" href="explore.php">
        Browse Posts
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 14 14">
          <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
    </div>
  <?php endif; ?>

</div><!-- /.bm-body -->

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

<div class="toast-dock" id="toastDock"></div>

<script>
/* ── State ── */
let _share = { postId: null, title: '', url: '' };

/* ── Modal ── */
function openModal()  { document.getElementById('shareModal').classList.add('open'); document.body.style.overflow = 'hidden'; }
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
  const el   = Object.assign(document.createElement('div'), { className: `toast toast-${type}`, textContent: msg });
  dock.appendChild(el);
  setTimeout(() => { el.style.cssText += 'opacity:0;transition:opacity .3s'; setTimeout(() => el.remove(), 300); }, 3200);
}

/* ── Wire up ── */
document.addEventListener('DOMContentLoaded', () => {

  /* bookmark remove */
  document.querySelectorAll('.bookmark-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault(); e.stopPropagation();
      const postId = btn.dataset.postId;
      fetch('ajax/bookmark.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId, action: 'remove' })
      })
      .then(r => r.text()).then(text => {
        if (!text.trim()) throw new Error('Empty response');
        const d = JSON.parse(text);
        if (d.success) {
          toast(d.message || 'Bookmark removed', 'success');
          const card = btn.closest('.post-card');
          if (card) {
            card.style.transition = 'opacity .3s ease, transform .3s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateY(-12px)';
            setTimeout(() => {
              card.remove();
              if (!document.querySelector('.post-card')) location.reload();
            }, 320);
          }
        } else {
          toast(d.message || 'Failed to remove bookmark', 'error');
        }
      })
      .catch(err => toast(err.message || 'Network error', 'error'));
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

<?php include '../shared/footer.php'; ?>

