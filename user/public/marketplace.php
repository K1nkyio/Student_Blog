<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';

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
    review_proof TEXT DEFAULT NULL,
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

$columns = ['item_condition','contact','link','stock_status','variant','delivery_estimate','features','specifications','materials','usage_instructions','review_proof'];
foreach ($columns as $col) {
    $check = $conn->query("SHOW COLUMNS FROM marketplace_items LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $type = in_array($col, ['features','specifications','materials','usage_instructions','review_proof','description']) ? 'TEXT' : 'VARCHAR(255)';
        if ($col === 'link') $type = 'VARCHAR(500)';
        $conn->query("ALTER TABLE marketplace_items ADD COLUMN $col $type DEFAULT NULL");
    }
}

$page_title = 'Marketplace — ' . SITE_NAME;
$meta_description = 'Buy, sell, and trade with fellow Zetech students. Find great deals on electronics, books, fashion, services, and accommodation.';

$items = $conn->query("SELECT * FROM marketplace_items WHERE LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open') ORDER BY created_at DESC");
$imagesResult = $conn->query("SELECT item_id, image_path FROM marketplace_item_images ORDER BY id ASC");
$itemImages = [];
if ($imagesResult) {
    while ($imgRow = $imagesResult->fetch_assoc()) {
        if (!isset($itemImages[$imgRow['item_id']])) {
            $itemImages[$imgRow['item_id']] = $imgRow['image_path'];
        }
    }
}
$imageCounts = [];
$imagesCountResult = $conn->query("SELECT item_id, COUNT(*) as cnt FROM marketplace_item_images GROUP BY item_id");
if ($imagesCountResult) {
    while ($countRow = $imagesCountResult->fetch_assoc()) {
        $imageCounts[$countRow['item_id']] = (int)$countRow['cnt'];
    }
}
$categories = [];
$categoryResult = $conn->query("SELECT DISTINCT category FROM marketplace_items WHERE LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open') ORDER BY category ASC");
if ($categoryResult) {
    while ($catRow = $categoryResult->fetch_assoc()) {
        if (!empty($catRow['category'])) $categories[] = $catRow['category'];
    }
}

include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS  (matches opportunities.php)
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

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── Page shell ─── */
.mp-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO — full-width dark strip
═══════════════════════════════════════════ */
.mp-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

/* big serif watermark */
.mp-hero::before {
  content: 'Mkt.';
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

/* subtle dot grid texture */
.mp-hero::after {
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
  text-align: justify;
}

/* hero bottom row: stats + CTA */
.hero-bottom {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1.5rem;
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

/* hero CTA button */
.hero-cta {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  background: var(--accent);
  color: #fff;
  text-decoration: none;
  padding: .7rem 1.4rem;
  border-radius: 3px;
  font-family: var(--font-sans);
  font-size: .8rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
  flex-shrink: 0;
}
.hero-cta:hover {
  background: #b05510;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(200,100,26,.35);
}
.hero-cta svg { width: 13px; height: 13px; }

/* ═══════════════════════════════════════════
   TOOLBAR — search + sort + filter
═══════════════════════════════════════════ */
.mp-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.mp-toolbar-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .85rem var(--gutter);
  display: flex;
  gap: .75rem;
  align-items: center;
  flex-wrap: wrap;
}

/* search */
.search-wrap {
  flex: 1;
  min-width: 200px;
  display: flex;
  align-items: center;
  gap: .6rem;
  border: 1.5px solid var(--rule);
  border-radius: 4px;
  padding: .55rem .9rem;
  background: var(--bg);
  transition: border-color var(--transition);
}
.search-wrap:focus-within {
  border-color: var(--ink);
  background: #fff;
}
.search-wrap svg { color: var(--ink-light); flex-shrink: 0; width: 15px; height: 15px; }
.search-wrap input {
  border: none; outline: none; flex: 1;
  font-size: .875rem; font-family: var(--font-sans);
  color: var(--ink); background: transparent;
}
.search-wrap input::placeholder { color: var(--ink-light); }

/* sort select */
.sort-select {
  border: 1.5px solid var(--rule);
  border-radius: 4px;
  padding: .55rem .9rem;
  background: var(--bg);
  font-family: var(--font-sans);
  font-size: .82rem;
  font-weight: 500;
  color: var(--ink-mid);
  outline: none;
  cursor: pointer;
  appearance: none;
  padding-right: 2rem;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237a7570' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  transition: border-color var(--transition);
}
.sort-select:hover, .sort-select:focus { border-color: var(--ink); }

/* filter toggle */
.filter-toggle {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  border: 1.5px solid var(--rule);
  border-radius: 4px;
  padding: .55rem 1rem;
  font-size: .82rem;
  font-weight: 500;
  font-family: var(--font-sans);
  color: var(--ink-mid);
  background: var(--bg);
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
}
.filter-toggle:hover, .filter-toggle.active {
  border-color: var(--ink);
  background: var(--bg-warm);
}
.filter-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  display: none;
  flex-shrink: 0;
}
.filter-dot.visible { display: inline-block; }

