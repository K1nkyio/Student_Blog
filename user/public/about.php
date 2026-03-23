<?php include '../shared/header.php'; ?>

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
  --amber:        #b8860b;
  --amber-dim:    #f5edcc;
  --red:          #b03030;
  --red-dim:      #f8e0e0;
  --purple:       #6b3fa0;
  --purple-dim:   #ede6f8;

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
.about-hero {
  background: var(--ink);
  color: #fff;
  padding: 3.5rem var(--gutter) 3rem;
  position: relative;
  overflow: hidden;
}

.about-hero::before {
  content: 'About.';
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

.about-hero::after {
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
@keyframes slideUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

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
  max-width: 48ch;
  line-height: 1.75;
  margin-bottom: 2rem;
}

/* hero stats */
.hero-stats { display: flex; gap: 2.5rem; flex-wrap: wrap; }
.hero-stat-num {
  font-family: var(--font-serif);
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  line-height: 1;
  display: block;
}
.hero-stat-label {
  font-size: .62rem;
  font-weight: 500;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.32);
  display: block;
  margin-top: .2rem;
}

/* ═══════════════════════════════════════════
   PAGE BODY
═══════════════════════════════════════════ */
.about-body {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 2.5rem var(--gutter) 6rem;
}

/* ── Section headers ── */
.sec-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.4rem;
  padding-top: 3rem;
  border-top: 1px solid var(--rule-light);
  flex-wrap: wrap;
}
.sec-header--first { border-top: none; padding-top: 0; }

.sec-kicker {
  font-size: .6rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: .3rem;
}
.sec-title {
  font-family: var(--font-serif);
  font-size: clamp(1.35rem, 2.5vw, 1.8rem);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.1;
  letter-spacing: -.01em;
}

/* ═══════════════════════════════════════════
   MISSION BLOCK — white card, editorial layout
═══════════════════════════════════════════ */
.mission-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 2.5rem;
  box-shadow: var(--shadow);
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 2.5rem;
  align-items: center;
  position: relative;
  overflow: hidden;
  animation: cardIn .5s ease both;
}

/* left accent bar — same as opp-row */
.mission-card::before {
  content: '';
  position: absolute;
  left: 0; top: 12px; bottom: 12px;
  width: 3px;
  border-radius: 0 2px 2px 0;
  background: var(--accent);
}

@keyframes cardIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

.mission-label {
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: .5rem;
}
.mission-number {
  font-family: var(--font-serif);
  font-size: 5rem;
  font-weight: 700;
  font-style: italic;
  color: var(--rule);
  line-height: 1;
  letter-spacing: -.04em;
}
.mission-text {
  font-size: .945rem;
  color: var(--ink-mid);
  line-height: 1.8;
  text-align: justify;
  text-align-last: left;
  hyphens: auto;
}
.mission-text strong { color: var(--ink); font-weight: 600; }

/* ═══════════════════════════════════════════
   TOPICS GRID — card per topic
═══════════════════════════════════════════ */
.topics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}

.topic-card {
  background: #fff;
  border: 1px solid var(--rule-light);
  border-radius: var(--radius-md);
  padding: 1.5rem 1.4rem;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
  animation: cardIn .4s ease both;
  text-decoration: none;
  color: var(--ink);
}
.topic-card:hover {
  border-color: var(--rule);
  box-shadow: 0 2px 8px rgba(24,22,15,.07), 0 8px 24px rgba(24,22,15,.07);
  transform: translateY(-3px);
}

/* top accent bar per topic — reuses opp-row::before pattern */
.topic-card::before {
  content: '';
  position: absolute;
  left: 0; top: 0; right: 0;
  height: 3px;
  border-radius: var(--radius-md) var(--radius-md) 0 0;
}
.topic-card:nth-child(1)::before { background: var(--sky); }
.topic-card:nth-child(2)::before { background: var(--amber); }
.topic-card:nth-child(3)::before { background: var(--accent); }
.topic-card:nth-child(4)::before { background: var(--green); }
.topic-card:nth-child(5)::before { background: var(--purple); }

.topic-card:nth-child(1) { animation-delay: .05s; }
.topic-card:nth-child(2) { animation-delay: .10s; }
.topic-card:nth-child(3) { animation-delay: .15s; }
.topic-card:nth-child(4) { animation-delay: .20s; }
.topic-card:nth-child(5) { animation-delay: .25s; }

