<?php
session_start();
define('BLOG_SYSTEM', true);

require_once '../shared/db_connect.php';
require_once '../shared/functions.php';
require_once '../shared/csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('login.php');
    }

    $identifier = sanitize_input($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        set_flash('error', 'Please provide both username/email and password.');
    } else {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (verify_password($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                set_flash('success', 'Logged in successfully.');
                log_activity('login', 'User logged in', $user['id']);
                redirect('index.php');
            } else {
                set_flash('error', 'Invalid username/email or password.');
            }
        } else {
            set_flash('error', 'Invalid username/email or password.');
        }
        $stmt->close();
    }
}

$page_title = 'Sign In — ' . SITE_NAME;
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
  --max-w: 1360px;
  --gutter: 1.5rem;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-sans);
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
}

/* ═══════════════════════════════════════════
   FULL-VIEWPORT SPLIT SHELL
═══════════════════════════════════════════ */
.auth-shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}

/* ═══════════════════════════════════════════
   LEFT — FORM SIDE
═══════════════════════════════════════════ */
.auth-form-side {
  background: var(--bg);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: clamp(2.5rem, 6vw, 5rem) clamp(2rem, 5vw, 4rem);
  position: relative;
}

/* faint dot-grid texture on form side */
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
  max-width: 380px;
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
.form-group { margin-bottom: 1.15rem; }

.form-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-mid);
  margin-bottom: .48rem;
}
.form-label a {
  font-weight: 400;
  font-size: .72rem;
  text-transform: none;
  letter-spacing: 0;
  color: var(--ink-light);
  text-decoration: none;
  transition: color var(--transition);
}
.form-label a:hover { color: var(--sky); }

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

/* ── divider ── */
.form-divider {
  height: 1px;
  background: var(--rule-light);
  margin: 1.5rem 0;
}

/* ── submit button — same as .btn-primary from opp list ── */
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
   RIGHT — DARK EDITORIAL PANEL
   (identical treatment to .opp-hero)
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

/* serif watermark — same as opp-hero::before */
.auth-visual-side::before {
  content: 'Back.';
  position: absolute;
  right: -1rem;
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

/* dot grid — same as opp-hero::after */
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

/* eyebrow — same component as opp hero */
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

/* feature tiles — 2×2 grid */
.visual-tiles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .65rem;
}
.visual-tile {
  background: rgba(255,255,255,.045);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 3px;
  padding: .9rem;
  transition: background var(--transition);
}
.visual-tile:hover { background: rgba(255,255,255,.07); }

.tile-icon {
  width: 26px; height: 26px;
  border-radius: 2px;
  background: rgba(200,100,26,.16);
  border: 1px solid rgba(200,100,26,.22);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: .5rem;
}
.tile-icon svg { width: 12px; height: 12px; color: var(--accent); }

.visual-tile h4 {
  font-size: .78rem;
  font-weight: 600;
  color: rgba(255,255,255,.78);
  margin-bottom: .18rem;
  letter-spacing: .01em;
}
.visual-tile p {
  font-size: .7rem;
  color: rgba(255,255,255,.32);
  line-height: 1.5;
  font-weight: 300;
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
  line-height: 1.6;
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
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 840px) {
  .auth-shell { grid-template-columns: 1fr; }
  .auth-visual-side {
    order: 0;
    padding: 2.5rem 1.75rem;
    min-height: auto;
  }
  .visual-mid { padding: 1.75rem 0; }
  .visual-tiles { grid-template-columns: 1fr 1fr; }
  .auth-form-side {
    order: 1;
    padding: 2.5rem 1.5rem;
    align-items: flex-start;
    padding-top: 2.5rem;
  }
}
@media (max-width: 420px) {
  .visual-tiles { grid-template-columns: 1fr; }
}
</style>

