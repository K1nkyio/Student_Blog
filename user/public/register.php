<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('register.php');
    }

    $username         = sanitize_input($_POST['username'] ?? '');
    $email            = sanitize_input($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        set_flash('error', 'Please fill out all fields.');
    } elseif (!is_valid_email($email)) {
        set_flash('error', 'Please provide a valid email address.');
    } elseif ($password !== $password_confirm) {
        set_flash('error', 'Passwords do not match.');
    } elseif (strlen($password) < 6) {
        set_flash('error', 'Password must be at least 6 characters.');
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $check->bind_param('ss', $email, $username);
        $check->execute();
        $res = $check->get_result();
        if ($res && $res->num_rows > 0) {
            set_flash('error', 'An account with that email or username already exists.');
        } else {
            $hash   = hash_password($password);
            $insert = $conn->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $insert->bind_param('sss', $username, $email, $hash);
            if ($insert->execute()) {
                $new_id = $conn->insert_id;
                $_SESSION['user_id'] = $new_id;
                set_flash('success', 'Registration successful. Welcome!');
                log_activity('register', 'New user registered', $new_id);
                redirect('index.php');
            } else {
                set_flash('error', 'Failed to create account. Please try again.');
            }
            $insert->close();
        }
        $check->close();
    }
}

$page_title = 'Register — ' . SITE_NAME;
include '../shared/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS — exact mirror of opportunities.php
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
  --transition: 200ms cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
}

/* ═══════════════════════════════════════════
   FULL-VIEWPORT SPLIT
═══════════════════════════════════════════ */
.auth-shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}

/* ═══════════════════════════════════════════
   LEFT — DARK EDITORIAL PANEL
═══════════════════════════════════════════ */
.auth-visual-side {
  background: var(--ink);
  color: #fff;
  padding: clamp(3rem, 7vw, 5.5rem) clamp(2.5rem, 5.5vw, 4.5rem);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}

/* serif watermark */
.auth-visual-side::before {
  content: 'Join.';
  position: absolute;
  right: -1.5rem;
  bottom: -2.5rem;
  font-family: var(--font-serif);
  font-size: clamp(7rem, 16vw, 13rem);
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.032);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

/* dot grid */
.auth-visual-side::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}

.visual-top, .visual-mid, .visual-bot {
  position: relative;
  z-index: 1;
}
.visual-mid { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 3rem 0; }

/* site brand */
.visual-brand {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: rgba(255,255,255,.4);
}
.visual-brand-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  box-shadow: 0 0 6px var(--accent);
  animation: blink 2s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

/* eyebrow */
.visual-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.4);
  padding: .28rem .8rem;
  border-radius: 2px;
  margin-bottom: 1.25rem;
  width: fit-content;
}

/* headline */
.visual-headline {
  font-family: var(--font-serif);
  font-size: clamp(2.4rem, 4.5vw, 3.8rem);
  font-weight: 700;
  line-height: 1.06;
  letter-spacing: -.02em;
  color: #fff;
  margin-bottom: .9rem;
}
.visual-headline em {
  font-style: italic;
  color: rgba(255,255,255,.38);
}

.visual-tagline {
  font-size: .9rem;
  color: rgba(255,255,255,.42);
  font-weight: 300;
  max-width: 30ch;
  line-height: 1.7;
  margin-bottom: 2.25rem;
}

/* perks — with green check icons */
.visual-perks {
  display: flex;
  flex-direction: column;
  gap: .8rem;
}
.visual-perk {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  font-size: .855rem;
  color: rgba(255,255,255,.52);
  line-height: 1.55;
}
.perk-check {
  width: 18px; height: 18px;
  border-radius: 50%;
  background: rgba(26,122,74,.22);
  border: 1px solid rgba(26,122,74,.38);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: .15rem;
}
.perk-check svg { width: 9px; height: 9px; color: #6ee7b7; }

/* stats row */
.visual-stats {
  display: flex;
  gap: 2rem;
  flex-wrap: wrap;
}
.visual-stat-num {
  font-family: var(--font-serif);
  font-size: 1.8rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
  display: block;
}
.visual-stat-label {
  font-size: .62rem;
  font-weight: 500;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.3);
  display: block;
  margin-top: .2rem;
}

