<?php
include $safespeak_header_include ?? 'includes/header.php';
include '../shared/db_connect.php';
include '../shared/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action    = $_POST['action'];
    $report_id = (int)($_POST['report_id'] ?? 0);

    if ($action === 'update_status' && $report_id > 0) {
        $new_status  = $_POST['status'] ?? 'pending';
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        $valid_statuses = ['pending', 'reviewing', 'resolved', 'dismissed'];
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $conn->prepare("UPDATE anonymous_reports SET status = ?, admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $new_status, $admin_notes, $report_id);
            if ($stmt->execute()) $success_message = "Report status updated successfully.";
            else $error_message = "Failed to update report status.";
            $stmt->close();
        }
    }
}

$status_filter   = $_GET['status']   ?? '';
$category_filter = $_GET['category'] ?? '';
$urgency_filter  = $_GET['urgency']  ?? '';
$search          = trim($_GET['search'] ?? '');
$date_from       = $_GET['date_from'] ?? '';
$date_to         = $_GET['date_to']   ?? '';

$where_clauses = []; $params = []; $types = '';

if (!empty($status_filter))   { $where_clauses[] = "status = ?";    $params[] = $status_filter;   $types .= 's'; }
if (!empty($category_filter)) { $where_clauses[] = "category = ?";  $params[] = $category_filter; $types .= 's'; }
if (!empty($urgency_filter))  { $where_clauses[] = "urgency = ?";   $params[] = $urgency_filter;  $types .= 's'; }
if (!empty($search))          { $where_clauses[] = "(subject LIKE ? OR message LIKE ?)"; $p = "%$search%"; $params[] = $p; $params[] = $p; $types .= 'ss'; }
if (!empty($date_from))       { $where_clauses[] = "DATE(created_at) >= ?"; $params[] = $date_from; $types .= 's'; }
if (!empty($date_to))         { $where_clauses[] = "DATE(created_at) <= ?"; $params[] = $date_to;   $types .= 's'; }

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM anonymous_reports $where_sql");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_reports = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$per_page     = 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;
$total_pages  = max(1, (int)ceil($total_reports / $per_page));

$sql = "SELECT ar.* FROM anonymous_reports ar $where_sql ORDER BY
    CASE WHEN ar.status='pending' AND ar.urgency='critical' THEN 1
         WHEN ar.status='pending' AND ar.urgency='high' THEN 2
         WHEN ar.status='pending' AND ar.urgency='medium' THEN 3
         WHEN ar.status='pending' AND ar.urgency='low' THEN 4
         WHEN ar.status='reviewing' THEN 5 ELSE 6 END,
    ar.created_at DESC LIMIT ? OFFSET ?";

$p2 = $params; $t2 = $types;
$p2[] = $per_page; $p2[] = $offset; $t2 .= 'ii';
$stmt = $conn->prepare($sql);
if (!empty($p2)) $stmt->bind_param($t2, ...$p2);
$stmt->execute();
$reports = $stmt->get_result();

$stats = $conn->query("SELECT
    COUNT(*) as total,
    SUM(status='pending')   as pending,
    SUM(status='reviewing') as reviewing,
    SUM(status='resolved')  as resolved,
    SUM(status='dismissed') as dismissed,
    SUM(urgency='critical' AND status IN ('pending','reviewing')) as critical_pending
FROM anonymous_reports")->fetch_assoc();
?>

<style>
/* ═══════════════════════════════════════════
   SafeSpeak — tokens from opportunities.php
═══════════════════════════════════════════ */

/* ── PAGE HEADING ── */
.ss-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.5rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
  animation: ssIn .45s ease both;
}
@keyframes ssIn {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}

.ss-heading-left {}

.ss-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.3);
  padding: .26rem .8rem;
  border-radius: 2px;
  margin-bottom: .75rem;
}

.ss-title {
  font-family: var(--font-serif);
  font-size: clamp(1.9rem, 3.5vw, 2.8rem);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.06;
  letter-spacing: -.015em;
  margin-bottom: .3rem;
}
.ss-title em { font-style: italic; color: var(--ink-light); }

