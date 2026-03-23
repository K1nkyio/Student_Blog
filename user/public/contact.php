<?php
include '../shared/db_connect.php';
include '../shared/header.php';

$msg = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    $stmt = $conn->prepare("INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $subject, $message);
    if ($stmt->execute()) {
        $msg     = "Thank you! Your message has been sent successfully. We'll get back to you soon.";
        $msgType = 'success';
    } else {
        $msg     = "Oops! Something went wrong. Please try again.";
        $msgType = 'error';
    }
}
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
  --red:          #b03030;
  --red-dim:      #f8e0e0;

  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-sans:  'Outfit', sans-serif;
  --max-w:      1360px;
  --gutter:     1.5rem;
  --transition: 200ms cubic-bezier(.4,0,.2,1);
  --shadow:     0 1px 3px rgba(24,22,15,.06), 0 4px 16px rgba(24,22,15,.06);
  --radius:     4px;
  --radius-md:  6px;
}

@media (max-width: 480px) { :root { --gutter: 1rem; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  line-height: 1.65;
}
a { color: inherit; text-decoration: none; }

/* ═══════════════════════════════════════════
   HERO — dark ink strip, same as opp-hero
═══════════════════════════════════════════ */
.contact-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
  margin-bottom: 0;
}

.contact-hero::before {
  content: 'Contact.';
  position: absolute;
  right: calc(var(--gutter) - 1rem);
  bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: clamp(5rem, 14vw, 10rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.03);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

.contact-hero::after {
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
  animation: slideUp .55s ease both;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(18px); }
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
  max-width: 44ch;
  line-height: 1.75;
}

/* ═══════════════════════════════════════════
   PAGE BODY
═══════════════════════════════════════════ */
.contact-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.5rem var(--gutter) 6rem;
}

/* ── Two-column layout ── */
.contact-layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 2rem;
  align-items: start;
}

/* ═══════════════════════════════════════════
   LEFT: INFO PANEL — dark ink card
═══════════════════════════════════════════ */
.info-panel {
  background: var(--ink);
  border-radius: var(--radius-md);
  padding: 2rem 1.75rem;
  color: #fff;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow);
}

/* dot grid inside panel */
.info-panel::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}

.info-panel > * { position: relative; z-index: 1; }

.info-heading {
  font-family: var(--font-serif);
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: -.01em;
  margin-bottom: .5rem;
}
.info-sub {
  font-size: .845rem;
  color: rgba(255,255,255,.38);
  font-weight: 300;
  line-height: 1.65;
  margin-bottom: 1.75rem;
}

/* rule */
.info-rule {
  height: 1px;
  background: rgba(255,255,255,.1);
  margin-bottom: 1.5rem;
}

/* contact items */
.info-item {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
  padding: .85rem 1rem;
  border: 1px solid rgba(255,255,255,.07);
  border-radius: var(--radius-md);
  background: rgba(255,255,255,.04);
  margin-bottom: .65rem;
  transition: background var(--transition);
}
.info-item:last-child { margin-bottom: 0; }
.info-item:hover { background: rgba(255,255,255,.08); }

.info-icon {
  width: 34px; height: 34px;
  border-radius: 3px;
  background: var(--accent-dim);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: .06rem;
}
.info-icon svg { width: 14px; height: 14px; color: var(--accent); }

.info-item-label {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.32);
  margin-bottom: .18rem;
}
.info-item-value {
  font-size: .845rem;
  color: rgba(255,255,255,.72);
  line-height: 1.5;
}

/* ═══════════════════════════════════════════
   RIGHT: FORM CARD
═══════════════════════════════════════════ */
.form-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow);
  overflow: hidden;
}

.form-card-head {
  background: var(--bg-warm);
  border-bottom: 1px solid var(--rule-light);
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  gap: .65rem;
}
.form-card-icon {
  width: 28px; height: 28px;
  border-radius: 3px;
  background: var(--accent-dim);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.form-card-icon svg { width: 13px; height: 13px; color: var(--accent); }
.form-card-title {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
}

.form-body { padding: 1.5rem; }

/* alerts */
.form-alert {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  padding: .85rem 1rem;
  border-radius: 3px;
  font-size: .845rem;
  line-height: 1.55;
  margin-bottom: 1.4rem;
  animation: slideUp .3s ease both;
}
.form-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: .15rem; }
.form-alert--success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(26,122,74,.18); }
.form-alert--error   { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(176,48,48,.18); }

/* two-col row for name + email */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

/* field groups */
.form-group { margin-bottom: 1.2rem; }