/* filter panel */
.mp-filter-panel {
  display: none;
  border-bottom: 1px solid var(--rule);
  background: var(--bg-warm);
}
.mp-filter-panel.active { display: block; animation: slideDown .18s ease; }
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-5px); }
  to   { opacity: 1; transform: translateY(0); }
}
.mp-filter-panel-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .85rem var(--gutter);
  display: flex;
  gap: 2rem;
  flex-wrap: wrap;
  align-items: flex-start;
}
.filter-group-title {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .45rem;
}
.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  border: 1.5px solid var(--rule);
  border-radius: 99px;
  padding: .28rem .8rem;
  font-size: .78rem;
  font-weight: 500;
  color: var(--ink-mid);
  cursor: pointer;
  transition: all var(--transition);
  background: var(--bg);
  user-select: none;
}
.filter-chip:has(input:checked) {
  border-color: var(--ink);
  background: var(--ink);
  color: #fff;
}
.filter-chip input { display: none; }

/* ═══════════════════════════════════════════
   BODY LAYOUT
═══════════════════════════════════════════ */
.mp-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
}

/* category tabs */
.mp-tabs {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--rule);
  margin-bottom: 1.75rem;
  overflow-x: auto;
  scrollbar-width: none;
}
.mp-tabs::-webkit-scrollbar { display: none; }

.mp-tab {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .65rem 1.1rem;
  font-size: .82rem;
  font-weight: 500;
  font-family: var(--font-sans);
  color: var(--ink-light);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: color var(--transition), border-color var(--transition);
  margin-bottom: -1px;
}
.mp-tab:hover { color: var(--ink-mid); }
.mp-tab.active {
  color: var(--ink);
  border-bottom-color: var(--ink);
  font-weight: 600;
}
.mp-tab-count {
  font-size: .68rem;
  font-weight: 600;
  background: var(--rule);
  color: var(--ink-light);
  border-radius: 99px;
  padding: .08rem .45rem;
}
.mp-tab.active .mp-tab-count {
  background: var(--ink);
  color: #fff;
}

/* results meta bar */
.results-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  font-size: .8rem;
  color: var(--ink-light);
  flex-wrap: wrap;
  gap: .5rem;
}
.results-bar strong { color: var(--ink-mid); font-weight: 600; }

/* active filter chips */
.active-filters {
  display: flex;
  gap: .35rem;
  flex-wrap: wrap;
}
.active-chip {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  background: var(--accent-dim);
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.2);
  padding: .2rem .6rem;
  border-radius: 99px;
  font-size: .72rem;
  font-weight: 600;
  cursor: pointer;
  transition: background var(--transition);
}
.active-chip:hover { background: #e8ccb0; }
.active-chip svg { width: 10px; height: 10px; }

/* ═══════════════════════════════════════════
   PRODUCT GRID
═══════════════════════════════════════════ */
.mp-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.1rem;
}

/* ═══════════════════════════════════════════
   PRODUCT CARD
═══════════════════════════════════════════ */
.mp-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  position: relative;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: cardIn .35s ease both;
}
.mp-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-3px);
}
@keyframes cardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.mp-card:nth-child(1) { animation-delay: .04s; }
.mp-card:nth-child(2) { animation-delay: .07s; }
.mp-card:nth-child(3) { animation-delay: .10s; }
.mp-card:nth-child(4) { animation-delay: .13s; }
.mp-card:nth-child(5) { animation-delay: .16s; }
.mp-card:nth-child(n+6) { animation-delay: .19s; }