.topic-icon-wrap {
  width: 44px; height: 44px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem;
  margin-bottom: 1rem;
}
.topic-card:nth-child(1) .topic-icon-wrap { background: var(--sky-dim); }
.topic-card:nth-child(2) .topic-icon-wrap { background: var(--amber-dim); }
.topic-card:nth-child(3) .topic-icon-wrap { background: var(--accent-dim); }
.topic-card:nth-child(4) .topic-icon-wrap { background: var(--green-dim); }
.topic-card:nth-child(5) .topic-icon-wrap { background: var(--purple-dim); }

.topic-name {
  font-family: var(--font-serif);
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.01em;
  margin-bottom: .4rem;
}
.topic-desc {
  font-size: .82rem;
  color: var(--ink-light);
  line-height: 1.6;
  font-weight: 300;
}

/* ═══════════════════════════════════════════
   VALUES — three-up row
═══════════════════════════════════════════ */
.values-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.value-card {
  background: var(--ink);
  border-radius: var(--radius-md);
  padding: 1.5rem;
  color: #fff;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow);
  animation: cardIn .4s ease both;
}
/* dot grid on dark cards */
.value-card::after {
  content: '';
  position: absolute; inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size: 20px 20px;
  pointer-events: none;
}
.value-card > * { position: relative; z-index: 1; }

.value-card:nth-child(1) { animation-delay: .05s; }
.value-card:nth-child(2) { animation-delay: .12s; }
.value-card:nth-child(3) { animation-delay: .19s; }

.value-num {
  font-family: var(--font-serif);
  font-size: 2.5rem;
  font-weight: 700;
  font-style: italic;
  color: var(--accent);
  line-height: 1;
  margin-bottom: .6rem;
}
.value-title {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.5);
  margin-bottom: .35rem;
}
.value-text {
  font-size: .845rem;
  color: rgba(255,255,255,.52);
  line-height: 1.65;
  font-weight: 300;
}

/* ═══════════════════════════════════════════
   CTA STRIP — full-width dark section
═══════════════════════════════════════════ */
.cta-strip {
  background: var(--ink);
  border-radius: var(--radius-md);
  padding: 3rem 2.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
  flex-wrap: wrap;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow);
}

/* dot grid */
.cta-strip::after {
  content: '';
  position: absolute; inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}

/* watermark */
.cta-strip::before {
  content: 'Join.';
  position: absolute;
  right: -1rem; bottom: -1.5rem;
  font-family: var(--font-serif);
  font-size: 8rem;
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.03);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

.cta-strip > * { position: relative; z-index: 1; }

.cta-left {}
.cta-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  font-size: .62rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--accent);
  border: 1px solid rgba(200,100,26,.4);
  padding: .26rem .78rem;
  border-radius: 2px;
  margin-bottom: .75rem;
}
.cta-headline {
  font-family: var(--font-serif);
  font-size: clamp(1.6rem, 3vw, 2.4rem);
  font-weight: 700;
  color: #fff;
  letter-spacing: -.015em;
  margin-bottom: .4rem;
  line-height: 1.1;
}
.cta-headline em { font-style: italic; color: rgba(255,255,255,.4); }
.cta-sub {
  font-size: .88rem;
  color: rgba(255,255,255,.4);
  font-weight: 300;
}

.cta-right { flex-shrink: 0; }

