<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';

// Ensure supporting tables exist
$conn->query("CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    event_date DATE DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    organizer VARCHAR(255) DEFAULT NULL,
    registration_link VARCHAR(500) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    description TEXT NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'image'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE events ADD COLUMN image VARCHAR(255) DEFAULT NULL");
}

$conn->query("CREATE TABLE IF NOT EXISTS opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    organization VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    link VARCHAR(500) DEFAULT NULL,
    description TEXT NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS marketplace_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    item_condition VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    stock_status VARCHAR(50) DEFAULT NULL,
    variant VARCHAR(100) DEFAULT NULL,
    delivery_estimate VARCHAR(100) DEFAULT NULL,
    contact VARCHAR(255) DEFAULT NULL,
    link VARCHAR(500) DEFAULT NULL,
    description TEXT NOT NULL,
    features TEXT DEFAULT NULL,
    specifications TEXT DEFAULT NULL,
    materials TEXT DEFAULT NULL,
    usage_instructions TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS marketplace_item_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES marketplace_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$page_title      = SITE_NAME . ' — Home';
$meta_description = 'Your campus hub for stories, opportunities, marketplace listings, and upcoming events.';

// Featured posts
$featured_stmt = $conn->prepare("SELECT p.id, p.title, p.excerpt, p.content, p.image, p.slug, p.created_at, p.view_count,
        u.username as author_name, u.avatar as author_avatar,
        c.name as category_name, c.slug as category_slug
    FROM posts p
    LEFT JOIN users u ON p.author_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE COALESCE(p.visible, 1) = 1 AND COALESCE(p.review_status, 'approved') = 'approved'
    ORDER BY p.created_at DESC
    LIMIT 7");
$featured_stmt->execute();
$featured_result = $featured_stmt->get_result();
$featured_posts  = [];
while ($row = $featured_result->fetch_assoc()) $featured_posts[] = $row;
$featured_stmt->close();

// Explore posts preview (replaces trending topics)
$explore_posts = $conn->query("SELECT p.id, p.title, p.excerpt, p.content, p.slug, p.created_at,
        c.name as category_name
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE COALESCE(p.visible, 1) = 1 AND COALESCE(p.review_status, 'approved') = 'approved'
    ORDER BY p.created_at DESC
    LIMIT 6");

// Upcoming events
$events_result = $conn->query("SELECT * FROM events
    WHERE LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open')
    AND (event_date IS NULL OR event_date = '' OR event_date >= CURDATE())
    ORDER BY event_date ASC, created_at DESC LIMIT 4");
if (!$events_result) $events_result = $conn->query("SELECT * FROM events ORDER BY event_date ASC, created_at DESC LIMIT 4");
$events = [];
if ($events_result) while ($row = $events_result->fetch_assoc()) $events[] = $row;

// Marketplace highlights
$marketplace_items = $conn->query("SELECT mi.*, (SELECT image_path FROM marketplace_item_images WHERE item_id = mi.id ORDER BY id ASC LIMIT 1) AS image_path
    FROM marketplace_items mi
    WHERE LOWER(COALESCE(NULLIF(mi.status, ''), 'active')) IN ('active','open')
    ORDER BY mi.created_at DESC LIMIT 4");

// Opportunities preview
$opportunities = $conn->query("SELECT * FROM opportunities
    WHERE LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open')
    ORDER BY created_at DESC LIMIT 3");

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

.home-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO — full-width dark strip
═══════════════════════════════════════════ */
.home-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 0;
  position: relative;
  overflow: hidden;
}

/* serif watermark — matches opp-hero */
.home-hero::before {
  content: 'Zetech.';
  position: absolute;
  right: calc(var(--gutter) - 2rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(6rem, 15vw, 12rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.03);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

/* dot-grid overlay — matches opp-hero */
.home-hero::after {
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
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: end;
  animation: slideUp .6s ease both;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── hero copy (left) ── */
.hero-copy { padding-bottom: 3.5rem; }

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
  margin-bottom: 1.1rem;
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
  font-size: clamp(2.5rem, 5.5vw, 4.2rem);
  font-weight: 700;
  line-height: 1.04;
  letter-spacing: -.02em;
  color: #fff;
  margin-bottom: .9rem;
}
.hero-headline em {
  font-style: italic;
  color: rgba(255,255,255,.38);
}

.hero-sub {
  font-size: .95rem;
  color: rgba(255,255,255,.42);
  font-weight: 300;
  max-width: 40ch;
  line-height: 1.75;
  margin-bottom: 1.75rem;
}

/* hero action buttons */
.hero-actions { display: flex; gap: .65rem; flex-wrap: wrap; margin-bottom: 2.25rem; }

.btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-family: var(--font-sans);
  font-size: .8rem;
  font-weight: 600;
  text-decoration: none;
  padding: .62rem 1.25rem;
  border-radius: 3px;
  border: none;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
  line-height: 1;
}
.btn svg { width: 12px; height: 12px; flex-shrink: 0; }
.btn:active { transform: scale(.97); }

.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #b05510; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(200,100,26,.35); color: #fff; }

.btn-outline {
  background: transparent;
  color: rgba(255,255,255,.62);
  border: 1.5px solid rgba(255,255,255,.16);
}
.btn-outline:hover { border-color: rgba(255,255,255,.38); color: #fff; background: rgba(255,255,255,.05); }

/* hero stats row — matches opp hero-stats */
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

/* ── hero dashboard panel (right) ── */
.hero-panel {
  align-self: stretch;
  display: flex;
  flex-direction: column;
  padding-top: .5rem;
  min-width: 0;
}

/* panel cards stacked — connected borders */
.hpanel-card {
  background: rgba(255,255,255,.045);
  border: 1px solid rgba(255,255,255,.08);
  border-bottom: none;
  padding: 1.1rem 1.3rem;
  transition: background var(--transition);
}
.hpanel-card:first-child { border-radius: 4px 4px 0 0; }
.hpanel-card:hover { background: rgba(255,255,255,.08); }

.hpanel-kicker {
  font-size: .58rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.28);
  margin-bottom: .45rem;
}
.hpanel-title {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  line-height: 1.3;
  margin-bottom: .4rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.hpanel-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem .9rem;
  font-size: .72rem;
  color: rgba(255,255,255,.34);
  align-items: center;
}
.hpanel-meta-item { display: inline-flex; align-items: center; gap: .28rem; }
.hpanel-meta-item svg { width: 10px; height: 10px; }

/* stat row */
.hpanel-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.07);
  border-top: none; border-bottom: none;
}
.hpanel-stat {
  background: rgba(255,255,255,.035);
  padding: .85rem .65rem;
  text-align: center;
  transition: background var(--transition);
}
.hpanel-stat:hover { background: rgba(255,255,255,.07); }
.hpanel-stat strong {
  display: block;
  font-family: var(--font-serif);
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
}
.hpanel-stat span {
  font-size: .58rem;
  font-weight: 500;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.28);
  display: block;
  margin-top: .2rem;
}

/* quicklinks bar at panel bottom */
.hpanel-links {
  border: 1px solid rgba(255,255,255,.07);
  border-top: none;
  display: flex;
  border-radius: 0 0 4px 4px;
  overflow: hidden;
  flex-shrink: 0;
}
.hpanel-link {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  padding: .65rem .4rem;
  font-size: .68rem;
  font-weight: 500;
  color: rgba(255,255,255,.35);
  text-decoration: none;
  border-right: 1px solid rgba(255,255,255,.06);
  transition: all var(--transition);
  white-space: nowrap;
}
.hpanel-link:last-child { border-right: none; }
.hpanel-link:hover { background: rgba(255,255,255,.07); color: rgba(255,255,255,.75); }
.hpanel-link svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   FEATURE STRIP — 4 modules
═══════════════════════════════════════════ */
.feature-strip {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule);
}
.feature-strip-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--gutter);
  display: grid;
  grid-template-columns: repeat(4, 1fr);
}
.feat-link {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 1.1rem .75rem;
  text-decoration: none;
  color: var(--ink-mid);
  border-right: 1px solid var(--rule);
  transition: all var(--transition);
  font-size: .82rem;
  font-weight: 500;
  position: relative;
}
.feat-link:last-child { border-right: none; }
.feat-link:hover { background: var(--bg-warmer); color: var(--ink); }
/* accent bottom line on hover */
.feat-link::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 2px;
  background: var(--accent);
  transform: scaleX(0);
  transition: transform var(--transition);
  transform-origin: left;
}
.feat-link:hover::after { transform: scaleX(1); }