/* ─── card image ─── */
.mp-card-media {
  position: relative;
  aspect-ratio: 4/3;
  background: var(--bg-warm);
  overflow: hidden;
  flex-shrink: 0;
}
.mp-card-media img {
  width: 100%; height: 100%;
  object-fit: cover;
  display: block;
  transition: transform .4s ease;
}
.mp-card:hover .mp-card-media img { transform: scale(1.05); }

/* no-image placeholder */
.mp-card-no-img {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: .5rem;
  background: linear-gradient(135deg, var(--bg-warm) 0%, var(--bg-warmer) 100%);
}
.mp-card-no-img svg { width: 28px; height: 28px; color: var(--rule); }
.mp-card-no-img span {
  font-size: .62rem;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
}

/* badges overlay */
.mp-card-badges {
  position: absolute;
  top: 9px; left: 9px;
  display: flex; gap: .3rem; flex-wrap: wrap;
  z-index: 2;
}
.mp-badge {
  padding: .2rem .55rem;
  border-radius: 2px;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .05em;
  text-transform: uppercase;
  white-space: nowrap;
}
.mp-badge-cat  { background: rgba(24,22,15,.72); color: rgba(255,255,255,.92); }
.mp-badge-cond { background: rgba(245,242,237,.92); color: var(--ink-mid); border: 1px solid rgba(24,22,15,.08); }
.mp-badge-urgent { background: var(--red); color: #fff; }

/* photo count */
.mp-photo-count {
  position: absolute;
  bottom: 8px; right: 8px;
  background: rgba(24,22,15,.58);
  color: rgba(255,255,255,.85);
  font-size: .62rem;
  padding: .18rem .5rem;
  border-radius: 2px;
  display: flex; align-items: center; gap: .3rem;
  z-index: 2;
}
.mp-photo-count svg { width: 10px; height: 10px; }

/* wishlist button */
.mp-wishlist-btn {
  position: absolute;
  top: 8px; right: 8px;
  width: 30px; height: 30px;
  border-radius: 3px;
  border: 1.5px solid rgba(245,242,237,.85);
  background: rgba(245,242,237,.92);
  color: var(--ink-light);
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: color var(--transition), transform var(--transition), background var(--transition);
  z-index: 3;
}
.mp-wishlist-btn:hover { color: var(--red); background: #fff; transform: scale(1.1); }
.mp-wishlist-btn.active { color: var(--red); }
.mp-wishlist-btn svg { width: 13px; height: 13px; }

/* ─── card body ─── */
.mp-card-body {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: .5rem;
  flex: 1;
}

.mp-card-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .6rem;
}

.mp-card-title {
  font-family: var(--font-sans);
  font-size: .875rem;
  font-weight: 600;
  color: var(--ink);
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.55em;
  min-width: 0;
}

.mp-card-price-row {
  display: flex;
  align-items: baseline;
  gap: .4rem;
  flex-shrink: 0;
  white-space: nowrap;
}
.mp-card-price {
  font-family: var(--font-serif);
  font-style: italic;
  font-size: 1.15rem;
  color: var(--ink);
  line-height: 1;
}
.mp-card-currency {
  font-size: .65rem;
  color: var(--ink-light);
}
.mp-price-on-request {
  font-size: .75rem;
  color: var(--ink-light);
  letter-spacing: .04em;
}

/* tags */
.mp-card-tags {
  display: flex; flex-wrap: wrap; gap: .3rem;
}
.mp-tag {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .05em;
  text-transform: uppercase;
  padding: .18rem .5rem;
  border-radius: 2px;
  background: var(--bg-warm);
  color: var(--ink-light);
  border: 1px solid var(--rule);
}
.mp-tag-negotiable { background: var(--green-dim); color: var(--green); border-color: rgba(26,122,74,.15); }
.mp-tag-urgent     { background: var(--red-dim);   color: var(--red);   border-color: rgba(176,48,48,.15); }

/* meta row */
.mp-card-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: .72rem;
  color: var(--ink-light);
  padding-top: .4rem;
  border-top: 1px solid var(--rule-light);
  margin-top: auto;
}
.mp-card-meta-loc {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}
.mp-card-meta-loc svg { width: 10px; height: 10px; }