<div class="auth-shell">

  <!-- ══════ LEFT: FORM ══════ -->
  <div class="auth-form-side">
    <div class="form-wrap">

      <div class="form-eyebrow">Sign In</div>

      <div class="form-heading">
        <h1>Welcome<br><em>Back</em></h1>
        <p>Use your username or email to access your account.</p>
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

      <form method="post" novalidate id="loginForm">
        <?php csrf_token_field(); ?>

        <div class="form-group">
          <label class="form-label" for="identifier">Username or Email</label>
          <div class="input-wrap">
            <svg class="input-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
              <circle cx="8" cy="5.5" r="2.5"/>
              <path d="M2 13.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke-linecap="round"/>
            </svg>
            <input id="identifier" name="identifier" type="text" class="field-input"
              placeholder="Enter your username or email"
              required autocomplete="username"
              value="<?php echo htmlspecialchars($_POST['identifier'] ?? '', ENT_QUOTES); ?>">
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
              placeholder="Enter your password"
              required autocomplete="current-password">
            <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle visibility">
              <svg id="eyeIcon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 16 16">
                <path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"/>
                <circle cx="8" cy="8" r="2"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="form-divider"></div>

        <button type="submit" class="btn-submit" id="submitBtn">
          Sign In
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
            <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <p class="form-foot">Don't have an account? <a href="register.php">Create one →</a></p>
      </form>

    </div>
  </div>

  <!-- ══════ RIGHT: DARK EDITORIAL PANEL ══════ -->
  <div class="auth-visual-side">
    <div class="visual-top">
      <div class="visual-brand">
        <span class="visual-brand-dot"></span>
        <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Student Blog'; ?>
      </div>
    </div>

    <div class="visual-mid">
      <div class="visual-eyebrow">Your Campus Feed</div>
      <h2 class="visual-headline">Everything<br>at Zetech<br><em>In One Place</em></h2>
      <p class="visual-tagline">Stay on top of opportunities, events, the marketplace, and everything happening on campus.</p>

      <div class="visual-tiles">
        <div class="visual-tile">
          <div class="tile-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            </svg>
          </div>
          <h4>Opportunities</h4>
          <p>Internships, jobs &amp; scholarships</p>
        </div>
        <div class="visual-tile">
          <div class="tile-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <h4>Events</h4>
          <p>Workshops &amp; campus socials</p>
        </div>
        <div class="visual-tile">
          <div class="tile-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="2" y="3" width="20" height="14" rx="2"/>
              <line x1="8" y1="21" x2="16" y2="21"/>
              <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
          </div>
          <h4>Marketplace</h4>
          <p>Buy &amp; sell with students</p>
        </div>
        <div class="visual-tile">
          <div class="tile-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <h4>Community</h4>
          <p>Posts, stories &amp; discussions</p>
        </div>
      </div>
    </div>

    <div class="visual-bot">
      <div class="visual-rule"></div>
      <p class="visual-switch">
        New here? <a href="register.php">Create a free account →</a>
      </p>
    </div>
  </div>

</div>

<script>
(function () {
  /* ── Password toggle ── */
  const togglePw = document.getElementById('togglePw');
  const pwInput  = document.getElementById('password');
  const eyeIcon  = document.getElementById('eyeIcon');

  togglePw?.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type = show ? 'text' : 'password';
    eyeIcon.innerHTML = show
      ? '<path d="M2 2l12 12M6.5 6.6A2 2 0 009.4 9.4M4.5 4.6C2.8 5.8 1 8 1 8s3 5 7 5a7 7 0 003.5-.95M7 3.05C7.17 3 7.33 3 7.5 3 11 3 15 8 15 8s-1 1.4-2.5 2.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/>'
      : '<path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="8" cy="8" r="2" fill="none" stroke="currentColor" stroke-width="1.8"/>';
  });

  /* ── Submit loading state ── */
  document.getElementById('loginForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<svg style="animation:spin .7s linear infinite" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9"/></svg> Signing in…';
    }
  });

  const s = document.createElement('style');
  s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
})();
</script>

<?php include '../shared/footer.php'; ?>