.feat-icon {
  width: 32px; height: 32px;
  border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.feat-icon svg { width: 14px; height: 14px; }
.feat-text {}
.feat-name { display: block; font-weight: 600; font-size: .82rem; color: var(--ink); }
.feat-desc { display: block; font-size: .68rem; color: var(--ink-light); margin-top: .06rem; font-weight: 400; }

/* ═══════════════════════════════════════════
   BODY WRAPPER
═══════════════════════════════════════════ */
.home-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--gutter);
}

/* ── Section headers ── */
.sec-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.4rem;
  padding-top: 3rem;
  border-top: 1px solid var(--rule-light);
  flex-wrap: wrap;
}
.sec-header--first { border-top: none; }

.sec-kicker {
  font-size: .6rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: .3rem;
}
.sec-title {
  font-family: var(--font-serif);
  font-size: clamp(1.35rem, 2.5vw, 1.65rem);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.1;
  letter-spacing: -.01em;
}
.sec-sub {
  font-size: .8rem;
  color: var(--ink-light);
  margin-top: .2rem;
  font-weight: 300;
}

/* "view all" link — same as opp toolbar filter toggle */
.sec-all {
  display: inline-flex;
  align-items: center;
  gap: .38rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--ink-mid);
  text-decoration: none;
  border: 1.5px solid var(--rule);
  padding: .4rem .9rem;
  border-radius: 3px;
  transition: all var(--transition);
  white-space: nowrap;
  flex-shrink: 0;
}
.sec-all:hover { border-color: var(--ink); background: var(--bg-warm); color: var(--ink); }
.sec-all svg { width: 11px; height: 11px; }

