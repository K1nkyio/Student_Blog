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

$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'requirements'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN requirements TEXT DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM opportunities LIKE 'benefits'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE opportunities ADD COLUMN benefits TEXT DEFAULT NULL");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: opportunities.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM opportunities WHERE id = ? AND LOWER(COALESCE(NULLIF(status, ''), 'active')) IN ('active','open') LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$opportunity = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$opportunity) { header('Location: opportunities.php'); exit; }

$daysLeft = null;
$daysLeftText = 'No deadline';
$urgencyClass = '';
if (!empty($opportunity['deadline'])) {
    $deadlineDate = new DateTime($opportunity['deadline']);
    $today = new DateTime('today');
    $daysLeft = (int)$today->diff($deadlineDate)->format('%r%a');
    if ($daysLeft > 14)       { $daysLeftText = $daysLeft . ' days left'; $urgencyClass = 'ok'; }
    elseif ($daysLeft > 0)    { $daysLeftText = $daysLeft . ' days left'; $urgencyClass = 'warn'; }
    elseif ($daysLeft === 0)  { $daysLeftText = 'Closes today';           $urgencyClass = 'critical'; }
    else                      { $daysLeftText = 'Deadline passed';        $urgencyClass = 'expired'; }
}

$requirements   = trim((string)($opportunity['requirements'] ?? ''));
$benefits       = trim((string)($opportunity['benefits'] ?? ''));
$requirementsList = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $requirements))));
$benefitsList     = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $benefits))));

$page_title      = htmlspecialchars($opportunity['title']) . ' — Opportunity';
$meta_description = 'Opportunity details on ' . SITE_NAME;

include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ─────────────────────────────────────────────
   TOKENS
───────────────────────────────────────────── */
:root {
  --bg:         #f5f2ed;
  --bg-warm:    #ede9e1;
  --ink:        #18160f;
  --ink-mid:    #4a4540;
  --ink-light:  #7a7570;
  --rule:       #d4cfc7;
  --rule-light: #e8e4dc;
  --accent:     #c8641a;
  --accent-dim: #f0dece;
  --sky:        #1a5fc8;
  --sky-dim:    #dce9f8;
  --green:      #1a7a4a;
  --green-dim:  #d2edd9;
  --gold:       #b8860b;
  --gold-dim:   #f5edcc;

  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-sans:  'Outfit', sans-serif;

  --max-w: 760px;
  --gutter: clamp(1.25rem, 5vw, 3rem);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ─────────────────────────────────────────────
   BASE
───────────────────────────────────────────── */
.opp-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ─────────────────────────────────────────────
   HERO — full-width strip, no box
───────────────────────────────────────────── */
.opp-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 0;
  position: relative;
  overflow: hidden;
}

/* decorative large numeral watermark */
.opp-hero::before {
  content: '#';
  position: absolute;
  right: var(--gutter);
  top: 1rem;
  font-family: var(--font-serif);
  font-size: clamp(8rem, 20vw, 18rem);
  font-weight: 700;
  color: rgba(255,255,255,.04);
  line-height: 1;
  user-select: none;
  pointer-events: none;
}

.hero-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  animation: slideUp .6s ease both;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(22px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* breadcrumb */
.breadcrumb {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .75rem;
  font-weight: 500;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: rgba(255,255,255,.4);
  margin-bottom: 2rem;
}
.breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; transition: color .15s; }
.breadcrumb a:hover { color: #fff; }
.breadcrumb span { color: rgba(255,255,255,.25); }

/* type tag */
.opp-type-tag {
  display: inline-block;
  font-family: var(--font-sans);
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.5);
  padding: .28rem .75rem;
  border-radius: 2px;
  margin-bottom: 1rem;
}

/* headline */
.opp-headline {
  font-family: var(--font-serif);
  font-size: clamp(2.1rem, 5.5vw, 3.6rem);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -.015em;
  color: #fff;
  max-width: 640px;
  margin-bottom: 1.75rem;
}

/* fact row */
.fact-row {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem 2rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
}

.fact-item {
  display: flex;
  align-items: center;
  gap: .45rem;
  font-size: .82rem;
  color: rgba(255,255,255,.5);
}
.fact-item i { font-size: .72rem; }
.fact-item strong { color: rgba(255,255,255,.85); font-weight: 500; }

