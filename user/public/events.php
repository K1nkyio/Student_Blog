<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';

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

$columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'event_date'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE events ADD COLUMN event_date DATE DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'status'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE events ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM events LIKE 'image'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE events ADD COLUMN image VARCHAR(255) DEFAULT NULL");
}
$conn->query("UPDATE events SET status = 'active' WHERE status IS NULL OR status = ''");

$page_title = 'Events — ' . SITE_NAME;
$meta_description = 'Discover hackathons, workshops, social events, and competitions happening at Zetech.';

$items = $conn->query("SELECT * FROM events
    WHERE LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open')
    ORDER BY event_date DESC, created_at DESC");
if (!$items) {
    $items = $conn->query("SELECT * FROM events ORDER BY event_date DESC, created_at DESC");
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
  --rose:         #b03060;
  --rose-dim:     #f8e0ec;

  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-sans:  'Outfit', sans-serif;

  --max-w: 1360px;
  --gutter: 1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─── Page shell ─── */
.ev-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO — full-width dark strip
═══════════════════════════════════════════ */
.ev-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

/* big serif watermark */
.ev-hero::before {
  content: 'Evts.';
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
.ev-hero::after {
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
.ev-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.ev-toolbar-inner {
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

/* results count in toolbar */
.toolbar-count {
  font-size: .8rem;
  color: var(--ink-light);
  white-space: nowrap;
}
.toolbar-count strong { color: var(--ink-mid); font-weight: 600; }

/* ═══════════════════════════════════════════
   BODY LAYOUT
═══════════════════════════════════════════ */
.ev-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
}

/* category tabs */
.ev-tabs {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--rule);
  margin-bottom: 1.75rem;
  overflow-x: auto;
  scrollbar-width: none;
}
.ev-tabs::-webkit-scrollbar { display: none; }

.ev-tab {
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
.ev-tab:hover { color: var(--ink-mid); }
.ev-tab.active {
  color: var(--ink);
  border-bottom-color: var(--ink);
  font-weight: 600;
}
.ev-tab-count {
  font-size: .68rem;
  font-weight: 600;
  background: var(--rule);
  color: var(--ink-light);
  border-radius: 99px;
  padding: .08rem .45rem;
}
.ev-tab.active .ev-tab-count {
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
   EVENT ROWS — card boxes
═══════════════════════════════════════════ */
.ev-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(320px, 1fr));
  gap: 1.6rem;
  grid-auto-rows: 1fr;
}

.ev-row {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: stretch;
  padding: 1.65rem 1.6rem;
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: rowIn .35s ease both;
  position: relative;
  height: 100%;
}

.ev-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}

@keyframes rowIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}

.ev-row--past {
  opacity: .55;
  background: var(--bg-warm);
}

/* left accent line per type */
.ev-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: var(--rule);
}
.ev-row[data-type="hackathon"]::before   { background: var(--accent); }
.ev-row[data-type="workshop"]::before    { background: var(--purple); }
.ev-row[data-type="competition"]::before { background: var(--amber); }
.ev-row[data-type="social"]::before      { background: var(--green); }

/* type icon */
.row-icon {
  width: 44px; height: 44px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  margin-top: .1rem;
}
.row-icon--hackathon   { background: var(--accent-dim); }
.row-icon--workshop    { background: var(--purple-dim); }
.row-icon--competition { background: var(--amber-dim); }
.row-icon--social      { background: var(--green-dim); }

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
.badge--hackathon   { background: var(--accent-dim); color: var(--accent); }
.badge--workshop    { background: var(--purple-dim); color: var(--purple); }
.badge--competition { background: var(--amber-dim);  color: var(--amber); }
.badge--social      { background: var(--green-dim);  color: var(--green); }
.badge--upcoming    { background: var(--sky-dim);    color: var(--sky); }
.badge--past        { background: var(--rule-light); color: var(--ink-light); border: 1px solid var(--rule); }

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