/* ── Empty state ── */
.home-empty {
  text-align: center;
  padding: 3.5rem 1rem;
  color: var(--ink-light);
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  background: #fff;
}
.home-empty-icon { font-size: 2rem; margin-bottom: .75rem; display: block; opacity: .35; }
.home-empty p { font-size: .845rem; }

/* ═══════════════════════════════════════════
   POSTS GRID — hero + small cards
═══════════════════════════════════════════ */
.posts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

/* Hero post — spans left */
.post-hero {
  grid-row: span 3;
  position: relative;
  border-radius: 6px;
  overflow: hidden;
  min-height: 480px;
  text-decoration: none;
  color: #fff;
  display: flex;
  flex-direction: column;
  background: var(--ink);
  box-shadow: 0 1px 4px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.09);
  transition: box-shadow var(--transition), transform var(--transition);
}
.post-hero:hover {
  box-shadow: 0 4px 16px rgba(24,22,15,.12), 0 16px 40px rgba(24,22,15,.1);
  transform: translateY(-2px);
}
.post-hero img {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
  opacity: .48;
  transition: transform .55s ease, opacity .3s ease;
}
.post-hero:hover img { transform: scale(1.04); opacity: .58; }

.post-hero-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(24,22,15,.96) 0%, rgba(24,22,15,.52) 42%, rgba(24,22,15,.08) 100%);
}
.post-hero-body {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: 1.75rem;
  display: flex; flex-direction: column; gap: .6rem;
}

.post-cat {
  display: inline-flex;
  align-items: center;
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  padding: .2rem .65rem;
  border-radius: 2px;
  background: var(--accent);
  color: #fff;
  width: fit-content;
}

.post-hero-title {
  font-family: var(--font-serif);
  font-size: clamp(1.2rem, 2.5vw, 1.65rem);
  font-weight: 700;
  line-height: 1.18;
  color: #fff;
  margin: 0;
}
.post-hero-excerpt {
  font-size: .83rem;
  color: rgba(255,255,255,.58);
  line-height: 1.6;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.post-hero-foot {
  display: flex;
  gap: 1rem;
  font-size: .72rem;
  color: rgba(255,255,255,.4);
  align-items: center;
}
.post-hero-foot .meta-item { display: inline-flex; align-items: center; gap: .28rem; }
.post-hero-foot svg { width: 10px; height: 10px; }

/* Small cards */
.post-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  padding: 1.1rem;
  display: flex;
  flex-direction: column;
  gap: .4rem;
  text-decoration: none;
  color: var(--ink);
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: cardIn .38s ease both;
  position: relative;
  overflow: hidden;
  min-width: 0;
}
.post-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}
/* accent bar */
.post-card::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: var(--accent);
  opacity: 0;
  transition: opacity var(--transition);
}
.post-card:hover::before { opacity: 1; }

@keyframes cardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

.post-card-cat {
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  padding: .18rem .6rem;
  border-radius: 2px;
  background: var(--accent-dim);
  color: var(--accent);
  width: fit-content;
}
.post-card-title {
  font-family: var(--font-serif);
  font-size: .95rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--ink);
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.post-card-excerpt {
  font-size: .75rem;
  color: var(--ink-light);
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.post-card-foot {
  display: flex;
  gap: .85rem;
  font-size: .7rem;
  color: var(--ink-light);
  border-top: 1px solid var(--rule-light);
  padding-top: .5rem;
  margin-top: auto;
  align-items: center;
}
.post-card-foot .meta-item { display: inline-flex; align-items: center; gap: .28rem; }
.post-card-foot svg { width: 10px; height: 10px; }

/* ═══════════════════════════════════════════
   TWO-COL PANELS (Trending + Events)
═══════════════════════════════════════════ */
.two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 3rem;
  padding-top: 3rem;
  border-top: 1px solid var(--rule-light);
}

.home-panel {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
}

.panel-head {
  padding: 1rem 1.35rem;
  border-bottom: 1px solid var(--rule-light);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--bg-warm);
}
.panel-head-left { display: flex; align-items: center; gap: .6rem; }
.panel-icon {
  width: 28px; height: 28px;
  border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.panel-icon svg { width: 13px; height: 13px; }
.panel-title {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}
.panel-viewall {
  font-size: .72rem;
  font-weight: 600;
  color: var(--ink-light);
  text-decoration: none;
  transition: color var(--transition);
}
.panel-viewall:hover { color: var(--sky); }

/* trending rows */
.trend-item {
  display: flex;
  align-items: center;
  gap: .85rem;
  padding: .75rem 1.35rem;
  text-decoration: none;
  color: var(--ink);
  border-bottom: 1px solid var(--rule-light);
  transition: background var(--transition);
}
.trend-item:last-child { border-bottom: none; }
.trend-item:hover { background: var(--bg); }

.trend-rank {
  font-family: var(--font-serif);
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--rule);
  min-width: 24px;
  line-height: 1;
  transition: color var(--transition);
}
.trend-item:hover .trend-rank { color: var(--ink-light); }

