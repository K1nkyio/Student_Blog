<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';

// Ensure schema supports images
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: events.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    $page_title = 'Event not found — ' . SITE_NAME;
    include '../shared/header.php';
    echo '<div style="max-width:900px;margin:3rem auto;padding:0 1.5rem;font-family:sans-serif;">Event not found. <a href="events.php">Back to events</a></div>';
    include '../shared/footer.php';
    exit();
}

$page_title      = $event['title'] . ' — ' . SITE_NAME;
$meta_description = substr(strip_tags($event['description']), 0, 160);

// Detect event type from content
$titleLow = strtolower($event['title'] ?? '');
$descLow  = strtolower($event['description'] ?? '');
$orgLow   = strtolower($event['organizer'] ?? '');
$content  = $titleLow . ' ' . $descLow . ' ' . $orgLow;

$typeKey = 'social';
if (strpos($content, 'hack') !== false)                                            { $typeKey = 'hackathon'; }
elseif (strpos($content, 'workshop') !== false)                                    { $typeKey = 'workshop'; }
elseif (strpos($content, 'competition') !== false || strpos($content, 'contest') !== false) { $typeKey = 'competition'; }

$typeLabels = [
    'hackathon'   => 'Hackathon',
    'workshop'    => 'Workshop',
    'competition' => 'Competition',
    'social'      => 'Social',
];
$typeLabel = $typeLabels[$typeKey] ?? ucfirst($typeKey);

$typeIcons = [
    'hackathon'   => '⚡',
    'workshop'    => '🛠️',
    'competition' => '🏆',
    'social'      => '🎉',
];
$typeIcon = $typeIcons[$typeKey] ?? '📅';

$dateStr    = '';
$daysStr    = '';
$isPast     = false;
$deadlineState = 'open';

if (!empty($event['event_date'])) {
    $ts       = strtotime($event['event_date']);
    $today    = strtotime('today');
    $dateStr  = date('M d, Y', $ts);
    $daysLeft = (int)floor(($ts - $today) / 86400);
    $isPast   = $ts < $today;

    if (!$isPast) {
        if ($daysLeft === 0)     { $daysStr = 'Today!';           $deadlineState = 'closing'; }
        elseif ($daysLeft <= 3)  { $daysStr = $daysLeft . 'd away'; $deadlineState = 'closing'; }
        else                     { $daysStr = 'In ' . $daysLeft . ' days'; $deadlineState = 'open'; }
    } else {
        $deadlineState = 'expired';
    }
}

$imageUrl = !empty($event['image']) ? '../' . ltrim($event['image'], '/') : '';

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

  --max-w:    1080px;
  --gutter:   1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}
@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.event-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO — full dark strip
═══════════════════════════════════════════ */
.event-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