/* ─── card actions ─── */
.mp-card-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .4rem;
  padding: 0 1rem 1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-family: var(--font-sans);
  font-size: .8rem;
  font-weight: 600;
  text-decoration: none;
  padding: .55rem 1rem;
  border-radius: 3px;
  border: none;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
  line-height: 1;
  justify-content: center;
  width: 100%;
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

/* ═══════════════════════════════════════════
   PAGINATION
═══════════════════════════════════════════ */
.mp-pagination {
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
  transition: all var(--transition);
}
.page-btn:hover:not(:disabled) { border-color: var(--ink); background: var(--bg-warm); }
.page-btn:disabled { opacity: .35; cursor: not-allowed; }
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
   EMPTY STATE
═══════════════════════════════════════════ */
.mp-empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 5rem 1rem;
  color: var(--ink-light);
}
.mp-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .4; }
.mp-empty h3 { font-family: var(--font-serif); font-size: 1.3rem; color: var(--ink-mid); margin-bottom: .4rem; }
.mp-empty p  { font-size: .875rem; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 1200px) { .mp-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px)  { .mp-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) {
  .mp-grid { grid-template-columns: 1fr; gap: .75rem; }
  .hero-stats { gap: 1.5rem; }
  .hero-bottom { flex-direction: column; align-items: flex-start; }
  .mp-toolbar-inner { gap: .5rem; }
}
@media (max-width: 420px) {
  .mp-toolbar-inner { flex-direction: column; align-items: stretch; }
  .filter-toggle { justify-content: center; }
}
@media (max-width: 480px) {
  :root { --gutter: 1rem; }
}
</style>