.trend-body { flex: 1; min-width: 0; }
.trend-name {
  font-size: .845rem;
  font-weight: 600;
  color: var(--ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-bottom: .08rem;
}
.trend-count { font-size: .7rem; color: var(--ink-light); }
@supports (-webkit-line-clamp: 2) {
  .trend-count {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}

.trend-chevron { color: var(--rule); transition: color var(--transition); flex-shrink: 0; }
.trend-chevron svg { width: 10px; height: 10px; }
.trend-item:hover .trend-chevron { color: var(--ink-light); }

/* event rows */
.ev-item {
  padding: .9rem 1.35rem;
  border-bottom: 1px solid var(--rule-light);
  text-decoration: none;
  color: var(--ink);
  display: block;
  transition: background var(--transition);
}
.ev-item:last-child { border-bottom: none; }
.ev-item:hover { background: var(--bg); }

.ev-type {
  display: inline-flex;
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
  padding: .18rem .55rem;
  border-radius: 2px;
  background: var(--purple-dim);
  color: var(--purple);
  margin-bottom: .4rem;
}
.ev-title {
  font-size: .875rem;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: .4rem;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.ev-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .28rem .85rem;
  font-size: .72rem;
  color: var(--ink-light);
}
.ev-meta-item { display: inline-flex; align-items: center; gap: .28rem; }
.ev-meta-item svg { width: 10px; height: 10px; }

/* ═══════════════════════════════════════════
   MARKETPLACE GRID — 4 cards
═══════════════════════════════════════════ */
.market-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.m-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  overflow: hidden;
  text-decoration: none;
  color: var(--ink);
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
}
.m-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-3px);
}

.m-media {
  position: relative;
  aspect-ratio: 1;
  background: var(--bg-warm);
  overflow: hidden;
  flex-shrink: 0;
}
.m-media img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform .4s ease;
}
.m-card:hover .m-media img { transform: scale(1.06); }

.m-no-img {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--bg-warm), var(--bg-warmer));
}
.m-no-img svg { width: 24px; height: 24px; color: var(--rule); }

.m-badge-stack {
  position: absolute;
  top: 8px; left: 8px;
  display: flex; gap: .25rem;
}
.m-badge {
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  padding: .18rem .52rem;
  border-radius: 2px;
  line-height: 1.2;
}
.m-badge--cat     { background: rgba(24,22,15,.72); color: rgba(255,255,255,.9); }
.m-badge--urgent  { background: var(--red); color: #fff; }
.m-cond-badge {
  position: absolute;
  top: 8px; right: 8px;
  background: rgba(245,242,237,.9);
  font-size: .58rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  padding: .18rem .52rem;
  border-radius: 2px;
  color: var(--ink-mid);
  border: 1px solid rgba(24,22,15,.07);
}

.m-body { padding: .9rem; display: flex; flex-direction: column; gap: .32rem; flex: 1; }
.m-title {
  font-size: .83rem;
  font-weight: 600;
  color: var(--ink);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.m-price {
  font-family: var(--font-serif);
  font-style: italic;
  font-size: 1rem;
  color: var(--ink);
  line-height: 1;
}
.m-loc {
  font-size: .7rem;
  color: var(--ink-light);
  display: flex; align-items: center; gap: .28rem;
}
.m-loc svg { width: 9px; height: 9px; }

/* ═══════════════════════════════════════════
   OPPORTUNITIES LIST ROWS
═══════════════════════════════════════════ */
.opp-list { display: flex; flex-direction: column; gap: .85rem; }

.opp-row {
  display: grid;
  grid-template-columns: 42px 1fr auto;
  gap: 1.1rem;
  align-items: center;
  padding: 1.25rem 1.4rem;
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  text-decoration: none;
  color: var(--ink);
  position: relative;
  overflow: hidden;
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: cardIn .38s ease both;
}
.opp-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}
/* left accent bar — mirrors opportunities.php exactly */
.opp-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
}
.opp-row[data-type="internship"]::before  { background: var(--sky); }
.opp-row[data-type="job"]::before         { background: var(--green); }
.opp-row[data-type="scholarship"]::before { background: var(--amber); }
.opp-row[data-type="attachment"]::before  { background: var(--purple); }
.opp-row[data-type="other"]::before       { background: var(--rule); }