/* rule + switch */
.visual-rule {
  width: 40px;
  height: 1px;
  background: rgba(255,255,255,.1);
  margin-bottom: 1.25rem;
}
.visual-switch {
  font-size: .8rem;
  color: rgba(255,255,255,.32);
}
.visual-switch a {
  color: rgba(255,255,255,.65);
  text-decoration: none;
  font-weight: 500;
  border-bottom: 1px solid rgba(255,255,255,.2);
  padding-bottom: 1px;
  transition: all var(--transition);
}
.visual-switch a:hover { color: #fff; border-bottom-color: rgba(255,255,255,.5); }

/* ═══════════════════════════════════════════
   RIGHT — FORM SIDE
═══════════════════════════════════════════ */
.auth-form-side {
  background: var(--bg);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: clamp(2.5rem, 5vw, 4rem) clamp(2rem, 5vw, 4rem);
  position: relative;
}

/* faint dot-grid texture */
.auth-form-side::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(var(--rule-light) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

.form-wrap {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 400px;
  animation: formIn .5s ease both;
}

@keyframes formIn {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── form eyebrow ── */
.form-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink-light);
  border: 1px solid var(--rule);
  padding: .26rem .75rem;
  border-radius: 2px;
  margin-bottom: .9rem;
}

/* ── form heading ── */
.form-heading {
  margin-bottom: 1.75rem;
}
.form-heading h1 {
  font-family: var(--font-serif);
  font-size: clamp(1.9rem, 3.5vw, 2.6rem);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.08;
  letter-spacing: -.015em;
  margin-bottom: .35rem;
}
.form-heading h1 em {
  font-style: italic;
  color: var(--ink-light);
}
.form-heading p {
  font-size: .845rem;
  color: var(--ink-light);
  font-weight: 300;
  line-height: 1.65;
}

/* ── alert ── */
.auth-alert {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  padding: .85rem 1rem;
  border-radius: 3px;
  font-size: .825rem;
  line-height: 1.55;
  margin-bottom: 1.4rem;
}
.auth-alert svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: .15rem; }
.auth-alert.error   { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(176,48,48,.18); }
.auth-alert.success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(26,122,74,.18); }

/* ── field groups ── */
.form-group { margin-bottom: 1.1rem; }

.form-label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .48rem;
}

/* two-col row */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .85rem;
}

.input-wrap { position: relative; }
.input-icon {
  position: absolute;
  left: .88rem;
  top: 50%;
  transform: translateY(-50%);
  width: 14px; height: 14px;
  color: var(--ink-light);
  pointer-events: none;
}
.field-input {
  width: 100%;
  padding: .74rem .9rem .74rem 2.45rem;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  font-family: var(--font-sans);
  font-size: .875rem;
  color: var(--ink);
  background: #fff;
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
  -webkit-appearance: none;
}
.field-input::placeholder { color: var(--ink-light); }
.field-input:focus {
  border-color: var(--ink);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(24,22,15,.06);
}
.field-input.valid   { border-color: var(--green); }
.field-input.invalid { border-color: var(--red); }

/* password eye toggle */
.toggle-pw {
  position: absolute;
  right: .5rem;
  top: 50%;
  transform: translateY(-50%);
  width: 30px; height: 30px;
  border: 1px solid var(--rule);
  background: transparent;
  color: var(--ink-light);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  border-radius: 3px;
  transition: all var(--transition);
}
.toggle-pw:hover { color: var(--ink); background: var(--bg-warm); border-color: var(--ink-mid); }
.toggle-pw svg { width: 13px; height: 13px; }

/* ── password strength meter ── */
.pw-meter { margin-top: .5rem; }
.pw-bars {
  display: flex;
  gap: 3px;
  margin-bottom: .3rem;
}
.pw-bar {
  flex: 1;
  height: 3px;
  border-radius: 99px;
  background: var(--rule);
  transition: background .25s ease;
}
.pw-bar.weak   { background: var(--red); }
.pw-bar.medium { background: var(--accent); }
.pw-bar.strong { background: var(--green); }

.pw-label {
  font-size: .7rem;
  color: var(--ink-light);
  transition: color .25s ease;
}
.pw-label.weak   { color: var(--red); }
.pw-label.medium { color: var(--accent); }
.pw-label.strong { color: var(--green); }

/* match hint */
.field-hint {
  font-size: .72rem;
  color: var(--ink-light);
  margin-top: .35rem;
  min-height: 1em;
  transition: color .2s ease;
}
.field-hint.match   { color: var(--green); }
.field-hint.mismatch { color: var(--red); }