.ss-sub {
  font-size: .855rem;
  color: var(--ink-light);
  font-weight: 300;
  max-width: 480px;
  line-height: 1.65;
}

/* critical alert pill — same as hero eyebrow but red */
.ss-critical-pill {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--red);
  border: 1px solid rgba(176,48,48,.3);
  padding: .3rem .9rem;
  border-radius: 2px;
  background: var(--red-dim);
  animation: blink 2s infinite;
  white-space: nowrap;
  flex-shrink: 0;
  align-self: flex-start;
  margin-top: .25rem;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.45} }

.ss-critical-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--red);
  box-shadow: 0 0 5px var(--red);
}

/* ── KPI STRIP ── */
.ss-kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.ss-kpi {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1.25rem 1.25rem 1rem;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: ssIn .45s ease both;
}
.ss-kpi:nth-child(1) { animation-delay:.07s; }
.ss-kpi:nth-child(2) { animation-delay:.14s; }
.ss-kpi:nth-child(3) { animation-delay:.21s; }
.ss-kpi:nth-child(4) { animation-delay:.28s; }

.ss-kpi:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}

/* left accent bar */
.ss-kpi::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
}
.ss-kpi--resolved::before  { background: var(--green); }
.ss-kpi--pending::before   { background: var(--accent); }
.ss-kpi--reviewing::before { background: var(--sky); }
.ss-kpi--critical::before  { background: var(--red); }

.ss-kpi-val {
  font-family: var(--font-serif);
  font-size: 2.2rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  letter-spacing: -.02em;
  margin-bottom: .3rem;
}

.ss-kpi-lbl {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-light);
  margin-bottom: .15rem;
}

.ss-kpi-desc {
  font-size: .72rem;
  color: var(--ink-light);
  font-weight: 300;
  line-height: 1.4;
}

/* ── ALERTS ── */
.ss-alert {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  padding: .85rem 1rem;
  border-radius: 3px;
  font-size: .825rem;
  line-height: 1.55;
  margin-bottom: 1.25rem;
  animation: ssIn .3s ease both;
}
.ss-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: .15rem; }
.ss-alert--success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(26,122,74,.18); }
.ss-alert--danger  { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(176,48,48,.18); }

/* ── FILTER TOOLBAR ── */
/* Same sticky bar approach as .opp-toolbar */
.ss-filters {
  border: 1px solid var(--rule);
  border-radius: var(--radius-md);
  background: #fff;
  margin-bottom: 1.75rem;
  overflow: hidden;
  box-shadow: var(--shadow);
}

.ss-filters-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: .9rem 1.25rem;
  cursor: pointer;
  user-select: none;
  border-bottom: 1px solid transparent;
  transition: all var(--transition);
}
.ss-filters-head:hover { background: var(--bg); }
.ss-filters.open .ss-filters-head { border-bottom-color: var(--rule); background: var(--bg-warm); }

.ss-filters-head-left {
  display: flex;
  align-items: center;
  gap: .75rem;
  min-width: 0;
}

.ss-filters-label {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-mid);
}

.ss-filters-meta {
  font-size: .72rem;
  color: var(--ink-light);
}

.ss-active-badge {
  font-size: .62rem;
  font-weight: 600;
  background: var(--accent-dim);
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.2);
  border-radius: 2px;
  padding: .15rem .5rem;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.ss-filters-chevron {
  width: 24px; height: 24px;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  color: var(--ink-light);
  flex-shrink: 0;
  transition: all var(--transition);
}
.ss-filters-chevron svg { width: 10px; height: 10px; transition: transform var(--transition); }
.ss-filters.open .ss-filters-chevron svg { transform: rotate(180deg); }
.ss-filters.open .ss-filters-chevron { border-color: var(--ink-mid); color: var(--ink); }

.ss-filters-body {
  display: none;
  padding: 1.25rem;
}
.ss-filters.open .ss-filters-body { display: block; }

.ss-filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: .85rem;
  margin-bottom: 1.25rem;
}

.ss-field { display: flex; flex-direction: column; gap: .45rem; }