.opp-icon {
  width: 40px; height: 40px;
  border-radius: 5px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.opp-icon--internship  { background: var(--sky-dim); }
.opp-icon--job         { background: var(--green-dim); }
.opp-icon--scholarship { background: var(--amber-dim); }
.opp-icon--attachment  { background: var(--purple-dim); }
.opp-icon--other       { background: var(--bg-warm); }

.opp-body { min-width: 0; }
.opp-badges { display: flex; gap: .3rem; margin-bottom: .28rem; flex-wrap: wrap; }
.opp-badge {
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .15rem .55rem;
  border-radius: 2px;
}
.opp-badge--internship  { background: var(--sky-dim);    color: var(--sky); }
.opp-badge--job         { background: var(--green-dim);  color: var(--green); }
.opp-badge--scholarship { background: var(--amber-dim);  color: var(--amber); }
.opp-badge--attachment  { background: var(--purple-dim); color: var(--purple); }
.opp-badge--other       { background: var(--bg-warmer);  color: var(--ink-light); }

.opp-title {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.25;
  margin-bottom: .28rem;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.opp-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .22rem .75rem;
  font-size: .72rem;
  color: var(--ink-light);
}
.opp-meta-item { display: inline-flex; align-items: center; gap: .28rem; }
.opp-meta-item svg { width: 10px; height: 10px; }

/* "View" button — matches .btn-ghost from opp list */
.opp-cta {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .75rem;
  font-weight: 600;
  color: var(--ink-mid);
  border: 1.5px solid var(--rule);
  padding: .42rem .85rem;
  border-radius: 3px;
  text-decoration: none;
  transition: all var(--transition);
  white-space: nowrap;
  flex-shrink: 0;
}
.opp-cta:hover { border-color: var(--ink); background: var(--bg-warm); color: var(--ink); }
.opp-cta svg { width: 10px; height: 10px; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 1024px) {
  .market-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 900px) {
  .hero-inner { grid-template-columns: 1fr; }
  .hero-panel { display: none; }
  .feature-strip-inner { grid-template-columns: repeat(2, 1fr); }
  .feat-link { border-bottom: 1px solid var(--rule); }
  .posts-grid { grid-template-columns: 1fr; grid-auto-rows: auto; }
  .post-hero { grid-row: span 1; min-height: 380px; }
  .two-col { grid-template-columns: 1fr; }
  .opp-row { grid-template-columns: 40px 1fr; }
  .opp-cta { display: none; }
}

@media (max-width: 540px) {
  .market-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
  .feature-strip-inner { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="home-page">

  <!-- ══════════════ HERO ══════════════ -->
  <div class="home-hero">
    <div class="hero-inner">

      <!-- left: editorial copy -->
      <div class="hero-copy">
        <span class="hero-eyebrow"><span class="live-dot"></span> Campus community</span>

        <h1 class="hero-headline">
          Your Campus,<br>
          Your Story,<br>
          <em>All Here.</em>
        </h1>

        <p class="hero-sub">
          Stories, opportunities, marketplace listings, and events — built for Zetech students and updated daily.
        </p>

        <div class="hero-actions">
          <a class="btn btn-primary" href="marketplace.php">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            Browse Marketplace
          </a>
          <a class="btn btn-outline" href="opportunities.php">
            Opportunities
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
        </div>

        <div class="hero-stats">
          <div>
            <span class="hero-stat-num"><?php echo count($events); ?></span>
            <span class="hero-stat-label">Events</span>
          </div>
          <div>
            <span class="hero-stat-num"><?php echo $marketplace_items ? (int)$marketplace_items->num_rows : 0; ?></span>
            <span class="hero-stat-label">Listings</span>
          </div>
          <div>
            <span class="hero-stat-num"><?php echo $opportunities ? (int)$opportunities->num_rows : 0; ?></span>
            <span class="hero-stat-label">Opportunities</span>
          </div>
          <div>
            <span class="hero-stat-num"><?php echo count($featured_posts); ?></span>
            <span class="hero-stat-label">New Posts</span>
          </div>
        </div>
      </div>

      <!-- right: dark dashboard panel -->
      <div class="hero-panel">

        <?php $next = $events[0] ?? null; ?>
        <div class="hpanel-card">
          <div class="hpanel-kicker">Next on campus</div>
          <?php if ($next): ?>
            <div class="hpanel-title"><?php echo htmlspecialchars($next['title']); ?></div>
            <div class="hpanel-meta">
              <span class="hpanel-meta-item">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
                <?php echo htmlspecialchars($next['event_date'] ? date('M d, Y', strtotime($next['event_date'])) : 'TBA'); ?>
              </span>
              <span class="hpanel-meta-item">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
                <?php echo htmlspecialchars($next['location'] ?: 'Campus'); ?>
              </span>
            </div>
          <?php else: ?>
            <div class="hpanel-title">No upcoming events</div>
            <div class="hpanel-meta"><span>Check back soon</span></div>
          <?php endif; ?>
        </div>

        <div class="hpanel-stats">
          <div class="hpanel-stat">
            <strong><?php echo count($events); ?></strong>
            <span>Events</span>
          </div>
          <div class="hpanel-stat">
            <strong><?php echo $marketplace_items ? (int)$marketplace_items->num_rows : 0; ?></strong>
            <span>Listings</span>
          </div>
          <div class="hpanel-stat">
            <strong><?php echo $opportunities ? (int)$opportunities->num_rows : 0; ?></strong>
            <span>Opps.</span>
          </div>
        </div>

        <?php if (!empty($featured_posts)): $lp = $featured_posts[0]; ?>
        <div class="hpanel-card">
          <div class="hpanel-kicker">Latest post</div>
          <div class="hpanel-title"><?php echo htmlspecialchars($lp['title']); ?></div>
          <div class="hpanel-meta">
            <span class="hpanel-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><circle cx="8" cy="5.5" r="2.5"/><path d="M2 13.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke-linecap="round"/></svg>
              <?php echo htmlspecialchars($lp['author_name'] ?? 'Anonymous'); ?>
            </span>
            <span class="hpanel-meta-item">
              <?php echo htmlspecialchars(function_exists('time_ago') ? time_ago($lp['created_at']) : date('M d', strtotime($lp['created_at']))); ?>
            </span>
          </div>
        </div>
        <?php endif; ?>

        <div class="hpanel-links">
          <a class="hpanel-link" href="explore.php">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            Stories
          </a>
          <a class="hpanel-link" href="events.php">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Events
          </a>
          <a class="hpanel-link" href="marketplace.php">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            Market
          </a>
          <a class="hpanel-link" href="safespeak.php">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            SafeSpeak
          </a>
        </div>

      </div>
    </div>
  </div><!-- /.home-hero -->

  <!-- ══════════════ FEATURE STRIP ══════════════ -->
  <div class="feature-strip">
    <div class="feature-strip-inner">
      <a class="feat-link" href="marketplace.php">
        <span class="feat-icon" style="background:var(--accent-dim)">
          <svg fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </span>
        <span class="feat-text">
          <span class="feat-name">Marketplace</span>
          <span class="feat-desc">Buy, sell &amp; trade on campus</span>
        </span>
      </a>
      <a class="feat-link" href="opportunities.php">
        <span class="feat-icon" style="background:var(--sky-dim)">
          <svg fill="none" stroke="var(--sky)" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        </span>
        <span class="feat-text">
          <span class="feat-name">Opportunities</span>
          <span class="feat-desc">Jobs, internships &amp; scholarships</span>
        </span>
      </a>
      <a class="feat-link" href="events.php">
        <span class="feat-icon" style="background:var(--purple-dim)">
          <svg fill="none" stroke="var(--purple)" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </span>
        <span class="feat-text">
          <span class="feat-name">Events</span>
          <span class="feat-desc">Hackathons, workshops &amp; socials</span>
        </span>
      </a>
      <a class="feat-link" href="safespeak.php">
        <span class="feat-icon" style="background:var(--green-dim)">
          <svg fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </span>
        <span class="feat-text">
          <span class="feat-name">SafeSpeak</span>
          <span class="feat-desc">Report concerns anonymously</span>
        </span>
      </a>
    </div>
  </div>

  <!-- ══════════════ BODY ══════════════ -->
  <div class="home-body">

    <!-- ── Featured Posts ── -->
    <div class="sec-header sec-header--first">
      <div>
        <div class="sec-kicker">Community</div>
        <div class="sec-title">Featured Posts</div>
        <div class="sec-sub">Fresh stories and insights from your campus.</div>
      </div>
      <a class="sec-all" href="explore.php">
        View all
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>

    <?php if (!empty($featured_posts)): ?>
    <div class="posts-grid">

      <?php
        $main     = $featured_posts[0];
        $main_url = !empty($main['slug']) ? "post.php?slug=" . urlencode($main['slug']) : "post.php?id=" . $main['id'];
        $main_exc = $main['excerpt'] ?: substr(strip_tags($main['content'] ?? ''), 0, 200) . '…';
      ?>
      <a class="post-hero" href="<?php echo htmlspecialchars($main_url); ?>" style="grid-row:span <?php echo min(count($featured_posts) - 1, 3); ?>">
        <?php if (!empty($main['image'])): ?>
          <img src="<?php echo htmlspecialchars(get_post_image_url($main['image'])); ?>"
               alt="<?php echo htmlspecialchars($main['title']); ?>"
               loading="lazy">
        <?php endif; ?>
        <div class="post-hero-overlay"></div>
        <div class="post-hero-body">
          <?php if (!empty($main['category_name'])): ?>
            <span class="post-cat"><?php echo htmlspecialchars($main['category_name']); ?></span>
          <?php endif; ?>
          <h2 class="post-hero-title"><?php echo htmlspecialchars($main['title']); ?></h2>
          <p class="post-hero-excerpt"><?php echo htmlspecialchars($main_exc); ?></p>
          <div class="post-hero-foot">
            <span class="meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <?php echo htmlspecialchars($main['author_name'] ?? 'Anonymous'); ?>
            </span>
            <span class="meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <?php echo htmlspecialchars(function_exists('time_ago') ? time_ago($main['created_at']) : date('M d, Y', strtotime($main['created_at']))); ?>
            </span>
          </div>
        </div>
      </a>

      <?php foreach (array_slice($featured_posts, 1, 6) as $i => $post):
        $purl = !empty($post['slug']) ? "post.php?slug=" . urlencode($post['slug']) : "post.php?id=" . $post['id'];
        $exc  = $post['excerpt'] ?: substr(strip_tags($post['content'] ?? ''), 0, 90) . '…';
      ?>
      <a class="post-card" href="<?php echo htmlspecialchars($purl); ?>"
         style="animation-delay:<?php echo ($i * .05 + .08); ?>s">
        <?php if (!empty($post['category_name'])): ?>
          <span class="post-card-cat"><?php echo htmlspecialchars($post['category_name']); ?></span>
        <?php endif; ?>
        <h3 class="post-card-title"><?php echo htmlspecialchars($post['title']); ?></h3>
        <p class="post-card-excerpt"><?php echo htmlspecialchars($exc); ?></p>
        <div class="post-card-foot">
          <span class="meta-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php echo htmlspecialchars(function_exists('time_ago') ? time_ago($post['created_at']) : date('M d', strtotime($post['created_at']))); ?>
          </span>
          <span class="meta-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <?php echo number_format($post['view_count'] ?? 0); ?>
          </span>
        </div>
      </a>
      <?php endforeach; ?>

    </div>
    <?php else: ?>
    <div class="home-empty">
      <span class="home-empty-icon">📭</span>
      <p>No posts yet — check back soon.</p>
    </div>
    <?php endif; ?>

    <!-- ── Trending + Events ── -->
    <div class="two-col">

      <!-- Explore Posts -->
      <div class="home-panel">
        <div class="panel-head">
          <div class="panel-head-left">
            <span class="panel-icon" style="background:var(--accent-dim)">
              <svg fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
            </span>
            <span class="panel-title">Explore Posts</span>
          </div>
        </div>

        <?php if ($explore_posts && $explore_posts->num_rows > 0): $rank = 1; while ($post = $explore_posts->fetch_assoc()): ?>
        <?php
          $post_url = !empty($post['slug']) ? "post.php?slug=" . urlencode($post['slug']) : "post.php?id=" . (int)$post['id'];
          $excerpt  = $post['excerpt'] ?: substr(strip_tags($post['content'] ?? ''), 0, 90) . '…';
        ?>
        <a class="trend-item" href="<?php echo htmlspecialchars($post_url); ?>">
          <span class="trend-rank"><?php echo str_pad($rank, 2, '0', STR_PAD_LEFT); ?></span>
          <div class="trend-body">
            <div class="trend-name"><?php echo htmlspecialchars($post['title']); ?></div>
            <div class="trend-count"><?php echo htmlspecialchars($excerpt); ?></div>
          </div>
          <span class="trend-chevron">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
          </span>
        </a>
        <?php $rank++; endwhile; else: ?>
        <p style="padding:1.5rem 1.35rem;font-size:.845rem;color:var(--ink-light)">No posts to explore yet.</p>
        <?php endif; ?>
      </div>

      <!-- Upcoming Events -->
      <div class="home-panel">
        <div class="panel-head">
          <div class="panel-head-left">
            <span class="panel-icon" style="background:var(--purple-dim)">
              <svg fill="none" stroke="var(--purple)" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <span class="panel-title">Upcoming Events</span>
          </div>
          <a class="panel-viewall" href="events.php">See all →</a>
        </div>

        <?php if (!empty($events)): foreach ($events as $ev):
          $tl = strtolower($ev['title'] ?? '');
          if (strpos($tl,'workshop')!==false) $evType='Workshop';
          elseif (strpos($tl,'hack')!==false) $evType='Hackathon';
          elseif (strpos($tl,'competition')!==false) $evType='Competition';
          elseif (strpos($tl,'social')!==false) $evType='Social';
          else $evType='Event';
        ?>
        <a class="ev-item" href="events.php">
          <span class="ev-type"><?php echo htmlspecialchars($evType); ?></span>
          <div class="ev-title"><?php echo htmlspecialchars($ev['title']); ?></div>
          <div class="ev-meta">
            <span class="ev-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
              <?php echo htmlspecialchars($ev['event_date'] ? date('M d, Y', strtotime($ev['event_date'])) : 'TBA'); ?>
            </span>
            <span class="ev-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
              <?php echo htmlspecialchars($ev['location'] ?: 'Campus'); ?>
            </span>
          </div>
        </a>
        <?php endforeach; else: ?>
        <p style="padding:1.5rem 1.35rem;font-size:.845rem;color:var(--ink-light)">No upcoming events right now.</p>
        <?php endif; ?>
      </div>

    </div><!-- /.two-col -->

    <!-- ── Marketplace ── -->
    <div class="sec-header">
      <div>
        <div class="sec-kicker">Buy &amp; Sell</div>
        <div class="sec-title">Marketplace Highlights</div>
        <div class="sec-sub">Trending listings from fellow students.</div>
      </div>
      <a class="sec-all" href="marketplace.php">
        Browse all
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>

    <?php if ($marketplace_items && $marketplace_items->num_rows > 0): ?>
    <div class="market-grid">
      <?php while ($item = $marketplace_items->fetch_assoc()):
        $urgent = !empty($item['stock_status']) && strtolower($item['stock_status']) === 'urgent';
      ?>
      <a class="m-card" href="marketplace_item.php?id=<?php echo (int)$item['id']; ?>">
        <div class="m-media">
          <?php if (!empty($item['image_path'])): ?>
            <img src="<?php echo htmlspecialchars($item['image_path']); ?>"
                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                 loading="lazy">
          <?php else: ?>
            <div class="m-no-img">
              <svg fill="none" stroke="currentColor" stroke-width="1.3" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
          <?php endif; ?>
          <div class="m-badge-stack">
            <span class="m-badge m-badge--cat"><?php echo htmlspecialchars($item['category']); ?></span>
            <?php if ($urgent): ?><span class="m-badge m-badge--urgent">Urgent</span><?php endif; ?>
          </div>
          <?php if (!empty($item['item_condition'])): ?>
            <div class="m-cond-badge"><?php echo htmlspecialchars($item['item_condition']); ?></div>
          <?php endif; ?>
        </div>
        <div class="m-body">
          <div class="m-title"><?php echo htmlspecialchars($item['title']); ?></div>
          <div class="m-price">
            <?php echo !empty($item['price']) ? 'KSh ' . number_format((float)$item['price'], 0) : 'On request'; ?>
          </div>
          <div class="m-loc">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?php echo htmlspecialchars($item['location'] ?: 'Campus'); ?>
          </div>
        </div>
      </a>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="home-empty">
      <span class="home-empty-icon">🛒</span>
      <p>No listings yet — be the first to post.</p>
    </div>
    <?php endif; ?>

    <!-- ── Opportunities ── -->
    <div class="sec-header">
      <div>
        <div class="sec-kicker">Career</div>
        <div class="sec-title">Opportunities Preview</div>
        <div class="sec-sub">Roles, internships &amp; scholarships to watch.</div>
      </div>
      <a class="sec-all" href="opportunities.php">
        View all
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>

    <?php if ($opportunities && $opportunities->num_rows > 0): ?>
    <div class="opp-list">
      <?php $oi = 0; while ($opp = $opportunities->fetch_assoc()):
        $typeKey = strtolower(trim($opp['type'] ?? '')) ?: 'other';
        $icons   = ['internship'=>'🏢','job'=>'💼','scholarship'=>'🏆','attachment'=>'📋','other'=>'🔗'];
        $icon    = $icons[$typeKey] ?? '🔗';
        $oi++;
      ?>
      <a class="opp-row" href="<?php echo htmlspecialchars($opp['link'] ?: 'opportunities.php'); ?>"
         target="<?php echo !empty($opp['link']) ? '_blank' : '_self'; ?>"
         rel="noopener"
         data-type="<?php echo htmlspecialchars($typeKey); ?>"
         style="animation-delay:<?php echo $oi * .06; ?>s">
        <div class="opp-icon opp-icon--<?php echo htmlspecialchars($typeKey); ?>"><?php echo $icon; ?></div>
        <div class="opp-body">
          <div class="opp-badges">
            <span class="opp-badge opp-badge--<?php echo htmlspecialchars($typeKey); ?>">
              <?php echo htmlspecialchars(ucfirst($opp['type'] ?: 'Opportunity')); ?>
            </span>
          </div>
          <div class="opp-title"><?php echo htmlspecialchars($opp['title']); ?></div>
          <div class="opp-meta">
            <?php if (!empty($opp['organization'])): ?>
              <span class="opp-meta-item">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M6 14V9h4v5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 7h12" stroke-linecap="round"/></svg>
                <?php echo htmlspecialchars($opp['organization']); ?>
              </span>
            <?php endif; ?>
            <?php if (!empty($opp['location'])): ?>
              <span class="opp-meta-item">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
                <?php echo htmlspecialchars($opp['location']); ?>
              </span>
            <?php endif; ?>
            <?php if (!empty($opp['deadline'])): ?>
              <span class="opp-meta-item">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
                Deadline <?php echo htmlspecialchars(date('M d, Y', strtotime($opp['deadline']))); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
        <span class="opp-cta">
          View
          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </a>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="home-empty">
      <span class="home-empty-icon">📋</span>
      <p>No opportunities available right now.</p>
    </div>
    <?php endif; ?>

  </div><!-- /.home-body -->
</div><!-- /.home-page -->

<?php include '../shared/footer.php'; ?>
