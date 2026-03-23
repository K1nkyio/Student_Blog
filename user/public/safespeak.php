<?php
/**
 * SafeSpeak - Anonymous Reporting Channel
 * Allows students to submit confidential concerns anonymously
 */

session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Page metadata
$page_title = 'SafeSpeak — Anonymous Reporting';
$meta_description = 'A secure and anonymous channel for students to report concerns, issues, or feedback confidentially. Your identity is protected.';

// Rate limiting check (basic IP-based)
$client_ip = get_client_ip();
$ip_hash = hash('sha256', $client_ip);

$rate_limit_check = $conn->prepare("
    SELECT COUNT(*) as count FROM anonymous_reports
    WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$rate_limit_check->bind_param("s", $ip_hash);
$rate_limit_check->execute();
$rate_result = $rate_limit_check->get_result()->fetch_assoc();
$recent_submissions = $rate_result['count'];
$rate_limit_check->close();

$can_submit = $recent_submissions < 3;

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_submit) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $message = 'Security validation failed. Please try again.';
        $message_type = 'error';
    } else {
        $subject       = trim($_POST['subject'] ?? '');
        $report_message = trim($_POST['message'] ?? '');
        $category      = $_POST['category'] ?? 'other';
        $urgency       = $_POST['urgency'] ?? 'medium';
        $contact_email = trim($_POST['contact_email'] ?? '');

        $errors = [];

        if (empty($subject)) {
            $errors[] = 'Please provide a subject for your report.';
        } elseif (strlen($subject) > 255) {
            $errors[] = 'Subject must be less than 255 characters.';
        }

        if (empty($report_message)) {
            $errors[] = 'Please provide details for your report.';
        } elseif (strlen($report_message) > 5000) {
            $errors[] = 'Report message must be less than 5000 characters.';
        }

        $valid_categories = ['academic', 'bullying', 'safety', 'discrimination', 'other'];
        if (!in_array($category, $valid_categories)) {
            $errors[] = 'Please select a valid category.';
        }

        $valid_urgencies = ['low', 'medium', 'high', 'critical'];
        if (!in_array($urgency, $valid_urgencies)) {
            $errors[] = 'Please select a valid urgency level.';
        }

        if (!empty($contact_email) && !is_valid_email($contact_email)) {
            $errors[] = 'Please provide a valid contact email address.';
        }

        if (empty($errors)) {
            $report_id   = 'ANON-' . strtoupper(substr(hash('sha256', uniqid(mt_rand(), true)), 0, 8)) . '-' . date('Ymd');
            $hashed_ip   = hash('sha256', $client_ip);
            $user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $hashed_email = !empty($contact_email) ? hash('sha256', strtolower($contact_email)) : null;

            $stmt = $conn->prepare("
                INSERT INTO anonymous_reports
                (report_id, subject, message, category, urgency, contact_email, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("ssssssss", $report_id, $subject, $report_message, $category, $urgency, $hashed_email, $hashed_ip, $user_agent);

            if ($stmt->execute()) {
                log_activity('anonymous_report_submitted', "Anonymous report submitted: $report_id", null, 'report', $stmt->insert_id);
                $message = 'Your report has been submitted anonymously and securely. Report ID: <strong>' . $report_id . '</strong>';
                $message_type = 'success';
                $_POST = [];
            } else {
                $message = 'There was an error submitting your report. Please try again.';
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
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

@media (max-width: 480px) {
  :root { --gutter: 1rem; }
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.ss-page {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ═══════════════════════════════════════════
   HERO
═══════════════════════════════════════════ */
.ss-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

.ss-hero::before {
  content: 'Safe.';
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

.ss-hero::after {
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
.hero-eyebrow .lock-icon {
  width: 7px; height: 7px;
  border-radius: 1px;
  background: var(--accent);
  box-shadow: 0 0 6px var(--accent);
}

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

/* trust badges */
.hero-trust {
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
}
.trust-badge {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 2px;
  padding: .35rem .8rem;
  font-size: .72rem;
  font-weight: 500;
  color: rgba(255,255,255,.7);
}
.trust-badge svg { width: 12px; height: 12px; color: var(--green); flex-shrink: 0; }

/* ═══════════════════════════════════════════
   BODY LAYOUT
═══════════════════════════════════════════ */
.ss-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.5rem var(--gutter) 0;
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 2rem;
  align-items: start;
}

@media (max-width: 780px) {
  .ss-body { grid-template-columns: 1fr; }
}

/* ═══════════════════════════════════════════
   FORM CARD
═══════════════════════════════════════════ */
.ss-form-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  overflow: hidden;
  animation: rowIn .4s ease both;
}

@keyframes rowIn {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.form-card-header {
  padding: 1.5rem 1.75rem 1.25rem;
  border-bottom: 1px solid var(--rule-light);
  background: var(--bg);
}

.form-card-header h2 {
  font-family: var(--font-serif);
  font-size: 1.35rem;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: .3rem;
  display: flex;
  align-items: center;
  gap: .6rem;
}
.form-card-header h2 svg { width: 16px; height: 16px; color: var(--ink-light); }
.form-card-header p {
  font-size: .82rem;
  color: var(--ink-light);
  line-height: 1.6;
}

.form-card-body { padding: 1.75rem; }

/* ── messages ── */
.ss-message {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: 1rem 1.25rem;
  border-radius: 4px;
  font-size: .875rem;
  line-height: 1.6;
  margin-bottom: 1.5rem;
}
.ss-message svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: .15rem; }
.ss-message.success {
  background: var(--green-dim);
  color: var(--green);
  border: 1px solid rgba(26,122,74,.2);
}
.ss-message.error {
  background: var(--red-dim);
  color: var(--red);
  border: 1px solid rgba(176,48,48,.2);
}

/* rate limit */
.ss-rate-warning {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: 1rem 1.25rem;
  background: var(--amber-dim);
  color: var(--amber);
  border: 1px solid rgba(184,134,11,.2);
  border-radius: 4px;
  font-size: .875rem;
  line-height: 1.6;
  margin-bottom: 1.5rem;
}
.ss-rate-warning svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: .15rem; }

/* ── form groups ── */
.form-group { margin-bottom: 1.35rem; }

.form-label {
  display: block;
  font-size: .8rem;
  font-weight: 600;
  color: var(--ink-mid);
  margin-bottom: .45rem;
  letter-spacing: .02em;
}
.form-label .req {
  color: var(--accent);
  margin-left: .15rem;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: .65rem .9rem;
  border: 1.5px solid var(--rule);
  border-radius: 4px;
  font-family: var(--font-sans);
  font-size: .875rem;
  color: var(--ink);
  background: var(--bg);
  outline: none;
  transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
}
.form-input::placeholder,
.form-textarea::placeholder { color: var(--ink-light); }
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  border-color: var(--ink);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
}

.form-select {
  appearance: none;
  cursor: pointer;
  padding-right: 2.2rem;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237a7570' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-color: var(--bg);
}

.form-textarea {
  resize: vertical;
  min-height: 130px;
  line-height: 1.6;
}

.form-help {
  font-size: .75rem;
  color: var(--ink-light);
  margin-top: .3rem;
}

/* char counter */
.char-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: .3rem;
}
.char-count {
  font-size: .72rem;
  color: var(--ink-light);
  font-variant-numeric: tabular-nums;
}
.char-count.warn { color: var(--accent); }
.char-count.danger { color: var(--red); }

/* ── two-col row ── */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 520px) {
  .form-row { grid-template-columns: 1fr; }
}

/* ── urgency pills ── */
.urgency-options {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}
.urgency-label {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  border: 1.5px solid var(--rule);
  border-radius: 4px;
  padding: .5rem .85rem;
  font-size: .78rem;
  font-weight: 500;
  color: var(--ink-mid);
  cursor: pointer;
  transition: all var(--transition);
  background: var(--bg);
  user-select: none;
}
.urgency-label input[type="radio"] { display: none; }
.urgency-label:has(input:checked) { border-color: var(--ink); background: var(--ink); color: #fff; }
.urgency-label .dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.urgency-label[data-level="low"]      .dot { background: var(--green); }
.urgency-label[data-level="medium"]   .dot { background: var(--amber); }
.urgency-label[data-level="high"]     .dot { background: var(--accent); }
.urgency-label[data-level="critical"] .dot { background: var(--red); }
.urgency-label:has(input:checked) .dot { background: rgba(255,255,255,.7); }

/* ── consent box ── */
.consent-box {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: 1rem 1.1rem;
  background: var(--bg);
  border: 1.5px solid var(--rule);
  border-radius: 4px;
  cursor: pointer;
  transition: border-color var(--transition);
}
.consent-box:has(input:checked) { border-color: var(--green); background: var(--green-dim); }
.consent-box input[type="checkbox"] {
  width: 15px; height: 15px;
  accent-color: var(--green);
  margin-top: .15rem;
  flex-shrink: 0;
}
.consent-box label {
  font-size: .82rem;
  color: var(--ink-mid);
  line-height: 1.55;
  cursor: pointer;
}

/* ── submit button ── */
.form-divider {
  height: 1px;
  background: var(--rule-light);
  margin: 1.5rem 0;
}

.ss-submit-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  padding: .75rem 1.5rem;
  background: var(--ink);
  color: #fff;
  border: none;
  border-radius: 3px;
  font-family: var(--font-sans);
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition);
  line-height: 1;
}
.ss-submit-btn:hover:not(:disabled) {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}
.ss-submit-btn:disabled {
  background: var(--rule);
  color: var(--ink-light);
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
.ss-submit-btn svg { width: 14px; height: 14px; }

/* ═══════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════ */
.ss-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  position: sticky;
  top: 1.5rem;
}

.ss-sidebar-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(24,22,15,.04), 0 4px 12px rgba(24,22,15,.04);
  padding: 1.25rem 1.4rem;
  animation: rowIn .4s ease both;
}
.ss-sidebar-card:nth-child(2) { animation-delay: .06s; }
.ss-sidebar-card:nth-child(3) { animation-delay: .12s; }

.sidebar-card-title {
  font-family: var(--font-serif);
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: .85rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
.sidebar-card-title svg { width: 14px; height: 14px; color: var(--ink-light); }

/* privacy checklist */
.privacy-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: .55rem;
}
.privacy-list li {
  display: flex;
  align-items: flex-start;
  gap: .55rem;
  font-size: .82rem;
  color: var(--ink-mid);
  line-height: 1.5;
}
.privacy-list li .check {
  width: 14px; height: 14px;
  border-radius: 50%;
  background: var(--green-dim);
  color: var(--green);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: .1rem;
}
.privacy-list li .check svg { width: 8px; height: 8px; }

/* steps */
.steps-list {
  display: flex;
  flex-direction: column;
  gap: .6rem;
}
.step {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  font-size: .82rem;
  color: var(--ink-mid);
  line-height: 1.5;
}
.step-num {
  width: 20px; height: 20px;
  border-radius: 50%;
  background: var(--bg-warm);
  border: 1px solid var(--rule);
  color: var(--ink-light);
  font-size: .65rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: .1rem;
}

/* contact card */
.contact-links {
  display: flex;
  flex-direction: column;
  gap: .5rem;
}
.contact-link-item {
  display: flex;
  align-items: center;
  gap: .65rem;
  font-size: .82rem;
  color: var(--ink-mid);
  line-height: 1.5;
}
.contact-link-item svg { width: 13px; height: 13px; color: var(--ink-light); flex-shrink: 0; }
.contact-link-item a {
  color: var(--sky);
  text-decoration: none;
  font-weight: 500;
  transition: color var(--transition);
}
.contact-link-item a:hover { color: var(--ink); }

/* divider within sidebar card */
.sidebar-rule {
  height: 1px;
  background: var(--rule-light);
  margin: .85rem 0;
}
</style>

<div class="ss-page">

  <!-- ══════════ HERO ══════════ -->
  <div class="ss-hero">
    <div class="hero-inner">
      <span class="hero-eyebrow"><span class="lock-icon"></span> Confidential channel</span>
      <h1 class="hero-headline">SafeSpeak<br><em>Speak Up, Stay Safe</em></h1>
      <p class="hero-sub">Report concerns, issues, or feedback anonymously and securely. Your identity is never stored or linked to your report.</p>
      <div class="hero-trust">
        <span class="trust-badge">
          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          100% Anonymous
        </span>
        <span class="trust-badge">
          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          End-to-end secure
        </span>
        <span class="trust-badge">
          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          Admin-only access
        </span>
        <span class="trust-badge">
          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg>
          IP never exposed
        </span>
      </div>
    </div>
  </div>

  <!-- ══════════ BODY ══════════ -->
  <div class="ss-body">

    <!-- ── FORM ── -->
    <div class="ss-form-card">
      <div class="form-card-header">
        <h2>
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Submit Anonymous Report
        </h2>
        <p>Reviewed only by authorised administrators. No personal information is required.</p>
      </div>

      <div class="form-card-body">

        <?php if (!empty($message)): ?>
        <div class="ss-message <?php echo $message_type; ?>">
          <?php if ($message_type === 'success'): ?>
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <?php else: ?>
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php endif; ?>
          <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!$can_submit): ?>
        <div class="ss-rate-warning">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <div>
            <strong>Submission limit reached.</strong> You may submit up to 3 reports per 24 hours.
            If this is urgent, contact an administrator directly.
          </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="safespeakForm" <?php echo !$can_submit ? 'style="display:none;"' : ''; ?>>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

          <!-- Subject -->
          <div class="form-group">
            <label class="form-label" for="subject">Subject <span class="req">*</span></label>
            <input type="text" id="subject" name="subject" class="form-input"
              placeholder="Brief description of your concern"
              maxlength="255"
              value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
              required>
            <div class="form-help">Keep it concise but descriptive (max 255 chars)</div>
          </div>

          <!-- Category + Urgency row -->
          <div class="form-row">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label" for="category">Category</label>
              <select id="category" name="category" class="form-select">
                <option value="other"          <?php echo ($_POST['category'] ?? '') === 'other'          ? 'selected' : ''; ?>>General / Other</option>
                <option value="academic"       <?php echo ($_POST['category'] ?? '') === 'academic'       ? 'selected' : ''; ?>>Academic Issues</option>
                <option value="bullying"       <?php echo ($_POST['category'] ?? '') === 'bullying'       ? 'selected' : ''; ?>>Bullying / Harassment</option>
                <option value="safety"         <?php echo ($_POST['category'] ?? '') === 'safety'         ? 'selected' : ''; ?>>Safety Concerns</option>
                <option value="discrimination" <?php echo ($_POST['category'] ?? '') === 'discrimination' ? 'selected' : ''; ?>>Discrimination</option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:0">
              <label class="form-label" for="urgency">Urgency</label>
              <select id="urgency" name="urgency" class="form-select">
                <option value="low"      <?php echo ($_POST['urgency'] ?? '') === 'low'      ? 'selected' : ''; ?>>Low — General feedback</option>
                <option value="medium"   <?php echo ($_POST['urgency'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium — Needs attention</option>
                <option value="high"     <?php echo ($_POST['urgency'] ?? '') === 'high'     ? 'selected' : ''; ?>>High — Urgent concern</option>
                <option value="critical" <?php echo ($_POST['urgency'] ?? '') === 'critical' ? 'selected' : ''; ?>>Critical — Immediate action</option>
              </select>
            </div>
          </div>

          <!-- Details -->
          <div class="form-group" style="margin-top:1.35rem">
            <label class="form-label" for="message">Details <span class="req">*</span></label>
            <textarea id="message" name="message" class="form-textarea"
              placeholder="Provide as much detail as you feel comfortable sharing. Your identity remains completely anonymous."
              maxlength="5000"
              required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            <div class="char-row">
              <span class="form-help">Be as specific as possible — it helps us act faster.</span>
              <span class="char-count" id="charCount">0 / 5000</span>
            </div>
          </div>

          <!-- Optional email -->
          <div class="form-group">
            <label class="form-label" for="contact_email">Follow-up Email <span style="font-weight:400;color:var(--ink-light)">(optional)</span></label>
            <input type="email" id="contact_email" name="contact_email" class="form-input"
              placeholder="your.email@example.com"
              value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>">
            <div class="form-help">If provided, it is hashed immediately — we never store your actual address.</div>
          </div>

          <!-- Consent -->
          <div class="form-group">
            <div class="consent-box">
              <input type="checkbox" id="confirm_anonymous" name="confirm_anonymous" required>
              <label for="confirm_anonymous">
                I understand this report will be submitted anonymously. My identity will be protected and only authorised administrators will have access to the content.
              </label>
            </div>
          </div>

          <div class="form-divider"></div>

          <button type="submit" class="ss-submit-btn" id="submitBtn">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Submit Report Anonymously
          </button>
        </form>

      </div>
    </div>

    <!-- ── SIDEBAR ── -->
    <aside class="ss-sidebar">

      <!-- Privacy -->
      <div class="ss-sidebar-card">
        <h3 class="sidebar-card-title">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Your Privacy
        </h3>
        <ul class="privacy-list">
          <li>
            <span class="check"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            No personal info required at all
          </li>
          <li>
            <span class="check"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            IP addresses are hashed — never stored raw
          </li>
          <li>
            <span class="check"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            Only authorised admins can view reports
          </li>
          <li>
            <span class="check"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            Not visible to other students or the public
          </li>
          <li>
            <span class="check"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            Optional emails are anonymised immediately
          </li>
        </ul>
      </div>

      <!-- What happens next -->
      <div class="ss-sidebar-card">
        <h3 class="sidebar-card-title">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          What Happens Next
        </h3>
        <div class="steps-list">
          <div class="step">
            <span class="step-num">1</span>
            Your report is assigned a unique tracking ID
          </div>
          <div class="step">
            <span class="step-num">2</span>
            Authorised administrators review it promptly
          </div>
          <div class="step">
            <span class="step-num">3</span>
            Appropriate action is taken based on urgency
          </div>
          <div class="step">
            <span class="step-num">4</span>
            You may be contacted if you provided an email
          </div>
        </div>
      </div>

      <!-- Need help -->
      <div class="ss-sidebar-card">
        <h3 class="sidebar-card-title">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Need Immediate Help?
        </h3>
        <div class="contact-links">
          <div class="contact-link-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.61 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9a16 16 0 0 0 6.91 6.91l.82-.82a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <span>Emergency — contact campus security or local authorities</span>
          </div>
          <div class="sidebar-rule"></div>
          <div class="contact-link-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span>Counselling — use <a href="#">student support services</a></span>
          </div>
          <div class="contact-link-item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span><a href="contact.php">Contact Administration</a></span>
          </div>
        </div>
      </div>

    </aside>
  </div><!-- /.ss-body -->
</div><!-- /.ss-page -->

<script>
(function () {
  const form      = document.getElementById('safespeakForm');
  const submitBtn = document.getElementById('submitBtn');
  const textarea  = document.getElementById('message');
  const charCount = document.getElementById('charCount');
  const maxLen    = 5000;

  /* ── Character counter ── */
  if (textarea && charCount) {
    const update = () => {
      const len = textarea.value.length;
      charCount.textContent = len.toLocaleString() + ' / ' + maxLen.toLocaleString();
      charCount.className = 'char-count' + (len > maxLen * 0.9 ? (len >= maxLen ? ' danger' : ' warn') : '');
    };
    textarea.addEventListener('input', update);
    update();
  }

  /* ── Form submit state ── */
  if (form && submitBtn) {
    form.addEventListener('submit', function (e) {
      if (!this.checkValidity()) { e.preventDefault(); return; }
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="animation:spin .8s linear infinite"><path d="M21 12a9 9 0 1 1-9-9"/></svg> Submitting…';
    });
  }

  /* ── Inline validation feedback ── */
  if (form) {
    form.querySelectorAll('[required]').forEach(field => {
      field.addEventListener('blur', function () {
        const empty = this.type === 'checkbox' ? !this.checked : this.value.trim() === '';
        this.style.borderColor = empty ? 'var(--red)' : 'var(--green)';
      });
      field.addEventListener('input', function () {
        if (this.style.borderColor) this.style.borderColor = '';
      });
    });
  }

  /* ── Spin keyframe ── */
  const style = document.createElement('style');
  style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
  document.head.appendChild(style);
})();
</script>

<?php
$conn->close();
include '../shared/footer.php';
?>
