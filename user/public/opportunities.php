<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';

$conn->query("CREATE TABLE IF NOT EXISTS opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    organization VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    link VARCHAR(500) DEFAULT NULL,
    description TEXT NOT NULL,
    requirements TEXT DEFAULT NULL,
    benefits TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

foreach (['type','deadline','organization','location','link','status','requirements','benefits'] as $col) {
    $r = $conn->query("SHOW COLUMNS FROM opportunities LIKE '$col'");
    if ($r && $r->num_rows === 0) {
        $defs = [
            'type'=>"VARCHAR(50) DEFAULT NULL",'deadline'=>"DATE DEFAULT NULL",
            'organization'=>"VARCHAR(255) DEFAULT NULL",'location'=>"VARCHAR(255) DEFAULT NULL",
            'link'=>"VARCHAR(500) DEFAULT NULL",'status'=>"ENUM('active','inactive') DEFAULT 'active'",
            'requirements'=>"TEXT DEFAULT NULL",'benefits'=>"TEXT DEFAULT NULL",
        ];
        $conn->query("ALTER TABLE opportunities ADD COLUMN $col {$defs[$col]}");
    }
}

$page_title      = 'Opportunities — ' . SITE_NAME;
$meta_description = 'Discover internships, attachments, jobs, and scholarships curated for Zetech students.';

$items = $conn->query("SELECT * FROM opportunities WHERE LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open') ORDER BY created_at DESC");

include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS  (matches detail page)
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

@media (max-width: 480px) {
  :root { --gutter: 1rem; }
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── Page shell ─── */
.opp-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO — full-width dark strip (same as detail)
═══════════════════════════════════════════ */
.opp-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

/* big serif watermark */
.opp-hero::before {
  content: 'Opp.';
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
.opp-hero::after {
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

/* stats row */
.hero-stats {
  display: flex;
  gap: 2.5rem;
  flex-wrap: wrap;
}
.hero-stat {}
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
   TOOLBAR — search + filter
═══════════════════════════════════════════ */
.opp-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.opp-toolbar-inner {
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

/* filter panel */
.opp-filter-panel {
  display: none;
  border-bottom: 1px solid var(--rule);
  background: var(--bg-warm);
}
.opp-filter-panel.active { display: block; animation: slideDown .18s ease; }
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-5px); }
  to   { opacity: 1; transform: translateY(0); }
}
.opp-filter-panel-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .85rem var(--gutter);
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
  align-items: center;
}
.filter-label {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-right: .25rem;
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
.opp-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
}

/* category tabs */
.opp-tabs {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--rule);
  margin-bottom: 1.75rem;
  overflow-x: auto;
  scrollbar-width: none;
}
.opp-tabs::-webkit-scrollbar { display: none; }

.opp-tab {
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
.opp-tab:hover { color: var(--ink-mid); }
.opp-tab.active {
  color: var(--ink);
  border-bottom-color: var(--ink);
  font-weight: 600;
}
.opp-tab-count {
  font-size: .68rem;
  font-weight: 600;
  background: var(--rule);
  color: var(--ink-light);
  border-radius: 99px;
  padding: .08rem .45rem;
}
.opp-tab.active .opp-tab-count {
  background: var(--ink);
  color: #fff;
}

/* results meta */
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
   OPPORTUNITY ROWS — card boxes
═══════════════════════════════════════════ */
.opp-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;          /* space between cards */
}

.opp-row {
  display: grid;
  grid-template-columns: 48px 1fr auto;
  gap: 1.25rem;
  align-items: start;
  padding: 1.5rem 1.5rem;  /* generous inner padding */
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: rowIn .35s ease both;
  position: relative;
}

.opp-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}

@keyframes rowIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}
.opp-row--expired {
  opacity: .55;
  background: var(--bg-warm);
}