.ss-field-label {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--ink-mid);
}

/* field inputs — same as admin-header .form-control */
.ss-input, .ss-select {
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .62rem .85rem;
  font-size: .855rem;
  font-family: var(--font-sans);
  color: var(--ink);
  background: var(--bg);
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition);
  width: 100%;
  -webkit-appearance: none;
}
.ss-input::placeholder { color: var(--ink-light); }
.ss-input:focus, .ss-select:focus {
  border-color: var(--ink);
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
  background: #fff;
}

/* custom select arrow */
.ss-select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237a7570'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right .85rem center;
  padding-right: 2.25rem;
}

.ss-filter-actions {
  display: flex;
  gap: .6rem;
  align-items: center;
  flex-wrap: wrap;
}

/* buttons — reuse admin-header btn styles */
.ss-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-family: var(--font-sans);
  font-size: .8rem;
  font-weight: 600;
  padding: .58rem 1.1rem;
  border-radius: 3px;
  border: none;
  cursor: pointer;
  transition: all var(--transition);
  text-decoration: none;
  white-space: nowrap;
  line-height: 1;
}
.ss-btn svg { width: 12px; height: 12px; }
.ss-btn:active { transform: scale(.98); }

.ss-btn--primary { background: var(--ink); color: #fff; }
.ss-btn--primary:hover { background: #2c2a22; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(24,22,15,.2); color: #fff; }

.ss-btn--ghost { background: transparent; color: var(--ink-mid); border: 1.5px solid var(--rule); }
.ss-btn--ghost:hover { border-color: var(--ink-mid); background: var(--bg-warm); color: var(--ink); }

.ss-btn--resolve { background: var(--green-dim); color: var(--green); border: 1.5px solid rgba(26,122,74,.2); }
.ss-btn--resolve:hover { background: #b8e5ca; }

.ss-btn--dismiss { background: var(--red-dim); color: var(--red); border: 1.5px solid rgba(176,48,48,.2); }
.ss-btn--dismiss:hover { background: #f0cccc; }

.ss-btn--view { background: var(--sky-dim); color: var(--sky); border: 1.5px solid rgba(26,95,200,.15); }
.ss-btn--view:hover { background: #c5d8f5; }

.ss-btn--sm { padding: .35rem .75rem; font-size: .72rem; }

/* ── RESULTS BAR ── */
.ss-results-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-size: .8rem;
  color: var(--ink-light);
}
.ss-results-bar strong { color: var(--ink-mid); font-weight: 600; }

/* ── REPORTS LIST ── */
.ss-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 2rem;
}

/* Report row — same card treatment as .opp-row */
.ss-row {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: ssIn .35s ease both;
}

.ss-row:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-2px);
}

/* left accent — exactly like .opp-row::before */
.ss-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 2px 2px 0;
}
.ss-row[data-status="pending"]::before   { background: var(--accent); }
.ss-row[data-status="reviewing"]::before { background: var(--sky); }
.ss-row[data-status="resolved"]::before  { background: var(--green); }
.ss-row[data-status="dismissed"]::before { background: var(--rule); }
.ss-row[data-urgency="critical"]::before { background: var(--red); box-shadow: 0 0 8px rgba(176,48,48,.4); }

.ss-row-inner {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 1.25rem;
  align-items: start;
  padding: 1.4rem 1.5rem;
}

/* row body */
.ss-row-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem;
  margin-bottom: .5rem;
  align-items: center;
}

/* badges — mirrors .badge from opportunities.php */
.ss-badge {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .18rem .6rem;
  border-radius: 2px;
}

.ss-badge--pending   { background: var(--accent-dim); color: var(--accent); }
.ss-badge--reviewing { background: var(--sky-dim);    color: var(--sky); }
.ss-badge--resolved  { background: var(--green-dim);  color: var(--green); }
.ss-badge--dismissed { background: var(--bg-warmer);  color: var(--ink-light); border: 1px solid var(--rule); }