.form-label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .45rem;
}
.form-label .req { color: var(--accent); margin-left: .15rem; }

.form-input,
.form-textarea {
  width: 100%;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: .72rem .9rem;
  font-size: .875rem;
  font-family: var(--font-sans);
  color: var(--ink);
  background: var(--bg);
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
  -webkit-appearance: none;
}
.form-input::placeholder,
.form-textarea::placeholder { color: var(--ink-light); }
.form-input:focus,
.form-textarea:focus {
  border-color: var(--ink);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
}
.form-textarea {
  resize: vertical;
  min-height: 140px;
  line-height: 1.65;
}

/* divider */
.form-divider {
  height: 1px;
  background: var(--rule-light);
  margin: 1.5rem 0;
}

/* submit — ink primary, same as all pages */
.form-submit {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  padding: .82rem 1.5rem;
  background: var(--ink);
  color: #fff;
  border: none;
  border-radius: 3px;
  font-family: var(--font-sans);
  font-size: .875rem;
  font-weight: 600;
  letter-spacing: .02em;
  cursor: pointer;
  transition: all var(--transition);
  line-height: 1;
}
.form-submit:hover {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}
.form-submit:active { transform: scale(.98); }
.form-submit svg { width: 14px; height: 14px; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 860px) {
  .contact-layout { grid-template-columns: 1fr; }
  .info-panel { position: static; }
}
@media (max-width: 540px) {
  .form-row { grid-template-columns: 1fr; }
}
</style>

<!-- ══════ HERO ══════ -->
<div class="contact-hero">
  <div class="hero-inner">
    <span class="hero-eyebrow"><span class="live-dot"></span> Reach Out</span>
    <h1 class="hero-headline">Get In<br><em>Touch</em></h1>
    <p class="hero-sub">Have a question or want to work together? We'd love to hear from you — send us a message and we'll respond within 24 hours.</p>
  </div>
</div>

<!-- ══════ BODY ══════ -->
<div class="contact-body">
  <div class="contact-layout">

    <!-- Left: contact info -->
    <div class="info-panel">
      <h2 class="info-heading">Contact<br>Information</h2>
      <p class="info-sub">Fill in the form and our team will get back to you within 24 hours.</p>

      <div class="info-rule"></div>

      <div class="info-item">
        <div class="info-icon">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
          </svg>
        </div>
        <div>
          <div class="info-item-label">Email</div>
          <div class="info-item-value">hello@example.com</div>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.06 6.06l.98-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
        </div>
        <div>
          <div class="info-item-label">Phone</div>
          <div class="info-item-value">+1 (555) 123-4567</div>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
        </div>
        <div>
          <div class="info-item-label">Address</div>
          <div class="info-item-value">123 Business St, Suite 100<br>City, State 12345</div>
        </div>
      </div>
    </div>

    <!-- Right: form -->
    <div class="form-card">
      <div class="form-card-head">
        <div class="form-card-icon">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </div>
        <span class="form-card-title">Send a Message</span>
      </div>

      <div class="form-body">

        <?php if ($msg): ?>
        <div class="form-alert form-alert--<?php echo $msgType; ?>">
          <?php if ($msgType === 'success'): ?>
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <?php else: ?>
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php endif; ?>
          <?php echo htmlspecialchars($msg); ?>
        </div>
        <?php endif; ?>

        <form method="POST">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="name">
                Name <span class="req">*</span>
              </label>
              <input class="form-input" type="text" id="name" name="name"
                     placeholder="Your name" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="email">
                Email <span class="req">*</span>
              </label>
              <input class="form-input" type="email" id="email" name="email"
                     placeholder="you@example.com" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="subject">Subject</label>
            <input class="form-input" type="text" id="subject" name="subject"
                   placeholder="How can we help?">
          </div>

          <div class="form-group">
            <label class="form-label" for="message">
              Message <span class="req">*</span>
            </label>
            <textarea class="form-textarea" id="message" name="message"
                      placeholder="Tell us more about your inquiry…"
                      required></textarea>
          </div>

          <div class="form-divider"></div>

          <button type="submit" class="form-submit" id="submitBtn">
            Send Message
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
              <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  const btn  = document.getElementById('submitBtn');
  const form = btn?.closest('form');
  if (!form || !btn) return;
  form.addEventListener('submit', function () {
    btn.disabled = true;
    btn.innerHTML = '<svg style="animation:spin .7s linear infinite" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9"/></svg> Sending…';
  });
  const s = document.createElement('style');
  s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
})();
</script>

<?php include '../shared/footer.php'; ?>