<!-- ══════════ HERO ══════════ -->
<div class="mp-page">

  <div class="mp-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="live-dot"></span> Student marketplace</span>
      <h1 class="hero-headline">Buy, Sell &amp;<br><em>Trade on Campus</em></h1>
      <p class="hero-sub">Electronics, books, fashion, services, and accommodation — all curated for Zetech students. Great deals from people you know.</p>
      <div class="hero-bottom">
        <div class="hero-stats">
          <div class="hero-stat">
            <span class="hero-stat-num" id="statTotal">–</span>
            <span class="hero-stat-label">Active listings</span>
          </div>
          <div class="hero-stat">
            <span class="hero-stat-num"><?php echo count($categories); ?></span>
            <span class="hero-stat-label">Categories</span>
          </div>
        </div>
        <a class="hero-cta" href="admin/marketplace.php">
          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Post a listing
        </a>
      </div>
    </div>
  </div>

  <!-- ══════════ STICKY TOOLBAR ══════════ -->
  <div class="mp-toolbar">
    <div class="mp-toolbar-inner">
      <div class="search-wrap">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="9" cy="9" r="6"/><path d="M15 15l3 3" stroke-linecap="round"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Search listings, location…">
      </div>
      <select class="sort-select" id="sortSelect">
        <option value="newest">Newest first</option>
        <option value="price-low">Price: low → high</option>
        <option value="price-high">Price: high → low</option>
      </select>
      <button class="filter-toggle" id="filterBtn" type="button">
        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M1 3h12M3 7h8M5 11h4" stroke-linecap="round"/>
        </svg>
        Filters
        <span class="filter-dot" id="filterDot"></span>
      </button>
    </div>
  </div>

  <!-- filter panel -->
  <div class="mp-filter-panel" id="filterPanel">
    <div class="mp-filter-panel-inner">
      <div>
        <div class="filter-group-title">Availability</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.3rem">
          <label class="filter-chip">
            <input type="checkbox" id="filterUrgent"> Urgent only
          </label>
          <label class="filter-chip">
            <input type="checkbox" id="filterNegotiable"> Negotiable
          </label>
        </div>
      </div>
      <div>
        <div class="filter-group-title">Media</div>
        <div style="margin-top:.3rem">
          <label class="filter-chip">
            <input type="checkbox" id="filterWithPhotos"> Has photos
          </label>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="mp-body">

    <!-- Category tabs -->
    <div class="mp-tabs" id="categoryTabs">
      <button class="mp-tab active" data-category="all">
        All <span class="mp-tab-count" id="countAll">0</span>
      </button>
      <?php foreach ($categories as $category): ?>
        <button class="mp-tab" data-category="<?php echo htmlspecialchars(strtolower($category)); ?>">
          <?php echo htmlspecialchars($category); ?>
          <span class="mp-tab-count" id="count-<?php echo htmlspecialchars(strtolower(preg_replace('/\s+/', '-', $category))); ?>">0</span>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Results bar -->
    <div class="results-bar">
      <span><strong id="resultsCount">0</strong> listings found</span>
      <div class="active-filters" id="activeFilters"></div>
    </div>

    <?php if ($items && $items->num_rows > 0): ?>

      <div class="mp-grid" id="marketplaceGrid">
        <?php while ($row = $items->fetch_assoc()):
          $primaryImage = $itemImages[$row['id']] ?? '';
          $photoCount   = $imageCounts[$row['id']] ?? 0;
          $isUrgent     = !empty($row['stock_status']) && strtolower(trim($row['stock_status'])) === 'urgent';
          $isNegotiable = !empty($row['stock_status']) && strtolower(trim($row['stock_status'])) === 'negotiable';
          $priceValue   = !empty($row['price']) ? (float)$row['price'] : 0;
          $catKey       = strtolower($row['category']);
          $catSlug      = preg_replace('/\s+/', '-', $catKey);
        ?>
        <article class="mp-card"
          data-title="<?php echo htmlspecialchars($row['title']); ?>"
          data-category="<?php echo htmlspecialchars($catKey); ?>"
          data-location="<?php echo htmlspecialchars(strtolower($row['location'] ?? '')); ?>"
          data-price="<?php echo htmlspecialchars($priceValue); ?>"
          data-urgent="<?php echo $isUrgent ? '1' : '0'; ?>"
          data-negotiable="<?php echo $isNegotiable ? '1' : '0'; ?>"
          data-photos="<?php echo $photoCount > 0 ? '1' : '0'; ?>"
          onclick="window.location='marketplace_item.php?id=<?php echo (int)$row['id']; ?>'">

          <!-- image -->
          <div class="mp-card-media">
            <?php if ($primaryImage): ?>
              <img src="<?php echo htmlspecialchars($primaryImage); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" loading="lazy">
            <?php else: ?>
              <div class="mp-card-no-img">
                <svg fill="none" stroke="currentColor" stroke-width="1.3" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span>No image</span>
              </div>
            <?php endif; ?>

            <div class="mp-card-badges">
              <span class="mp-badge mp-badge-cat"><?php echo htmlspecialchars($row['category']); ?></span>
              <?php if (!empty($row['item_condition'])): ?>
                <span class="mp-badge mp-badge-cond"><?php echo htmlspecialchars($row['item_condition']); ?></span>
              <?php endif; ?>
              <?php if ($isUrgent): ?>
                <span class="mp-badge mp-badge-urgent">Urgent</span>
              <?php endif; ?>
            </div>

            <?php if ($photoCount > 1): ?>
              <div class="mp-photo-count">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <?php echo $photoCount; ?>
              </div>
            <?php endif; ?>

            <button class="mp-wishlist-btn" type="button"
              onclick="event.stopPropagation(); this.classList.toggle('active'); this.querySelector('svg').setAttribute('fill', this.classList.contains('active') ? 'currentColor' : 'none')">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
          </div>

          <!-- body -->
          <div class="mp-card-body">
            <div class="mp-card-title-row">
              <h3 class="mp-card-title"><?php echo htmlspecialchars($row['title']); ?></h3>

              <div class="mp-card-price-row">
                <?php if (!empty($row['price'])): ?>
                  <div class="mp-card-price">KSh <?php echo number_format((float)$row['price'], 0); ?></div>
                  <span class="mp-card-currency">KES</span>
                <?php else: ?>
                  <div class="mp-price-on-request">Price on request</div>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($isNegotiable || $isUrgent || (!empty($row['stock_status']) && !$isNegotiable && !$isUrgent)): ?>
            <div class="mp-card-tags">
              <?php if ($isNegotiable): ?>
                <span class="mp-tag mp-tag-negotiable">Negotiable</span>
              <?php endif; ?>
              <?php if ($isUrgent): ?>
                <span class="mp-tag mp-tag-urgent">Urgent</span>
              <?php elseif (!empty($row['stock_status']) && !$isNegotiable && !$isUrgent): ?>
                <span class="mp-tag"><?php echo htmlspecialchars($row['stock_status']); ?></span>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="mp-card-meta">
              <span class="mp-card-meta-loc">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?php echo htmlspecialchars($row['location'] ?: 'Campus'); ?>
              </span>
              <span><?php echo date('M j', strtotime($row['created_at'])); ?></span>
            </div>
          </div>

          <!-- actions -->
          <div class="mp-card-actions" onclick="event.stopPropagation()">
            <a class="btn btn-primary" href="marketplace_item.php?id=<?php echo (int)$row['id']; ?>">
              View listing
              <svg width="10" height="10" viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <a class="btn btn-ghost" href="marketplace_item.php?id=<?php echo (int)$row['id']; ?>#details">Details</a>
          </div>

        </article>
        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <div class="mp-pagination">
        <button class="page-btn" id="prevPage">
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Prev
        </button>
        <div class="page-info" id="pageIndicator">Page 1 of 1</div>
        <button class="page-btn" id="nextPage">
          Next
          <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>

    <?php else: ?>
      <div class="mp-grid">
        <div class="mp-empty">
          <span class="mp-empty-icon">🛍️</span>
          <h3>No listings yet</h3>
          <p>Be the first to post something for sale!</p>
        </div>
      </div>
    <?php endif; ?>

  </div><!-- /.mp-body -->