.ss-badge--low      { background: var(--bg-warm);   color: var(--ink-light); border: 1px solid var(--rule); }
.ss-badge--medium   { background: var(--amber-dim); color: var(--amber); }
.ss-badge--high     { background: var(--accent-dim); color: var(--accent); }
.ss-badge--critical {
  background: var(--red-dim); color: var(--red);
  animation: blink 1.8s infinite;
}

.ss-badge--cat      { background: var(--purple-dim); color: var(--purple); }

/* report ID */
.ss-row-id {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .68rem;
  color: var(--ink-light);
  font-weight: 500;
  margin-bottom: .4rem;
}

/* title */
.ss-row-title {
  font-family: var(--font-serif);
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--ink);
  line-height: 1.25;
  margin-bottom: .45rem;
  cursor: pointer;
  transition: color var(--transition);
  display: inline-block;
}
.ss-row-title:hover { color: var(--sky); }

/* preview */
.ss-row-preview {
  font-size: .845rem;
  color: var(--ink-mid);
  line-height: 1.6;
  text-align: justify;
  text-align-last: left;
  hyphens: auto;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: .55rem;
}

/* meta row — same pattern as .row-meta */
.ss-row-info {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem 1.1rem;
  font-size: .78rem;
  color: var(--ink-light);
  align-items: center;
}
.ss-row-info-item {
  display: inline-flex;
  align-items: center;
  gap: .28rem;
}
.ss-row-info-item svg { width: 11px; height: 11px; opacity: .65; flex-shrink: 0; }

/* row actions */
.ss-row-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: .5rem;
  flex-shrink: 0;
}

.ss-row-btns {
  display: flex;
  flex-direction: column;
  gap: .35rem;
  align-items: stretch;
}

/* reviewed timestamp */
.ss-reviewed {
  font-size: .68rem;
  color: var(--ink-light);
  text-align: right;
}

/* ── EMPTY STATE ── */
.ss-empty {
  text-align: center;
  padding: 5rem 2rem;
  color: var(--ink-light);
}
.ss-empty-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: .35; }
.ss-empty h4 { font-family: var(--font-serif); font-size: 1.2rem; color: var(--ink-mid); margin-bottom: .35rem; }
.ss-empty p  { font-size: .845rem; margin-bottom: 1.25rem; }

/* ── PAGINATION ── same as .opp-pagination */
.ss-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  margin-top: 2rem;
  border-top: 1px solid var(--rule-light);
  padding-top: 2rem;
  flex-wrap: wrap;
}

.ss-page-btn {
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
  cursor: pointer;
  transition: all var(--transition);
  text-decoration: none;
  min-width: 36px;
  justify-content: center;
}
.ss-page-btn:hover { border-color: var(--ink); background: var(--bg-warm); color: var(--ink); }
.ss-page-btn.active { background: var(--ink); color: #fff; border-color: var(--ink); font-weight: 600; }
.ss-page-btn.dots   { pointer-events: none; color: var(--ink-light); }
.ss-page-btn svg    { width: 11px; height: 11px; }

/* ── MODAL OVERLAY ── */
.ss-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(24,22,15,.6);
  backdrop-filter: blur(4px);
  z-index: 9000;
  align-items: center; justify-content: center;
  padding: 1.5rem;
}
.ss-overlay.open {
  display: flex;
  animation: fadeIn .2s ease;
}
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

.ss-modal {
  background: #fff;
  border: 1px solid var(--rule);
  border-radius: var(--radius-md);
  max-width: 800px; width: 100%;
  max-height: 88vh; overflow-y: auto;
  box-shadow: 0 8px 32px rgba(24,22,15,.18), 0 32px 80px rgba(24,22,15,.14);
  animation: modalIn .3s ease;
}
@keyframes modalIn {
  from { opacity: 0; transform: translateY(16px) scale(.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.ss-modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--rule);
  background: var(--bg-warm);
  position: sticky; top: 0; z-index: 1;
}

.ss-modal-title {
  font-family: var(--font-serif);
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}

.ss-modal-close {
  width: 28px; height: 28px;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  background: var(--bg);
  color: var(--ink-light);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all var(--transition);
}
.ss-modal-close:hover { border-color: var(--ink); color: var(--ink); background: var(--bg-warm); }
.ss-modal-close svg { width: 12px; height: 12px; }

.ss-modal-body  { padding: 1.5rem; }

.ss-modal-foot {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--rule);
  display: flex; justify-content: flex-end; gap: .6rem;
  background: var(--bg-warm);
}