/* large serif watermark */
.event-hero::before {
  content: 'Event.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(5rem, 17vw, 13rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.035);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

/* dot grid */
.event-hero::after {
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

/* back link */
.hero-back {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .72rem;
  font-weight: 500;
  letter-spacing: .06em;
  color: rgba(255,255,255,.45);
  text-decoration: none;
  margin-bottom: 1.25rem;
  transition: color var(--transition);
}
.hero-back:hover { color: rgba(255,255,255,.75); }
.hero-back svg { width: 12px; height: 12px; }

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
.hero-eyebrow .type-icon { font-size: .9rem; }

/* headline */
.hero-headline {
  font-family: var(--font-serif);
  font-size: clamp(2rem, 5vw, 3.5rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -.015em;
  color: #fff;
  margin-bottom: 1.25rem;
  max-width: 700px;
}

/* meta row */
.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem 1.5rem;
  font-size: .82rem;
  color: rgba(255,255,255,.5);
  margin-bottom: 1.75rem;
}
.hero-meta-item {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
}
.hero-meta-item svg { width: 12px; height: 12px; opacity: .7; flex-shrink: 0; }

/* status badges in hero */
.hero-badges {
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
}
.badge {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .2rem .65rem;
  border-radius: 2px;
}
.badge--hackathon   { background: var(--accent-dim); color: var(--accent); }
.badge--workshop    { background: var(--purple-dim); color: var(--purple); }
.badge--competition { background: var(--amber-dim);  color: var(--amber);  }
.badge--social      { background: var(--green-dim);  color: var(--green);  }
.badge--closing     { background: var(--accent-dim); color: var(--accent); }
.badge--past        { background: rgba(255,255,255,.08); color: rgba(255,255,255,.4); border: 1px solid rgba(255,255,255,.12); }
.badge--days        { background: rgba(255,255,255,.08); color: rgba(255,255,255,.55); border: 1px solid rgba(255,255,255,.12); }

/* ═══════════════════════════════════════════
   STICKY TOOLBAR
═══════════════════════════════════════════ */
.event-toolbar {
  border-bottom: 1px solid var(--rule);
  background: var(--bg);
  position: sticky;
  top: 0;
  z-index: 50;
}
.event-toolbar-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: .75rem var(--gutter);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.toolbar-crumb {
  font-size: .8rem;
  color: var(--ink-light);
  display: flex;
  align-items: center;
  gap: .4rem;
}
.toolbar-crumb a {
  color: var(--ink-light);
  text-decoration: none;
  transition: color var(--transition);
}
.toolbar-crumb a:hover { color: var(--ink); }
.toolbar-crumb svg { width: 10px; height: 10px; opacity: .5; }
.toolbar-crumb strong { color: var(--ink-mid); font-weight: 600; }
.toolbar-actions { display: flex; gap: .5rem; }

/* ═══════════════════════════════════════════
   BODY — two-column layout
═══════════════════════════════════════════ */
.event-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.25rem var(--gutter) 0;
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 2rem;
  align-items: start;
}
@media (max-width: 768px) {
  .event-body {
    grid-template-columns: 1fr;
  }
  .event-sidebar { order: -1; }
}

/* ── Main card ── */
.event-main-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  overflow: hidden;
  animation: cardIn .4s ease both;
}
@keyframes cardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* image */
.event-image-wrap {
  width: 100%;
  aspect-ratio: 16 / 7;
  background: var(--bg-warm);
  overflow: hidden;
  border-bottom: 1px solid var(--rule-light);
}
.event-image-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
/* placeholder when no image */
.event-image-placeholder {
  width: 100%;
  aspect-ratio: 16 / 7;
  background: var(--bg-warm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 4rem;
  border-bottom: 1px solid var(--rule-light);
  opacity: .35;
}

.event-main-body { padding: 2rem; }

/* description prose */
.event-desc-label {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .75rem;
}
.event-desc {
  font-size: .975rem;
  color: var(--ink-mid);
  line-height: 1.8;
  text-align: justify;
  text-align-last: left;
  hyphens: auto;
}
.event-desc p + p { margin-top: .75rem; }

/* ── Sidebar ── */
.event-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  animation: cardIn .45s .1s ease both;
}

.sidebar-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  padding: 1.5rem;
}
.sidebar-card-title {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: 1rem;
  padding-bottom: .6rem;
  border-bottom: 1px solid var(--rule-light);
}

/* detail rows */
.detail-rows { display: flex; flex-direction: column; gap: .85rem; }
.detail-row {
  display: flex;
  gap: .75rem;
  align-items: flex-start;
}
.detail-icon {
  width: 30px; height: 30px;
  border-radius: 6px;
  background: var(--bg-warm);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.detail-icon svg { width: 13px; height: 13px; color: var(--ink-light); }
.detail-content {}
.detail-label {
  font-size: .7rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ink-light);
  line-height: 1;
  margin-bottom: .2rem;
}
.detail-value {
  font-size: .875rem;
  color: var(--ink-mid);
  font-weight: 500;
  line-height: 1.35;
}
.detail-value.highlight { color: var(--ink); font-weight: 600; }
.detail-value.closing   { color: var(--accent); }
.detail-value.past      { color: var(--ink-light); }

/* action block */
.action-block { display: flex; flex-direction: column; gap: .5rem; }

/* ── Buttons ── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  font-family: var(--font-sans);
  font-size: .82rem;
  font-weight: 600;
  text-decoration: none;
  padding: .7rem 1.25rem;
  border-radius: 3px;
  border: none;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
  line-height: 1;
  width: 100%;
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
.btn-primary[disabled],
.btn-primary.disabled {
  background: var(--rule);
  color: var(--ink-light);
  pointer-events: none;
  box-shadow: none;
  transform: none;
}
.btn-ghost {
  background: transparent;
  color: var(--ink-mid);
  border: 1.5px solid var(--rule);
}
.btn-ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); }

/* share micro-btn */
.share-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  font-family: var(--font-sans);
  font-size: .78rem;
  font-weight: 500;
  color: var(--ink-light);
  background: transparent;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .55rem;
  cursor: pointer;
  transition: all var(--transition);
  width: 100%;
}
.share-btn:hover { border-color: var(--ink-mid); color: var(--ink); background: var(--bg-warm); }
.share-btn svg { width: 12px; height: 12px; }