/* media */
.row-media {
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: 6px;
  overflow: hidden;
  background: var(--bg-warm);
  margin-bottom: .8rem;
}
.row-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* description snippet */
.row-desc {
  font-size: .855rem;
  color: var(--ink-mid);
  line-height: 1.6;
  text-align: justify;
  text-align-last: left;
  hyphens: auto;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: .6rem;
}

/* row actions */
.row-actions {
  display: flex;
  flex-direction: row;
  gap: .5rem;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  margin-top: auto;
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
.btn-icon.copied { border-color: var(--green); color: var(--green); }

/* ═══════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════ */
.ev-empty {
  text-align: center;
  padding: 5rem 1rem;
  color: var(--ink-light);
  border-top: 1px solid var(--rule-light);
}
.ev-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .4; }
.ev-empty h3 { font-family: var(--font-serif); font-size: 1.3rem; color: var(--ink-mid); margin-bottom: .4rem; }
.ev-empty p  { font-size: .875rem; }

/* ═══════════════════════════════════════════
   PAGINATION
═══════════════════════════════════════════ */
.ev-pagination {
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
@media (max-width: 1200px) {
  .ev-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
  .ev-list { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .ev-row { padding: 1.1rem 1.1rem; }
  .hero-stats { gap: 1.5rem; }
  .ev-toolbar-inner { gap: .5rem; }
}
@media (max-width: 420px) {
  .ev-toolbar-inner { flex-direction: column; align-items: stretch; }
}
</style>

<!-- ══════════ HERO ══════════ -->
<div class="ev-page">

  <div class="ev-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="live-dot"></span> Upcoming events</span>
      <h1 class="hero-headline">Campus Events<br><em>Worth Showing Up For</em></h1>
      <p class="hero-sub">Hackathons, workshops, socials, and competitions curated for Zetech students. Find what excites you, then show up.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num" id="statTotal">–</span>
          <span class="hero-stat-label">Total</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num" id="statUpcoming">–</span>
          <span class="hero-stat-label">Upcoming</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num" id="statCategories">4</span>
          <span class="hero-stat-label">Categories</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ STICKY TOOLBAR ══════════ -->
  <div class="ev-toolbar">
    <div class="ev-toolbar-inner">
      <div class="search-wrap">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="9" cy="9" r="6"/><path d="M15 15l3 3" stroke-linecap="round"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Search by title, organiser, or location…">
      </div>
      <span class="toolbar-count"><strong id="resultsCount">0</strong> events found</span>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="ev-body">

    <!-- Category tabs -->
    <div class="ev-tabs" id="categoryTabs">
      <button class="ev-tab active" data-category="all">
        All <span class="ev-tab-count" id="countAll">0</span>
      </button>
      <button class="ev-tab" data-category="hackathon">
        Hackathons <span class="ev-tab-count" id="countHackathon">0</span>
      </button>
      <button class="ev-tab" data-category="workshop">
        Workshops <span class="ev-tab-count" id="countWorkshop">0</span>
      </button>
      <button class="ev-tab" data-category="social">
        Social <span class="ev-tab-count" id="countSocial">0</span>
      </button>
      <button class="ev-tab" data-category="competition">
        Competitions <span class="ev-tab-count" id="countCompetition">0</span>
      </button>
    </div>

    <!-- Results bar -->
    <div class="results-bar">
      <span><strong id="resultsCountBody">0</strong> events found</span>
      <span id="resultsMeta"></span>
    </div>

    <?php if ($items && $items->num_rows > 0): ?>

      <div class="ev-list" id="eventsList">
        <?php while ($row = $items->fetch_assoc()):
          /* Classify type from title/description/organizer */
          $titleLow   = strtolower($row['title'] ?? '');
          $descLow    = strtolower($row['description'] ?? '');
          $orgLow     = strtolower($row['organizer'] ?? '');
          $content    = $titleLow . ' ' . $descLow . ' ' . $orgLow;

          $typeKey = 'social';
          if (strpos($content, 'hack') !== false) {
              $typeKey = 'hackathon';
          } elseif (strpos($content, 'workshop') !== false) {
              $typeKey = 'workshop';
          } elseif (strpos($content, 'competition') !== false || strpos($content, 'contest') !== false) {
              $typeKey = 'competition';
          }

          $typeLabels = [
              'hackathon'   => 'Hackathon',
              'workshop'    => 'Workshop',
              'competition' => 'Competition',
              'social'      => 'Social',
          ];
          $typeIcons = [
              'hackathon'   => '⚡',
              'workshop'    => '📖',
              'competition' => '🏆',
              'social'      => '🎉',
          ];
          $typeLabel = $typeLabels[$typeKey] ?? ucfirst($typeKey);
          $typeIcon  = $typeIcons[$typeKey] ?? '📅';

          /* Date state */
          $isPast     = false;
          $dateStr    = '';
          $isUpcoming = false;
          if (!empty($row['event_date'])) {
              $ts         = strtotime($row['event_date']);
              $dateStr    = date('M d, Y', $ts);
              $isPast     = $ts < strtotime('today');
              $isUpcoming = !$isPast;
          }

          $detailLink = "event.php?id=" . (int)$row['id'];
          $regLink = htmlspecialchars($row['registration_link'] ?: '#');
          $eventImage = !empty($row['image']) ? '../' . ltrim($row['image'], '/') : '';
        ?>
        <div class="ev-row <?php echo $isPast ? 'ev-row--past' : ''; ?>"
          data-title="<?php echo htmlspecialchars($row['title']); ?>"
          data-organizer="<?php echo htmlspecialchars($row['organizer'] ?: ''); ?>"
          data-location="<?php echo htmlspecialchars($row['location'] ?: ''); ?>"
          data-type="<?php echo htmlspecialchars($typeKey); ?>"
          data-upcoming="<?php echo $isUpcoming ? '1' : '0'; ?>">

          <!-- icon -->
          <div class="row-icon row-icon--<?php echo htmlspecialchars($typeKey); ?>">
            <?php echo $typeIcon; ?>
          </div>

          <!-- body -->
          <div class="row-body">
            <div class="row-badges">
              <span class="badge badge--<?php echo htmlspecialchars($typeKey); ?>"><?php echo htmlspecialchars($typeLabel); ?></span>
              <?php if ($isUpcoming): ?>
                <span class="badge badge--upcoming">Upcoming</span>
              <?php elseif ($isPast): ?>
                <span class="badge badge--past">Past</span>
              <?php endif; ?>
            </div>

            <?php if (!empty($eventImage)): ?>
              <div class="row-media">
                <img src="<?php echo htmlspecialchars($eventImage); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
              </div>
            <?php endif; ?>

            <h3 class="row-title">
              <a href="<?php echo htmlspecialchars($detailLink); ?>">
                <?php echo htmlspecialchars($row['title']); ?>
              </a>
            </h3>

            <div class="row-meta">
              <?php if (!empty($row['organizer'])): ?>
                <span class="row-meta-item">
                  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 13v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1"/><circle cx="8" cy="5" r="3"/></svg>
                  <?php echo htmlspecialchars($row['organizer']); ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($row['location'])): ?>
                <span class="row-meta-item">
                  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
                  <?php echo htmlspecialchars($row['location']); ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($dateStr)): ?>
                <span class="row-meta-item">
                  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
                  <?php echo htmlspecialchars($dateStr); ?>
                </span>
              <?php endif; ?>
            </div>

            <p class="row-desc"><?php echo htmlspecialchars($row['description']); ?></p>
          </div>

          <!-- actions -->
          <div class="row-actions">
            <?php if (!empty($row['registration_link']) && !$isPast): ?>
                <a class="btn btn-primary" href="<?php echo $regLink; ?>" target="_blank" rel="noopener noreferrer">
                  Register
                  <svg width="10" height="10" viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
              <?php else: ?>
                <button class="btn btn-primary" disabled><?php echo $isPast ? 'Past Event' : 'Register'; ?></button>
              <?php endif; ?>
              <a class="btn btn-ghost" href="<?php echo htmlspecialchars($detailLink); ?>">View details</a>
              <button class="btn-icon" type="button" title="Share"
                data-share="true"
                data-title="<?php echo htmlspecialchars($row['title']); ?>"
                data-link="<?php echo htmlspecialchars($detailLink); ?>">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="3" r="2"/><circle cx="4" cy="8" r="2"/><circle cx="12" cy="13" r="2"/><path d="M6 7l4-2.5M6 9l4 2.5" stroke-linecap="round"/></svg>
            </button>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <div class="ev-pagination">
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
      <div class="ev-empty">
        <span class="ev-empty-icon">📭</span>
        <h3>No events yet</h3>
        <p>Check back soon — events are posted regularly.</p>
      </div>
    <?php endif; ?>

  </div><!-- /.ev-body -->