/* skeleton */
.ss-skel-line {
  height: 12px; border-radius: 2px;
  background: linear-gradient(90deg, var(--bg-warm) 25%, var(--bg-warmer) 50%, var(--bg-warm) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  margin-bottom: .75rem;
}
@keyframes shimmer { to { background-position: -200% 0; } }

/* status update form */
.ss-form-group { margin-bottom: 1.1rem; }
.ss-form-label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .45rem;
}
.ss-textarea {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .65rem .9rem;
  font-size: .875rem;
  font-family: var(--font-sans);
  color: var(--ink);
  background: var(--bg);
  min-height: 100px;
  resize: vertical;
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition);
}
.ss-textarea:focus {
  border-color: var(--ink);
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
  background: #fff;
}

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .ss-kpis { grid-template-columns: 1fr 1fr; }
  .ss-row-inner { grid-template-columns: 1fr; }
  .ss-row-actions { flex-direction: row; flex-wrap: wrap; align-items: center; }
  .ss-row-btns { flex-direction: row; }
  .ss-filters-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
  .ss-kpis { grid-template-columns: 1fr; }
  .ss-filters-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ══ PAGE HEADING ══ -->
<div class="ss-heading">
  <div class="ss-heading-left">
    <div class="ss-eyebrow">Anonymous Reporting</div>
    <h1 class="ss-title">Safe<em>Speak</em></h1>
    <p class="ss-sub">Manage and respond to confidential student concerns, safety reports, and institutional feedback.</p>
  </div>
  <?php if (!empty($stats['critical_pending'])): ?>
  <div class="ss-critical-pill">
    <span class="ss-critical-dot"></span>
    <?php echo (int)$stats['critical_pending']; ?> critical pending
  </div>
  <?php endif; ?>
</div>

<!-- ══ ALERTS ══ -->
<?php if (isset($success_message)): ?>
<div class="ss-alert ss-alert--success">
  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
  <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>
<?php if (isset($error_message)): ?>
<div class="ss-alert ss-alert--danger">
  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- ══ KPI STRIP ══ -->
<div class="ss-kpis">
  <div class="ss-kpi ss-kpi--resolved">
    <div class="ss-kpi-val"><?php echo (int)$stats['resolved']; ?></div>
    <div class="ss-kpi-lbl">Resolved</div>
    <div class="ss-kpi-desc">Concerns successfully addressed</div>
  </div>
  <div class="ss-kpi ss-kpi--pending">
    <div class="ss-kpi-val"><?php echo (int)$stats['pending']; ?></div>
    <div class="ss-kpi-lbl">Pending</div>
    <div class="ss-kpi-desc">Awaiting administrator action</div>
  </div>
  <div class="ss-kpi ss-kpi--reviewing">
    <div class="ss-kpi-val"><?php echo (int)$stats['reviewing']; ?></div>
    <div class="ss-kpi-lbl">Reviewing</div>
    <div class="ss-kpi-desc">Currently being investigated</div>
  </div>
  <div class="ss-kpi ss-kpi--critical">
    <div class="ss-kpi-val"><?php echo (int)$stats['critical_pending']; ?></div>
    <div class="ss-kpi-lbl">Critical</div>
    <div class="ss-kpi-desc">Requires immediate attention</div>
  </div>
</div>