</div><!-- /.mp-page -->

<script>
(function () {
  const searchInput    = document.getElementById('searchInput');
  const tabs           = document.querySelectorAll('.mp-tab');
  const grid           = document.getElementById('marketplaceGrid');
  const resultsCount   = document.getElementById('resultsCount');
  const prevPage       = document.getElementById('prevPage');
  const nextPage       = document.getElementById('nextPage');
  const pageIndicator  = document.getElementById('pageIndicator');
  const filterBtn      = document.getElementById('filterBtn');
  const filterPanel    = document.getElementById('filterPanel');
  const filterUrgent   = document.getElementById('filterUrgent');
  const filterNegotiable = document.getElementById('filterNegotiable');
  const filterWithPhotos = document.getElementById('filterWithPhotos');
  const filterDot      = document.getElementById('filterDot');
  const sortSelect     = document.getElementById('sortSelect');
  const activeFiltersEl = document.getElementById('activeFilters');

  const cards    = grid ? Array.from(grid.querySelectorAll('.mp-card')) : [];
  const pageSize = 9;
  let currentPage     = 1;
  let activeCategory  = 'all';

  /* ── Hero stat ── */
  const statTotal = document.getElementById('statTotal');
  if (statTotal) statTotal.textContent = cards.length;

  /* ── Tab counts ── */
  const updateTabCounts = () => {
    const map = { all: cards.length };
    cards.forEach(c => {
      const cat = c.dataset.category || '';
      if (cat) map[cat] = (map[cat] || 0) + 1;
    });
    // update "All" tab
    const allEl = document.getElementById('countAll');
    if (allEl) allEl.textContent = map.all;
    // update category tabs
    tabs.forEach(tab => {
      const cat = tab.dataset.category;
      if (cat && cat !== 'all') {
        const slug = cat.replace(/\s+/g, '-');
        const el = document.getElementById('count-' + slug);
        if (el) el.textContent = map[cat] || 0;
      }
    });
  };

  /* ── Filter dot ── */
  const updateFilterDot = () => {
    const active = [filterUrgent, filterNegotiable, filterWithPhotos].some(i => i?.checked);
    filterDot?.classList.toggle('visible', active);
  };

  /* ── Active filter chips ── */
  const buildChips = (query) => {
    if (!activeFiltersEl) return;
    activeFiltersEl.innerHTML = '';
    const filters = [];
    if (query) filters.push({ key: 'search', label: '"' + query + '"' });
    if (activeCategory !== 'all') filters.push({ key: 'category', label: activeCategory });
    if (filterUrgent?.checked)     filters.push({ key: 'urgent',     label: 'Urgent' });
    if (filterNegotiable?.checked) filters.push({ key: 'negotiable', label: 'Negotiable' });
    if (filterWithPhotos?.checked) filters.push({ key: 'photos',     label: 'Has photos' });
    filters.forEach(f => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'active-chip';
      btn.innerHTML = f.label + '<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
      btn.addEventListener('click', () => {
        if (f.key === 'search' && searchInput) searchInput.value = '';
        if (f.key === 'category') { activeCategory = 'all'; tabs.forEach(t => t.classList.remove('active')); tabs[0]?.classList.add('active'); }
        if (f.key === 'urgent'     && filterUrgent)     filterUrgent.checked = false;
        if (f.key === 'negotiable' && filterNegotiable) filterNegotiable.checked = false;
        if (f.key === 'photos'     && filterWithPhotos) filterWithPhotos.checked = false;
        currentPage = 1; render();
      });
      activeFiltersEl.appendChild(btn);
    });
  };

  /* ── Match ── */
  const matches = (card, query, cat) => {
    const title = (card.dataset.title    || '').toLowerCase();
    const loc   = (card.dataset.location || '').toLowerCase();
    const cardCat = card.dataset.category || '';
    return (!query || title.includes(query) || loc.includes(query))
      && (cat === 'all' || cardCat === cat)
      && (!filterUrgent?.checked     || card.dataset.urgent === '1')
      && (!filterNegotiable?.checked || card.dataset.negotiable === '1')
      && (!filterWithPhotos?.checked || card.dataset.photos === '1');
  };

  /* ── Render ── */
  const render = () => {
    const query = (searchInput?.value || '').trim().toLowerCase();
    const sort  = sortSelect?.value || 'newest';
    const filtered = cards.filter(c => matches(c, query, activeCategory));
    const sorted   = [...filtered].sort((a, b) => {
      const pA = parseFloat(a.dataset.price || 0);
      const pB = parseFloat(b.dataset.price || 0);
      if (sort === 'price-low')  return pA - pB;
      if (sort === 'price-high') return pB - pA;
      return 0;
    });

    const total  = sorted.length;
    const pages  = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > pages) currentPage = pages;
    const start = (currentPage - 1) * pageSize;
    const end   = start + pageSize;

    cards.forEach(c => (c.style.display = 'none'));
    sorted.slice(start, end).forEach(c => (c.style.display = 'flex'));

    if (resultsCount)  resultsCount.textContent  = String(total);
    if (pageIndicator) pageIndicator.textContent = `Page ${currentPage} of ${pages}`;
    if (prevPage) prevPage.disabled = currentPage === 1;
    if (nextPage) nextPage.disabled = currentPage === pages;

    buildChips(query);
    updateFilterDot();
  };

  /* ── Events ── */
  let debounce;
  searchInput?.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => { currentPage = 1; render(); }, 280);
  });

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeCategory = tab.dataset.category || 'all';
      currentPage = 1;
      render();
    });
  });

  [filterUrgent, filterNegotiable, filterWithPhotos].forEach(el => {
    el?.addEventListener('change', () => { currentPage = 1; render(); });
  });

  sortSelect?.addEventListener('change', () => { currentPage = 1; render(); });

  filterBtn?.addEventListener('click', () => {
    filterPanel?.classList.toggle('active');
    filterBtn.classList.toggle('active');
  });

  prevPage?.addEventListener('click', () => { currentPage = Math.max(1, currentPage - 1); render(); });
  nextPage?.addEventListener('click', () => { currentPage++; render(); });

  updateTabCounts();
  render();
})();
</script>

<?php include '../shared/footer.php'; ?>