/* left accent line per type */
.opp-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: var(--rule);
}
.opp-row[data-type="internship"]::before  { background: #3b82f6; }
.opp-row[data-type="attachment"]::before  { background: var(--purple); }
.opp-row[data-type="job"]::before         { background: var(--green); }
.opp-row[data-type="scholarship"]::before { background: var(--amber); }
.opp-row[data-type="other"]::before       { background: var(--sky); }

/* type icon */
.row-icon {
  width: 44px; height: 44px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  margin-top: .1rem;
}
.row-icon--internship  { background: #dbeafe; }
.row-icon--attachment  { background: var(--purple-dim); }
.row-icon--job         { background: var(--green-dim); }
.row-icon--scholarship { background: var(--amber-dim); }
.row-icon--other       { background: var(--sky-dim); }

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
.badge--internship  { background: #dbeafe; color: #1e40af; }
.badge--attachment  { background: var(--purple-dim); color: var(--purple); }
.badge--job         { background: var(--green-dim); color: var(--green); }
.badge--scholarship { background: var(--amber-dim); color: var(--amber); }
.badge--other       { background: var(--sky-dim); color: var(--sky); }
.badge--closing     { background: var(--accent-dim); color: var(--accent); }
.badge--expired     { background: var(--red-dim); color: var(--red); }
.badge--days        { background: var(--bg-warm); color: var(--ink-light); border: 1px solid var(--rule); }

/* title */
.row-title {
  font-family: var(--font-serif);
  font-size: clamp(1rem, 2vw, 1.2rem);
  font-weight: 600;
  color: var(--ink);
  line-height: 1.25;
  margin-bottom: .5rem;
}
.row-title a {
  color: inherit; text-decoration: none;
  transition: color var(--transition);
}
.row-title a:hover { color: var(--sky); }

/* meta facts */
.row-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem 1.25rem;
  font-size: .8rem;
  color: var(--ink-light);
  margin-bottom: .55rem;
  align-items: center;
}
.row-meta-item {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}
.row-meta-item svg { width: 12px; height: 12px; opacity: .7; flex-shrink: 0; }

/* description snippet — justified */
.row-desc {
  font-size: .855rem;
  color: var(--ink-mid);
  line-height: 1.6;
  text-align: justify;
  text-align-last: left;          /* last line stays left-aligned */
  hyphens: auto;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: .6rem;
}

/* benefit tags */
.row-tags {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem;
}
.row-tag {
  font-size: .68rem;
  font-weight: 500;
  background: var(--bg-warm);
  color: var(--ink-light);
  border: 1px solid var(--rule);
  border-radius: 2px;
  padding: .16rem .5rem;
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

.btn-primary {
  background: var(--ink);
  color: #fff;
}
.btn-primary:hover {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}
.btn-primary:disabled {
  background: var(--rule);
  color: var(--ink-light);
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
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

/* views micro-count */
.row-views {
  font-size: .72rem;
  color: var(--rule);
  display: inline-flex;
  align-items: center;
  gap: .25rem;
}
.row-views svg { width: 11px; height: 11px; }

/* ═══════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════ */
.opp-empty {
  text-align: center;
  padding: 5rem 1rem;
  color: var(--ink-light);
  border-top: 1px solid var(--rule-light);
}
.opp-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .4; }
.opp-empty h3 { font-family: var(--font-serif); font-size: 1.3rem; color: var(--ink-mid); margin-bottom: .4rem; }
.opp-empty p  { font-size: .875rem; }

/* ═══════════════════════════════════════════
   PAGINATION
═══════════════════════════════════════════ */
.opp-pagination {
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
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 640px) {
  .opp-row {
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
  .opp-toolbar-inner { gap: .5rem; }
}
@media (max-width: 420px) {
  .opp-toolbar-inner { flex-direction: column; align-items: stretch; }
  .filter-toggle { justify-content: center; }
}
</style>

<!-- ══════════ HERO ══════════ -->
<div class="opp-page">

  <div class="opp-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="live-dot"></span> Live opportunities</span>
      <h1 class="hero-headline">Find Your Next<br><em>Big Opportunity</em></h1>
      <p class="hero-sub">Internships, attachments, jobs, and scholarships handpicked for Zetech students. Your career starts here.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num" id="statTotal">–</span>
          <span class="hero-stat-label">Total</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num" id="statActive">–</span>
          <span class="hero-stat-label">Active</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num" id="statClosing">–</span>
          <span class="hero-stat-label">Closing Soon</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ STICKY TOOLBAR ══════════ -->
  <div class="opp-toolbar">
    <div class="opp-toolbar-inner">
      <div class="search-wrap">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="9" cy="9" r="6"/><path d="M15 15l3 3" stroke-linecap="round"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Search by title, organisation, or location…">
      </div>
      <button class="filter-toggle" id="filterBtn" type="button">
        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M1 3h12M3 7h8M5 11h4" stroke-linecap="round"/>
        </svg>
        Filters
      </button>
    </div>
  </div>

  <!-- filter panel -->
  <div class="opp-filter-panel" id="filterPanel">
    <div class="opp-filter-panel-inner">
      <span class="filter-label">Refine</span>
      <label class="filter-chip">
        <input type="checkbox" id="filterClosing">
        <svg width="12" height="12" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="6.5" cy="6.5" r="5"/><path d="M6.5 4v2.5l1.5 1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Closing soon
      </label>
      <label class="filter-chip">
        <input type="checkbox" id="filterWithLink">
        <svg width="12" height="12" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7a3 3 0 004.5.3l1.8-1.8a3 3 0 00-4.2-4.2L5.7 2.7" stroke-linecap="round"/><path d="M8 6a3 3 0 00-4.5-.3L1.7 7.5A3 3 0 005.9 11.7l1.4-1.4" stroke-linecap="round"/></svg>
        Has apply link
      </label>
      <label class="filter-chip">
        <input type="checkbox" id="filterRemote">
        <svg width="12" height="12" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="3" width="11" height="7" rx="1.5"/><path d="M4 10v2M9 10v2M3 12h7" stroke-linecap="round"/></svg>
        Remote / Online
      </label>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="opp-body">

    <!-- Category tabs -->
    <div class="opp-tabs" id="categoryTabs">
      <button class="opp-tab active" data-category="all">
        All <span class="opp-tab-count" id="countAll">0</span>
      </button>
      <button class="opp-tab" data-category="internship">
        Internships <span class="opp-tab-count" id="countInternship">0</span>
      </button>
      <button class="opp-tab" data-category="attachment">
        Attachments <span class="opp-tab-count" id="countAttachment">0</span>
      </button>
      <button class="opp-tab" data-category="job">
        Jobs <span class="opp-tab-count" id="countJob">0</span>
      </button>
      <button class="opp-tab" data-category="scholarship">
        Scholarships <span class="opp-tab-count" id="countScholarship">0</span>
      </button>
    </div>

    <!-- Results bar -->
    <div class="results-bar">
      <span><strong id="resultsCount">0</strong> opportunities found</span>
      <span id="resultsMeta"></span>
    </div>

    <?php if ($items && $items->num_rows > 0): ?>

      <div class="opp-list" id="opportunitiesList">
        <?php while ($row = $items->fetch_assoc()):
          /* deadline state */
          $daysLeftText = '';
          $deadlineState = 'open';
          $daysLeft = null;
          if (!empty($row['deadline'])) {
            $deadlineDate = new DateTime($row['deadline']);
            $today = new DateTime('today');
            $daysLeft = (int)$today->diff($deadlineDate)->format('%r%a');
            if ($daysLeft >= 0) {
              $daysLeftText  = $daysLeft === 0 ? 'Closes today' : $daysLeft . 'd left';
              $deadlineState = $daysLeft <= 3 ? 'closing' : 'open';
            } else {
              $deadlineState = 'expired';
            }
          }
          $typeKey  = strtolower(trim($row['type'] ?? '')) ?: 'other';
          $typeIcons = ['internship'=>'🏢','attachment'=>'📋','job'=>'💼','scholarship'=>'🏆','other'=>'🔗'];
          $typeIcon  = $typeIcons[$typeKey] ?? '🔗';
          $benefits  = trim((string)($row['benefits'] ?? ''));
          $benefitList = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $benefits))));
          $detailsUrl  = 'opportunity.php?id=' . (int)$row['id'];
          $isExpired   = ($deadlineState === 'expired');
        ?>
        <div class="opp-row <?php echo $isExpired ? 'opp-row--expired' : ''; ?>"
          data-title="<?php echo htmlspecialchars($row['title']); ?>"
          data-organization="<?php echo htmlspecialchars($row['organization'] ?: ''); ?>"
          data-location="<?php echo htmlspecialchars($row['location'] ?: ''); ?>"
          data-type="<?php echo htmlspecialchars($typeKey); ?>"
          data-deadline-state="<?php echo htmlspecialchars($deadlineState); ?>"
          data-has-link="<?php echo !empty($row['link']) ? '1' : '0'; ?>">

          <!-- icon -->
          <div class="row-icon row-icon--<?php echo htmlspecialchars($typeKey); ?>">
            <?php echo $typeIcon; ?>
          </div>

          <!-- body -->
          <div class="row-body">
            <div class="row-badges">
              <?php if (!empty($row['type'])): ?>
                <span class="badge badge--<?php echo htmlspecialchars($typeKey); ?>"><?php echo htmlspecialchars(ucfirst($row['type'])); ?></span>
              <?php endif; ?>
              <?php if ($deadlineState === 'closing'): ?>
                <span class="badge badge--closing">⚡ Closing soon</span>
              <?php elseif ($deadlineState === 'expired'): ?>
                <span class="badge badge--expired">Expired</span>
              <?php elseif (!empty($daysLeftText)): ?>
                <span class="badge badge--days"><?php echo htmlspecialchars($daysLeftText); ?></span>
              <?php endif; ?>
            </div>

            <h3 class="row-title">
              <a href="<?php echo htmlspecialchars($detailsUrl); ?>"><?php echo htmlspecialchars($row['title']); ?></a>
            </h3>

            <div class="row-meta">
              <?php if (!empty($row['organization'])): ?>
                <span class="row-meta-item">
                  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M6 14V9h4v5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 7h12" stroke-linecap="round"/></svg>
                  <?php echo htmlspecialchars($row['organization']); ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($row['location'])): ?>
                <span class="row-meta-item">
                  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
                  <?php echo htmlspecialchars($row['location']); ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($row['deadline'])): ?>
                <span class="row-meta-item">
                  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
                  <?php echo htmlspecialchars(date('M d, Y', strtotime($row['deadline']))); ?>
                </span>
              <?php endif; ?>
            </div>

            <p class="row-desc"><?php echo htmlspecialchars($row['description']); ?></p>

            <?php if (!empty($benefitList)): ?>
              <div class="row-tags">
                <?php foreach (array_slice($benefitList, 0, 4) as $b): ?>
                  <span class="row-tag"><?php echo htmlspecialchars($b); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- actions -->
          <div class="row-actions">
            <?php if (!empty($row['link']) && !$isExpired): ?>
              <a class="btn btn-primary" href="<?php echo htmlspecialchars($row['link']); ?>" target="_blank" rel="noopener noreferrer">
                Apply
                <svg width="10" height="10" viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </a>
            <?php else: ?>
              <button class="btn btn-primary" disabled><?php echo $isExpired ? 'Expired' : 'Closed'; ?></button>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?php echo htmlspecialchars($detailsUrl); ?>">Details</a>
            <button class="btn-icon" type="button" title="Share"
              data-share="true"
              data-title="<?php echo htmlspecialchars($row['title']); ?>"
              data-link="<?php echo htmlspecialchars($detailsUrl); ?>">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="3" r="2"/><circle cx="4" cy="8" r="2"/><circle cx="12" cy="13" r="2"/><path d="M6 7l4-2.5M6 9l4 2.5" stroke-linecap="round"/></svg>
            </button>
            <span class="row-views" data-view="<?php echo (int)$row['id']; ?>">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
              <span class="view-count">0</span>
            </span>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <div class="opp-pagination">
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
      <div class="opp-empty">
        <span class="opp-empty-icon">📭</span>
        <h3>No opportunities yet</h3>
        <p>Check back soon — new listings are added regularly.</p>
      </div>
    <?php endif; ?>

  </div><!-- /.opp-body -->