<!-- ══ FILTERS ══ -->
<div class="ss-filters open" id="filtersPanel">
  <div class="ss-filters-head" onclick="document.getElementById('filtersPanel').classList.toggle('open')">
    <div class="ss-filters-head-left">
      <span class="ss-filters-label">Filters</span>
      <span class="ss-filters-meta">
        <?php echo (int)$total_reports; ?> result<?php echo $total_reports !== 1 ? 's' : ''; ?>
      </span>
      <?php if (!empty($status_filter) || !empty($category_filter) || !empty($urgency_filter) || !empty($search)): ?>
        <span class="ss-active-badge">Active</span>
      <?php endif; ?>
    </div>
    <div class="ss-filters-chevron">
      <svg viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M1 1l5 6 5-6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
  </div>

  <div class="ss-filters-body">
    <form method="GET">
      <div class="ss-filters-grid">
        <div class="ss-field">
          <label class="ss-field-label">Status</label>
          <select name="status" class="ss-select">
            <option value="">All Statuses</option>
            <option value="pending"   <?= $status_filter==='pending'   ? 'selected' : '' ?>>Pending</option>
            <option value="reviewing" <?= $status_filter==='reviewing' ? 'selected' : '' ?>>Reviewing</option>
            <option value="resolved"  <?= $status_filter==='resolved'  ? 'selected' : '' ?>>Resolved</option>
            <option value="dismissed" <?= $status_filter==='dismissed' ? 'selected' : '' ?>>Dismissed</option>
          </select>
        </div>
        <div class="ss-field">
          <label class="ss-field-label">Category</label>
          <select name="category" class="ss-select">
            <option value="">All Categories</option>
            <option value="academic"       <?= $category_filter==='academic'       ? 'selected' : '' ?>>Academic</option>
            <option value="bullying"       <?= $category_filter==='bullying'       ? 'selected' : '' ?>>Bullying</option>
            <option value="safety"         <?= $category_filter==='safety'         ? 'selected' : '' ?>>Safety</option>
            <option value="discrimination" <?= $category_filter==='discrimination' ? 'selected' : '' ?>>Discrimination</option>
            <option value="other"          <?= $category_filter==='other'          ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="ss-field">
          <label class="ss-field-label">Urgency</label>
          <select name="urgency" class="ss-select">
            <option value="">All Priorities</option>
            <option value="low"      <?= $urgency_filter==='low'      ? 'selected' : '' ?>>Low</option>
            <option value="medium"   <?= $urgency_filter==='medium'   ? 'selected' : '' ?>>Medium</option>
            <option value="high"     <?= $urgency_filter==='high'     ? 'selected' : '' ?>>High</option>
            <option value="critical" <?= $urgency_filter==='critical' ? 'selected' : '' ?>>Critical</option>
          </select>
        </div>
        <div class="ss-field">
          <label class="ss-field-label">Search</label>
          <input type="text" name="search" class="ss-input"
                 placeholder="Subject or message…"
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="ss-field">
          <label class="ss-field-label">From</label>
          <input type="date" name="date_from" class="ss-input" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="ss-field">
          <label class="ss-field-label">To</label>
          <input type="date" name="date_to" class="ss-input" value="<?= htmlspecialchars($date_to) ?>">
        </div>
      </div>
      <div class="ss-filter-actions">
        <button type="submit" class="ss-btn ss-btn--primary">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 3h12M3 7h8M5 11h4" stroke-linecap="round"/></svg>
          Apply Filters
        </button>
        <a href="safespeak.php" class="ss-btn ss-btn--ghost">
          <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 2l10 10M12 2L2 12" stroke-linecap="round"/></svg>
          Clear
        </a>
      </div>
    </form>
  </div>
</div>

<!-- ══ RESULTS BAR ══ -->
<div class="ss-results-bar">
  <span><strong><?php echo (int)$total_reports; ?></strong> report<?php echo $total_reports !== 1 ? 's' : ''; ?> found</span>
  <?php if ($total_pages > 1): ?>
    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
  <?php endif; ?>
</div>