/* ─────────────────────────────────────────────
   DEADLINE TICKER — sits right under hero
───────────────────────────────────────────── */
.deadline-ticker {
  background: var(--ink);
  border-top: 1px solid rgba(255,255,255,.08);
}
.deadline-ticker-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 1rem var(--gutter);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.ticker-left {
  display: flex;
  align-items: center;
  gap: .75rem;
}
.urgency-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .07em;
  text-transform: uppercase;
  padding: .3rem .8rem;
  border-radius: 99px;
}
.urgency-badge .dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.badge-ok       { background: rgba(26,122,74,.25);  color: #6ee7b7; }
.badge-ok .dot  { background: #34d399; box-shadow: 0 0 5px #34d399; }
.badge-warn     { background: rgba(184,134,11,.25); color: #fcd34d; }
.badge-warn .dot{ background: #fbbf24; box-shadow: 0 0 5px #fbbf24; }
.badge-critical { background: rgba(200,100,26,.25); color: #fdba74; animation: pulseBadge 2s infinite; }
.badge-critical .dot { background: #fb923c; box-shadow: 0 0 5px #fb923c; }
.badge-expired  { background: rgba(120,120,120,.2); color: #94a3b8; }
.badge-expired .dot { background: #64748b; }

@keyframes pulseBadge {
  0%,100% { box-shadow: 0 0 0 0 rgba(200,100,26,.3); }
  50%      { box-shadow: 0 0 0 5px rgba(200,100,26,0); }
}

.ticker-date {
  font-size: .82rem;
  color: rgba(255,255,255,.4);
}
.ticker-date strong { color: rgba(255,255,255,.7); font-weight: 500; }

/* progress track */
.deadline-track {
  width: 130px;
  height: 3px;
  background: rgba(255,255,255,.08);
  border-radius: 99px;
  overflow: hidden;
  flex-shrink: 0;
}
.deadline-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--accent), #e8850a);
  border-radius: 99px;
  transition: width 1s ease;
}

/* ─────────────────────────────────────────────
   CONTENT — open layout, no containers
───────────────────────────────────────────── */
.opp-content {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--gutter);
}

/* ── Section headings ── */
.section-heading {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
.section-heading::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--rule);
}
.sh-label {
  font-family: var(--font-sans);
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  white-space: nowrap;
}
.sh-icon {
  width: 26px; height: 26px;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  font-size: .62rem;
  flex-shrink: 0;
}
.sh-icon.orange { background: var(--accent-dim); color: var(--accent); }
.sh-icon.blue   { background: var(--sky-dim);    color: var(--sky); }
.sh-icon.green  { background: var(--green-dim);  color: var(--green); }

/* ── Description section ── */
.section-description {
  padding: 3rem 0 2.5rem;
  border-bottom: 1px solid var(--rule-light);
  animation: fadeIn .7s .1s ease both;
}

.description-text {
  font-family: var(--font-serif);
  font-size: clamp(1.05rem, 2vw, 1.22rem);
  font-weight: 400;
  line-height: 1.85;
  color: var(--ink-mid);
  letter-spacing: .01em;
}
.description-text p + p { margin-top: 1.1em; }

/* drop cap on first letter */
.description-text::first-letter {
  font-size: 3.2em;
  font-weight: 600;
  color: var(--ink);
  float: left;
  line-height: .85;
  margin: .05em .12em 0 0;
}

/* ── Two-column grid for Req + Benefits ── */
.two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  padding: 2.75rem 0;
  border-bottom: 1px solid var(--rule-light);
  animation: fadeIn .7s .2s ease both;
}

.two-col-section:first-child {
  padding-right: 2.5rem;
  border-right: 1px solid var(--rule-light);
}
.two-col-section:last-child {
  padding-left: 2.5rem;
}

/* ── Check / benefit items ── */
.item-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: .9rem;
}
.item-list li {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  font-size: .9rem;
  color: var(--ink-mid);
  line-height: 1.55;
}
.item-icon {
  flex-shrink: 0;
  margin-top: .18rem;
  width: 18px; height: 18px;
  border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  font-size: .55rem;
}
.item-icon.req-ic  { background: var(--sky-dim);   color: var(--sky); }
.item-icon.ben-ic  { background: var(--green-dim);  color: var(--green); }

.empty-note {
  font-size: .85rem;
  color: var(--ink-light);
  font-style: italic;
  padding: .9rem 1rem;
  border: 1px dashed var(--rule);
  border-radius: 6px;
  background: var(--bg-warm);
}

/* ── Actions strip ── */
.section-actions {
  padding: 2.5rem 0 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  flex-wrap: wrap;
  animation: fadeIn .7s .3s ease both;
}

.action-left {
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  font-family: var(--font-sans);
  font-size: .83rem;
  font-weight: 500;
  padding: .65rem 1.2rem;
  border-radius: 4px;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: all .18s;
}
.btn:active { transform: scale(.97); }

.btn-back {
  background: transparent;
  color: var(--ink-mid);
  border: 1px solid var(--rule);
}
.btn-back:hover { background: var(--bg-warm); border-color: var(--ink-light); }

.btn-apply {
  background: var(--ink);
  color: #fff;
  padding: .75rem 2rem;
  font-size: .9rem;
  font-weight: 600;
  letter-spacing: .02em;
  border-radius: 3px;
  transition: background .2s, transform .15s, box-shadow .2s;
  box-shadow: 0 2px 12px rgba(24,22,15,.18);
}
.btn-apply:hover {
  background: #2c2a22;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(24,22,15,.25);
}
.btn-apply i { font-size: .72rem; opacity: .7; }

/* share cluster */
.share-cluster {
  display: flex;
  align-items: center;
  gap: .45rem;
}
.share-label {
  font-size: .72rem;
  color: var(--ink-light);
  font-weight: 500;
  letter-spacing: .05em;
  text-transform: uppercase;
  margin-right: .15rem;
}
.icon-btn {
  width: 32px; height: 32px;
  border-radius: 4px;
  border: 1px solid var(--rule);
  background: var(--bg);
  color: var(--ink-mid);
  display: flex; align-items: center; justify-content: center;
  font-size: .75rem;
  text-decoration: none;
  cursor: pointer;
  transition: all .15s;
}
.icon-btn:hover { background: var(--bg-warm); border-color: var(--ink-light); color: var(--ink); }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 620px) {
  .two-col {
    grid-template-columns: 1fr;
    gap: 2.25rem;
  }
  .two-col-section:first-child {
    padding-right: 0;
    border-right: none;
    border-bottom: 1px solid var(--rule-light);
    padding-bottom: 2.25rem;
  }
  .two-col-section:last-child { padding-left: 0; }
  .section-actions { flex-direction: column; align-items: flex-start; }
  .btn-apply { width: 100%; justify-content: center; }
  .description-text::first-letter { font-size: 2.5em; }
}
</style>

<!-- ═══════════════════════════════════════
     HERO  (full-width, no card)
════════════════════════════════════════ -->
<div class="opp-page">

  <div class="opp-hero">
    <div class="hero-inner">

      <!-- Breadcrumb -->
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="opportunities.php"><i class="fas fa-briefcase"></i> Opportunities</a>
        <span>/</span>
        <span><?php echo htmlspecialchars($opportunity['title']); ?></span>
      </nav>

      <?php if (!empty($opportunity['type'])): ?>
        <span class="opp-type-tag"><?php echo htmlspecialchars(ucfirst($opportunity['type'])); ?></span>
      <?php endif; ?>

      <h1 class="opp-headline"><?php echo htmlspecialchars($opportunity['title']); ?></h1>

      <div class="fact-row">
        <span class="fact-item">
          <i class="fas fa-building"></i>
          <strong><?php echo htmlspecialchars($opportunity['organization'] ?: 'Organisation not specified'); ?></strong>
        </span>
        <?php if (!empty($opportunity['location'])): ?>
        <span class="fact-item">
          <i class="fas fa-map-marker-alt"></i>
          <?php echo htmlspecialchars($opportunity['location']); ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($opportunity['deadline'])): ?>
        <span class="fact-item">
          <i class="far fa-calendar-alt"></i>
          Deadline: <strong><?php echo htmlspecialchars(date('M j, Y', strtotime($opportunity['deadline']))); ?></strong>
        </span>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Deadline ticker bar -->
  <?php if ($daysLeft !== null): ?>
  <div class="deadline-ticker">
    <div class="deadline-ticker-inner">
      <div class="ticker-left">
        <span class="urgency-badge badge-<?php echo $urgencyClass; ?>">
          <span class="dot"></span>
          <?php echo htmlspecialchars($daysLeftText); ?>
        </span>
        <?php if (!empty($opportunity['deadline'])): ?>
        <span class="ticker-date">Application deadline: <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($opportunity['deadline']))); ?></strong></span>
        <?php endif; ?>
      </div>
      <?php if ($daysLeft > 0): ?>
      <?php
        $windowDays = 60;
        $elapsed = max(0, $windowDays - $daysLeft);
        $pct = min(round(($elapsed / $windowDays) * 100), 100);
      ?>
      <div class="deadline-track" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="deadline-fill" style="width:<?php echo $pct; ?>%"></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══ OPEN CONTENT ═══ -->
  <div class="opp-content">

    <!-- Description -->
    <section class="section-description">
      <div class="section-heading">
        <span class="sh-icon orange"><i class="fas fa-align-left"></i></span>
        <span class="sh-label">About this opportunity</span>
      </div>
      <div class="description-text">
        <?php
          $desc = htmlspecialchars($opportunity['description']);
          // Wrap plain paragraphs
          $paragraphs = preg_split('/\r\n\r\n|\n\n/', $desc);
          foreach ($paragraphs as $p) {
              echo '<p>' . nl2br($p) . '</p>';
          }
        ?>
      </div>
    </section>

    <!-- Requirements + Benefits -->
    <div class="two-col">

      <div class="two-col-section">
        <div class="section-heading">
          <span class="sh-icon blue"><i class="fas fa-check"></i></span>
          <span class="sh-label">Requirements</span>
        </div>
        <?php if (!empty($requirementsList)): ?>
          <ul class="item-list">
            <?php foreach ($requirementsList as $item): ?>
              <li>
                <span class="item-icon req-ic"><i class="fas fa-check"></i></span>
                <?php echo htmlspecialchars($item); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="empty-note">No specific requirements listed.</p>
        <?php endif; ?>
      </div>

      <div class="two-col-section">
        <div class="section-heading">
          <span class="sh-icon green"><i class="fas fa-star"></i></span>
          <span class="sh-label">Benefits</span>
        </div>
        <?php if (!empty($benefitsList)): ?>
          <ul class="item-list">
            <?php foreach ($benefitsList as $item): ?>
              <li>
                <span class="item-icon ben-ic"><i class="fas fa-plus"></i></span>
                <?php echo htmlspecialchars($item); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="empty-note">Benefits information not provided.</p>
        <?php endif; ?>
      </div>

    </div>

    <!-- Actions -->
    <div class="section-actions">
      <div class="action-left">
        <a class="btn btn-back" href="opportunities.php">
          <i class="fas fa-arrow-left"></i> All Opportunities
        </a>
        <div class="share-cluster">
          <span class="share-label">Share</span>
          <a class="icon-btn" href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($opportunity['title']); ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="fab fa-twitter"></i></a>
          <a class="icon-btn" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
          <button class="icon-btn" onclick="navigator.clipboard?.writeText(window.location.href).then(()=>{this.innerHTML='<i class=\'fas fa-check\'></i>';setTimeout(()=>{this.innerHTML='<i class=\'fas fa-link\'></i>'},1500)})" aria-label="Copy link"><i class="fas fa-link"></i></button>
        </div>
      </div>
      <?php if (!empty($opportunity['link'])): ?>
        <a class="btn btn-apply" href="<?php echo htmlspecialchars($opportunity['link']); ?>" target="_blank" rel="noopener noreferrer">
          Apply Now <i class="fas fa-arrow-up-right-from-square"></i>
        </a>
      <?php endif; ?>
    </div>

  </div><!-- /.opp-content -->
</div><!-- /.opp-page -->

<?php include '../shared/footer.php'; ?>