</div><!-- /.opp-page -->

<script>
(function () {
  const searchInput   = document.getElementById('searchInput');
  const tabs          = document.querySelectorAll('.opp-tab');
  const list          = document.getElementById('opportunitiesList');
  const resultsCount  = document.getElementById('resultsCount');
  const resultsMeta   = document.getElementById('resultsMeta');
  const prevPage      = document.getElementById('prevPage');
  const nextPage      = document.getElementById('nextPage');
  const pageIndicator = document.getElementById('pageIndicator');
  const filterBtn     = document.getElementById('filterBtn');
  const filterPanel   = document.getElementById('filterPanel');
  const filterClosing = document.getElementById('filterClosing');
  const filterWithLink= document.getElementById('filterWithLink');
  const filterRemote  = document.getElementById('filterRemote');

  const rows    = list ? Array.from(list.querySelectorAll('.opp-row')) : [];
  const pageSize = 8;
  let currentPage = 1;
  let activeCategory = 'all';

  /* ── View counter ── */
  const updateViews = () => {
    document.querySelectorAll('[data-view]').forEach(node => {
      const id  = node.getAttribute('data-view');
      const key = `opp_views_${id}`;
      const sKey= `opp_viewed_${id}`;
      const existing = parseInt(localStorage.getItem(key) || '0', 10);
      let updated = existing;
      if (!sessionStorage.getItem(sKey)) {
        updated = existing + 1;
        localStorage.setItem(key, String(updated));
        sessionStorage.setItem(sKey, 'true');
      }
      const el = node.querySelector('.view-count');
      if (el) el.textContent = updated.toLocaleString();
    });
  };

  /* ── Stats ── */
  const updateStats = () => {
    const total   = rows.length;
    const active  = rows.filter(r => r.dataset.deadlineState !== 'expired').length;
    const closing = rows.filter(r => r.dataset.deadlineState === 'closing').length;
    ['statTotal','statActive','statClosing'].forEach((id, i) => {
      const el = document.getElementById(id);
      if (el) el.textContent = [total, active, closing][i];
    });
  };

  /* ── Tab counts ── */
  const updateTabCounts = () => {
    const map = { all: rows.length };
    rows.forEach(r => { const t = r.dataset.type || 'other'; map[t] = (map[t]||0)+1; });
    ['all','internship','attachment','job','scholarship'].forEach(cat => {
      const el = document.getElementById('count' + cat.charAt(0).toUpperCase() + cat.slice(1));
      if (el) el.textContent = map[cat] || 0;
    });
  };

  /* ── Match ── */
  const matches = (row, query, category) => {
    const title    = (row.dataset.title || '').toLowerCase();
    const org      = (row.dataset.organization || '').toLowerCase();
    const location = (row.dataset.location || '').toLowerCase();
    const type     = row.dataset.type || 'other';
    const ds       = row.dataset.deadlineState || 'open';
    const hasLink  = row.dataset.hasLink === '1';
    const isRemote = location.includes('remote') || location.includes('online');

    return (!query || title.includes(query) || org.includes(query) || location.includes(query))
      && (category === 'all' || type === category)
      && (!filterClosing?.checked  || ds === 'closing')
      && (!filterWithLink?.checked || hasLink)
      && (!filterRemote?.checked   || isRemote);
  };

  /* ── Render ── */
  const render = () => {
    const query    = (searchInput?.value || '').trim().toLowerCase();
    const filtered = rows.filter(r => matches(r, query, activeCategory));
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

    if (resultsCount) resultsCount.textContent = total;
    if (resultsMeta)  resultsMeta.textContent  = total ? `Showing ${Math.min(end, total)} of ${total}` : '';
    if (pageIndicator) pageIndicator.textContent = `Page ${currentPage} of ${pages}`;
    if (prevPage) prevPage.disabled = currentPage === 1;
    if (nextPage) nextPage.disabled = currentPage === pages;
  };

  /* ── Search ── */
  if (searchInput) {
    let debounce;
    searchInput.addEventListener('input', () => {
      clearTimeout(debounce);
      debounce = setTimeout(() => { currentPage = 1; render(); }, 280);
    });
  }

  /* ── Filter panel toggle ── */
  if (filterBtn && filterPanel) {
    filterBtn.addEventListener('click', () => {
      filterPanel.classList.toggle('active');
      filterBtn.classList.toggle('active');
    });
  }
  [filterClosing, filterWithLink, filterRemote].forEach(el => {
    if (el) el.addEventListener('change', () => { currentPage = 1; render(); });
  });

  /* ── Tabs ── */
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeCategory = tab.dataset.category || 'all';
      currentPage = 1;
      render();
    });
  });

  /* ── Pagination ── */
  if (prevPage) prevPage.addEventListener('click', () => { currentPage = Math.max(1, currentPage - 1); render(); });
  if (nextPage) nextPage.addEventListener('click', () => { currentPage++; render(); });

  /* ── Share ── */
  document.querySelectorAll('[data-share]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const title = btn.dataset.title || 'Opportunity';
      const link  = btn.dataset.link  || window.location.href;
      if (navigator.share) {
        try { await navigator.share({ title, url: window.location.origin + '/' + link }); } catch (_) {}
      } else {
        try {
          await navigator.clipboard.writeText(window.location.origin + '/' + link);
          const svg = btn.querySelector('svg');
          if (svg) { svg.style.stroke = 'var(--green)'; setTimeout(() => svg.style.stroke = '', 1800); }
        } catch (_) {
          window.prompt('Copy this link:', link);
        }
      }
    });
  });

  updateViews();
  updateStats();
  updateTabCounts();
  render();
})();
</script>

<?php include '../shared/footer.php'; ?>