<!-- ══ REPORTS LIST ══ -->
<?php if ($reports->num_rows > 0): ?>
<div class="ss-list">
  <?php
  $i = 0;
  while ($r = $reports->fetch_assoc()):
    $urgency_class = in_array($r['urgency'], ['low','medium','high','critical']) ? $r['urgency'] : 'low';
    $status_class  = in_array($r['status'], ['pending','reviewing','resolved','dismissed']) ? $r['status'] : 'pending';
    $row_urgency   = ($r['status'] === 'pending' && $r['urgency'] === 'critical') ? 'critical' : $r['urgency'];
    $i++;
  ?>
  <div class="ss-row"
       data-status="<?php echo htmlspecialchars($r['status']); ?>"
       data-urgency="<?php echo htmlspecialchars($row_urgency); ?>"
       style="animation-delay: <?php echo $i * 35; ?>ms">
    <div class="ss-row-inner">

      <!-- Left: body -->
      <div class="ss-row-body">
        <div class="ss-row-id">
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M8 2v4M8 10v4M2 8h4M10 8h4" stroke-linecap="round"/>
          </svg>
          <?php echo htmlspecialchars($r['report_id'] ?? '#' . $r['id']); ?>
        </div>

        <div class="ss-row-meta">
          <span class="ss-badge ss-badge--<?php echo $status_class; ?>">
            <?php echo ucfirst($r['status']); ?>
          </span>
          <span class="ss-badge ss-badge--<?php echo $urgency_class; ?>">
            <?php if ($r['urgency'] === 'critical'): ?>⚡ <?php endif; ?>
            <?php echo ucfirst($r['urgency']); ?>
          </span>
          <?php if (!empty($r['category'])): ?>
          <span class="ss-badge ss-badge--cat"><?php echo ucfirst($r['category']); ?></span>
          <?php endif; ?>
        </div>

        <div class="ss-row-title" onclick="viewReport(<?php echo (int)$r['id']; ?>)">
          <?php echo htmlspecialchars($r['subject']); ?>
        </div>

        <div class="ss-row-preview">
          <?php echo htmlspecialchars(substr($r['message'], 0, 220) . (strlen($r['message']) > 220 ? '…' : '')); ?>
        </div>

        <div class="ss-row-info">
          <span class="ss-row-info-item">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="2" y="3" width="12" height="11" rx="1.5"/>
              <path d="M5 1.5v3M11 1.5v3M2 7h12" stroke-linecap="round"/>
            </svg>
            <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
          </span>
          <?php if (!empty($r['reviewed_at'])): ?>
          <span class="ss-row-info-item">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"/>
              <circle cx="8" cy="8" r="2"/>
            </svg>
            Updated <?php echo function_exists('time_ago') ? time_ago($r['reviewed_at']) : date('M d', strtotime($r['reviewed_at'])); ?>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: actions -->
      <div class="ss-row-actions">
        <div class="ss-row-btns">
          <button class="ss-btn ss-btn--view ss-btn--sm" onclick="viewReport(<?php echo (int)$r['id']; ?>)">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
            View
          </button>
          <?php if (!in_array($r['status'], ['resolved','dismissed'])): ?>
          <button class="ss-btn ss-btn--resolve ss-btn--sm"
                  onclick="updateStatus(<?php echo (int)$r['id']; ?>, 'resolved')">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 8 6 11 13 4"/></svg>
            Resolve
          </button>
          <button class="ss-btn ss-btn--dismiss ss-btn--sm"
                  onclick="updateStatus(<?php echo (int)$r['id']; ?>, 'dismissed')">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l10 10M13 3L3 13"/></svg>
            Dismiss
          </button>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
  <?php endwhile; ?>
</div>
<?php else: ?>
<div class="ss-empty">
  <span class="ss-empty-icon">📭</span>
  <h4><?php echo !empty($where_clauses) ? 'No Matching Reports' : 'No Reports Yet'; ?></h4>
  <p><?php echo !empty($where_clauses) ? 'No reports match your current filters.' : 'SafeSpeak is ready to receive student concerns.'; ?></p>
  <?php if (!empty($where_clauses)): ?>
    <a href="safespeak.php" class="ss-btn ss-btn--ghost">Clear Filters</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══ PAGINATION ══ -->
<?php if ($total_pages > 1):
  $qp = $_GET; unset($qp['page']);