/* CTA button — same white inverse treatment */
.cta-btn {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  background: #fff;
  color: var(--ink);
  border: none;
  border-radius: 3px;
  padding: .82rem 1.75rem;
  font-family: var(--font-sans);
  font-size: .875rem;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--transition);
}
.cta-btn:hover {
  background: var(--bg-warm);
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(24,22,15,.25);
}
.cta-btn svg { width: 13px; height: 13px; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 860px) {
  .mission-card { grid-template-columns: 1fr; }
  .mission-number { font-size: 3rem; }
  .values-row { grid-template-columns: 1fr; }
  .cta-strip { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 540px) {
  .topics-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<!-- ══════ HERO ══════ -->
<div class="about-hero">
  <div class="hero-inner">
    <span class="hero-eyebrow"><span class="live-dot"></span> Student Development</span>
    <h1 class="hero-headline">About<br><em>This Blog</em></h1>
    <p class="hero-sub">A space where young people and students can learn, share, and grow together — built for the Zetech community.</p>
    <div class="hero-stats">
      <div>
        <span class="hero-stat-num">5</span>
        <span class="hero-stat-label">Topic Areas</span>
      </div>
      <div>
        <span class="hero-stat-num">∞</span>
        <span class="hero-stat-label">Resources</span>
      </div>
      <div>
        <span class="hero-stat-num">1</span>
        <span class="hero-stat-label">Community</span>
      </div>
    </div>
  </div>
</div>

<!-- ══════ BODY ══════ -->
<div class="about-body">

  <!-- Mission -->
  <div class="sec-header sec-header--first">
    <div>
      <div class="sec-kicker">Mission</div>
      <div class="sec-title">What We're About</div>
    </div>
  </div>

  <div class="mission-card">
    <div>
      <div class="mission-label">Our Purpose</div>
      <div class="mission-number">01</div>
    </div>
    <p class="mission-text">
      Welcome to the <strong>Student Development Blog</strong> — your go-to resource for personal
      and professional growth. We're dedicated to empowering the next generation with knowledge,
      skills, and opportunities that matter in today's fast-paced world. From financial literacy
      to the latest in technology, we curate content that prepares you for what lies ahead.
    </p>
  </div>

  <!-- Topics -->
  <div class="sec-header">
    <div>
      <div class="sec-kicker">Coverage</div>
      <div class="sec-title">What We Cover</div>
    </div>
  </div>

  <div class="topics-grid">
    <div class="topic-card">
      <div class="topic-icon-wrap">💰</div>
      <div class="topic-name">Finance</div>
      <p class="topic-desc">Master money management, investing, and financial literacy for a secure future.</p>
    </div>
    <div class="topic-card">
      <div class="topic-icon-wrap">💡</div>
      <div class="topic-name">Innovation</div>
      <p class="topic-desc">Explore creative thinking, entrepreneurship, and turning ideas into reality.</p>
    </div>
    <div class="topic-card">
      <div class="topic-icon-wrap">💻</div>
      <div class="topic-name">Technology</div>
      <p class="topic-desc">Stay ahead with the latest in tech trends, coding, and digital transformation.</p>
    </div>
    <div class="topic-card">
      <div class="topic-icon-wrap">🎯</div>
      <div class="topic-name">Skills</div>
      <p class="topic-desc">Develop practical abilities that set you apart in academics and your career.</p>
    </div>
    <div class="topic-card">
      <div class="topic-icon-wrap">🚀</div>
      <div class="topic-name">Opportunities</div>
      <p class="topic-desc">Discover scholarships, internships, and pathways to accelerate your growth.</p>
    </div>
  </div>

  <!-- Values -->
  <div class="sec-header">
    <div>
      <div class="sec-kicker">Principles</div>
      <div class="sec-title">What We Stand For</div>
    </div>
  </div>

  <div class="values-row">
    <div class="value-card">
      <div class="value-num">01</div>
      <div class="value-title">Accessibility</div>
      <p class="value-text">Every resource, guide, and opportunity we share is free and open to all Zetech students — no barriers, no paywalls.</p>
    </div>
    <div class="value-card">
      <div class="value-num">02</div>
      <div class="value-title">Relevance</div>
      <p class="value-text">We curate only what matters now — content that applies directly to your academic journey and career ambitions.</p>
    </div>
    <div class="value-card">
      <div class="value-num">03</div>
      <div class="value-title">Community</div>
      <p class="value-text">Growth is better together. We foster a culture of sharing, mentorship, and mutual support within the student body.</p>
    </div>
  </div>

  <!-- CTA -->
  <div class="sec-header">
    <div>
      <div class="sec-kicker">Get Started</div>
      <div class="sec-title">Join the Community</div>
    </div>
  </div>

  <div class="cta-strip">
    <div class="cta-left">
      <div class="cta-eyebrow">
        <span style="width:5px;height:5px;border-radius:50%;background:var(--accent);display:inline-block"></span>
        Ready to Begin?
      </div>
      <div class="cta-headline">Start your journey<br>towards <em>excellence</em></div>
      <div class="cta-sub">Explore articles, opportunities, events, and more — all in one place.</div>
    </div>
    <div class="cta-right">
      <a href="index.php" class="cta-btn">
        Explore Articles
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 14 14">
          <path d="M5 2l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
    </div>
  </div>

</div><!-- /.about-body -->

<?php include '../shared/footer.php'; ?>