/* ── divider ── */
.form-divider {
  height: 1px;
  background: var(--rule-light);
  margin: 1.5rem 0;
}

/* ── submit button ── */
.btn-submit {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  padding: .8rem 1.5rem;
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
.btn-submit:hover {
  background: #2c2a22;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.2);
}
.btn-submit:active  { transform: scale(.98); }
.btn-submit:disabled {
  background: var(--rule);
  color: var(--ink-light);
  cursor: not-allowed;
  transform: none; box-shadow: none;
}
.btn-submit svg { width: 14px; height: 14px; }

/* ── form footer ── */
.form-foot {
  margin-top: 1.35rem;
  text-align: center;
  font-size: .8rem;
  color: var(--ink-light);
}
.form-foot a {
  color: var(--sky);
  font-weight: 500;
  text-decoration: none;
  transition: color var(--transition);
}
.form-foot a:hover { color: var(--ink); }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 840px) {
  .auth-shell { grid-template-columns: 1fr; }
  .auth-visual-side {
    padding: 2.5rem 1.75rem;
    min-height: auto;
  }
  .visual-mid { padding: 1.75rem 0; }
  .auth-form-side {
    padding: 2.5rem 1.5rem;
    align-items: flex-start;
    padding-top: 2.5rem;
  }
}
@media (max-width: 480px) {
  .form-row { grid-template-columns: 1fr; }
}
</style>

<div class="auth-shell">

  <!-- ══════ LEFT: DARK EDITORIAL PANEL ══════ -->
  <div class="auth-visual-side">
    <div class="visual-top">
      <div class="visual-brand">
        <span class="visual-brand-dot"></span>
        <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Student Blog'; ?>
      </div>
    </div>

    <div class="visual-mid">
      <div class="visual-eyebrow">New Account</div>
      <h2 class="visual-headline">Join the<br>Campus<br><em>Community</em></h2>
      <p class="visual-tagline">Discover opportunities, explore the marketplace, and connect with students who share your ambitions.</p>

      <div class="visual-perks">
        <div class="visual-perk">
          <span class="perk-check">
            <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          </span>
          Save favourite posts and listings
        </div>
        <div class="visual-perk">
          <span class="perk-check">
            <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          </span>
          Comment and interact with the community
        </div>
        <div class="visual-perk">
          <span class="perk-check">
            <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          </span>
          Track events and opportunities easily
        </div>
      </div>
    </div>

    <div class="visual-bot">
      <div class="visual-rule"></div>
      <p class="visual-switch">
        Already have an account? <a href="login.php">Sign in →</a>
      </p>
    </div>
  </div>

  <!-- ══════ RIGHT: FORM ══════ -->
  <div class="auth-form-side">
    <div class="form-wrap">

      <div class="form-eyebrow">Registration</div>

      <div class="form-heading">
        <h1>Create<br><em>Account</em></h1>
        <p>Fill in your details to get started in seconds.</p>
      </div>

      <?php if (isset($_SESSION['flash'])): ?>
        <?php $ftype = $_SESSION['flash']['type']; $fmsg = $_SESSION['flash']['message']; unset($_SESSION['flash']); ?>
        <div class="auth-alert <?php echo htmlspecialchars($ftype); ?>">
          <?php if ($ftype === 'success'): ?>
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <?php else: ?>
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php endif; ?>
          <span><?php echo htmlspecialchars($fmsg); ?></span>
        </div>
      <?php endif; ?>

      <form method="post" novalidate id="registerForm">
        <?php csrf_token_field(); ?>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <div class="input-wrap">
              <svg class="input-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
                <circle cx="8" cy="5.5" r="2.5"/>
                <path d="M2 13.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke-linecap="round"/>
              </svg>
              <input id="username" name="username" type="text" class="field-input"
                placeholder="your_handle"
                required autocomplete="username"
                value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <div class="input-wrap">
              <svg class="input-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
                <rect x="2" y="4" width="12" height="9" rx="1.5"/>
                <path d="M2 6l6 4 6-4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <input id="email" name="email" type="email" class="field-input"
                placeholder="you@example.com"
                required autocomplete="email"
                value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrap">
            <svg class="input-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
              <rect x="3" y="7" width="10" height="7" rx="1.5"/>
              <path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke-linecap="round"/>
            </svg>
            <input id="password" name="password" type="password" class="field-input"
              placeholder="Create a password"
              required autocomplete="new-password">
            <button type="button" class="toggle-pw" id="togglePw1" aria-label="Toggle visibility">
              <svg id="eyeIcon1" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
                <path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"/>
                <circle cx="8" cy="8" r="2"/>
              </svg>
            </button>
          </div>
          <div class="pw-meter">
            <div class="pw-bars">
              <div class="pw-bar" id="bar1"></div>
              <div class="pw-bar" id="bar2"></div>
              <div class="pw-bar" id="bar3"></div>
              <div class="pw-bar" id="bar4"></div>
            </div>
            <div class="pw-label" id="pwLabel">At least 6 characters</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password_confirm">Confirm Password</label>
          <div class="input-wrap">
            <svg class="input-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
              <rect x="3" y="7" width="10" height="7" rx="1.5"/>
              <path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke-linecap="round"/>
            </svg>
            <input id="password_confirm" name="password_confirm" type="password" class="field-input"
              placeholder="Repeat your password"
              required autocomplete="new-password">
            <button type="button" class="toggle-pw" id="togglePw2" aria-label="Toggle visibility">
              <svg id="eyeIcon2" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
                <path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"/>
                <circle cx="8" cy="8" r="2"/>
              </svg>
            </button>
          </div>
          <div class="field-hint" id="matchHint"></div>
        </div>

        <div class="form-divider"></div>

        <button type="submit" class="btn-submit" id="submitBtn">
          Create Account
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
            <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <p class="form-foot">Already have an account? <a href="login.php">Sign in →</a></p>
      </form>

    </div>
  </div>