/* organiser tag */
.organiser-tag {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .78rem;
  font-weight: 500;
  background: var(--bg-warm);
  color: var(--ink-mid);
  border: 1px solid var(--rule-light);
  border-radius: 3px;
  padding: .3rem .65rem;
  margin-top: .4rem;
}
.organiser-tag svg { width: 11px; height: 11px; opacity: .6; }
</style>

<div class="event-page">

  <!-- ══════════ HERO ══════════ -->
  <div class="event-hero">
    <div class="hero-inner">

      <a class="hero-back" href="events.php">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Back to events
      </a>

      <span class="hero-eyebrow">
        <span class="type-icon"><?= $typeIcon ?></span>
        <?= htmlspecialchars($typeLabel) ?>
      </span>

      <h1 class="hero-headline"><?= htmlspecialchars($event['title']) ?></h1>

      <div class="hero-meta">
        <?php if (!empty($dateStr)): ?>
          <span class="hero-meta-item">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
            <?= htmlspecialchars($dateStr) ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($event['location'])): ?>
          <span class="hero-meta-item">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
            <?= htmlspecialchars($event['location']) ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($event['organizer'])): ?>
          <span class="hero-meta-item">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 13v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1"/><circle cx="8" cy="5" r="3"/></svg>
            <?= htmlspecialchars($event['organizer']) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="hero-badges">
        <span class="badge badge--<?= htmlspecialchars($typeKey) ?>"><?= htmlspecialchars($typeLabel) ?></span>
        <?php if ($isPast): ?>
          <span class="badge badge--past">Past Event</span>
        <?php elseif ($deadlineState === 'closing'): ?>
          <span class="badge badge--closing">⚡ <?= htmlspecialchars($daysStr) ?></span>
        <?php elseif (!empty($daysStr)): ?>
          <span class="badge badge--days"><?= htmlspecialchars($daysStr) ?></span>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- ══════════ STICKY TOOLBAR ══════════ -->
  <div class="event-toolbar">
    <div class="event-toolbar-inner">
      <nav class="toolbar-crumb" aria-label="Breadcrumb">
        <a href="events.php">Events</a>
        <svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 1l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <strong><?= htmlspecialchars(mb_strimwidth($event['title'], 0, 48, '…')) ?></strong>
      </nav>
      <div class="toolbar-actions">
        <?php if (!empty($event['registration_link']) && !$isPast): ?>
          <a class="btn btn-primary" style="width:auto;padding:.5rem 1.1rem;font-size:.78rem;"
             href="<?= htmlspecialchars($event['registration_link']) ?>" target="_blank" rel="noopener noreferrer">
            Register
            <svg viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        <?php else: ?>
          <span class="btn btn-primary disabled" style="width:auto;padding:.5rem 1.1rem;font-size:.78rem;"><?= $isPast ? 'Past Event' : 'Register' ?></span>
        <?php endif; ?>
        <a class="btn btn-ghost" style="width:auto;padding:.5rem 1.1rem;font-size:.78rem;" href="events.php">All Events</a>
      </div>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="event-body">

    <!-- ── Main ── -->
    <div class="event-main-card">

      <?php if (!empty($imageUrl)): ?>
        <div class="event-image-wrap">
          <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($event['title']) ?>">
        </div>
      <?php else: ?>
        <div class="event-image-placeholder"><?= $typeIcon ?></div>
      <?php endif; ?>

      <div class="event-main-body">
        <div class="event-desc-label">About this event</div>
        <div class="event-desc">
          <?php
          // Render paragraphs nicely
          $paragraphs = array_filter(array_map('trim', explode("\n", $event['description'])));
          foreach ($paragraphs as $para) {
              echo '<p>' . htmlspecialchars($para) . '</p>';
          }
          ?>
        </div>
      </div>
    </div>

    <!-- ── Sidebar ── -->
    <aside class="event-sidebar">

      <!-- Details card -->
      <div class="sidebar-card">
        <div class="sidebar-card-title">Event Details</div>
        <div class="detail-rows">

          <?php if (!empty($dateStr)): ?>
          <div class="detail-row">
            <div class="detail-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/></svg>
            </div>
            <div class="detail-content">
              <div class="detail-label">Date</div>
              <div class="detail-value highlight"><?= htmlspecialchars($dateStr) ?></div>
              <?php if (!empty($daysStr)): ?>
                <div class="detail-value <?= $deadlineState === 'closing' ? 'closing' : ($isPast ? 'past' : '') ?>">
                  <?= htmlspecialchars($daysStr) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($event['location'])): ?>
          <div class="detail-row">
            <div class="detail-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5S12.5 9.5 12.5 6c0-2.5-2-4.5-4.5-4.5z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/></svg>
            </div>
            <div class="detail-content">
              <div class="detail-label">Location</div>
              <div class="detail-value"><?= htmlspecialchars($event['location']) ?></div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($event['organizer'])): ?>
          <div class="detail-row">
            <div class="detail-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 13v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1"/><circle cx="8" cy="5" r="3"/></svg>
            </div>
            <div class="detail-content">
              <div class="detail-label">Organiser</div>
              <div class="organiser-tag">
                <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="10" height="9" rx="1.5"/><path d="M5 3V2a2 2 0 014 0v1" stroke-linecap="round"/></svg>
                <?= htmlspecialchars($event['organizer']) ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="detail-row">
            <div class="detail-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h10v2L8 10 3 5V3z" stroke-linejoin="round"/><path d="M3 5v8h10V5" stroke-linejoin="round"/></svg>
            </div>
            <div class="detail-content">
              <div class="detail-label">Type</div>
              <div class="detail-value"><?= htmlspecialchars($typeLabel) ?></div>
            </div>
          </div>

        </div>
      </div>

      <!-- Action card -->
      <div class="sidebar-card">
        <div class="sidebar-card-title">Take Action</div>
        <div class="action-block">
          <?php if (!empty($event['registration_link']) && !$isPast): ?>
            <a class="btn btn-primary"
               href="<?= htmlspecialchars($event['registration_link']) ?>"
               target="_blank" rel="noopener noreferrer">
              Register Now
              <svg viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9L9 2M9 2H5M9 2v4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
          <?php else: ?>
            <button class="btn btn-primary disabled" disabled><?= $isPast ? 'Registration Closed' : 'Register' ?></button>
          <?php endif; ?>

          <a class="btn btn-ghost" href="events.php">Browse All Events</a>

          <button class="share-btn" id="shareBtn"
                  data-title="<?= htmlspecialchars($event['title']) ?>"
                  data-url="<?= htmlspecialchars('event.php?id=' . $event['id']) ?>">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="3" r="2"/><circle cx="4" cy="8" r="2"/><circle cx="12" cy="13" r="2"/><path d="M6 7l4-2.5M6 9l4 2.5" stroke-linecap="round"/></svg>
            Share this event
          </button>
        </div>
      </div>

    </aside>

  </div><!-- /.event-body -->
</div><!-- /.event-page -->

<script>
// ── Share ──
const shareBtn = document.getElementById('shareBtn');
if (shareBtn) {
  shareBtn.addEventListener('click', async () => {
    const title = shareBtn.dataset.title;
    const url   = window.location.origin + '/' + shareBtn.dataset.url;
    if (navigator.share) {
      try { await navigator.share({ title, url }); } catch (_) {}
    } else {
      try {
        await navigator.clipboard.writeText(url);
        const orig = shareBtn.innerHTML;
        shareBtn.innerHTML = `<svg viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="2.2" width="12" height="12"><path d="M2 6.5l3 3L11 2" stroke-linecap="round" stroke-linejoin="round"/></svg> Link copied!`;
        shareBtn.style.color = 'var(--green)';
        shareBtn.style.borderColor = 'var(--green)';
        setTimeout(() => {
          shareBtn.innerHTML = orig;
          shareBtn.style.color = '';
          shareBtn.style.borderColor = '';
        }, 2000);
      } catch (_) {
        window.prompt('Copy this link:', url);
      }
    }
  });
}
</script>

<?php include '../shared/footer.php'; ?>