?>
<nav class="ss-pagination">
  <?php if ($current_page > 1): ?>
    <a href="?<?php echo http_build_query(array_merge($qp, ['page' => $current_page - 1])); ?>" class="ss-page-btn">
      <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 2L4 7l5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
  <?php endif; ?>

  <?php
  $s = max(1, $current_page - 2);
  $e = min($total_pages, $current_page + 2);
  if ($s > 1):
  ?><a href="?<?php echo http_build_query(array_merge($qp, ['page' => 1])); ?>" class="ss-page-btn">1</a><?php
  if ($s > 2) echo '<span class="ss-page-btn dots">…</span>';
  endif;

  for ($pg = $s; $pg <= $e; $pg++):
  ?><a href="?<?php echo http_build_query(array_merge($qp, ['page' => $pg])); ?>"
       class="ss-page-btn <?php echo $pg === $current_page ? 'active' : ''; ?>">
      <?php echo $pg; ?>
    </a><?php
  endfor;

  if ($e < $total_pages):
    if ($e < $total_pages - 1) echo '<span class="ss-page-btn dots">…</span>';
  ?><a href="?<?php echo http_build_query(array_merge($qp, ['page' => $total_pages])); ?>" class="ss-page-btn"><?php echo $total_pages; ?></a><?php
  endif;

  if ($current_page < $total_pages):
  ?><a href="?<?php echo http_build_query(array_merge($qp, ['page' => $current_page + 1])); ?>" class="ss-page-btn">
    <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a><?php
  endif;
?>
</nav>
<?php endif; ?>

<!-- ══ MODAL ══ -->
<div class="ss-overlay" id="reportOverlay">
  <div class="ss-modal">
    <div class="ss-modal-head">
      <div class="ss-modal-title">Report Details</div>
      <button class="ss-modal-close" onclick="closeModal()" aria-label="Close">
        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 2l10 10M12 2L2 12" stroke-linecap="round"/></svg>
      </button>
    </div>
    <div class="ss-modal-body" id="reportContent">
      <div class="ss-skel-line" style="width:35%; height:10px;"></div>
      <div class="ss-skel-line" style="width:70%; height:16px;"></div>
      <div class="ss-skel-line" style="width:100%;"></div>
      <div class="ss-skel-line" style="width:100%;"></div>
      <div class="ss-skel-line" style="width:80%;"></div>
    </div>
    <div class="ss-modal-foot">
      <button class="ss-btn ss-btn--ghost" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<script>
const overlay = document.getElementById('reportOverlay');

function openModal()  { overlay.classList.add('open');  document.body.style.overflow = 'hidden'; }
function closeModal() { overlay.classList.remove('open'); document.body.style.overflow = ''; }

function viewReport(id) {
    document.getElementById('reportContent').innerHTML = `
        <div>
          <div class="ss-skel-line" style="width:35%; height:10px;"></div>
          <div class="ss-skel-line" style="width:70%; height:16px;"></div>
          <div class="ss-skel-line" style="width:100%;"></div>
          <div class="ss-skel-line" style="width:100%;"></div>
          <div class="ss-skel-line" style="width:80%;"></div>
        </div>`;
    openModal();
    fetch(`<?php echo htmlspecialchars($safespeak_report_endpoint ?? 'ajax/get_report.php', ENT_QUOTES); ?>?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('reportContent').innerHTML = data.success
                ? data.html
                : `<div class="ss-alert ss-alert--danger">${data.message || 'Failed to load report.'}</div>`;
        })
        .catch(() => {
            document.getElementById('reportContent').innerHTML =
                `<div class="ss-alert ss-alert--danger">Error loading report details.</div>`;
        });
}

function updateStatus(id, status) {
    if (!confirm(`Mark this report as ${status}?`)) return;
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('report_id', id);
    fd.append('status', status);
    fetch('<?php echo htmlspecialchars($safespeak_post_endpoint ?? 'safespeak.php', ENT_QUOTES); ?>', { method: 'POST', body: fd })
        .then(() => location.reload())
        .catch(() => alert('Error updating status. Please try again.'));
}

overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<?php
$stmt->close();
$conn->close();
include $safespeak_footer_include ?? 'includes/footer.php';
?>