</div>

<script>
(function () {
  /* ── Password toggle helper ── */
  function bindToggle(btnId, inputId, iconId) {
    const btn = document.getElementById(btnId);
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (!btn || !inp) return;
    btn.addEventListener('click', () => {
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      if (ico) {
        ico.innerHTML = show
          ? '<path d="M2 2l12 12M6.5 6.6A2 2 0 009.4 9.4M4.5 4.6C2.8 5.8 1 8 1 8s3 5 7 5a7 7 0 003.5-.95M7 3.05C7.17 3 7.33 3 7.5 3 11 3 15 8 15 8s-1 1.4-2.5 2.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/>'
          : '<path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="8" cy="8" r="2" fill="none" stroke="currentColor" stroke-width="1.8"/>';
      }
    });
  }
  bindToggle('togglePw1', 'password', 'eyeIcon1');
  bindToggle('togglePw2', 'password_confirm', 'eyeIcon2');

  /* ── Password strength ── */
  const pwInput = document.getElementById('password');
  const bars    = ['bar1','bar2','bar3','bar4'].map(id => document.getElementById(id));
  const pwLabel = document.getElementById('pwLabel');

  function calcStrength(pw) {
    let s = 0;
    if (pw.length >= 6)  s++;
    if (pw.length >= 10) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/\d/.test(pw) || /[^A-Za-z0-9]/.test(pw)) s++;
    return s;
  }

  pwInput?.addEventListener('input', function () {
    const score = calcStrength(this.value);
    const cls   = score <= 1 ? 'weak' : score <= 2 ? 'medium' : 'strong';
    const text  = ['At least 6 characters','Weak','Fair','Good','Strong'][score] ?? 'Strong';
    bars.forEach((b, i) => { b.className = 'pw-bar' + (i < score ? ' ' + cls : ''); });
    pwLabel.className   = 'pw-label' + (score > 0 ? ' ' + cls : '');
    pwLabel.textContent = text;
  });

  /* ── Confirm match ── */
  const confirmInput = document.getElementById('password_confirm');
  const matchHint    = document.getElementById('matchHint');

  confirmInput?.addEventListener('input', function () {
    if (!this.value) {
      matchHint.textContent = '';
      matchHint.className   = 'field-hint';
      this.classList.remove('valid','invalid');
      return;
    }
    const match = this.value === pwInput?.value;
    matchHint.textContent = match ? '✓ Passwords match' : '✗ Passwords do not match';
    matchHint.className   = 'field-hint ' + (match ? 'match' : 'mismatch');
    this.classList.toggle('valid',   match);
    this.classList.toggle('invalid', !match);
  });

  /* ── Submit loading state ── */
  document.getElementById('registerForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<svg style="animation:spin .7s linear infinite" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9"/></svg> Creating…';
    }
  });

  const s = document.createElement('style');
  s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
})();
</script>

<?php include '../shared/footer.php'; ?>