</div><!-- /.ev-page -->

<script>
(function () {
  const searchInput    = document.getElementById('searchInput');
  const tabs           = document.querySelectorAll('.ev-tab');
  const list           = document.getElementById('eventsList');
  const resultsCount   = document.getElementById('resultsCount');
  const resultsCountBody = document.getElementById('resultsCountBody');
  const resultsMeta    = document.getElementById('resultsMeta');
  const prevPage       = document.getElementById('prevPage');
  const nextPage       = document.getElementById('nextPage');
  const pageIndicator  = document.getElementById('pageIndicator');

  const rows    = list ? Array.from(list.querySelectorAll('.ev-row')) : [];
  const pageSize = 8;
  let currentPage = 1;
  let activeCategory = 'all';

  /* ── Stats ── */
  const updateStats = () => {
    const total    = rows.length;
    const upcoming = rows.filter(r => r.dataset.upcoming === '1').length;
    const el = id => document.getElementById(id);
    if (el('statTotal'))    el('statTotal').textContent    = total;
    if (el('statUpcoming')) el('statUpcoming').textContent = upcoming;
  };

  /* ── Tab counts ── */
  const updateTabCounts = () => {
    const map = { all: rows.length };
    rows.forEach(r => { const t = r.dataset.type || 'social'; map[t] = (map[t]||0)+1; });
    ['all','hackathon','workshop','social','competition'].forEach(cat => {
      const id = 'count' + cat.charAt(0).toUpperCase() + cat.slice(1);
      const el = document.getElementById(id);
      if (el) el.textContent = map[cat] || 0;
    });
  };

  /* ── Match ── */
  const matches = (row, query, category) => {
    const title = (row.dataset.title     || '').toLowerCase();
    const org   = (row.dataset.organizer || '').toLowerCase();
    const loc   = (row.dataset.location  || '').toLowerCase();
    const type  = row.dataset.type || 'social';
    return (!query || title.includes(query) || org.includes(query) || loc.includes(query))
      && (category === 'all' || type === category);
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

    const countText = String(total);
    if (resultsCount)     resultsCount.textContent     = countText;
    if (resultsCountBody) resultsCountBody.textContent = countText;
    if (resultsMeta)      resultsMeta.textContent      = total ? `Showing ${Math.min(end, total)} of ${total}` : '';
    if (pageIndicator)    pageIndicator.textContent    = `Page ${currentPage} of ${pages}`;
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
    searchInput.addEventListener('keydown', e => {
      if (e.key === 'Escape') { searchInput.value = ''; currentPage = 1; render(); }
    });
  }

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
      const title = btn.dataset.title || 'Event';
      const link  = btn.dataset.link  || window.location.href;
      if (navigator.share) {
        try { await navigator.share({ title, url: link }); } catch (_) {}
      } else {
        try {
          await navigator.clipboard.writeText(link);
          btn.classList.add('copied');
          setTimeout(() => btn.classList.remove('copied'), 1800);
        } catch (_) {
          window.prompt('Copy this link:', link);
        }
      }
    });
  });

  updateStats();
  updateTabCounts();
  render();
})();
</script>

<?php include '../shared/footer.php'; ?>
